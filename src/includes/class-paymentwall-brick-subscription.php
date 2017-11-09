<?php

/*
 * Paymentwall Brick for WooCommerce
 *
 * Description: Official Paymentwall module for WordPress WooCommerce.
 * Plugin URI: https://www.paymentwall.com/en/documentation/WooCommerce/1409
 * Author: Paymentwall
 * License: The MIT License (MIT)
 *
 */

class Paymentwall_Brick_Subscription extends Paymentwall_Brick {

    public function __construct() {
        parent::__construct();
        $this->supports = array(
            'products',
            'subscriptions',
            'subscription_suspension',
            'subscription_reactivation'
        );

        $this->reference_transaction_supported_features = array(
            'subscription_cancellation',
            'subscription_payment_method_change',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'subscription_amount_changes',
            'subscription_date_changes',
        );


        add_action('woocommerce_subscription_cancelled_' . $this->id, array($this, 'cancel_subscription_action'));
        add_filter('woocommerce_subscription_payment_gateway_supports', array($this, 'add_feature_support_for_subscription'), 10, 3);
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) {

        $this->init_configs();
        $order = wc_get_order($order_id);

        try {
            if (wcs_order_contains_subscription($order)) {
                $subscription = wcs_get_subscriptions_for_order($order);
                $subscription = reset($subscription); // The current version does not support multi subscription
                $return = $this->process_subscription_payment($order, $subscription);
            } else {
                $return = $this->process_standard_payment($order);
            }

        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
        }

        // Return redirect
        return $return;
    }

    /**
     * @param $order
     * @param $subscription
     * @return array
     * @throws Exception
     */
    public function process_subscription_payment(WC_Order $order, WC_Subscription $subscription) {
        $this->init_configs();
        $return = array(
            'result' => 'fail',
            'redirect' => ''
        );

        $orderData = $this->get_order_data($order);

        $userId = $orderData['user_id'];
        $billingEmail = $orderData['billing_email'];

        $paymentwall_subscription = new Paymentwall_Subscription();
        $paymentwall_subscription->create(array_merge(
            $this->prepare_subscription_data($order, $subscription),
            $this->prepare_user_profile_data($order),
            array(
                'custom[integration_module]' => 'woocommerce',
                'uid' => empty($userId) ? (empty($billingEmail) ? $this->getRealClientIP() : $orderData['billing_email']) : $orderData['user_id']
            )
        ));
        $response = json_decode($paymentwall_subscription->GetRawResponseData());

        if ($paymentwall_subscription->isSuccessful() && $response->object == 'subscription') {

            if ($paymentwall_subscription->isActive()) {
                // Add order note
                $order->add_order_note(sprintf(__('Brick subscription payment approved (ID: %s)', PW_TEXT_DOMAIN), $response->id));
                update_post_meta( $orderData['order_id'], '_subscription_id', $response->id);
            }

            $return['result'] = 'success';
            $return['redirect'] = $this->get_return_url($order);
            $return['message'] = 'Your order has been received !';

            // Clear shopping cart
            WC()->cart->empty_cart();
            WC()->session->set('orderId', null);
        } elseif (!empty($response->secure)) {
            WC()->session->set('orderId', $orderData['order_id']);
            $return['result'] = 'secure';
            $return['secure'] = $response->secure->formHTML;
            die(json_encode($return));
        } else {
            $return['result'] ='error';
            $return['message'] = $response->error;
            wc_add_notice(__($response->error), 'error');
            die(json_encode($return));
        }

        return $return;
    }

    /**
     * @param $order
     * @param $subscription
     * @return array
     * @throws Exception
     */
    protected function prepare_subscription_data(WC_Order $order, WC_Subscription $subscription) {
        if (!isset($_POST['brick'])) {
            throw new Exception("Payment Invalid!");
        }

        $brick = $_POST['brick'];
        $trial_data = $this->prepare_trial_data($order, $subscription);

        $orderData = $this->get_order_data($order);
        $subscriptionData = $this->get_subscription_data($subscription);

        $data = array(
            'amount' => WC_Subscriptions_Order::get_recurring_total($order),
            'currency' => $orderData['currencyCode'],
            'email' => $orderData['billing_email'],
            'description' => sprintf(__('%s - Order #%s', PW_TEXT_DOMAIN), esc_html(get_bloginfo('name', 'display')), $order->get_order_number()),
            'plan' => $orderData['order_id'],
            'period' => $subscriptionData['billing_period'],
            'period_duration' => $subscriptionData['billing_interval'],
            'secure_token' => (!empty($brick['cc_brick_secure_token'])) ? $brick['cc_brick_secure_token'] : null,
            'charge_id' => (!empty($brick['cc_brick_charge_id'])) ? $brick['cc_brick_charge_id'] : null,
        );

        if ($brick['token'] && $brick['fingerprint']) {
            $data = array_merge($data, array(
                'token' => $brick['token'],
                'fingerprint' => $brick['fingerprint']
            ));
        } elseif (!empty($_POST['wc-brick-payment-token'])) {
            $token = WC_Payment_Tokens::get($_POST['wc-brick-payment-token'])->get_token();
            $data = array_merge($data, array(
                'token' => $token
            ));
        }

        return array_merge(
            $data,
            $trial_data
        );
    }

    /**
     * Include total of onetime payments, physical products
     *
     * @param $order
     * @param $subscription
     * @return array
     */
    protected function prepare_trial_data(WC_Order $order, WC_Subscription $subscription) {
        $orderData = $this->get_order_data($order);
        $subscriptionData = $this->get_subscription_data($subscription);

        $trial_end = $subscriptionData['schedule_trial_end'];
        $start = $subscriptionData['date_created'];

        $trial_period = $subscriptionData['trial_period'];
        $trial_period_duration = 0;

        if ($trial_end) {
            $trial_period_duration = round(($trial_end - $start) / (3600 * 24));
        }

        // No trial or signup fee or normal product
        if (!$trial_end && $orderData['total'] == WC_Subscriptions_Order::get_recurring_total($order)) {
            return array();
        } else {
            if (!$trial_end) {
                $trial_period = $subscriptionData['billing_period'];
                $trial_period_duration = $subscriptionData['billing_interval'];
            }
        }

        return array(
            'trial[amount]' => $orderData['total'],
            'trial[currency]' => $orderData['currencyCode'],
            'trial[period]' => $trial_period,
            'trial[period_duration]' => $trial_period_duration,
        );
    }

    /**
     * @param $is_supported
     * @param $feature
     * @param $subscription
     * @return bool
     */
    public function add_feature_support_for_subscription($is_supported, $feature, $subscription) {
        if ($this->id === $subscription->get_payment_method()) {

            if ('gateway_scheduled_payments' === $feature) {
                $is_supported = false;
            } elseif (in_array($feature, $this->supports)) {
                $is_supported = true;
            }
        }
        return $is_supported;
    }

    /**
     * Cancel subscription from merchant site
     *
     * @param $subscription
     */
    public function cancel_subscription_action($subscription) {
        $this->init_configs();
        $order_id = !method_exists($subscription, 'get_id') ? $subscription->order->id : $subscription->order->get_id();

        if ($subscription_key = $this->get_subscription_key($order_id)) {
            $subscription_api = new Paymentwall_Subscription($subscription_key);
            $result = $subscription_api->cancel();
        }
    }

    /**
     * @param $post_id
     * @return mixed
     */
    protected function get_subscription_key($post_id) {
        $subscription_key = get_post_meta($post_id, '_subscription_id');
        return isset($subscription_key[0]) ? $subscription_key[0] : false;
    }
}