<?php

/*
Plugin Name: Netpluspay Payment Gateway
Plugin URI: https://www.netpluspay.com
Description: Netpluspay Payment gateway for woocommerce
Version: 0.5
Author: NetplusDotCom
Author URI: https://www.netpluspay.com

*/
add_action( 'plugins_loaded', 'woocommerce_netpluspay_init');

function woocommerce_netpluspay_init()
{
	if(!class_exists('WC_Payment_Gateway')) return;
	
	class WC_Netpluspay extends WC_Payment_Gateway
		{
		public function __construct()
				{
			$this->id = 'netpluspay';
			$this ->method_title = 'Netpluspay';
			$this->icon  = 'http://netpluspay.com/images/logo.png';
			$this ->has_fields = false;
			$this ->init_form_fields();
			$this ->init_settings();
			
			$this ->title = $this->settings['title'];
			$this ->description = $this->settings['description'];
			$this ->merchant_id = $this->settings['merchant_id'];
			$this ->currency = $this->settings['currency'];
			$this->test_merchant_id = $this->settings['test_merchant_id'];
			$this ->liveurl = 'https://netpluspay.com/payment/paysrc/';
			$this->testurl ='https://netpluspay.com/testpayment/paysrc/';
			$this->production = $this->settings['production'];
			
			$this->msg['message'] = "";
			$this->msg['class'] = "";
			
			add_action( 'woocommerce_api_wc_netpluspay',array( $this, 'check_netpluspay_response' ));
			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
			}
			else {
				add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			}
			add_action('woocommerce_receipt_netpluspay', array(&$this, 'receipt_page'));
		}
		function init_form_fields()
		    	{
			$this -> form_fields = array(
			                'enabled' => array(
			                    'title' => __('Enable/Disable', 'Npp'),
			                    'type' => 'checkbox',
			                    'label' => __('Enable Netpluspay Payment Module.', 'Npp'),
			                    'default' => 'no'),
			                'title' => array(
			                    'title' => __('Title:', 'Npp'),
			                    'type'=> 'text',
			                    'description' => __('This controls the title which the user sees during checkout.', 'Npp'),
			                    'default' => __('Netpluspay', 'Npp')),
			                'description' => array(
			                    'title' => __('Description:', 'Npp'),
			                    'type' => 'textarea',
			                    'description' => __('This controls the description which the user sees during checkout.', 'Npp'),
			                    'default' => __('Pay securely by Credit or Debit card or internet banking.', 'Npp')),
			                'merchant_id' => array(
			                    'title' => __('Merchant ID', 'Npp'),
			                    'type' => 'text',
			                    'description' => __('Your Netpluspay Merchant ID')), 
			                'test_merchant_id' => array(
			                    'title' => __('Test Merchant ID', 'Npp'),
			                    'type' => 'text',
			                    'description' => __('Your Netpluspay Test Merchant ID.')),                   
			                'production' => array(
			                    'title' => __('Enable Live Payments', 'Npp'),
			                    'type' => 'checkbox',
			                    'label' => __('Enable Live payments.', 'Npp'),
			                    'default' => 'no'
			                ),
			                'currency' => array(
			                  'title' => __('Currency Code', 'Npp'),
			                  'type' => 'text',
			                  'description' => __('ISO currency code e.g NGN, USD.'),
			                )
			            );
		}
		
		public function admin_options(){
			echo '<h3>'.__('Netpluspay Payment Gateway', 'Npp').'</h3>';
			echo '<p>'.__('Netpluspay is most popular payment gateway for online shopping in Nigeria').'</p>';
			echo '<table class="form-table">';
			// 			Generate the HTML For the settings form.
			        $this -> generate_settings_html();
			echo '</table>';
			
		}
		
		function payment_fields(){
			if($this -> description) echo wpautop(wptexturize($this -> description));
		}
		
		/**
		* Receipt Page
		     **/
		    function receipt_page($order){
			echo '<p>'.__('Thank you for your order, please click the button below to pay with NetPlusPay.', 'mrova').'</p>';
			echo $this -> generate_netplus_form($order);
		}
		
		/**
		* Generate payu button link
		     **/
		    public function generate_netplus_form($order_id){
			
			global $woocommerce;
			
			$order = new WC_Order($order_id);
			$txnid = $order_id.'_'.date("ymds");
			$productinfo = "Order $order_id";
			$merchantID = "";
			if($this->production == 'yes')
			        	{
				$this->url = $this->liveurl;
				$merchantID = $this -> merchant_id;
				
			}
			else
			
			{
				$this->url = $this->testurl;
				$merchantID = $this->test_merchant_id;
			}
			
			
			
			$netpluspay_args = array(
			          'merchant_id' => $merchantID,
			          'order_id' => $order_id,
			          'total_amount' => $order -> order_total,
			          'narration' => $productinfo,
			          'full_name' => $order ->billing_first_name . " ".$order ->billing_last_name,         
			          'email' => $order ->billing_email,
			          'return_url' => WC()->api_request_url( 'WC_Netpluspay' ),
			          'currency_code' => $this->currency          
			          );
			
			$netpluspay_args_array = array();
			foreach($netpluspay_args as $key => $value){
				$netpluspay_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
			}
			return '<form action="'.$this->url.'" method="post" id="payu_payment_form">
            ' . implode('', $netpluspay_args_array) . '
            <input type="submit" class="button-alt" id="submit_payu_payment_form" value="'.__('Pay via NetPlusPay', 'mrova').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'mrova').'</a>
				</form>';
			
			
		}
		function process_payment($order_id){
			$order = new WC_Order($order_id);
			return array('result' => 'success', 'redirect' => add_query_arg('order',
			            $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
			        );
		}
		
		
		function check_netpluspay_response(){
			
			global $woocommerce;
			$order_id = $_REQUEST['order_id'];
			$code = $_REQUEST['code'];
			$amount = $_REQUEST['amount_paid'];
			$transref = $_REQUEST['transaction_id'];
			$status = "";
			
			switch ($code) {
				case '00':
				      	   		$status = 'Success';
				break;
				case '90':
				      	   		$status = 'Payment Failed';
				break;
				case '50':
				      	   		$status = 'Transaction cancelled by user';
				break;
				default:
				      	   		$status = 'Payment cannot be completed at this point';
				break;
			}
			
			$order = new WC_Order($order_id);
			
			
			$message = 'Thank you for shopping with us.<br />'.'Payment Status:  '.$status.'</br>'.'Total Amount:  NGN'.$amount.'</br>'.'Transaction Reference ID:    '.$transref;
			
			if($status ==  "Success")
			            {
				$order->payment_complete();
				$order->add_order_note('Payment Via Netpluspay');
				// 				Reduce stock levels
				                $order->reduce_order_stock();
				// 				Empty cart
				                WC()->cart->empty_cart();
				// 				Reduce stock levels
				                $order->reduce_order_stock();
				// 				Empty cart
				                WC()->cart->empty_cart();
				$message_type = 'success';
				
			}
			elseif($status ==  "Payment Failed")
			            {
				$order->update_status('No Payment Received', '');
				
				$order->add_order_note('Payment Via Netpluspay ');
				
				$message_type = 'notice';
			}
			elseif($status ==  "Transaction cancelled by user")
			            {
				
				$message_type = 'error';
			}
			else
			            {
				$message_type='error';
			}
			
			if ( function_exists( 'wc_add_notice' ) )
			            {
				wc_add_notice( $message, $message_type );
				
			}
			else // 			WC < 2.1
			            {
				$woocommerce->add_error( $message );
				
				$woocommerce->set_messages();
			}
			
			$redirect_url = $order->get_checkout_order_received_url();
			$this->web_redirect($redirect_url);
			exit;
			
			
		}
		public function web_redirect($url){
			
			echo "<html><head><script language=\"javascript\">
                <!--
                window.location=\"{$url}\";
                //-->
                </script>
                </head><body><noscript><meta http-equiv=\"refresh\" content=\"0;url={$url}\"></noscript></body></html>";
			
		}
		
		
		function showMessage($content){
			return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
		}
		
	}
	
	

	
	/**
	* Add the Gateway to WooCommerce
	     **/
	    function woocommerce_add_netpluspay_gateway($methods) {
		$methods[] = 'WC_Netpluspay';
		return $methods;
	}
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_netpluspay_gateway' );
}


