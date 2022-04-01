<?php

/*
 * Paymentwall Brick for WooCommerce
 *
 * Description: Official Paymentwall module for WordPress WooCommerce.
 * Plugin URI: https://www.paymentwall.com/en/documentation/WooCommerce/1409
 * Author: Paymentwall
 * License: The MIT License (MIT)
 *
 */

class Paymentwall_Brick extends Paymentwall_Abstract {

    CONST BRICK_METHOD = 'brick';
    public $id;
    public $has_fields = true;

    public function __construct() {
        $this->id = self::BRICK_METHOD;

        parent::__construct();

        $this->icon = PW_PLUGIN_URL . '/assets/images/icon-creditcard.jpg';
        $this->title = $this->settings['title'];
        $this->notify_url = str_replace('https:', 'http:', add_query_arg('wc-api', 'Paymentwall_Brick', home_url('/')));
        $this->method_title = __('Brick', 'paymentwall-for-woocommerce');
        $this->method_description = __('Brick provides in-app and fully customizable credit card processing for merchants around the world. With Brick connected to banks in different countries, Paymentwall has created the best global credit card processing solution in the world to help you process in local currency.', PW_TEXT_DOMAIN);
//        $this->saved_cards = 'yes' === $this->get_option( 'saved_cards' );
        $this->saved_cards = false;
        $this->supports = array(
            'tokenization',
        );
        // Our Actions
        add_filter('woocommerce_after_checkout_validation', __CLASS__ . '::brick_preprocessing_validation');
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        add_filter('woocommerce_available_payment_gateways', __CLASS__ . '::get_available_payment_gateways');
    }

    /**
     * Initial Paymentwall Configs
     */
    public function init_configs($isPingback = false) {
        if ($isPingback) {
            Paymentwall_Config::getInstance()->set(array(
                'api_type' => Paymentwall_Config::API_GOODS,
                'private_key' => $this->settings['secretkey']
            ));
        } else {
            Paymentwall_Config::getInstance()->set(array(
                'api_type' => Paymentwall_Config::API_GOODS,
                'public_key' => $this->settings['publickey'],
                'private_key' => $this->settings['privatekey']
            ));
        }
    }

    /**
     * Displays credit card form
     */
    public function payment_fields()
    {
        $display_tokenization = is_checkout() && $this->saved_cards;
        if ($display_tokenization) {
            $this->supports = array_merge($this->supports, array('tokenization'));
            $this->tokenization_script();
            $this->saved_payment_methods();
        }
        if (count($this->get_tokens()) > 0) {
            $haveToken = true;
        } else {
            $haveToken = false;
        }

        $brickFormUrl = get_site_url() . '/?wc-api=paymentwall_gateway&action=brick_form';
        echo $this->get_template('brick/form.html', array(
            'payment_id' => $this->id,
            'public_key' => $this->settings['publickey'],
            'entry_card_number' => __("Card number", PW_TEXT_DOMAIN),
            'entry_card_expiration' => __("Card expiration", PW_TEXT_DOMAIN),
            'entry_card_cvv' => __("Card CVV", PW_TEXT_DOMAIN),
            'plugin_url' => PW_PLUGIN_URL,
            'brick_form_url' => $brickFormUrl,
            'have_token' => $haveToken
        ));

        if (!$this->cart_subscription_exists() && apply_filters('wc_brick_display_save_payment_method_checkbox', $display_tokenization)) {
            $this->save_payment_method_checkbox();
        }
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $this->init_configs();
        $order = wc_get_order($order_id);
        try {
            $return = $this->process_standard_payment($order);
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
        }

        // Return redirect
        return $return;
    }

    /**
     * @param $order
     * @return array
     * @throws Exception
     */
    function prepare_card_info($order)
    {
        $data = array(
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'email' => $order->get_billing_email(),
            'plan' => !method_exists($order, 'get_id') ? $order->id : $order->get_id(),
            'description' => sprintf(__('%s - Order #%s', PW_TEXT_DOMAIN), esc_html(get_bloginfo('name', 'display')), $order->get_order_number()),
        );

        return $data;
    }

    /**
     * @return bool
     */
    protected function cart_subscription_exists()
    {
        return class_exists('WC_Subscriptions_Cart') && WC_Subscriptions_Cart::cart_contains_subscription();
    }

