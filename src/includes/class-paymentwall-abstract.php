<?php

abstract class Paymentwall_Abstract extends WC_Payment_Gateway
{
    public $wcVersion = '';
    public $wcsVersion = '';

    public function __construct()
    {
        $this->plugin_path = PW_PLUGIN_PATH;

        // Load the settings.
        $this->init_settings();
        $this->init_form_fields();

        $this->wcVersion = $this->get_woo_version_number('woocommerce');
        $this->wcsVersion = $this->get_woo_version_number('woocommerce-subscriptions');
    }

    abstract public function init_configs($isPingback);

    protected function get_template($templateFileName, $data = array())
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

    protected function prepare_user_profile_data($order)
    {
        $orderData = $this->get_order_data($order);
        return array(
            'customer[city]' => $orderData['billing_city'],
            'customer[state]' => $orderData['billing_state'] ? $orderData['billing_state'] : 'NA',
            'customer[address]' => $orderData['billing_address1'],
            'customer[country]' => $orderData['billing_country'],
            'customer[zip]' => $orderData['billing_postcode'],
            'customer[username]' => $orderData['billing_email'],
            'customer[firstname]' => $orderData['billing_firstname'],
            'customer[lastname]' => $orderData['billing_lastname'],
            'email' => $orderData['billing_email'],
            'history[registration_date]' => get_userdata(get_current_user_id())->user_registered ? get_userdata(get_current_user_id())->user_registered : 'NA',
            'history[payments_amount]' => $this->cumulative_payments_customer(PW_ORDER_STATUS_COMPLETED, $orderData['billing_email']),
            'history[payments_number]' => count($this->get_customer_orders( PW_ORDER_STATUS_COMPLETED, $orderData['billing_email'])),
            'history[cancelled_payments]' => count($this->get_customer_orders( PW_ORDER_STATUS_CANCELLED, $orderData['billing_email'])),
        );

        return array(
            'customer[city]' => $order->get_billing_city(),
            'customer[state]' => $order->get_billing_state() ? $order->get_billing_state() : 'NA',
            'customer[address]' => $order->get_billing_address_1(),
            'customer[country]' => $order->get_billing_country(),
            'customer[zip]' => $order->get_billing_postcode(),
            'customer[username]' => $order->get_billing_email(),
            'customer[firstname]' => $order->get_billing_first_name(),
            'customer[lastname]' => $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'history[registration_date]' => get_userdata(get_current_user_id())->user_registered ? get_userdata(get_current_user_id())->user_registered : 'NA',
            'history[payments_amount]' => $this->cumulative_payments_customer(PW_ORDER_STATUS_COMPLETED, $order->get_billing_email() ),
            'history[payments_number]' => count($this->get_customer_orders( PW_ORDER_STATUS_COMPLETED, $order->get_billing_email() )),
            'history[cancelled_payments]' => count($this->get_customer_orders( PW_ORDER_STATUS_CANCELLED, $order->get_billing_email() )),
        );
    }

    /*
     * Display administrative fields under the Payment Gateways tab in the Settings page
     */
    function init_form_fields()
    {
        $this->form_fields = include($this->plugin_path . 'includes/admin/settings/' . $this->id . '.php');
    }

    /**
     * Displays a short description to the user during checkout
     */
    function payment_fields()
    {
        echo $this->settings['description'];
    }

    /**
     * Displays text like introduction, instructions in the admin area of the widget
     */
    public function admin_options()
    {
        ob_start();
        $this->generate_settings_html();
        $settings = ob_get_contents();
        ob_clean();

        echo $this->get_template('admin/options.html', array(
            'title' => $this->method_title,
            'description' => $this->method_description,
            'settings' => $settings
        ));
    }

