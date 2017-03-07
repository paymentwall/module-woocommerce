<?php

/*
 * Paymentwall Gateway for WooCommerce
 *
 * Description: Official Paymentwall module for WordPress WooCommerce.
 * Plugin URI: https://www.paymentwall.com/en/documentation/WooCommerce/1409
 * Author: Paymentwall
 * License: The MIT License (MIT)
 *
 */

class Paymentwall_Gateway extends Paymentwall_Abstract {

    public $id = 'paymentwall';
    public $has_fields = true;

    public function __construct() {
        parent::__construct();

        $this->icon = PW_PLUGIN_URL . '/assets/images/icon.png';
        $this->method_title = __('Paymentwall', PW_TEXT_DOMAIN);
        $this->method_description = __('Enables the Paymentwall Payment Solution. The easiest way to monetize your game or web service globally.', PW_TEXT_DOMAIN);
        $this->title = $this->settings['title'];
        $this->notify_url = str_replace('https:', 'http:', add_query_arg('wc-api', 'Paymentwall_Gateway', home_url('/')));

        // Our Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_api_' . $this->id . '_gateway', array($this, 'handle_action'));
    }

    /**
     * Initial Paymentwall Configs
     */
    function init_configs($isPingback = false) {
        Paymentwall_Config::getInstance()->set(array(
            'api_type' => Paymentwall_Config::API_GOODS,
            'public_key' => $this->settings['appkey'],
            'private_key' => $this->settings['secretkey']
        ));
    }

    /**
     * @param $order_id
     */
    function receipt_page($order_id) {
        $this->init_configs();

        $order = wc_get_order($order_id);
        $widget = new Paymentwall_Widget(
            empty($order->user_id) ? $order->billing_email : $order->user_id,
            $this->settings['widget'],
            array(
                new Paymentwall_Product($order->id, $order->order_total, $order->order_currency, 'Order #' . $order->id)
            ),
            array_merge(
                array(
                    'email' => $order->billing_email,
                    'integration_module' => 'woocommerce',
                    'test_mode' => $this->settings['test_mode']
                ),
                $this->prepare_user_profile_data($order)
            )
        );

        $iframe = $widget->getHtmlCode(array(
            'width' => '100%',
            'height' => '1000',
            'frameborder' => 0
        ));

        // Clear shopping cart
        WC()->cart->empty_cart();

        echo $this->get_template('widget.html', array(
            'orderId' => $order->id,
            'title' => __('Please continue the purchase via Paymentwall using the widget below.', PW_TEXT_DOMAIN),
            'iframe' => $iframe,
            'baseUrl' => get_site_url(),
            'pluginUrl' => plugins_url('', __FILE__)
        ));
    }

    /**
     * Process the order after payment is made
     * @param int $order_id
     * @return array
     */
    function process_payment($order_id) {
        $order = wc_get_order($order_id);

        return array(
            'result' => 'success',
            'redirect' => add_query_arg(
                'key',
                $order->order_key,
                add_query_arg(
                    'order',
                    $order->id,
                    $order->get_checkout_payment_url(true)
                )
            )
        );
    }

