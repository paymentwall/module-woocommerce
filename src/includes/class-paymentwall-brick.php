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
        $display_tokenization = is_checkout() && $this->saved_cards;

        if ( $display_tokenization ) {
            $this->supports = array_merge($this->supports, array('tokenization'));
            $this->tokenization_script();
            $this->saved_payment_methods();
        }

        echo $this->get_template('brick/form.html', array(
            'payment_id' => $this->id,
            'public_key' => $this->settings['publickey'],
            'entry_card_number' => __("Card number", PW_TEXT_DOMAIN),
            'entry_card_expiration' => __("Card expiration", PW_TEXT_DOMAIN),
            'entry_card_cvv' => __("Card CVV", PW_TEXT_DOMAIN),
            'plugin_url' => PW_PLUGIN_URL
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
        if (!isset($_POST['brick'])) {
            throw new Exception("Payment Invalid!");
        }

        $brick = $_POST['brick'];
        $data = array(
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'email' => $order->get_billing_email(),
            'plan' => !method_exists($order, 'get_id') ? $order->id : $order->get_id(),
            'description' => sprintf(__('%s - Order #%s', PW_TEXT_DOMAIN), esc_html(get_bloginfo('name', 'display')), $order->get_order_number()),
        );
        if ($brick['token'] && $brick['fingerprint']) {
            $data = array_merge($data, array(
                'token' => $brick['token'],
                'fingerprint' => $brick['fingerprint']
            ));

        } elseif (!empty($_POST['wc-brick-payment-token'])) {
            $token = WC_Payment_Tokens::get($_POST['wc-brick-payment-token'])->get_token();
            $data = array_merge($data, array(
                'token' => $token
            ));
        }
        if (!empty($brick['cc_brick_secure_token'])) {
            $data['secure_token'] = $brick['cc_brick_secure_token'];
        }
        if (!empty($brick['cc_brick_charge_id'])) {
            $data['charge_id'] = $brick['cc_brick_charge_id'];
        }
        return $data;
    }

    /**
     * Add custom fields validation
     */
    public function brick_fields_validation() {
        if ($_POST['payment_method'] == $this->id) {
            $brick = $_POST['brick'];

            if ((trim($brick['token']) == '' || trim($brick['fingerprint']) == '') && empty($_POST['wc-brick-payment-token']))
                wc_add_notice(sprintf(__('The <strong>%s</strong> payment has some errors. Please try again.', PW_TEXT_DOMAIN), $this->title), 'error');
        }
    }

    /**
     * @param $order
     * @return array
     * @throws Exception
     */
    public function process_standard_payment($order) {
        $return = array();
        $cardInfo = $this->prepare_card_info($order);
        $charge = new Paymentwall_Charge();
        $charge->create(array_merge(
            $this->prepare_user_profile_data($order), // for User Profile API
            $cardInfo,
            $this->get_extra_data($order)
        ));

        $response = $charge->getPublicData();
        $responseData = json_decode($charge->getRawResponseData(), true);

        if ($charge->isSuccessful() && empty($responseData['secure'])) {
            $return['result'] = 'success';
            $return['redirect'] = $this->process_success($order, $charge, $message);
            $return['message'] = $message;

            if (is_checkout() && !empty($_POST['wc-brick-new-payment-method']) && $_POST['wc-brick-payment-token'] == 'new') {
                $token = new WC_Payment_Token_CC();
                $token->set_token($responseData['card']['token']);
                $token->set_gateway_id($this->id);
                $token->set_card_type($responseData['card']['type']);
                $token->set_last4($responseData['card']['last4']);
                $token->set_expiry_month($responseData['card']['exp_month']);
                $token->set_expiry_year('20' . $responseData['card']['exp_year']);
                $token->set_user_id(get_current_user_id());
                $token->save();
            }
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
        if ($charge->isCaptured()) {
            // Add order note
            $order->add_order_note(sprintf(
                __('Brick payment approved (ID: %s)', PW_TEXT_DOMAIN),
                $charge->getId()));
            // Payment complete
            $message = "Your order has been received !";
        } elseif ($charge->isUnderReview()) {
            $order->update_status('on-hold');
            $message = 'Your order is under review !';
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
}
