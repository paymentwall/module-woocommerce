<?php

defined('ABSPATH') or exit();
/*
 * Plugin Name: Paymentwall for WooCommerce
 * Plugin URI: https://docs.paymentwall.com/modules/woocommerce
 * Description: Official Paymentwall module for WordPress WooCommerce.
 * Version: 1.13.0
 * Author: The Paymentwall Team
 * Author URI: http://www.paymentwall.com/
 * Text Domain: paymentwall-for-woocommerce
 * License: The MIT License (MIT)
 * Domain Path: /languages
 */

define('PW_TEXT_DOMAIN', 'paymentwall-for-woocommerce');
define('PW_DEFAULT_SUCCESS_PINGBACK_VALUE', 'OK');
define('PW_ORDER_STATUS_PENDING', 'wc-pending');
define('PW_ORDER_STATUS_COMPLETED', 'wc-completed');
define('PW_ORDER_STATUS_PROCESSING', 'wc-processing');
define('PW_ORDER_STATUS_CANCELLED', 'wc-cancelled');
define('PW_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PW_PLUGIN_URL', plugins_url('', __FILE__));

function paymentwall_subscription_enable(){
    return class_exists('WC_Subscriptions_Order');
}

function load_paymentwall_payments() {
    if (!class_exists('WC_Payment_Gateway')) return; // Nothing happens here is WooCommerce is not loaded

    include(PW_PLUGIN_PATH . '/lib/paymentwall-php/lib/paymentwall.php');
    include(PW_PLUGIN_PATH . '/includes/class-paymentwall-abstract.php');
    include(PW_PLUGIN_PATH . '/includes/class-paymentwall-gateway.php');
    include(PW_PLUGIN_PATH . '/includes/class-paymentwall-brick.php');
    include(PW_PLUGIN_PATH . '/includes/class-paymentwall-brick-subscription.php');
    include(PW_PLUGIN_PATH . '/includes/class-paymentwall-api.php');

    function paymentwall_payments($methods) {
        $methods[] = 'Paymentwall_Gateway';
        if (paymentwall_subscription_enable()) {
            $methods[] = 'Paymentwall_Brick_Subscription';
        } else {
            $methods[] = 'Paymentwall_Brick';
        }
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'paymentwall_payments');
}

add_action('plugins_loaded', 'load_paymentwall_payments', 0);

/**
 * Add Paymentwall Scripts
 */
function paymentwall_scripts() {
    wp_register_script('placeholder', PW_PLUGIN_URL . '/assets/js/payment.js', array('jquery'), '1', true);
    wp_enqueue_script('placeholder');
}

add_action('wp_enqueue_scripts', 'paymentwall_scripts');

/**
 * Require the woocommerce plugin installed first
 */
function pw_child_plugin_notice() {
    ?>
    <div class="error">
        <p><?php echo __("Sorry, but Paymentwall Plugin requires the Woocommerce plugin to be installed and active.", PW_TEXT_DOMAIN)?></p>
    </div>
<?php
}

