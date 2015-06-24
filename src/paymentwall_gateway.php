<?php

/*
 * Paymentwall Gateway for WooCommerce
 *
 * Description: Official Paymentwall module for WordPress WooCommerce.
 * Plugin URI: https://www.paymentwall.com/en/documentation/WooCommerce/1409
 * Version: 1.0.1
 * Author: Paymentwall
 * License: MIT
 *
 */

class Paymentwall_Gateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'paymentwall';
        $this->icon = plugins_url('paymentwall-for-woocommerce/images/icon.png');
        $this->has_fields = true;
        $this->method_title = __('Paymentwall', 'woocommerce');

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Load Paymentwall Merchant Information
        Paymentwall_Config::getInstance()->set(array(
            'api_type' => Paymentwall_Config::API_GOODS,
            'public_key' => $this->settings['appkey'],
            'private_key' => $this->settings['secretkey']
        ));
        $this->app_key = $this->settings['appkey'];
        $this->secret_key = $this->settings['secretkey'];
        $this->widget_code = $this->settings['widget'];
        $this->test_mode = $this->settings['test_mode'];

        $this->description = $this->settings['description'];
        $this->title = 'Paymentwall';
        $this->notify_url = str_replace('https:', 'http:', add_query_arg('wc-api', 'Paymentwall_Gateway', home_url('/')));

        // Our Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_paymentwall', array($this, 'receipt_page'));
        add_action('woocommerce_api_paymentwall_gateway', array($this, 'check_ipn_response'));
    }

    /**
     * @param $order_id
     */
    function receipt_page($order_id)
    {
        $order = new WC_Order($order_id);
        $iframe = '';
        $products = array();

        if ($order) {
            $products[] = new Paymentwall_Product($order->id, $order->order_total, $order->order_currency, 'Order #' . $order->id);
        }

        $widget = new Paymentwall_Widget($order->billing_email, $this->widget_code, $products, array(
            'email' => $order->billing_email,
            'integration_module' => 'woocommerce',
            'test_mode' => $this->test_mode,
            'ref' => rand(99, 999)
        ));

        $iframe = $widget->getHtmlCode(array(
            'width' => '100%',
            'height' => 400,
            'frameborder' => 0
        ));

        echo '<p>' . __('Please continue the purchase via Paymentwall using the widget below.', 'woocommerce') . '</p>';
        echo $iframe;
    }

    /**
     * Process the order after payment is made
     * @param int $order_id
     * @return array
     */
    function process_payment($order_id)
    {
        $order = new WC_Order ($order_id);
        global $woocommerce;
        if (isset ($_REQUEST ['ipn']) && $_REQUEST ['ipn'] == true) {

            // Remove cart
            $woocommerce->cart->empty_cart();

            // Payment complete
            $order->payment_complete();

            return array(
                'result' => 'success',
                'redirect' => add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(woocommerce_get_page_id('thanks'))))

            );

        } else {

            return array(
                'result' => 'success',
                'redirect' => add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(woocommerce_get_page_id('pay'))))
            );

        }
    }

    /*
     * Display administrative fields under the Payment Gateways tab in the Settings page
     */
    function init_form_fields()
    {

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable the Paymentwall Payment Solution', 'woocommerce'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default' => __('Paymentwall', 'woocommerce')
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                'default' => __("Pay via Paymentwall.", 'woocommerce')
            ),
            'appkey' => array(
                'title' => __('Project Key', 'woocommerce'),
                'type' => 'text',
                'description' => __('Your Paymentwall Application Key', 'woocommerce'),
                'default' => ''
            ),
            'secretkey' => array(
                'title' => __('Secret Key', 'woocommerce'),
                'type' => 'text',
                'description' => __('Your Paymentwall Secret Key', 'woocommerce'),
                'default' => ''
            ),
            'widget' => array(
                'title' => __('Widget Code', 'woocommerce'),
                'type' => 'text',
                'description' => __('Enter your preferred widget code', 'woocommerce'),
                'default' => ''
            ),
            'test_mode' => array(
                'title' => __('Enable', 'woocommerce'),
                'type' => 'checkbox',
                'description' => __('Enable test mode', 'woocommerce'),
                'default' => 'Yes'
            )
        );
    } // End init_form_fields()

    /*
     * Displays a short description to the user during checkout
     */
    function payment_fields()
    {
        echo $this->description;
    }

    /*
     * Displays text like introduction, instructions in the admin area of the widget
     */
    public function admin_options()
    {
        ?>
        <h3><?php _e('Paymentwall Gateway', 'woocommerce'); ?></h3>
        <p><?php _e('Enables the Paymentwall Payment Solution. The easiest way to monetize your game or web service globally.', 'woocommerce'); ?></p>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table>
    <?php
    }

    /*
     * Check the response from Paymentwall's Servers
     */
    function check_ipn_response()
    {
        global $woocommerce;
        $_REQUEST['ipn'] = true;

        if (isset ($_GET ['paymentwallListener']) && $_GET ['paymentwallListener'] == 'paymentwall_IPN') {

            $result = false;
            $pingback = false;
            $orderId = isset($_GET['goodsid']) ? $_GET['goodsid'] : false;
            $type = isset($_GET['type']) ? $_GET['type'] : false;
            $reason = isset($_GET ['reason']) ? $_GET ['reason'] : null;

            $params = array_merge($_GET, array(
                'sign_version' => Paymentwall_Signature_Abstract::VERSION_THREE
            ));

            $pingback = new Paymentwall_Pingback($params, $_SERVER['REMOTE_ADDR']);

            if ($pingback->validate()) {

                // Get Order Info
                $order = new WC_Order (( int )$orderId);
                if ($order->get_order($orderId)) {

                    // Check request changeback
                    if ($type == 2) {
                        $order->update_status('cancelled', __('Reason: ' . $reason, 'woocommerce'));
                        $result = true;
                    } else {
                        $order->add_order_note(__('Paymentwall payment completed', 'woocommerce'));
                        $order->payment_complete();
                        $woocommerce->cart->empty_cart();
                        $result = true;
                    }
                } else {
                    $result = false;
                    die ();
                }

                if ($result) {
                    die ('OK');
                } else {
                    die ('Paymentwall IPN Request Failure');
                }

            } else {
                die ('Missing parameters!');
            }

        } else {
            die ('Invalid request');
        }
    }
}