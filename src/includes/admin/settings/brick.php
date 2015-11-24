<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings for Paymentwall Gateway
 */
return array(
    'enabled' => array(
        'title' => __('Enable/Disable', PW_TEXT_DOMAIN),
        'type' => 'checkbox',
        'label' => __('Enable the Brick Credit Card Processing', PW_TEXT_DOMAIN),
        'default' => 'yes'
    ),
    'title' => array(
        'title' => __('Title', PW_TEXT_DOMAIN),
        'type' => 'text',
        'description' => __('This controls the title which the user sees during checkout.', PW_TEXT_DOMAIN),
        'default' => __('Credit Card', PW_TEXT_DOMAIN)
    ),
    'description' => array(
        'title' => __('Description', PW_TEXT_DOMAIN),
        'type' => 'text',
        'description' => __('This controls the description which the user sees during checkout.', PW_TEXT_DOMAIN),
        'default' => __("Pay via Brick Credit Card Processing.", PW_TEXT_DOMAIN)
    ),
    'publickey' => array(
        'title' => __('Public Key', PW_TEXT_DOMAIN),
        'type' => 'text',
        'description' => __('Your Brick Public Key', PW_TEXT_DOMAIN),
        'default' => ''
    ),
    'privatekey' => array(
        'title' => __('Secret Key', PW_TEXT_DOMAIN),
        'type' => 'text',
        'description' => __('Your Brick Private Key', PW_TEXT_DOMAIN),
        'default' => ''
    ),
    't_publickey' => array(
        'title' => __('Test Public Key', PW_TEXT_DOMAIN),
        'type' => 'text',
        'description' => __('Your Brick Test Public Key', PW_TEXT_DOMAIN),
        'default' => ''
    ),
    't_privatekey' => array(
        'title' => __('Test Secret Key', PW_TEXT_DOMAIN),
        'type' => 'text',
        'description' => __('Your Brick Test Private Key', PW_TEXT_DOMAIN),
        'default' => ''
    ),
    'test_mode' => array(
        'title' => __('Test Mode', PW_TEXT_DOMAIN),
        'type' => 'select',
        'description' => __('Enable test mode', PW_TEXT_DOMAIN),
        'options' => array(
            '0' => 'No',
            '1' => 'Yes'
        ),
        'default' => '1'
    ),
);