    function getRealClientIP()
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            $headers = $_SERVER;
        }

        //Get the forwarded IP if it exists
        if (array_key_exists('X-Forwarded-For', $headers) && filter_var($headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $the_ip = $headers['X-Forwarded-For'];
        } elseif (array_key_exists('HTTP_X_FORWARDED_FOR', $headers) && filter_var($headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $the_ip = $headers['HTTP_X_FORWARDED_FOR'];
        } elseif (array_key_exists('Cf-Connecting-Ip', $headers)) {
            $the_ip = $headers['Cf-Connecting-Ip'];
        } else {
            $the_ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        }

        return $the_ip;
    }

    /**
     * Return the orders of a current user with specific order status.
     *
     * @param string $status, $billing_email
     * @return array
     */
    function get_customer_orders($status, $billing_email) {
        $customer_orders = get_posts( array(
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_billing_email',
                    'value' => $billing_email,
                ),
                array(
                    'key' => '_payment_method',
                    'value' => 'paymentwall',
                ),

            ),
            'post_type'   => 'shop_order',
            'numberposts' => -1,
            'post_status' => $status,
    
        ) );

        return $customer_orders;
    }
    
    function cumulative_payments_customer($status, $billing_email) {
        
        $customer_orders = $this->get_customer_orders($status, $billing_email);
        $sum_total = 0;
        
        if (!empty($customer_orders)) {
            foreach ( $customer_orders as $customer_order ) {
                $order = new WC_Order($customer_order->ID);
                $amount = $order->get_total();
                $sum_total += $amount;
            }
        }
        
        return $sum_total;
    }

    function s_datediff( $str_interval, $dt_menor, $dt_maior, $relative=false){

        if( is_string( $dt_menor)) $dt_menor = date_create( $dt_menor);
        if( is_string( $dt_maior)) $dt_maior = date_create( $dt_maior);

        $diff = date_diff( $dt_menor, $dt_maior, ! $relative);

        switch( $str_interval){
            case "year":
                $total = $diff->y + $diff->m / 12 + $diff->d / 365.25;
                break;
            case "month":
                $total= $diff->y * 12 + $diff->m + $diff->d/30 + $diff->h / 24;
                break;
            case 'week':
                $total = $diff->days / 7;
                break;
            case "day":
                $total = $diff->y * 365.25 + $diff->m * 30 + $diff->d + $diff->h/24 + $diff->i / 60;
                break;
        }
        if( $diff->invert) {
            return -1 * intval($total);
        } else {
            return intval($total);
        }
    }


    function get_woo_version_number($type) {
        // If get_plugins() isn't available, require it
        if ( ! function_exists( 'get_plugins' ) )
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

        // Create the plugins folder and file variables
        $plugin_folder = get_plugins( '/' . $type );
        $plugin_file = $type . '.php';

        // If the plugin version number is set, return it
        if ( isset( $plugin_folder[$plugin_file]['Version'] ) ) {
            return $plugin_folder[$plugin_file]['Version'];

        } else {
            // Otherwise return null
            return NULL;
        }
    }

    function get_order_data(WC_Order $order) {
        $orderData = array();
        $orderData['total'] = $order->get_total();
        $orderData['user_id'] = $order->get_user_id();

        if (version_compare('2.7', $this->wcVersion, '>')) {
            $orderData = array_merge($orderData, array(
                'order_id' => $order->id,
                'billing_city' => $order->billing_city,
                'billing_state' => $order->billing_state,
                'billing_address1' => $order->billing_address_1,
                'billing_country' => $order->billing_country,
                'billing_postcode' => $order->billing_postcode,
                'billing_firstname' => $order->billing_first_name,
                'billing_lastname' => $order->billing_last_name,
                'billing_email' => $order->billing_email,
                'currencyCode' => $order->order_currency,
            ));
        } else {
            $orderData = array_merge($orderData, array(
                'order_id' => $order->get_id(),
                'billing_city' => $order->get_billing_city(),
                'billing_state' => $order->get_billing_state(),
                'billing_address1' => $order->get_billing_address_1(),
                'billing_country' => $order->get_billing_country(),
                'billing_postcode' => $order->get_billing_postcode(),
                'billing_firstname' => $order->get_billing_first_name(),
                'billing_lastname' => $order->get_billing_last_name(),
                'billing_email' => $order->get_billing_email(),
                'currencyCode' => $order->get_currency(),
            ));
        }

        return $orderData;
    }

    function get_subscription_data(WC_Subscription $subscription) {
        if (version_compare('2.2', $this->wcsVersion, '>')) {
            $subsData = array(
                'schedule_trial_end' => strtotime($subscription->schedule_trial_end),
                'date_created' => strtotime($subscription->order_date),
                'billing_interval' => $subscription->billing_interval,
                'billing_period' => $subscription->billing_period,
                'trial_period' => $subscription->trial_period
            );
        } else {
            $subsData = $subscription->get_data();
            $subsData['schedule_trial_end'] = (!empty($subsData['schedule_trial_end'])) ? $subsData['schedule_trial_end']->getTimestamp() : null;
            $subsData['date_created'] = $subsData['date_created']->getTimestamp();
            $subsData['billing_interval'] = $subscription->get_billing_interval();
            $subsData['billing_period'] = $subscription->get_billing_period();
            $subsData['trial_period'] = $subscription->get_trial_period();
        }

        return $subsData;
    }
}