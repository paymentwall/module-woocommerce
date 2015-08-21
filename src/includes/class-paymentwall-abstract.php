<?php

abstract class Paymentwall_Abstract extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->plugin_path = PW_PLUGIN_PATH;

        // Load the settings.
        $this->init_settings();
        $this->init_form_fields();
    }

    abstract public function init_paymentwall_configs();

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
}