    /**
     * @param $order
     * @return array
     * @throws Exception
     */
    public function process_standard_payment($order)
    {
        if (!$this->is_valid_order()) {
            $this->checkout_error_response("Order is invalid");
        }
        if (isset($_POST['wc-brick-payment-token']) && $_POST['wc-brick-payment-token'] != 'new') {
            $parameters = $_POST;
            $chargeInfo = $this->prepare_charge_info($parameters, $order);
            $charge = $this->create_charge($chargeInfo);
        } else {
            $charge = WC()->session->get('brick_charge');
        }
        $return = array();
        if ($charge->isSuccessful()) {
            $return['result'] = 'success';
            $return['redirect'] = $this->process_success($order, $charge, $message);
            $return['message'] = $message;
            $this->handle_storing_card($charge, WC()->session->get('email_brick_charge'));
        } else {
            $this->checkout_error_response("Payment error");
        }

        return $return;
    }

    /**
     * @param $message
     */
    protected function checkout_error_response($message)
    {
        $return['result'] = 'error';
        $return['message'] = $message;
        wc_add_notice(__($message), 'error');
        die(json_encode($return));
    }

    public function get_extra_data($order)
    {
        $userId = $order->get_user_id();
        return array(
            'custom[integration_module]' => 'woocomerce',
            'uid' => empty($userId) ? $order->get_billing_email() : $order->get_user_id()
        );
    }

    public function process_success($order, $charge, &$message)
    {
        $order->add_order_note(sprintf(
            __('Brick payment approved (ID: %s)', PW_TEXT_DOMAIN),
            $charge->getId()));

        if ($charge->isUnderReview()) {
            $message = "Thank you. Your order has been received, the payment is under review!";
        } else {
            $message = "Thank you. Your order has been received!";
        }

        $_SESSION['message_thank_you_page'] = $message;
        $thanksPage = $this->get_return_url($order);
        WC()->session->set('orderId', null);
        WC()->session->set('amount_charge_brick', null);
        WC()->session->set('cart_charge_brick', null);
        WC()->cart->empty_cart();
        return $thanksPage;
    }

    public static function get_available_payment_gateways($available_gateways)
    {
        foreach ($available_gateways as $gateway_id => $gateway) {
            if (self::BRICK_METHOD == $gateway_id && is_add_payment_method_page()) {
                unset($available_gateways[ $gateway_id] );
            }
        }

        return $available_gateways;
    }

    public function handle_brick_charge()
    {
        $this->init_configs();
        $parameters = $_POST;
        $orderId = $this->create_temporary_order();
        $order = wc_get_order($orderId);
        $amount = (float)WC()->cart->get_total('edit');
        $currency = WC()->session->get('currency');
        $order->set_total($amount);
        $order->set_currency($currency);
        $chargeInfo = $this->prepare_charge_info($parameters, $order);
        $charge = $this->create_charge($chargeInfo);
        $response = $charge->getPublicData();
        $responseData = json_decode($charge->getRawResponseData(), true);
        WC()->session->set('brick_charge', $charge);
        WC()->session->set('amount_charge_brick', (float)WC()->cart->get_total('edit'));
        WC()->session->set('cart_charge_brick', WC()->cart->get_cart());
        $result = [
            'is_successful' => $charge->isSuccessful(),
            'is_captured' => $charge->isCaptured(),
            'is_under_review' => $charge->isUnderReview(),
            'payment' => $responseData
        ];
        WC()->session->set('email_brick_charge', $parameters['email']);
        $result = array_merge($result, json_decode($response, true));

        echo json_encode($result);
        die();
    }

    /**
     * @return mixed
     */
    protected function create_temporary_order()
    {
        $order = new WC_Order();
        $order->set_total(WC()->cart->get_total('edit'));
        $cartHash = WC()->cart->get_cart_hash();
        $order->set_cart_hash($cartHash);
        $orderId = $order->save();
        $order = wc_get_order($orderId);
        WC()->session->set('order_awaiting_payment', $orderId);

        return $orderId;
    }