function pw_child_plugin_has_parent_plugin() {
    if (is_admin() && current_user_can('activate_plugins') && !is_plugin_active('woocommerce/woocommerce.php')) {
        add_action('admin_notices', 'pw_child_plugin_notice');

        deactivate_plugins(plugin_basename(__FILE__));
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
}

add_action('admin_init', 'pw_child_plugin_has_parent_plugin');

function sendDeliveryApi($orderId) {
    $paymentwallApi = new Paymentwall_Api();
    $paymentwallApi->sendDeliveryApi($orderId, Paymentwall_Api::DELIVERY_STATUS_DELIVERED);
}
add_action('woocommerce_order_status_completed', 'sendDeliveryApi');

function sendDeliveryApiOrderPlace($orderId) {
    $paymentwallApi = new Paymentwall_Api();
    $paymentwallApi->sendDeliveryApi($orderId, Paymentwall_Api::DELIVERY_STATUS_ORDER_PLACE);
}
add_action('woocommerce_order_status_processing', 'sendDeliveryApiOrderPlace');

function sendDeliveryApiOrderShipped($meta_id, $post_id, $meta_key, $meta_value) {
    if ($meta_key != '_wc_shipment_tracking_items'){
        return;
    }

    $order = wc_get_order($post_id);
    if (!$order) {
        return;
    }

    if (check_order_has_virtual_product($order)) {
        return;
    }

    $trackingData = !is_array($meta_value) ? unserialize($meta_value) : $meta_value;

    if (empty($trackingData) || !is_array($trackingData)) {
        return;
    }

    $trackingData = end($trackingData);

    $paymentwallApi = new Paymentwall_Api();
    $paymentwallApi->sendDeliveryApi($post_id, Paymentwall_Api::DELIVERY_STATUS_ORDER_SHIPPED, $trackingData);
}
add_action( 'added_post_meta', 'sendDeliveryApiOrderShipped', 10, 4 );

function check_order_has_virtual_product(WC_Order $order) {
    $items = $order->get_items();
    foreach ($items as $item) {
        if ($item->is_type('line_item')) {
            $product = $item->get_product();

            if (!$product) {
                continue;
            }

            if ($product->is_virtual()) {
                return true;
            }
        }
    }

    return false;
}

add_action('init', 'start_session');
function start_session() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

add_action('updated_postmeta', 'pw_on_order_tracking_change', 10, 4);

add_action('added_post_meta', 'pw_on_order_tracking_change', 10, 4);

function pw_on_order_tracking_change($meta_id, $post_id, $meta_key, $meta_value) {
    if ($meta_key != '_wc_shipment_tracking_items' || empty($meta_value)) {
        return;
    }

    $tracking_data = $meta_value;

    if (!is_array($meta_value)) {
        $tracking_data = unserialize($meta_value);
    }

    if (empty($tracking_data[0])) {
        return;
    }

    $tracking_data_to_send = $tracking_data[0];

    $order = wc_get_order($post_id);
    if (!$order) {
        return;
    }

    $gateway = wc_get_payment_gateway_by_order($order);
    if (is_gateway_valid($gateway)) {
        return;
    }

    pw_update_delivery_status($order, Paymentwall_Api::DELIVERY_STATUS_ORDER_SHIPPED, $tracking_data_to_send);
}

function is_gateway_valid($gateway = null) {
    if (empty($gateway) || empty($gateway->id) || $gateway->id != Paymentwall_Gateway::PAYMENTWALL_METHOD) {
        return false;
    }

    return true;
}

function pw_update_delivery_status(WC_Order $order, $status, $tracking_data = null) {
    try {
        $paymentwallApi = new Paymentwall_Api();
        $paymentwallApi->sendDeliveryApi($order->get_id(), $status, $tracking_data);
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

add_filter('woocommerce_available_payment_gateways', 'addPaymentwallGateway', 99, 2);
function addPaymentwallGateway($availableGateways){

    if (is_checkout() || is_checkout_pay_page()) {

        $paymentwallGateway = new Paymentwall_Gateway();

        if (!$paymentwallGateway->is_available()) {
            return $availableGateways;
        }

        if (!array_key_exists(Paymentwall_Gateway::PAYMENTWALL_METHOD, $availableGateways)) {
            $availableGateways[Paymentwall_Gateway::PAYMENTWALL_METHOD] = $paymentwallGateway;
        }
    }

    return $availableGateways;
}

add_filter('woocommerce_available_payment_gateways', 'addBrickGateway', 100, 2);
function addBrickGateway($availableGateways)
{

    if (is_checkout() || is_checkout_pay_page()){

        $brickGateway = new Paymentwall_Brick();

        if (!$brickGateway->is_available()) {
            return $availableGateways;
        }

        if (!array_key_exists(Paymentwall_Brick::BRICK_METHOD, $availableGateways)) {
            $availableGateways[Paymentwall_Brick::BRICK_METHOD] = $brickGateway;
        }
    }
    return $availableGateways;
}

add_action('woocommerce_review_order_before_payment', 'html_payment_system');
function html_payment_system() {
    $paymentwallGateway = new Paymentwall_Gateway();

    if (!$paymentwallGateway->is_available()) {
        return;
    }

    $paymentMethods = $paymentwallGateway->get_local_payment_methods();
    if (is_array($paymentMethods) && !empty($paymentMethods)) {
        echo '<ul class="wc_payment_methods payment_methods methods paymentwall-method">';
        foreach ($paymentMethods as $gateway) {
            $dataPaymentSystem = array(
                'id' => $gateway['id'],
                'name' => $gateway['name']
            );
            ?>
            <li class="wc_payment_method payment_method_paymentwall_ps">
                <input id="payment_method_<?php echo esc_attr($gateway['id']); ?>" type="radio"
                       class="input-radio pw_payment_system" name="payment_method"
                       data-payment-system='<?php echo json_encode($dataPaymentSystem); ?>'
                       value="paymentwall"/>
                <label for="payment_method_<?php echo esc_attr($gateway['id']); ?>">
                    <?php echo $gateway['name']; ?> <img alt="<?php echo $gateway['name']; ?>"
                                                         src="<?php echo $gateway['img_url']; ?>">
                </label>
            </li>
            <?php
        }
        echo '</ul>';
        ?>
        <input id="pw_payment_method" type="hidden" class="hidden" name="pw_payment_system" value=""/>
        <script type="text/javascript">
            var pwPSs = document.getElementsByClassName("pw_payment_system");
            for (var i = 0; i < pwPSs.length; i++) {
                pwPSs[i].addEventListener('click', function(){
                    var paymentData = this.getAttribute('data-payment-system');
                    document.getElementById("pw_payment_method").value = paymentData;
                }, false);
            }
        </script>
        <style>li.wc_payment_method.payment_method_paymentwall {
                display: none
            }

            .wc_payment_methods:not(.paymentwall-method) {
                margin-top: 1rem;
            } </style>
        <?php
    }
}


add_action( 'init', 'paymentwall_load_textdomain' );
function paymentwall_load_textdomain() {
    load_plugin_textdomain( PW_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_filter('woocommerce_thankyou_order_received_text', 'custom_message_thank_you_page', 20, 2);
function custom_message_thank_you_page()
{
    $str = null;
    if (isset($_SESSION['message_thank_you_page'])) {
        $str = '<h3>' . $_SESSION['message_thank_you_page'] . '</h3>';
    }

    return $str;
}