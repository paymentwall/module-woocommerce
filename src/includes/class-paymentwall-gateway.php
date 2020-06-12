<?php
/*
 * Paymentwall Gateway for WooCommerce
 *
 * Description: Official Paymentwall module for WordPress WooCommerce.
 * Plugin URI: https://www.paymentwall.com/en/documentation/WooCommerce/1409
 * Author: Paymentwall
 * License: The MIT License (MIT)
 *
 */
class Paymentwall_Gateway extends Paymentwall_Abstract {

    const USER_ID_GEOLOCATION = 'user101';
    
    public $id = 'paymentwall';
    public $has_fields = true;

    public function __construct() {
        $this->supports = array(
            'products',
            'subscriptions'
        );

        parent::__construct();

        $this->method_title = __('Paymentwall', PW_TEXT_DOMAIN);
        $this->method_description = __('Enables the Paymentwall Payment Solution. The easiest way to monetize your game or web service globally.', PW_TEXT_DOMAIN);
        $this->title = $this->settings['title'];
        $this->notify_url = str_replace('https:', 'http:', add_query_arg('wc-api', 'Paymentwall_Gateway', home_url('/')));

        // Our Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_api_' . $this->id . '_gateway', array($this, 'handle_action'));

        //Payment System intergrate
        add_action('woocommerce_review_order_before_payment', array($this, 'html_payment_system'));
        add_action('woocommerce_checkout_update_order_meta',  array($this,'update_payment_system_order_meta'));
        add_filter('woocommerce_order_get_payment_method_title', array($this,'customize_payment_gateways_title'), 10, 2 );


        add_filter('woocommerce_subscription_payment_gateway_supports', array($this, 'add_feature_support_for_subscription'), 11, 3);
    }

    /**
     * Initial Paymentwall Configs
     */
    function init_configs($isPingback = false) {
        Paymentwall_Config::getInstance()->set(array(
            'api_type' => Paymentwall_Config::API_GOODS,
            'public_key' => $this->settings['appkey'],
            'private_key' => $this->settings['secretkey']
        ));
    }

    /**
     * @param $order_id
     */
    function receipt_page($order_id) {
        $this->init_configs();
        $order = wc_get_order($order_id);
        $orderData = $this->get_order_data($order);
        $pwPsId = $this->get_payment_system_by_order_id($order->get_id());

        try {
            if (function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order)) {
                $subscription = wcs_get_subscriptions_for_order($order);
                $subscription = reset($subscription); // The current version does not support multi subscription
                $subscriptionData = $this->prepare_subscription_data($order, $subscription);

                $goods = array($subscriptionData['goods']);
            } else {
                $goods = array(
                    new Paymentwall_Product($order_id, $orderData['total'], $orderData['currencyCode'], 'Order #' . $order_id)
                );
            }
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
        }
        $userId = $orderData['user_id'];
        $widget = new Paymentwall_Widget(
            empty($userId) ? $orderData['billing_email'] : $orderData['user_id'],
            $this->settings['widget'],
            $goods,
            array_merge(
                array(
                    'email' => $orderData['billing_email'],
                    'integration_module' => 'woocommerce',
                    'test_mode' => $this->settings['test_mode'],
                    'show_post_trial_non_recurring' => (!empty($subscriptionData['showPostTrialNonRecurring'])) ? $subscriptionData['showPostTrialNonRecurring'] : 0,
                    'success_url' => $order->get_checkout_order_received_url()
                ),
                $this->prepare_user_profile_data($order),
                $this->prepare_ps_param($pwPsId)
            )
        );

        $iframe = $widget->getHtmlCode(array(
            'width' => '100%',
            'height' => '1000',
            'frameborder' => 0
        ));

        // Clear shopping cart
        WC()->cart->empty_cart();
        $paymentSystemName = $this->get_payment_system_by_order_id($order->get_id(), 'name');;
        if ($paymentSystemName == null) {
            $paymentSystemName = 'Paymentwall';
        }

