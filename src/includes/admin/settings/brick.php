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
    ),
    'saved_cards' => array(
        'title' => __('Saved Cards', PW_TEXT_DOMAIN),
        'lable' => __('Enable Payment via Saved Cards', PW_TEXT_DOMAIN),
        'type' => 'checkbox',
        'description' => __('If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Paymentwall servers, not on your store', PW_TEXT_DOMAIN),
        'default' => 'no',
        'desc_tip' => true,
    )
);
