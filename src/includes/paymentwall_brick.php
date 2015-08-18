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

class Paymentwall_Brick extends Paymentwall_Abstract
{
    public $id = 'brick';
    public $has_fields = true;

    public function __construct()
    {
        parent::__construct();

        $this->title = $this->settings['title'];
        $this->notify_url = str_replace('https:', 'http:', add_query_arg('wc-api', 'Paymentwall_Brick', home_url('/')));
        $this->method_title = __('Brick', 'woocommerce');
        $this->method_description = __('Brick provides in-app and fully customizable credit card processing for merchants around the world. With Brick connected to banks in different countries, Paymentwall has created the best global credit card processing solution in the world to help you process in local currency.', 'woocommerce');

        // Our Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_filter('woocommerce_after_checkout_validation', array($this, 'brickFieldsValidation'));
    }


    /**
     * Initial Paymentwall Configs
     */
    public function  initPaymentwallConfigs()
    {
        Paymentwall_Config::getInstance()->set(array(
            'api_type' => Paymentwall_Config::API_GOODS,
            'public_key' => $this->settings['test_mode'] ? $this->settings['t_publickey'] : $this->settings['publickey'],
            'private_key' => $this->settings['test_mode'] ? $this->settings['t_privatekey'] : $this->settings['privatekey'],
        ));
    }

    /**
     * Displays credit card form
     */
    public function payment_fields()
    {
        echo $this->getTemplate('brick/form.html', array(
            'payment_id' => $this->id,
            'public_key' => $this->settings['publickey'],
            'entry_card_number' => __("Card number"),
            'entry_card_expiration' => __("Card expiration"),
            'entry_card_cvv' => __("Card CVV"),
        ));
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $return = array(
            'result' => 'fail',
            'redirect' => ''
        );

        $order = wc_get_order($order_id);
        $charge = new Paymentwall_Charge();

        try {
            $charge->create(array_merge(
                $this->prepareUserProfileData($order), // for User Profile API
                $this->prepareCardInfo($order)
            ));

            $response = $charge->getPublicData();

            if ($charge->isSuccessful()) {
                if ($charge->isCaptured()) {

                    // Add order note
                    $order->add_order_note(sprintf(__('Brick payment approved (ID: %s, Card: xxxx-%s)', 'woocommerce'), $charge->getId(), $charge->getCard()->getAlias()));

                    // Payment complete
                    $order->payment_complete($charge->getId());

                    $return['result'] = 'success';
                    $return['redirect'] = $this->get_return_url($order);
                } elseif ($charge->isUnderReview()) {
                    $order->update_status('on-hold');
                }

                // Clear shopping cart
                WC()->cart->empty_cart();
            } else {
                $errors = json_decode($response, true);
                wc_add_notice(__($errors['error']['message']), 'error');
            }

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
    function prepareCardInfo($order)
    {
        if (!isset($_POST['brick'])) {
            throw new Exception("Payment Invalid!");
        }

        $brick = $_POST['brick'];

        return array(
            'token' => $brick['token'],
            'amount' => $order->get_total(),
            'currency' => $order->get_order_currency(),
            'email' => $order->billing_email,
            'fingerprint' => $brick['fingerprint'],
            'description' => sprintf(__('%s - Order #%s', 'woocommerce'), esc_html(get_bloginfo('name', 'display')), $order->get_order_number()),
        );
    }

    /**
     * Add custom fields validation
     */
    public function brickFieldsValidation()
    {
        if ($_POST['payment_method'] == $this->id) {
            $brick = $_POST['brick'];

            if (trim($brick['card_number']) == '')
                wc_add_notice(__('Please enter valid Card Number.'), 'error');

            if (trim($brick['exp_month']) == '')
                wc_add_notice(__('Please enter valid Expiration Month.'), 'error');

            if (trim($brick['exp_year']) == '')
                wc_add_notice(__('Please enter valid Expiration Year.'), 'error');

            if (trim($brick['card_cvv']) == '')
                wc_add_notice(__('Please enter valid Card CVV.'), 'error');

            if (trim($brick['token']) == '' || trim($brick['fingerprint']) == '')
                wc_add_notice(sprintf(__('The <strong>%s</strong> payment has some errors. Please try again.'), $this->title), 'error');
        }
    }
}