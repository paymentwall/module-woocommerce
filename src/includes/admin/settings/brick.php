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
        'label' => __('Enable the Brick Credit Card Processing', 'woocommerce'),
        'default' => 'yes'
    ),
    'title' => array(
        'title' => __('Title', 'woocommerce'),
        'type' => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
        'default' => __('Credit Card', 'woocommerce')
    ),
    'description' => array(
        'title' => __('Description', 'woocommerce'),
        'type' => 'text',
        'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
        'default' => __("Pay via Brick Credit Card Processing.", 'woocommerce')
    ),
    'publickey' => array(
        'title' => __('Public Key', 'woocommerce'),
        'type' => 'text',
        'description' => __('Your Brick Public Key', 'woocommerce'),
        'default' => ''
    ),
    'privatekey' => array(
        'title' => __('Secret Key', 'woocommerce'),
        'type' => 'text',
        'description' => __('Your Brick Private Key', 'woocommerce'),
        'default' => ''
    ),
    't_publickey' => array(
        'title' => __('Test Public Key', 'woocommerce'),
        'type' => 'text',
        'description' => __('Your Brick Test Public Key', 'woocommerce'),
        'default' => ''
    ),
    't_privatekey' => array(
        'title' => __('Test Secret Key', 'woocommerce'),
        'type' => 'text',
        'description' => __('Your Brick Test Private Key', 'woocommerce'),
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
);
