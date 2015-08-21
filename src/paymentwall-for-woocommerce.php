<?php

defined('ABSPATH') or exit();
/*
 * Plugin Name: Paymentwall for WooCommerce
 * Plugin URI: https://www.paymentwall.com/en/documentation/WooCommerce/1409
 * Description: Official Paymentwall module for WordPress WooCommerce.
 * Version: 1.2.0
 * Author: The Paymentwall Team
 * Author URI: http://www.paymentwall.com/
 * Text Domain: paymentwall-for-woocommerce
 * License: The MIT License (MIT)
 *
 */

define('PW_DEFAULT_SUCCESS_PINGBACK_VALUE', 'OK');
define('PW_ORDER_STATUS_PENDING', 'wc-pending');
define('PW_ORDER_STATUS_COMPLETED', 'wc-completed');
define('PW_ORDER_STATUS_PROCESSING', 'wc-processing');
define('PW_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PW_PLUGIN_URL', plugins_url('', __FILE__));

function load_paymentwall_payments()
{
    if (!class_exists('WC_Payment_Gateway')) return; // Nothing happens here is WooCommerce is not loaded

    include(dirname(__FILE__) . '/lib/paymentwall-php/lib/paymentwall.php');
    include(dirname(__FILE__) . '/includes/class-paymentwall-abstract.php');
    include(dirname(__FILE__) . '/includes/class-paymentwall-gateway.php');
    include(dirname(__FILE__) . '/includes/class-paymentwall-brick.php');

    function paymentwall_payments($methods)
    {
        $methods[] = 'Paymentwall_Gateway';
        $methods[] = 'Paymentwall_Brick';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'paymentwall_payments');
}

add_action('plugins_loaded', 'load_paymentwall_payments', 0);

/**
 * Add Paymentwall Scripts
 */
function paymentwall_scripts()
{
    wp_register_script('placeholder', PW_PLUGIN_URL . '/assets/js/payment.js', array('jquery'), '1', true);
    wp_enqueue_script('placeholder');
}

add_action('wp_enqueue_scripts', 'paymentwall_scripts');

/**
 * Require the woocommerce plugin installed first
 */
function child_plugin_notice()
{
    ?>
    <div class="error">
        <p><?php echo __("Sorry, but Paymentwall Plugin requires the Woocommerce plugin to be installed and active.")?></p>
    </div>
<?php
}

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

add_action('admin_init', 'child_plugin_has_parent_plugin');

