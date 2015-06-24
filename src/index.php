<?php

/*
 * Plugin Name: Paymentwall for WooCommerce
 * Plugin URI: http://www.paymentwall.com/
 * Description: Paymentwall Gateway for WooCommerce
 * Version: 1.0.0
 * Author: The Paymentwall Team
 * Author URI: http://www.paymentwall.com/
 * License: GNU General Public License 2.0 (GPL) http://www.gnu.org/licenses/gpl.html
 *
 */

define('REQUEST_CHARGE_BACK', 2);
define('DEFAULT_SUCCESS_PINGBACK_VALUE', 'OK');
define('WC_ORDER_STATUS_PENDING', 'wc-pending');
define('WC_ORDER_STATUS_COMPLETED', 'wc-completed');
define('WC_ORDER_STATUS_PROCESSING', 'wc-processing');

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