        echo $this->get_template('widget.html', array(
            'orderId' => $order_id,
            'title' => __(sprintf('Please continue the purchase via %s using the widget below.', $paymentSystemName), PW_TEXT_DOMAIN),
            'iframe' => $iframe,
            'baseUrl' => get_site_url(),
            'pluginUrl' => plugins_url('', __FILE__)
        ));
    }

    function prepare_subscription_data(WC_Order $order, WC_Subscription $subscription) {
        $orderData = $this->get_order_data($order);
        $subsData = $this->get_subscription_data($subscription);

        $product = array(
            'id' => $orderData['order_id'],
            'name' => sprintf(__('Order #%s  - recurring payment', PW_TEXT_DOMAIN), $order->get_order_number()),
            'amount' => WC_Subscriptions_Order::get_recurring_total($order),
            'currencyCode' => $orderData['currencyCode'],
            'recurring' => true,
            'productType' => Paymentwall_Product::TYPE_SUBSCRIPTION,
            'periodLength' => $subsData['billing_interval'],
            'periodType' => $subsData['billing_period'],

        );

        $trialProduct = array(
            'id' => $orderData['order_id'],
            'amount' => $orderData['total'],
            'currencyCode' => $orderData['currencyCode'],
            'recurring' => true,
            'productType' => Paymentwall_Product::TYPE_SUBSCRIPTION,
            'name' => sprintf(__('Order #%s  - first time payment', PW_TEXT_DOMAIN), $order->get_order_number()),
            'periodType' => $subsData['trial_period'],
        );


        if (!empty($subsData['schedule_trial_end'])) { // has trial
            $trialLength = ($subsData['schedule_trial_end'] - $subsData['date_created']) / (3600*24);
            $trialProduct['periodLength'] = intval($trialLength);
            if ($orderData['total'] == 0) { // no setup fee
                $showPostTrialNonRecurring = 1;
            }
        } else {
            if ($orderData['total'] != WC_Subscriptions_Order::get_recurring_total($order)) { // has setup fee
                $trialProduct['periodType'] = $subsData['billing_period'];
                $trialProduct['periodLength'] = $subsData['billing_interval'];
            } else {
                $product['name'] = sprintf(__('Order #%s  - first time payment', PW_TEXT_DOMAIN), $order->get_order_number());
                $trialProduct = null;
            }
        }

        if (!empty($trialProduct)) {
            $pwTrialProduct = new Paymentwall_Product(
                $trialProduct['id'],
                $trialProduct['amount'],
                $trialProduct['currencyCode'],
                $trialProduct['name'],
                $trialProduct['productType'],
                $trialProduct['periodLength'],
                $trialProduct['periodType'],
                $trialProduct['recurring']
            );
        } else {
            $pwTrialProduct = null;
        }

        $pwProduct = new Paymentwall_Product(
            $product['id'],
            $product['amount'],
            $product['currencyCode'],
            $product['name'],
            $product['productType'],
            $product['periodLength'],
            $product['periodType'],
            $product['recurring'],
            $pwTrialProduct
        );
        return array(
            'goods' => $pwProduct,
            'showPostTrialNonRecurring' => !empty($showPostTrialNonRecurring) ? $showPostTrialNonRecurring : 0
        );
    }

    /**
     * Process the order after payment is made
     * @param int $order_id
     * @return array
     */
    function process_payment($order_id) {
        $order = wc_get_order($order_id);

        if (version_compare('2.7', $this->wcVersion, '>')) {
            return array(
                'result' => 'success',
                'redirect' => add_query_arg(
                    'key',
                    $order->order_key,
                    add_query_arg(
                        'order',
                        $order->id,
                        $order->get_checkout_payment_url(true)
                    )
                )
            );
        } else {
            return array(
                'result' => 'success',
                'redirect' => add_query_arg(
                    'key',
                    $order->get_order_key(),
                    $order->get_checkout_payment_url(true)
                )
            );
        }

    }

    /**
     * Check the response from Paymentwall's Servers
     */
    function ipn_response() {

        $original_order_id = $_GET['goodsid'];
        $order = wc_get_order($_GET['goodsid']);

        if (!$order) {
            die('The order is Invalid!');
        }

        $payment = wc_get_payment_gateway_by_order($order);
        $payment->init_configs(true);

        $pingback_params = $_GET;
        
        $pingback = new Paymentwall_Pingback($pingback_params, $this->getRealClientIP());
        if ($pingback->validate(true)) {

            if ($pingback->isDeliverable()) {

                if ($order->get_status() == PW_ORDER_STATUS_PROCESSING) {
                    die(PW_DEFAULT_SUCCESS_PINGBACK_VALUE);
                }

                if (paymentwall_subscription_enable()) {
                    $subscriptions = wcs_get_subscriptions_for_order( $original_order_id, array( 'order_type' => 'parent' ) );
                    $subscription  = array_shift( $subscriptions );
                    $subscription_key = get_post_meta($original_order_id, '_subscription_id');
                }

                if ($pingback->getParameter('initial_ref') && (isset($subscription_key[0]) && $subscription_key[0] == $pingback->getParameter('initial_ref'))) {
                    $subscription->update_status('on-hold');
                    $subscription->add_order_note(__('Subscription renewal payment due: Status changed from Active to On hold.', PW_TEXT_DOMAIN));
                    $new_order = wcs_create_renewal_order( $subscription );
                    $new_order->add_order_note(__('Payment approved by Paymentwall - Transaction Id: ' . $pingback->getReferenceId(), PW_TEXT_DOMAIN));
                    update_post_meta(!method_exists($new_order, 'get_id') ? $new_order->id : $new_order->get_id(), '_subscription_id', $pingback->getReferenceId());
                    $new_order->set_payment_method($subscription->payment_gateway);
                    $new_order->payment_complete($pingback->getReferenceId());
                } else {
                    $order->add_order_note(__('Payment approved by Paymentwall - Transaction Id: ' . $pingback->getReferenceId(), PW_TEXT_DOMAIN));
                    $order->payment_complete($pingback->getReferenceId());
                }

                if (!empty($subscriptions)) {
                    $action_args = array('subscription_id' => !method_exists($subscription, 'get_id') ? $subscription->id : $subscription->get_id());
                    $hooks = array(
                        'woocommerce_scheduled_subscription_payment',
                    );

                    foreach ($hooks as $hook) {
                        $result = wc_unschedule_action($hook, $action_args);
                    }
                }

            } elseif ($pingback->isCancelable()) {
                $order->cancel_order(__('Reason: ' . $pingback->getParameter('reason'), PW_TEXT_DOMAIN));
            } elseif ($pingback->isUnderReview()) {
                $order->update_status('on-hold');
            }

            die(PW_DEFAULT_SUCCESS_PINGBACK_VALUE);
        } else {
            die($pingback->getErrorSummary());
        }
    }

    /**
     * Process Ajax Request
     */
    function ajax_response() {
        $this->init_configs();

        $order = wc_get_order(intval($_POST['order_id']));
        $return = array(
            'status' => false,
            'url' => ''
        );

        if ($order) {
            if ($order->get_status() == PW_ORDER_STATUS_PROCESSING) {
                WC()->cart->empty_cart();
                $return['status'] = true;
                $return['url'] = get_permalink(wc_get_page_id('checkout')) . '/order-received/' . intval($_POST['order_id']) . '?key=' . $order->post->post_password;
            }
        }
        die(json_encode($return));
    }

    /**
     * Handle Action
     */
    function handle_action() {
        switch ($_GET['action']) {
            case 'ajax':
                $this->ajax_response();
                break;
            case 'ipn':
                $this->ipn_response();
                break;
            default:
                break;
        }
    }

    function prepare_delivery_confirmation_data($order, $ref) {
        return array(
            'payment_id' => $ref,
            'type' => 'digital',
            'status' => 'delivered',
            'estimated_delivery_datetime' => date('Y/m/d H:i:s'),
            'estimated_update_datetime' => date('Y/m/d H:i:s'),
            'refundable' => 'yes',
            'details' => 'Item will be delivered via email by ' . date('Y/m/d H:i:s'),
            'shipping_address[email]' => $order->get_billing_email(),
            'shipping_address[firstname]' => $order->get_shipping_first_name(),
            'shipping_address[lastname]' => $order->get_shipping_last_name(),
            'shipping_address[country]' => $order->get_shipping_country(),
            'shipping_address[street]' => $order->get_shipping_address_1(),
            'shipping_address[state]' => $order->get_shipping_state(),
            'shipping_address[zip]' => $order->get_shipping_postcode(),
            'shipping_address[city]' => $order->get_shipping_city(),
            'reason' => 'none',
            'is_test' => $this->settings['test_mode'] ? 1 : 0,
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

    public function get_support_payment() {
        $this->init_configs();
        $uesrIp = $this->get_user_ip();
        $userCountry = $this->get_country_by_ip($uesrIp);

        $params = array(
            'key' =>  $this->settings['appkey'],
            'country_code' => $userCountry,
            'sign_version' => 2
        );

        $params['sign'] = (new Paymentwall_Signature_Widget())->calculate(
            $params,
            $params['sign_version']
        );

        $url = 'https://api.paymentwall.com/api/payment-systems/?'.http_build_query($params);
        $curl = curl_init($url);
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,TRUE);

        $response = curl_exec($curl);

        return $response;
    }

    /**
     * Setup HTML payment method in checkout page
     */
    public function html_payment_system() {
        $paymentSystem = json_decode($this->get_support_payment());
        if (count($paymentSystem) > 0 && is_array($paymentSystem)) {
            echo '<ul class="wc_payment_methods payment_methods methods paymentwall-method">';
            foreach ($paymentSystem as $gateway) {
                $dataPaymentSystem = array(
                    'id'    => $gateway->id,
                    'name'  => $gateway->name
                );
                ?>
                <li class="wc_payment_method payment_method_paymentwall_ps">
                    <input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" type="radio" class="input-radio pw_payment_system" name="payment_method" data-payment-system='<?php echo json_encode($dataPaymentSystem); ?>' value="paymentwall"  />
                    <label for="payment_method_<?php echo esc_attr( $gateway->id ); ?>">
                        <?php echo $gateway->name; ?> <img alt="<?php echo $gateway->name; ?>" src="<?php echo $gateway->img_url;?>">
                    </label>
                </li>
                <?php
            }
            echo '</ul>';
            ?>
            <input id="pw_gateway" type="hidden" class="hidden" name="pw_payment_system" value=""  />
            <?php
            echo '<style>li.wc_payment_method.payment_method_paymentwall{ display: none } .wc_payment_methods:not(.paymentwall-method){ margin-top: 1rem; } </style>';
        }
    }

    /**
     * Update payment system to order
     * @param $order_id
     */
    function update_payment_system_order_meta( $order_id ) {
        if ( ! empty($_POST['pw_payment_system'])) {
            update_post_meta($order_id, 'pw_payment_system', sanitize_text_field($_POST['pw_payment_system']));
        }
    }

    /**
     * @param $oderId
     * @param string $type
     * @return mixed
     */
    function get_payment_system_by_order_id($oderId, $type = 'id') {
        if (is_int($oderId)) {
            $paymentSystem = json_decode(get_post_meta($oderId, 'pw_payment_system', true));

            if ($type == 'id') {
                return $paymentSystem->id;
            } else {
                return $paymentSystem->name;
            }
        }

    }

    /**
     * @param $orderData
     * @return array
     */
    protected function prepare_ps_param($pwPsId) {
        if (isset($pwPsId)) {
            return array('ps' => $pwPsId);
        }
        return array();
    }

    /**
     * Get user country by IP
     * @return mixed
     */
    public function get_country_by_ip($ip) {
        $params = array(
            'key' => $this->settings['appkey'],
            'uid' => self::USER_ID_GEOLOCATION,
            'user_ip' => $ip
        );
        $url = 'https://api.paymentwall.com/api/rest/country?' . http_build_query($params);
        $curl = curl_init($url);
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, TRUE);
        $response = curl_exec($curl);
        $response = json_decode($response);
        return $response->code;
    }

    /**
     * Get user IP
     * @return mixed|void
     */
    public function get_user_ip() {
        if ( ! empty($_SERVER['HTTP_CLIENT_IP']) ) {
        //check ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty($_SERVER['HTTP_X_FORWARDED_FOR']) ) {
        //to check ip is pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    /**
     * @param $order_id
     * @param $posted_data
     * @param $order
     */
    public function customize_payment_gateways_title($prop, $object)
    {
        $paymentSystemName = $this->get_payment_system_by_order_id($object->get_id(), 'name');
        if ($object->get_payment_method() == $this->id && $paymentSystemName != '') {
            $prop = $paymentSystemName;
            return $prop;
        }
    }

}