    /**
     * @param $charge
     * @param $responseData
     */
    protected function handle_storing_card($charge, $email)
    {
        if (!$charge->isSuccessful()) {
            return;
        }
        $isNewCard = true;
        if (count($this->get_tokens()) > 0) {
            $isNewCard = $_POST['wc-brick-payment-token'] == 'new';
        }
        if (is_checkout() && !empty($_POST['wc-brick-new-payment-method']) && $isNewCard) {
            $responseData = json_decode($charge->getRawResponseData(), true);
            $token = new WC_Payment_Token_CC();
            $token->set_token($responseData['card']['token']);
            $token->set_gateway_id($this->id);
            $token->set_card_type($responseData['card']['type']);
            $token->set_last4($responseData['card']['last4']);
            $token->set_expiry_month($responseData['card']['exp_month']);
            $token->set_expiry_year($responseData['card']['exp_year']);
            $token->set_user_id(get_current_user_id());
            $token->add_meta_data('email', $email);
            $token->save();
        }
    }

    /**
     * @param $params
     * @param $orderId
     * @return array
     */
    public function prepare_charge_info($params, $order)
    {
        $chargeInfo = [
            'email' => $params['email'],
            'history[registration_date]' => '1489655092',
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'description' => 'Brick Paymentwall',
            'plan' => $order->get_id(),
        ];
        if (isset($params['brick_token'])) {
            $chargeInfo['token'] = $params['brick_token'];
            $chargeInfo['fingerprint'] = $params['brick_fingerprint'];
        } elseif (!empty($_POST['wc-brick-payment-token'])) {
            $token = WC_Payment_Tokens::get($_POST['wc-brick-payment-token']);
            $chargeInfo['token'] = $token->get_token();
            $chargeInfo['email'] = $token->get_meta('email');
        }
        if (isset($params['brick_charge_id']) && isset($params['brick_secure_token'])) {
            $chargeInfo['charge_id'] = $params['brick_charge_id'];
            $chargeInfo['secure_token'] = $params['brick_secure_token'];
        }
        if (!empty($params['brick_reference_id'])) {
            $chargeInfo['reference_id'] = $params['brick_reference_id'];
        }
        $userProfileData = $this->prepare_user_profile();

        return array_merge($chargeInfo, $userProfileData);
    }

    /**
     * @return bool
     */
    protected function is_valid_order()
    {
        return (WC()->session->get('amount_charge_brick') == (float)WC()->cart->get_total('edit'))
            && (WC()->session->get('cart_charge_brick') == WC()->cart->get_cart());
    }

    public function prepare_user_profile()
    {
        $customer = WC()->customer;

        return array(
            'customer[city]' => $customer->get_city(),
            'customer[state]' => $customer->get_state() ? $customer->get_state() : 'NA',
            'customer[address]' => $customer->get_address(),
            'customer[country]' => $customer->get_country(),
            'customer[zip]' => $customer->get_postcode(),
            'customer[username]' => $customer->get_email(),
            'customer[firstname]' => $customer->get_first_name(),
            'customer[lastname]' => $customer->get_last_name(),
        );
    }

    public function create_charge($chargeInfo)
    {
        $charge = new Paymentwall_Charge();
        $charge->create($chargeInfo);

        return $charge;
    }

    public function prepare_brick_form()
    {
        WC()->session->set('currency', get_woocommerce_currency());
        echo $this->get_template('pages/brick_form.html', array(
                'cart_total' => (float)WC()->cart->get_total('edit'),
                'currency' => get_woocommerce_currency(),
                'private_key' => $this->settings['privatekey'],
                'public_key' => $this->settings['publickey'],
                'brick_form_action' => $this->prepare_brick_action_endpoint(),
                'save_card' => $this->saved_cards && !$this->cart_subscription_exists() && is_user_logged_in(),
                'payment_js_url' => PW_PLUGIN_URL . '/assets/js/payment.js'
            )
        );
        die();
    }

    private function prepare_brick_action_endpoint()
    {
        $endpoint = get_site_url() . '/?wc-api=paymentwall_gateway&action=';

        if ($this->cart_subscription_exists()) {
            return $endpoint . 'brick_subscription';
        }

        return $endpoint . 'brick_charge';
    }

    public static function brick_preprocessing_validation($posted) {
        if ($posted['payment_method'] != self::BRICK_METHOD) {
            return;
        }
        if ($_POST['brick-pre-validation-flag'] == "1") {
            wc_add_notice( __( "brick_custom_notice", 'fake_error' ), 'error');
        }
    }
}