    /**
     * Check the response from Paymentwall's Servers
     */
    function ipn_response() {

        $original_order = $_GET['goodsid'];
        $order = wc_get_order($_GET['goodsid']);

        if (!$order) {
            die('The order is Invalid!');
        }

        $payment = wc_get_payment_gateway_by_order($order);
        $payment->init_configs(true);

        $pingback_params = $_GET;

        $pingback = new Paymentwall_Pingback($pingback_params, $_SERVER['REMOTE_ADDR']);
        if ($pingback->validate()) {

            if ($pingback->isDeliverable()) {

                if ($order->get_status() == PW_ORDER_STATUS_PROCESSING) {
                    die(PW_DEFAULT_SUCCESS_PINGBACK_VALUE);
                }

                // Call Delivery Confirmation API
                if ($this->settings['enable_delivery']) {
                    // Delivery Confirmation
                    $delivery = new Paymentwall_GenerericApiObject('delivery');
                    $response = $delivery->post($this->prepare_delivery_confirmation_data($order, $pingback->getReferenceId()));
                }

                $subscriptions = wcs_get_subscriptions_for_order( $original_order, array( 'order_type' => 'parent' ) );
                $subscription  = array_shift( $subscriptions );
                $subscription_key = get_post_meta($original_order, '_subscription_id');
                if(isset($_GET['initial_ref']) && $_GET['initial_ref'] && (isset($subscription_key[0]) && $subscription_key[0] == $_GET['initial_ref'] || 1==1) ) {
                    $subscription->update_status('on-hold');
                    $subscription->add_order_note(__('Subscription renewal payment due: Status changed from Active to On hold.', PW_TEXT_DOMAIN));
                    $new_order = wcs_create_renewal_order( $subscription );
                    $new_order->add_order_note(__('Payment approved by Paymentwall - Transaction Id: ' . $_GET['ref'], PW_TEXT_DOMAIN));
                    update_post_meta( $new_order->id, '_subscription_id', $_GET['ref']);
                    $new_order->set_payment_method( $subscription->payment_gateway );
                    $new_order->payment_complete($_GET['ref']);
                } else {
                    $order->add_order_note(__('Payment approved by Paymentwall - Transaction Id: ' . $pingback->getReferenceId(), PW_TEXT_DOMAIN));
                    $order->payment_complete($pingback->getReferenceId());


                }
                $action_args = array( 'subscription_id' => $subscription->id );
                $hooks = array(
                    'woocommerce_scheduled_subscription_payment',
                );
                foreach($hooks as $hook) {
                    $result = wc_unschedule_action( $hook, $action_args );
                }
            } elseif ($pingback->isCancelable()) {
                $order->cancel_order(__('Reason: ' . $pingback->getParameter('reason'), PW_TEXT_DOMAIN));
            } elseif ($pingback->isUnderReview()) {
                $order->update_status('on-hold');
            }

            die(PW_DEFAULT_SUCCESS_PINGBACK_VALUE);
        } else {
            die($pingback->getErrorSummary());
        }
    }

    /**
     * Process Ajax Request
     */
    function ajax_response() {
        $this->init_configs();

        $order = wc_get_order(intval($_POST['order_id']));
        $return = array(
            'status' => false,
            'url' => ''
        );

        if ($order) {
            if ($order->post_status == PW_ORDER_STATUS_PROCESSING) {
                WC()->cart->empty_cart();
                $return['status'] = true;
                $return['url'] = get_permalink(wc_get_page_id('checkout')) . '/order-received/' . $order->id . '?key=' . $order->post->post_password;
            }
        }
        die(json_encode($return));
    }

    /**
     * Handle Action
     */
    function handle_action() {
        switch ($_GET['action']) {
            case 'ajax':
                $this->ajax_response();
                break;
            case 'ipn':
                $this->ipn_response();
                break;
            default:
                break;
        }
    }

    function prepare_delivery_confirmation_data($order, $ref) {
        return array(
            'payment_id' => $ref,
            'type' => 'digital',
            'status' => 'delivered',
            'estimated_delivery_datetime' => date('Y/m/d H:i:s'),
            'estimated_update_datetime' => date('Y/m/d H:i:s'),
            'refundable' => 'yes',
            'details' => 'Item will be delivered via email by ' . date('Y/m/d H:i:s'),
            'shipping_address[email]' => $order->billing_email,
            'shipping_address[firstname]' => $order->shipping_first_name,
            'shipping_address[lastname]' => $order->shipping_last_name,
            'shipping_address[country]' => $order->shipping_country,
            'shipping_address[street]' => $order->shipping_address_1,
            'shipping_address[state]' => $order->shipping_state,
            'shipping_address[zip]' => $order->shipping_postcode,
            'shipping_address[city]' => $order->shipping_city,
            'reason' => 'none',
            'is_test' => $this->settings['test_mode'] ? 1 : 0,
        );
    }

}
