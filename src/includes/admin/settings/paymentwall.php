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
        'label' => __('Enable the Paymentwall Payment Solution', PW_TEXT_DOMAIN),
        'default' => 'yes'
    ),
    'title' => array(
        'title' => __('Title', PW_TEXT_DOMAIN),
        'type' => 'text',
        'description' => __('This controls the title which the user sees during checkout.', PW_TEXT_DOMAIN),
        'default' => __('Paymentwall', PW_TEXT_DOMAIN)
    ),
    'description' => array(
        'title' => __('Description', PW_TEXT_DOMAIN),
        'type' => 'text',
        'description' => __('This controls the description which the user sees during checkout.', PW_TEXT_DOMAIN),
        'default' => __("Pay via Paymentwall.", PW_TEXT_DOMAIN)
    ),
    'appkey' => array(
        'title' => __('Project Key', PW_TEXT_DOMAIN),
        'type' => 'text',
        'description' => __('Your Paymentwall Project Key', PW_TEXT_DOMAIN),
        'default' => ''
    ),
    'secretkey' => array(
        'title' => __('Secret Key', PW_TEXT_DOMAIN),
        'type' => 'text',
        'description' => __('Your Paymentwall Secret Key', PW_TEXT_DOMAIN),
        'default' => ''
    ),
    'widget' => array(
        'title' => __('Widget Code', PW_TEXT_DOMAIN),
        'type' => 'text',
        'description' => __('Enter your preferred widget code', PW_TEXT_DOMAIN),
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
        'default' => '0'
    ),
    'enable_delivery' => array(
        'title' => __('Enable Delivery Confirmation API', PW_TEXT_DOMAIN),
        'type' => 'select',
        'description' => '',
        'options' => array(
            '1' => 'Yes',
            '0' => 'No'
        ),
        'default' => '1'
    )
);
