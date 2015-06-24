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

function loadPaymentwallGateway()
{

    if (!class_exists('WC_Payment_Gateway')) return; // Nothing happens here is WooCommerce is not loaded
    include (dirname(__FILE__) . '/lib/paymentwall-php/lib/paymentwall.php');
    include (dirname(__FILE__) . '/paymentwall_gateway.php');

    function WcPwGateway($methods)
    {
        $methods[] = 'Paymentwall_Gateway';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'WcPwGateway');
}

add_action('plugins_loaded', 'loadPaymentwallGateway', 0);