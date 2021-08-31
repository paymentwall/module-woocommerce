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
        $this->saved_cards = 'yes' === $this->get_option( 'saved_cards' );
        $this->supports = array(
            'tokenization',
        );
        // Our Actions
        add_filter('woocommerce_after_checkout_validation', array($this, 'brick_fields_validation'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        add_filter( 'woocommerce_available_payment_gateways', __CLASS__ . '::get_available_payment_gateways');

        $wcSession = WC()->session;
        if(!empty($wcSession) && isset($_POST['brick']) && $orderId = WC()->session->get('orderId')) {
            $result = $this->process_payment($orderId);
            die(json_encode($result));
        }
    }

    /**
     * Initial Paymentwall Configs
     */
    public function init_configs($isPingback = false) {
        if ($isPingback) {
            Paymentwall_Config::getInstance()->set(array(
                'api_type' => Paymentwall_Config::API_GOODS,
                'private_key' => $this->settings['test_mode'] ? $this->settings['privatekey'] : $this->settings['secretkey']
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
    public function payment_fields() {
        $currency = get_woocommerce_currency();
        $display_tokenization = is_checkout() && $this->saved_cards;

        session_start();
        $_SESSION['cart_total'] = WC()->cart->cart_contents_total;
        $_SESSION['currency'] = $currency;
        $_SESSION['private_key'] = $this->settings['privatekey'];
        $_SESSION['public_key'] = $this->settings['publickey'];
        $_SESSION['brick_form_action'] = get_site_url() . '/?wc-api=paymentwall_gateway&action=brick_charge';

        if ( $display_tokenization ) {
            $this->supports = array_merge($this->supports, array('tokenization'));
            $this->tokenization_script();
            $this->saved_payment_methods();
        }
        $brickFormUrl = PW_PLUGIN_URL . '/templates/pages/brick_form.php';

        echo $this->get_template('brick/form.html', array(
            'payment_id' => $this->id,
            'public_key' => $this->settings['publickey'],
            'entry_card_number' => __("Card number", PW_TEXT_DOMAIN),
            'entry_card_expiration' => __("Card expiration", PW_TEXT_DOMAIN),
            'entry_card_cvv' => __("Card CVV", PW_TEXT_DOMAIN),
            'plugin_url' => PW_PLUGIN_URL,
            'brick_form_url' => $brickFormUrl,
        ));

        $hasSubscription = class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription();

        if ( !$hasSubscription && apply_filters( 'wc_brick_display_save_payment_method_checkbox', $display_tokenization ) ) {
            $this->save_payment_method_checkbox();
        }
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) {
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
    function prepare_card_info($order) {
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
     * @param $order
     * @return array
     * @throws Exception
     */
    public function process_standard_payment($order) {
        $return = array();
        session_start();

        $charge = $_SESSION['charge'];
        $responseData = $_SESSION['charge_response_data'];
        if (empty($responseData['secure'])) {
            $return['result'] = 'success';
            $return['redirect'] = $this->process_success($order, $charge, $message);
            $return['message'] = $message;
        } elseif (!empty($responseData['secure'])) {
            WC()->session->set('orderId', !method_exists($order, 'get_id') ? $order->id : $order->get_id());
            $return['result'] = 'secure';
            $return['secure'] = $responseData['secure']['formHTML'];
            die(json_encode($return));
        } else {
            $return['result'] ='error';
            $return['message'] = $responseData['error'];
            wc_add_notice(__($responseData['error']), 'error');
            die(json_encode($return));
        }

        return $return;
    }

    public function get_extra_data($order) {
        $userId = $order->get_user_id();
        return array(
            'custom[integration_module]' => 'woocomerce',
            'uid' => empty($userId) ? $order->get_billing_email() : $order->get_user_id()
        );
    }

    public function process_success($order, $charge, &$message) {
        if ($charge->isCaptured() && $charge->isUnderReview()) {
            // Add order note
            $order->add_order_note(sprintf(
                __('Brick payment approved (ID: %s)', PW_TEXT_DOMAIN),
                $charge->getId()));
            // Payment complete
            $message = "Your order has been received and is under review!";
        } elseif ($charge->isUnderReview()) {
            $order->update_status('on-hold');
            $message = 'Your order is under review !';
        } elseif ($charge->isCaptured()) {
            $order->add_order_note(sprintf(
                __('Brick payment approved (ID: %s)', PW_TEXT_DOMAIN),
                $charge->getId()));
            // Payment complete
            $message = "Your order has been received!";
        }

        $thanksPage = $this->get_return_url($order);
        WC()->session->set('orderId', null);
        WC()->cart->empty_cart();
        unset($_POST['brick']);
        return $thanksPage;
    }

    public static function get_available_payment_gateways( $available_gateways ) {
        foreach ($available_gateways as $gateway_id => $gateway) {
            if (self::BRICK_METHOD == $gateway_id && is_add_payment_method_page()) {
                unset($available_gateways[ $gateway_id ]);
            }
        }

        return $available_gateways;
    }

    public function handle_brick_charge()
    {
        session_start();

        $this->init_configs();
        $parameters = $_POST;
        $chargeInfo = $this->getChargeInfo($parameters);
        $charge = $this->createCharge($chargeInfo);
        $response = $charge->getPublicData();
        $responseData = json_decode($charge->getRawResponseData(), true);
        $result = [];
        $result['payment'] = $responseData;
        $result = array_merge($result, json_decode($response, true));
        $_SESSION['charge_response_data'] = $responseData;
        $_SESSION['charge'] = $charge;

        if ($charge->isSuccessful()) {
            if ($charge->isCaptured()) {
                $result = json_encode($result);
                var_dump($result);
                die();
            } elseif ($charge->isUnderReview()) {
                var_dump('under_review');
                die();
            }
        }
        else {
            if (isset($result['payment']['secure']['formHTML'])) {
                $resultError['success'] = 0;
                $resultError['secure']['formHTML'] = $result['payment']['secure']['formHTML'];
                $resultError = json_encode($resultError);
                var_dump($resultError);
                die();
            }
        }
    }

    function getChargeInfo($params)
    {
        $chargeInfo = [
            'email' => $params['email'],
            'history[registration_date]' => '1489655092',
            'amount' => (float) $_SESSION['cart_total'],
            'currency' => $_SESSION['currency'],
            'token' => $params['brick_token'],
            'fingerprint' => $params['brick_fingerprint'],
            'description' => 'Brick Paymentwall'
        ];
        if (isset($params['brick_charge_id']) && isset($params['brick_secure_token'])) {
            $chargeInfo['charge_id'] = $params['brick_charge_id'];
            $chargeInfo['secure_token'] = $params['brick_secure_token'];
        }
        if (!empty($params['brick_reference_id'])) {
            $chargeInfo['reference_id'] = $params['brick_reference_id'];
        }

        return $chargeInfo;
    }

    function createCharge($chargeInfo)
    {
        $charge = new Paymentwall_Charge();
        $charge->create($chargeInfo);

        return $charge;
    }

}
