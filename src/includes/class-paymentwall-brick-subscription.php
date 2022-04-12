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
            'subscription_cancellation',
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
        if ($this->cart_subscription_exists()) {
            $return = $this->process_subscription_payment($order);
        } else {
            $return = $this->process_standard_payment($order);
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
    public function process_subscription_payment(WC_Order $order)
    {
        if (!$this->is_valid_order()) {
            $this->checkout_error_response("Order is invalid");
        }

        session_start();
        $this->init_configs();
        $return = array(
            'result' => 'fail',
            'redirect' => ''
        );

        $orderData = $this->get_order_data($order);
        if (isset($_POST['wc-brick-payment-token']) && $_POST['wc-brick-payment-token'] != 'new') {
            $parameters = $_POST;
            $orderId = $order->get_id();
            $subscriptionInfo = $this->prepare_subscription_info($parameters, $orderId);
            $paymentwall_subscription = $this->create_subscription($subscriptionInfo);
        } else {
            $paymentwall_subscription = WC()->session->get('brick_subscription');
        }
        $response = json_decode($paymentwall_subscription->getRawResponseData());
        if ($paymentwall_subscription->isSuccessful()) {
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
            WC()->session->set('amount_charge_brick', null);
            WC()->session->set('cart_charge_brick', null);
        } else {
            $this->checkout_error_response($response->error);

        }
        $_SESSION['message_thank_you_page'] = $return['message'];
    
        return $return;
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
    
    public function handle_brick_subscription()
    {
        if (!$this->is_processable_subscription_payment()) {
            return json_encode($this->prepare_unprocessable_subscriptions_error_response());
        }

        $orderId = $this->create_temporary_order();
        $this->set_subscription($orderId);

        $this->init_configs();
        $parameters = $_POST;
        $dataPrepare = $this->prepare_subscription_info($parameters, $orderId);
        $subscription = $this->create_subscription($dataPrepare);
        WC()->session->set('brick_subscription', $subscription);
        $response = $subscription->getPublicData();
        $responseData = json_decode($subscription->getRawResponseData());

        WC()->session->set('brick_response_subscription', $responseData);
        WC()->session->set('amount_charge_brick', (float) WC()->cart->get_total('edit'));
        WC()->session->set('cart_charge_brick', WC()->cart->get_cart());
        $result = [
            'is_successful' => $subscription->isSuccessful(),
            'payment' => $responseData
        ];
        WC()->session->set('email_brick_subscription', $parameters['email']);
    
        $result = array_merge($result, json_decode($response, true));
       
        return json_encode($result);
    }

    /**
     * @return int
     */
    private function is_processable_subscription_payment()
    {
        $count = 0;
        if ( ! empty( WC()->cart->cart_contents ) && ! wcs_cart_contains_renewal() ) {
            foreach ( WC()->cart->cart_contents as $cart_item ) {
                if ( WC_Subscriptions_Product::is_subscription( $cart_item['data'] ) ) {
                    $count++;
                } else {
                    return false;
                }
            }
        }
        if ($count > 1) {
            return false;
        }

        return true;
    }

    /**
     * @return array[]
     */
    private function prepare_unprocessable_subscriptions_error_response()
    {
        return [
            "error" => [
                'message' => 'Brick currently does not support payment for multiple subscriptions or mixed cart'
            ]
        ];
    }
    
    public function create_subscription($dataPrepare)
    {
        $subscription = new Paymentwall_Subscription();
        $subscription->create($dataPrepare);
        
        return $subscription;
    }

    /**
     * @param $parameters
     * @param $orderId
     * @return array
     */
    public function prepare_subscription_info($parameters, $orderId)
    {
        $subscription = $this->get_subscription($orderId);
        $subscriptionData = $this->get_subscription_data($subscription);

        $subscriptionInfo = array(
            'token' => $parameters['brick_token'],
            'email' => $parameters['email'],
            'currency' => get_woocommerce_currency(),
            'amount' => WC_Subscriptions_Order::get_recurring_total(wc_get_order($orderId)),
            'fingerprint' => $_POST['brick_fingerprint'],
            'plan' => $orderId,
            'description' => PW_TEXT_DOMAIN,
            'period' => $subscriptionData['billing_period'],
            'period_duration' => $subscriptionData['billing_interval'],
        );
        if (!empty($_POST['wc-brick-payment-token'])) {
            $token = WC_Payment_Tokens::get($_POST['wc-brick-payment-token']);
            $subscriptionInfo['token'] = $token->get_token();
            $subscriptionInfo['email'] = $token->get_meta('email');
        }
        $trialData = $this->prepare_trial_data(wc_get_order($orderId), $subscription);
        $subscriptionInfo = array_merge($subscriptionInfo, $trialData);

        $userProfile = $this->prepare_user_profile();
        
        return array_merge($subscriptionInfo, $userProfile);
    }

    /**
     * @param $orderId
     */
    private function set_subscription($orderId)
    {
        WC_Subscriptions_Cart::calculate_subscription_totals(0, WC()->cart);
        foreach (WC()->cart->recurring_carts as $cart) {
            WC_Subscriptions_Checkout::create_subscription(wc_get_order( $orderId ), $cart, []);
        }
    }

    /**
     * @param $orderId
     * @return mixed
     */
    private function get_subscription($orderId)
    {
        $subscription = wcs_get_subscriptions_for_order(wc_get_order($orderId));
        $subscription = reset($subscription);

        return $subscription;
    }

    /**
     * @return mixed
     */
    protected function create_temporary_order()
    {
        $order = new WC_Order();
        $order->set_total( WC()->cart->get_total( 'edit' ) );
        $cartHash = WC()->cart->get_cart_hash();
        $order->set_cart_hash( $cartHash );
        $order->set_customer_id(get_current_user_id());
        $orderId = $order->save();
        $order = wc_get_order( $orderId );
        WC()->session->set( 'order_awaiting_payment', $orderId);

        return $orderId;
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
    
    public function prepare_user_profile()
    {
        return parent::prepare_user_profile();
    }
    
}