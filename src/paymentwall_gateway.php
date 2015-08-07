<?php

/*
 * Paymentwall Gateway for WooCommerce
 *
 * Description: Official Paymentwall module for WordPress WooCommerce.
 * Plugin URI: https://www.paymentwall.com/en/documentation/WooCommerce/1409
 * Version: 1.0.1
 * Author: Paymentwall
 * License: The MIT License (MIT)
 *
 */

class Paymentwall_Gateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'paymentwall';
        $this->icon = plugins_url('images/icon.png', __FILE__);
        $this->has_fields = true;
        $this->method_title = __('Paymentwall', 'woocommerce');
        $this->plugin_path = plugin_dir_path(__FILE__);

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Load Paymentwall Merchant Information
        $this->initPaymentwallConfigs();

        $this->title = 'Paymentwall';
        $this->notify_url = str_replace('https:', 'http:', add_query_arg('wc-api', 'Paymentwall_Gateway', home_url('/')));

        // Our Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_api_paymentwall_gateway', array($this, 'handleAction'));
    }

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

        $order = new WC_Order($order_id);

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
        global $woocommerce;

        $order = new WC_Order($order_id);

        if (isset ($_REQUEST ['ipn']) && $_REQUEST ['ipn'] == true) {

            // Remove cart
            $woocommerce->cart->empty_cart();

            // Payment complete
            $order->payment_complete();

            return $this->prepareProcessPaymentResult($order, 'success');
        } else {
            return $this->prepareProcessPaymentResult($order, 'pay');
        }
    }

    function prepareProcessPaymentResult($order, $pageId)
    {
        return array(
            'result' => 'success',
            'redirect' => add_query_arg(
                'key',
                $order->order_key,
                add_query_arg(
                    'order',
                    $order->id,
                    get_permalink(wc_get_page_id($pageId))
                )
            )
        );
    }

    /*
     * Displays a short description to the user during checkout
     */
    function payment_fields()
    {
        echo $this->settings['description'];
    }

    /*
     * Displays text like introduction, instructions in the admin area of the widget
     */
    public function admin_options()
    {
        ob_start();
        $this->generate_settings_html();
        $settings = ob_get_contents();
        ob_clean();

        echo $this->getTemplate('admin/options.html', array(
            'title' => __('Paymentwall Gateway', 'woocommerce'),
            'description' => __('Enables the Paymentwall Payment Solution. The easiest way to monetize your game or web service globally.', 'woocommerce'),
            'settings' => $settings
        ));
    }

    /*
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
                    $order->payment_complete();

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

    function getTemplate($templateFileName, $data)
    {
        if (file_exists($this->plugin_path . 'templates/' . $templateFileName)) {
            $content = file_get_contents($this->plugin_path . 'templates/' . $templateFileName);
            foreach ($data as $key => $var) {
                $content = str_replace('{{' . $key . '}}', $var, $content);
            }
            return $content;
        }
        return false;
    }

    function prepareUserProfileData($order)
    {
        return array(
            'customer[city]' => $order->billing_city,
            'customer[state]' => $order->billing_state,
            'customer[address]' => $order->shipping_address_1,
            'customer[country]' => $order->shipping_country,
            'customer[zip]' => $order->billing_postcode,
            'customer[username]' => $order->billing_email,
            'customer[firstname]' => $order->billing_first_name,
            'customer[lastname]' => $order->billing_last_name,
            'email' => $order->billing_email,
        );
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
            'shipping_address[phone]' => '',
            'shipping_address[zip]' => $order->shipping_postcode,
            'shipping_address[city]' => $order->shipping_city,
            'reason' => 'none',
            'is_test' => $this->settings['test_mode'] ? 1 : 0,
            'product_description' => '',
        );
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
                'description' => __('Your Paymentwall Project Key', 'woocommerce'),
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
                'title' => __('Test Mode', 'woocommerce'),
                'type' => 'select',
                'description' => __('Enable test mode', 'woocommerce'),
                'options' => array(
                    '0' => 'No',
                    '1' => 'Yes'
                ),
                'default' => '1'
            ),
            'enable_delivery' => array(
                'title' => __('Enable Delivery Confirmation API', 'woocommerce'),
                'type' => 'select',
                'description' => '',
                'options' => array(
                    '1' => 'Yes',
                    '0' => 'No'
                ),
                'default' => '1'
            )
        );
    } // End init_form_fields()
}
