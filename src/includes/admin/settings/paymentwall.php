<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings for Paymentwall Gateway
 */
return array(
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
