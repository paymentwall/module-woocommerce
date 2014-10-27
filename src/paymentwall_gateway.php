<?php

/*
 * Paymentwall Gateway for WooCommerce
 *
 * Description: Official Paymentwall module for WordPress WooCommerce.
 * Plugin URI: https://www.paymentwall.com/en/documentation/WooCommerce/1409
 * Version: 0.2.1
 * Author: Paymentwall
 * License: MIT
 *
 */

add_action ( 'plugins_loaded', 'loadPaymentwallGateway', 0 );

function loadPaymentwallGateway () {

    if ( ! class_exists ( 'WC_Payment_Gateway' ) ) return; // Nothing happens here is WooCommerce is not loaded
    
	class Paymentwall_Gateway extends WC_Payment_Gateway {
	
		public function __construct () {

			$this->id = 'paymentwall';
			$this->icon = plugins_url ( 'paymentwall-for-woocommerce/images/icon.png' );
			$this->has_fields = true;
			$this->method_title = __( 'Paymentwall', 'woocommerce' );

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();
			
			// Load Paymentwall Merchant Information
			$this->app_key = $this->settings['appkey'];
			$this->secret_key = $this->settings['secretkey'];
			$this->widget_code = $this->settings['widget'];
			$this->description = $this->settings['description'];
			
			$this->title = 'Paymentwall';
			$this->notify_url = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'Paymentwall_Gateway', home_url ( '/' ) ) );
			
			// Our Actions
			add_action ( 'woocommerce_update_options_payment_gateways_' . $this->id, array ( $this, 'process_admin_options' ) );
			add_action ( 'woocommerce_receipt_paymentwall', array ( $this, 'receipt_page' ) );
			add_action ( 'woocommerce_api_paymentwall_gateway', array( $this, 'check_ipn_response' ) );
			
		}
		
		/*
		 * Makes the widget call
		 */
		function receipt_page ( $order_id ) {
		
			$order = new WC_Order ( $order_id );
			
			$params = array (
			
				'key' => $this->app_key,
		
				'uid' => $order->billing_email,
		
				'widget' => $this->widget_code,
		
				'sign_version' => 2,
		
				'amount' => $order->order_total,
				
				'email' => $order->billing_email,
		
				'currencyCode' => $order->order_currency,
		
				'ag_name' => 'Order #' . $order->id,
		
				'ag_external_id' => $order->id,
		
				'ag_type' => 'fixed'

			);
            
			$params['sign'] = $this->calculateWidgetSignature($params, $this->secret_key);
			$url = 'https://api.paymentwall.com/api/subscription';
			
			echo '<p>'.__( 'Please continue the purchase via Paymentwall using the widget below.', 'woocommerce' ).'</p>';
			echo '<iframe width="700" height="800" src="' . $url . '?' . http_build_query ( $params ) . '"></iframe>';
			
		}
		
		/*
		 * Process the order after payment is made
		 */
		function process_payment ( $order_id ) {
			
			$order = new WC_Order ( $order_id );
			
			global $woocommerce;
			
			if ( isset ( $_REQUEST [ 'ipn' ] ) && $_REQUEST [ 'ipn' ] == true ) {
			
				// Remove cart
				$woocommerce->cart->empty_cart();
				
				// Payment complete
				$order->payment_complete ();
			
				return array (
					'result' 	=> 'success',
					'redirect'	=> add_query_arg( 'key', $order->order_key, add_query_arg ( 'order', $order_id, get_permalink ( woocommerce_get_page_id ( 'thanks' ) ) ) )
					
				);
			
			} else {
			
				return array(
					'result' 	=> 'success',
					'redirect'	=> add_query_arg( 'key', $order->order_key, add_query_arg ( 'order', $order_id, get_permalink ( woocommerce_get_page_id ( 'pay' ) ) ) )
				);
				
			}
		}

		/*
		 * Display administrative fields under the Payment Gateways tab in the Settings page
		 */
		function init_form_fields() {
		
			$this->form_fields = array (
				'enabled' => array (
					'title' => __( 'Enable/Disable', 'woocommerce' ),
					'type' => 'checkbox',
					'label' => __( 'Enable the Paymentwall Payment Solution', 'woocommerce' ),
					'default' => 'yes'
				),
				'title' => array (
					'title' => __( 'Title', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default' => __( 'Paymentwall', 'woocommerce' )
				),
				'description' => array (
					'title' => __( 'Description', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
					'default' => __("Pay via Paymentwall.", 'woocommerce')
				),
				'appkey' => array (
					'title' => __( 'Application Key', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'Your Paymentwall Application Key', 'woocommerce' ),
					'default' => ''
				),
				'secretkey' => array (
					'title' => __( 'Secret Key', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'Your Paymentwall Secret Key', 'woocommerce' ),
					'default' => ''
				),
				'widget' => array (
					'title' => __( 'Widget Code', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'Enter your preferred widget code', 'woocommerce' ),
					'default' => ''
				)
			);
		} // End init_form_fields()
	
		/*
		 * Displays a short description to the user during checkout
		 */
		function payment_fields () {
			
			echo $this->description;

		}
		
		/*
		 * Displays text like introduction, instructions in the admin area of the widget
		 */
		public function admin_options () {
			
	    	?>
	    	<h3><?php _e ( 'Paymentwall Gateway', 'woocommerce' ); ?></h3>
	    	<p><?php _e ( 'Enables the Paymentwall Payment Solution. The easiest way to monetize your game or web service globally.', 'woocommerce' ); ?></p>
	    	<table class="form-table">
				<?php $this->generate_settings_html (); ?>
			</table>
	    	<?php
	    }
		
		/*
		 * Calculate the parameters so we know we are sending a legitimate (unaltered) request to the servers
		 */
		function calculateWidgetSignature($params, $secret) {
			// work with sorted data
			ksort($params);
			// generate the base string
			$baseString = '';
			foreach($params as $key => $value) {
				$baseString .= $key . '=' . $value;
			}
			$baseString .= $secret;
			return md5($baseString);
		}
		
		/*
		 * This ensures that the pingback response was not tampered
		 */
		function calculatePingbackSignature($params, $secret) {
			$str = '';
			foreach ($params as $k=>$v) {
				$str .= "$k=$v";
			}
			$str .= $secret;
			
			return md5($str);
		}
		
		/*
		 * Check the response from Paymentwall's Servers
		 */
		function check_ipn_response() {
            
			$_REQUEST['ipn'] = true;

			if ( isset ( $_GET [ 'paymentwallListener' ] ) && $_GET [ 'paymentwallListener' ] == 'paymentwall_IPN' ) {
				
				$userId = isset( $_GET [ 'uid' ] ) ? $_GET [ 'uid' ] : null;
				$goodsId = isset( $_GET [ 'goodsid' ] ) ? $_GET [ 'goodsid' ] : null;
				$length = isset( $_GET [ 'slength' ] ) ? $_GET [ 'slength' ] : null;
				$period = isset( $_GET [ 'speriod' ] ) ? $_GET [ 'speriod' ] : null;
				$type = isset( $_GET [ 'type' ] ) ? $_GET [ 'type' ] : null;
				$reason = isset( $_GET [ 'reason' ] ) ? $_GET [ 'reason' ] : null;
				$refId = isset( $_GET [ 'ref' ] ) ? $_GET [ 'ref' ] : null;
				$signature = isset( $_GET ['sig' ] ) ? $_GET [ 'sig' ] : null;
				$result = false;
				
                
				if ( ! empty ( $userId ) && ! empty ( $goodsId ) && isset ( $type ) && ! empty ( $refId ) && ! empty ( $signature ) ) {

					$signatureParams = array(
						'uid' => $userId,
						'goodsid' => $goodsId,
						'slength' => $length,
						'speriod' => $period,
						'type' => $type,
						'ref' => $refId
					);
					
					$ipsWhitelist = array ( 
						'174.36.92.186', 
						'174.36.96.66', 
						'174.36.92.187', 
						'174.36.92.192', 
						'174.37.14.28'
                    );
					

					$signatureCalculated = $this->calculatePingbackSignature ( $signatureParams, $this->secret_key );

					// check if IP is in whitelist and if signature matches
					if ( in_array ( $_SERVER [ 'REMOTE_ADDR' ], $ipsWhitelist ) ) {
						
                        if ( $signature == $signatureCalculated ) {
                            $order = new WC_Order ( ( int ) $goodsId );
                            global $woocommerce;
                            
                            if ( $order->get_order ( $goodsId ) ) {
                            
                                if ( $type == 2 ) {
                                
                                    $order->update_status ( 'cancelled', __( 'Reason: ' . $reason, 'woocommerce' ) );
                                    $result = true;
                                    
                                } else {
                                
                                    $order->add_order_note ( __( 'Paymentwall payment completed', 'woocommerce' ) );
                                    $order->payment_complete ();
                        
                                    $woocommerce->cart->empty_cart();
                    
                                    $result = true;
                                }	
                            
                            } else {
                            
                                $result = false;
                                die ();
                            }
                            
                        }
                        
					} else {
                        die ('unauthorized IP address!');
                    }
					
					if ( $result ) {

						die ('OK');
						
					} else {
					
						die ( 'Paymentwall IPN Request Failure' );
					
					}
                    
				} else {

                    die ('missing parameters!');
                
                }
                
			} else {
			
				die ('invalid request');
				
			}
		}
	}
	
	function WcPwGateway ( $methods ) {
		$methods[] = 'Paymentwall_Gateway'; 
		return $methods;
	}
	
	add_filter( 'woocommerce_payment_gateways', 'WcPwGateway' );

}

	