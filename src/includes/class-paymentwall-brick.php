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
    }


    /**
     * Initial Paymentwall Configs
     * For pingback request
     */
    public function  init_paymentwall_configs() {
        Paymentwall_Config::getInstance()->set(array(
            'api_type' => Paymentwall_Config::API_GOODS,
            'public_key' => $this->settings['projectkey'],
            'private_key' => $this->settings['secretkey'],
        ));
    }

    /**
     * Initial Brick Configs
     */
    public function  init_brick_configs() {
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
            'entry_card_cvv' => __("Card CVV", PW_TEXT_DOMAIN),
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

        $charge = new Paymentwall_Charge();
        $charge->create(array_merge(
            $this->prepare_user_profile_data($order), // for User Profile API
            $this->prepare_card_info($order),
            array(
                'custom[integration_module]' => 'woocommerce',
                'uid' => empty($order->user_id) ? $_SERVER['REMOTE_ADDR'] : $order->user_id
            )
        ));
        $response = $charge->getPublicData();

        if ($charge->isSuccessful()) {
            if ($charge->isCaptured()) {

                // Add order note
                $order->add_order_note(sprintf(__('Brick payment approved (ID: %s, Card: xxxx-%s)', PW_TEXT_DOMAIN), $charge->getId(), $charge->getCard()->getAlias()));

                // Payment complete
                $order->payment_complete($charge->getId());
            } elseif ($charge->isUnderReview()) {
                $order->update_status('on-hold');
            }

            $return['result'] = 'success';
            $return['redirect'] = $this->get_return_url($order);

            // Clear shopping cart
            WC()->cart->empty_cart();
        } else {
            $errors = json_decode($response, true);
            wc_add_notice(__($errors['error']['message']), 'error');
        }

        return $return;
    }
}