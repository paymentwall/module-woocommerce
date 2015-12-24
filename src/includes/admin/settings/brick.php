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
    'projectkey' => array(
        'title' => __('Project Key', PW_TEXT_DOMAIN),
        'type' => 'text',
        'description' => __('Your Paymentwall Key', PW_TEXT_DOMAIN),
        'default' => ''
    ),
    'secretkey' => array(
        'title' => __('Secret Key', PW_TEXT_DOMAIN),
        'type' => 'text',
        'description' => __('Your Paymentwall Secret Key', PW_TEXT_DOMAIN),
        'default' => ''
    ),
    'publickey' => array(
        'title' => __('Public Key', PW_TEXT_DOMAIN),
        'type' => 'text',
        'description' => __('Your Brick Public Key', PW_TEXT_DOMAIN),
        'default' => ''
    ),
    'privatekey' => array(
        'title' => __('Private Key', PW_TEXT_DOMAIN),
        'type' => 'text',
        'description' => __('Your Brick Private Key', PW_TEXT_DOMAIN),
        'default' => ''
    ),
);
