<?php

/*
 * Paymentwall Api for WooCommerce
 *
 * Description: Official Paymentwall module for WordPress WooCommerce.
 * Plugin URI: https://www.paymentwall.com/en/documentation/WooCommerce/1409
 * Author: Paymentwall
 * License: The MIT License (MIT)
 *
 */
class Paymentwall_Api {

    CONST PAYMENTWALL_METHOD = 'paymentwall';
    CONST BRICK_METHOD = 'brick';
    private $settings;

    /**
     * Initial Paymentwall Configs
     */
    function initSettings($settings) {
        $this->settings = $settings;
        Paymentwall_Config::getInstance()->set(array(
            'api_type' => Paymentwall_Config::API_GOODS,
            'private_key' => $settings['secretkey']
        ));
    }

    function sendDeliveryApi($orderId) {
        $order = wc_get_order($orderId);
        $payment = wc_get_payment_gateway_by_order($order);
        $this->initSettings($payment->settings);

        // Call Delivery Confirmation API
        if (!empty($order) && $payment->settings['enable_delivery'] && ($payment->id == self::BRICK_METHOD || $payment->id == self::PAYMENTWALL_METHOD)) {
            // Delivery Confirmation
            $delivery = new Paymentwall_GenerericApiObject('delivery');
            $delivery->post($this->prepare_delivery_confirmation_data($orderId));
        }
    }

    function prepare_delivery_confirmation_data($orderId) {
        $order = wc_get_order($orderId);
        $shippingAddress = $order->get_address('shipping');
        $billingAddress = $order->get_address('billing');
        if (empty($shippingAddress['first_name']) && empty($shippingAddress['last_name'])) {
            $deliveryAddress = $billingAddress;
            $type = 'digital';
        } else {
            $deliveryAddress = array_merge($billingAddress, $shippingAddress);
            $type = 'physical';
        }
        return array(
            'payment_id' => $order->get_transaction_id(),
            'merchant_reference_id' => $order->id,
            'type' => $type,
            'status' => 'delivered',
            'estimated_delivery_datetime' => date('Y/m/d H:i:s'),
            'estimated_update_datetime' => date('Y/m/d H:i:s'),
            'refundable' => 'yes',
            'details' => 'Order status has been updated on ' . date('Y/m/d H:i:s'),
            'shipping_address[email]' => $deliveryAddress['email'],
            'shipping_address[firstname]' => $deliveryAddress['first_name'],
            'shipping_address[lastname]' => $deliveryAddress['last_name'],
            'shipping_address[country]' => $deliveryAddress['country'],
            'shipping_address[street]' => $deliveryAddress['address_1'],
            'shipping_address[state]' => $deliveryAddress['state'] ? $deliveryAddress['state'] : 'N/A',
            'shipping_address[zip]' => $deliveryAddress['postcode'],
            'shipping_address[city]' => $deliveryAddress['city'],
            'shipping_address[phone]' => $deliveryAddress['phone'],
            'reason' => 'none',
            'is_test' => $this->settings['test_mode'] ? 1 : 0,
            'attachments' => null
        );
    }
}