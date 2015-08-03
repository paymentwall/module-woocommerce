<?php

/*
 * Plugin Name: Paymentwall for WooCommerce
 * Plugin URI: https://www.paymentwall.com/en/documentation/WooCommerce/1409
 * Description: Official Paymentwall module for WordPress WooCommerce.
 * Version: 1.0.1
 * Author: The Paymentwall Team
 * Author URI: http://www.paymentwall.com/
 * License: The MIT License (MIT)
 *
 */

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

/**
 * Require the woocommerce plugin installed first
 */
add_action('admin_init', 'child_plugin_has_parent_plugin');
function child_plugin_has_parent_plugin()
{
    if (is_admin() && current_user_can('activate_plugins') && !is_plugin_active('woocommerce/woocommerce.php')) {
        add_action('admin_notices', 'child_plugin_notice');

        deactivate_plugins(plugin_basename(__FILE__));
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
}

function child_plugin_notice()
{
    ?>
    <div class="error">
        <p>Sorry, but Paymentwall Plugin requires the Woocommerce plugin to be installed and active.</p>
    </div>
<?php
}
