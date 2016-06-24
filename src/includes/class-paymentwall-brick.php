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

    public $id = 'brick';
    public $has_fields = true;

    public function __construct() {
        parent::__construct();

        $this->title = $this->settings['title'];
        $this->notify_url = str_replace('https:', 'http:', add_query_arg('wc-api', 'Paymentwall_Brick', home_url('/')));
        $this->method_title = __('Brick', 'paymentwall-for-woocommerce');
        $this->method_description = __('Brick provides in-app and fully customizable credit card processing for merchants around the world. With Brick connected to banks in different countries, Paymentwall has created the best global credit card processing solution in the world to help you process in local currency.', PW_TEXT_DOMAIN);

        // Our Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_filter('woocommerce_after_checkout_validation', array($this, 'brick_fields_validation'));

        if (!empty($_GET['_3ds'])) {
            $this->check3ds($_GET['_3ds']);
        }
    }


    /**
     * Initial Paymentwall Configs
     * For pingback request
     */
    public function init_paymentwall_configs() {
        Paymentwall_Config::getInstance()->set(array(
            'api_type' => Paymentwall_Config::API_GOODS,
            'public_key' => $this->settings['projectkey'],
            'private_key' => $this->settings['secretkey'],
        ));
    }

    /**
     * Initial Brick Configs
     */
    public function init_brick_configs() {
        Paymentwall_Config::getInstance()->set(array(
            'api_type' => Paymentwall_Config::API_GOODS,
            'public_key' => $this->settings['publickey'],
            'private_key' => $this->settings['privatekey'],
        ));
    }

    /**
     * Displays credit card form
     */
    public function payment_fields() {
        echo $this->get_template('brick/form.html', array(
            'payment_id' => $this->id,
            'public_key' => $this->settings['publickey'],
            'entry_card_number' => __("Card number", PW_TEXT_DOMAIN),
            'entry_card_expiration' => __("Card expiration", PW_TEXT_DOMAIN),
            'entry_card_cvv' => __("Card CVV", PW_TEXT_DOMAIN)
        ));
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) {
        $this->init_brick_configs();
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

        return array(
            'token' => $brick['token'],
            'amount' => $order->get_total(),
            'currency' => $order->get_order_currency(),
            'email' => $order->billing_email,
            'plan' => $order->id,
            'fingerprint' => $brick['fingerprint'],
            'description' => sprintf(__('%s - Order #%s', PW_TEXT_DOMAIN), esc_html(get_bloginfo('name', 'display')), $order->get_order_number()),
        );
    }

    /**
     * Add custom fields validation
     */
    public function brick_fields_validation() {
        if ($_POST['payment_method'] == $this->id) {
            $brick = $_POST['brick'];

            if (trim($brick['token']) == '' || trim($brick['fingerprint']) == '')
                wc_add_notice(sprintf(__('The <strong>%s</strong> payment has some errors. Please try again.', PW_TEXT_DOMAIN), $this->title), 'error');
        }
    }

    /**
     * @param $order
     * @return array
     * @throws Exception
     */
    public function process_standard_payment($order) {
        $return = array(
            'result' => 'fail',
            'redirect' => ''
        );
        $cardInfo = $this->prepare_card_info($order);

        $wc_cart = new WC_Cart();
        $checkout_url = $wc_cart->get_checkout_url();

        $charge = new Paymentwall_Charge();
        $charge->create(array_merge(
            $this->prepare_user_profile_data($order), // for User Profile API
            $cardInfo,
            $this->get_extra_data($order, $checkout_url)
        ));
        $response = $charge->getPublicData();
        $responseData = json_decode($charge->getRawResponseData(), true);

        if ($charge->isSuccessful() && empty($responseData['secure'])) {
            $return['result'] = 'success';
            $return['redirect'] = $this->process_success($order, $charge);
        } elseif (!empty($responseData['secure'])) {
            $secureData = array(
                'formHTML' => $responseData['secure']['formHTML'],
                'cardInfo' => $cardInfo,
                'order' => $order,
            );
            WC()->session->set('3ds', $secureData);
            $return['result'] = 'success';
            $return['redirect'] = $checkout_url . '?_3ds=confirm';
        } else {
            $errors = json_decode($response, true);
            wc_add_notice(__($errors['error']['message']), 'error');
        }

        return $return;
    }

    public function get_extra_data($order, $checkout_url) {
        return array(
            'custom[integration_module]' => 'woocomerce',
            'uid' => empty($order->user_id) ? $_SERVER['REMOTE_ADDR'] : $order->user_id,
            'secure_redirect_url' => $checkout_url . '?_3ds=process'
        );
    }

    public function confirm_3ds() {
        $dataSecure = WC()->session->get('3ds');
        echo $this->get_template('brick/3ds.html', array(
            '3ds' => $dataSecure['formHTML']
        ));
    }

    public function process_3ds() {
        $charge = new Paymentwall_Charge();

        $secureData = WC()->session->get('3ds');
        $order = $secureData['order'];

        $cardInfo = $secureData['cardInfo'];
        if (!empty($_POST['brick_charge_id']) && !empty($_POST['brick_secure_token'])) {
            $cardInfo['charge_id'] = $_POST['brick_charge_id'];
            $cardInfo['secure_token'] = $_POST['brick_secure_token'];
        }

        $charge->create($cardInfo);
        WC()->session->set('3ds', null);

        if ($charge->isSuccessful()) {
            $thanksPage = $this->process_success($order, $charge);
            wp_redirect($thanksPage);
        } else {
            $order->update_status('cancelled');
            wc_add_notice(__("Confirm 3d secure has been cancelled"), 'error');
            WC()->cart->empty_cart();
        }
    }

    public function process_success($order, $charge) {
        if ($charge->isCaptured()) {
            // Add order note
            $order->add_order_note(sprintf(
                __('Brick payment approved (ID: %s)', PW_TEXT_DOMAIN),
                $charge->getId())
            );
            // Payment complete
            $order->payment_complete($charge->getId());
        } elseif ($charge->isUnderReview()) {
            $order->update_status('on-hold');
        }

        $thanksPage = $this->get_return_url($order);
        WC()->cart->empty_cart();
        return $thanksPage;
    }

    public function check3ds($_3ds) {
        switch ($_3ds) {
            case 'confirm':
                $this->confirm_3ds();
                break;
            case 'process':
                $this->init_brick_configs();
                $this->process_3ds();
                break;
            default:
                break;
        }
    }
}