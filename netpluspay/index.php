<?php

/*
Plugin Name: Netpluspay Payment Gateway
Plugin URI: https://www.netpluspay.com
Description: Netpluspay Payment gateway for woocommerce
Version: 1.5
Author: NetplusDotCom
Author URI: https://www.netpluspay.com

*/
add_action('plugins_loaded', 'woocommerce_netpluspay_init');

function woocommerce_netpluspay_init()
{
    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_Netpluspay extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id = 'netpluspay';
            $this->method_title = 'Netpluspay';
            $this->icon = 'http://netpluspay.com/images/logo.png';
            $this->has_fields = false;
            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->merchant_id = $this->settings['merchant_id'];
            $this->preauthmode = $this->settings['preauthmode'];
            $this->currency = $this->settings['currency'];
            $this->test_merchant_id = $this->settings['test_merchant_id'];
            $this->liveurl = 'https://netpluspay.com/payment/paysrc/';
            $this->testurl = 'https://netpluspay.com/testpayment/paysrc/';
            $this->production = $this->settings['production'];

            $this->msg['message'] = "";
            $this->msg['class'] = "";

            add_action('woocommerce_api_wc_netpluspay', array($this, 'check_netpluspay_response'));
            add_action('woocommerce_api_netpluspay_redirect', array($this, 'netpluspay_redirect'));
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
            }

        }

        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'Npp'),
                    'type' => 'checkbox',
                    'label' => __('Enable Netpluspay Payment Module.', 'Npp'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Title:', 'Npp'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'Npp'),
                    'default' => __('Netpluspay (Debit Card)', 'Npp')),
                'description' => array(
                    'title' => __('Description:', 'Npp'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'Npp'),
                    'default' => __('Pay securely by Credit or Debit card.', 'Npp')),
                'merchant_id' => array(
                    'title' => __('Live Merchant ID', 'Npp'),
                    'type' => 'text',
                    'description' => __('Your Netpluspay Merchant ID')),
                'test_merchant_id' => array(
                    'title' => __(' Test Merchant ID', 'Npp'),
                    'type' => 'text',
                    'description' => __('Your Netpluspay Test Merchant ID.')),
                'production' => array(
                    'title' => __('Enable Live Payments', 'Npp'),
                    'type' => 'checkbox',
                    'label' => __('Enable Live payments.', 'Npp'),
                    'default' => 'no'
                ),
                'preauthmode' => array(
                    'title' => __('Enable Pre Auth Mode', 'Npp'),
                    'type' => 'checkbox',
                    'label' => __('Enable Pre Auth Mode.', 'Npp'),
                    'default' => 'no'
                ),
                'currency' => array(
                    'title' => __('Currency Code', 'Npp'),
                    'type' => 'text',
                    'description' => __('ISO currency code e.g NGN, USD.'),
                )
            );
        }


        function payment_fields()
        {
            if ($this->description) echo wpautop(wptexturize($this->description));
        }


        function process_payment($order_id)
        {

            $order = new WC_Order( $order_id );
            $productinfo = "Order $order_id";
            $merchantID = ($this->production == 'yes') ? $this->merchant_id : $this->test_merchant_id;

            $netpluspay_args = array(
                'merchant_id' => $merchantID,
                'order_id' => $order_id,
                'total_amount' => $order->order_total,
                'narration' => $productinfo,
                'full_name' => $order->billing_first_name . " " . $order->billing_last_name,
                'email' => $order->billing_email,
                'return_url' => WC()->api_request_url('WC_Netpluspay'),
                'currency_code' => $this->currency
            );
            $redirect_url = WC()->api_request_url('netpluspay_redirect') . '?' . http_build_query($netpluspay_args, '', '&');


            return array(
                'result' => 'success',
                'redirect' => $redirect_url,
            );
        }

        function netpluspay_redirect(){
            if ($this->production == 'yes') {
                $this->url = $this->liveurl;

            } else {
                $this->url = $this->testurl;
            }
            include_once dirname( __FILE__ ) . '/includes/redirect.php';
            exit;
        }


        function check_netpluspay_response()
        {

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


            $message = 'Sorry Payment could not be proccessed.<br />' . 'Payment Status:  ' . $status . '</br>' . 'Total Amount:  NGN' . $amount . '</br>' . 'Transaction Reference ID:    ' . $transref;

            if ($status == "Success") {
                $message = 'Thank you for shopping with us.<br />' . 'Payment Status:  ' . $status . '</br>' . 'Total Amount:  NGN' . $amount . '</br>' . 'Transaction Reference ID:    ' . $transref;

                $order->payment_complete();
                $order->add_order_note('Payment Via NetPlusPay');

                // 				Empty cart
                WC()->cart->empty_cart();
                $message_type = 'success';

            } elseif ($status == "Payment Failed") {
                $order->update_status('failed');

                $order->add_order_note('Payment Via Netpluspay');


                $message_type = 'error';
            } elseif ($status == "Transaction cancelled by user") {
                $order->update_status('failed');
                $message_type = 'error';
            } else {
                $order->update_status('failed');
                $message_type = 'error';
            }

            if (function_exists('wc_add_notice')) {
                wc_add_notice($message, $message_type);

            } else // 			WC < 2.1
            {
                $woocommerce->add_error($message);

                $woocommerce->set_messages();
            }

            $redirect_url = $order->get_checkout_order_received_url();
            $this->web_redirect($redirect_url);
            exit;


        }

        public function web_redirect($url)
        {

            echo "<html><head><script language=\"javascript\">
                <!--
                window.location=\"{$url}\";
                //-->
                </script>
                </head><body><noscript><meta http-equiv=\"refresh\" content=\"0;url={$url}\"></noscript></body></html>";

        }


        function showMessage($content)
        {
            return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
        }

    }


    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_netpluspay_gateway($methods)
    {
        $methods[] = 'WC_Netpluspay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_netpluspay_gateway');
}


