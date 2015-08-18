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

class Paymentwall_Gateway extends Paymentwall_Abstract
{
    public $id = 'paymentwall';
    public $has_fields = true;

    public function __construct()
    {
        parent::__construct();

        $this->icon = WC_PAYMENTWALL_PLUGIN_URL . '/assets/images/icon.png';
        $this->method_title = __('Paymentwall', 'woocommerce');
        $this->method_description = __('Enables the Paymentwall Payment Solution. The easiest way to monetize your game or web service globally.', 'woocommerce');
        $this->title = $this->settings['title'];
        $this->notify_url = str_replace('https:', 'http:', add_query_arg('wc-api', 'Paymentwall_Gateway', home_url('/')));

        // Our Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_api_' . $this->id . '_gateway', array($this, 'handleAction'));
    }

    /**
     * Initial Paymentwall Configs
     */
    function  initPaymentwallConfigs()
    {
        Paymentwall_Config::getInstance()->set(array(
            'api_type' => Paymentwall_Config::API_GOODS,
            'public_key' => $this->settings['appkey'],
            'private_key' => $this->settings['secretkey']
        ));
    }

    /**
     * @param $order_id
     */
    function receipt_page($order_id)
    {
        $this->initPaymentwallConfigs();

        $order = wc_get_order($order_id);

        $widget = new Paymentwall_Widget(
            $order->billing_email,
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
                $this->prepareUserProfileData($order)
            )
        );

        $iframe = $widget->getHtmlCode(array(
            'width' => '100%',
            'height' => 400,
            'frameborder' => 0
        ));

        // Clear shopping cart
        WC()->cart->empty_cart();

        echo $this->getTemplate('widget.html', array(
            'orderId' => $order->id,
            'title' => __('Please continue the purchase via Paymentwall using the widget below.', 'woocommerce'),
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
    function process_payment($order_id)
    {
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
    function ipnResponse()
    {
        $this->initPaymentwallConfigs();
        // params not use in pingback signature
        unset($_GET['wc-api']);
        unset($_GET['action']);

        $pingback = new Paymentwall_Pingback($_GET, $_SERVER['REMOTE_ADDR']);

        if ($pingback->validate()) {

            if ($order = new WC_Order($pingback->getProductId())) {

                if ($pingback->isDeliverable()) {

                    // Call Delivery Confirmation API
                    if ($this->settings['enable_delivery']) {
                        // Delivery Confirmation
                        $delivery = new Paymentwall_GenerericApiObject('delivery');
                        $response = $delivery->post($this->prepareDeliveryConfirmationData($order, $pingback->getReferenceId()));
                    }

                    $order->add_order_note(__('Paymentwall payment completed', 'woocommerce'));
                    $order->payment_complete($pingback->getReferenceId());

                } elseif ($pingback->isCancelable()) {
                    $order->update_status('cancelled', __('Reason: ' . $pingback->getParameter('reason'), 'woocommerce'));
                }

                die(DEFAULT_SUCCESS_PINGBACK_VALUE);
            } else {
                die('Order Invalid!');
            }

        } else {
            die($pingback->getErrorSummary());
        }
    }

    /**
     * Process Ajax Request
     */
    function ajaxResponse()
    {
        global $woocommerce;
        $order = new WC_Order(intval($_POST['order_id']));
        $return = array(
            'status' => false,
            'url' => ''
        );

        if ($order) {
            if ($order->post_status == WC_ORDER_STATUS_PROCESSING) {
                $woocommerce->cart->empty_cart();
                $return['status'] = true;
                $return['url'] = get_permalink(wc_get_page_id('checkout')) . '/order-received/' . $order->id . '?key=' . $order->post->post_password;
            }
        }
        die(json_encode($return));
    }

    /**
     * Handle Action
     */
    function handleAction()
    {
        switch ($_GET['action']) {
            case 'ajax':
                $this->ajaxResponse();
                break;
            case 'ipn':
                $this->ipnResponse();
                break;
            default:
                break;
        }
    }

    function prepareDeliveryConfirmationData($order, $ref)
    {
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
