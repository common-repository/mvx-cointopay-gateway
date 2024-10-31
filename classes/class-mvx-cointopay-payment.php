<?php
require_once ABSPATH . 'wp-admin/includes/plugin.php';

add_action('plugins_loaded', 'woocommerce_mvx_cointopay_init', 0);

function woocommerce_mvx_cointopay_init()
{
    if (!class_exists('WC_Payment_Gateway'))
    {
        return;
    }

    class MVX_Cointopay_Gateway_Payment_Method extends WC_Payment_Gateway
    {
        // This one stores the WooCommerce Order Id
        const SESSION_KEY                     = 'cointopay_wc_order_id';
        const COINTOPAY_PAYMENT_ID            = 'cointopay_payment_id';
        const COINTOPAY_ORDER_ID              = 'cointopay_order_id';
        const COINTOPAY_WC_FORM_SUBMIT        = 'cointopay_wc_form_submit';

        const INR                            = 'INR';
        const WC_ORDER_ID                    = 'woocommerce_order_id';

        const DEFAULT_LABEL                  = 'Credit Card/Debit Card/NetBanking';
        const DEFAULT_DESCRIPTION            = 'Pay securely by Credit or Debit card or Internet Banking through Cointopay.';
        const DEFAULT_SUCCESS_MESSAGE        = 'Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be processing your order soon.';

        protected $visibleSettings = array(
            'enabled',
            'title',
            'description',
            'merchant_id',
            'security_code',
        );

        public $form_fields = array();

        public $supports = array(
            'products',
            //'refunds'
        );

        /**
         * Can be set to true if you want payment fields
         * to show on the checkout (if doing a direct integration).
         * @var boolean
         */
        public $has_fields = false;

        /**
         * Unique ID for the gateway
         * @var string
         */
        public $id = 'mvx-cointopay';

        /**
         * Title of the payment method shown on the admin page.
         * @var string
         */
        public $method_title = 'Cointopay Payments (MVX Compatible)';


        /**
         * Description of the payment method shown on the admin page.
         * @var  string
         */
        public $method_description = 'Allow customers to securely pay via Cointopay (Credit/Debit Cards, NetBanking, UPI, Wallets)';

        /**
         * Icon URL, set in constructor
         * @var string
         */
        public $icon;

        /**
         * TODO: Remove usage of $this->msg
         */
        protected $msg = array(
            'message'   =>  '',
            'class'     =>  '',
        );

        /**
         * Return Wordpress plugin settings
         * @param  string $key setting key
         * @return mixed setting value
         */
        public function getSetting($key)
        {
            return $this->get_option($key);
        }

        protected function getCustomOrdercreationMessage()
        {
            $message =  $this->getSetting('order_success_message');
            if (isset($message) === false)
            {
                $message = STATIC::DEFAULT_SUCCESS_MESSAGE;
            }
            return $message;
        }

        /**
         * @param boolean $hooks Whether or not to
         *                       setup the hooks on
         *                       calling the constructor
         */
        public function __construct($hooks = true)
        {
			global $MVX_Cointopay_Gateway;
            $this->icon =  $MVX_Cointopay_Gateway->plugin_url  . 'images/cointopay.png';

            $this->init_form_fields();
            $this->init_settings();

            // TODO: This is hacky, find a better way to do this
            // See mergeSettingsWithParentPlugin() in subscriptions for more details.
            if ($hooks)
            {
                $this->initHooks();
            }

            $this->title = $this->getSetting('title');
        }

        protected function initHooks()
        {
            add_action('init', array(&$this, 'check_cointopay_response'));

            //add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            add_action('woocommerce_api_' . $this->id, array($this, 'check_cointopay_response'));

            $cb = array($this, 'process_admin_options');

            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
            {
                add_action("woocommerce_update_options_payment_gateways_{$this->id}", $cb);
            }
            else
            {
                add_action('woocommerce_update_options_payment_gateways', $cb);
            }
        }

        public function init_form_fields()
        {
            $defaultFormFields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', $this->id),
                    'type' => 'checkbox',
                    'label' => __('Enable this module?', $this->id),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', $this->id),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.', $this->id),
                    'default' => __(static::DEFAULT_LABEL, $this->id)
                ),
                'description' => array(
                    'title' => __('Description', $this->id),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', $this->id),
                    'default' => __(static::DEFAULT_DESCRIPTION, $this->id)
                ),
                'merchant_id' => array(
                    'title' => __('Merchant Id', $this->id),
                    'type' => 'text',
                    'description' => __('Please enter your Cointopay Merchant Id; this is needed in order to take payment.', $this->id)
                ),
                'security_code' => array(
                    'title' => __('Security Code', $this->id),
                    'type' => 'text',
                    'description' => __('Please enter your Cointopay Security Code; this is needed in order to take payment.', $this->id)
                ),
            );

            foreach ($defaultFormFields as $key => $value)
            {
                if (in_array($key, $this->visibleSettings, true))
                {
                    $this->form_fields[$key] = $value;
                }
            }
        }

        public function admin_options()
        {
            echo '<h3>'.__('Cointopay Payment Gateway', $this->id) . '</h3>';
            echo '<p>'.__('Allows Crypto payments') . '</p>';
            echo '<table class="form-table">';

            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
        }

        public function get_description()
        {
            return $this->getSetting('description');
        }

        /**
         * Receipt Page
         * @param string $orderId WC Order Id
         **/
        function receipt_page($orderId)
        {
            //echo $this->generate_cointopay_form($orderId);
        }

        /**
         * Returns key to use in session for storing Cointopay order Id
         * @param  string $orderId Cointopay Order Id
         * @return string Session Key
         */
        protected function getOrderSessionKey($orderId)
        {
            return self::COINTOPAY_ORDER_ID . $orderId;
        }

        /**
         * Given a order Id, find the associated
         * Cointopay Order from the session and verify
         * that is is still correct. If not found
         * (or incorrect), create a new Cointopay Order
         *
         * @param  string $orderId Order Id
         * @return mixed Cointopay Order Id or Exception
         */
        protected function createOrGetCointopayOrderId($orderId)
        {
            global $woocommerce;

            $sessionKey = $this->getOrderSessionKey($orderId);

            $create = false;

            try
            {
                $cointopayOrderId = $woocommerce->session->get($sessionKey);

                // If we don't have an Order
                // or the if the order is present in session but doesn't match what we have saved
                if (($cointopayOrderId === null) or
                    (($cointopayOrderId and ($this->verifyOrderAmount($cointopayOrderId, $orderId)) === false)))
                {
                    $create = true;
                }
                else
                {
                    return $cointopayOrderId;
                }
            }
            // Order doesn't exist or verification failed
            // So try creating one
            catch (Exception $e)
            {
                $create = true;
            }

            if ($create)
            {
                try
                {
                    return $this->createCointopayOrderId($orderId, $sessionKey);
                }
                // For any other exceptions, we make sure that the error message
                // does not propagate to the front-end.
                catch (Exception $e)
                {
                    return new Exception("Payment failed");
                }
            }
        }

        /**
         * Returns redirect URL post payment processing
         * @return string redirect URL
         */
        private function getRedirectUrl()
        {
            return add_query_arg( 'wc-api', $this->id, trailingslashit( get_home_url() ) );
        }

        /**
         * Specific payment parameters to be passed to checkout
         * for payment processing
         * @param  string $orderId WC Order Id
         * @return array payment params
         */
        protected function getCointopayPaymentParams($orderId)
        {
            $cointopayOrderId = $this->createOrGetCointopayOrderId($orderId);

            if ($cointopayOrderId === null)
            {
                throw new Exception('COINTOPAY ERROR: Cointopay API could not be reached');
            }
            else if ($cointopayOrderId instanceof Exception)
            {
                $message = $cointopayOrderId->getMessage();

                throw new Exception("COINTOPAY ERROR: Order creation failed with the message: '$message'.");
            }

            return [
                'order_id'  =>  $cointopayOrderId
            ];
        }

        /**
         * Generate cointopay button link
         * @param string $orderId WC Order Id
         **/
        public function generate_cointopay_form($orderId)
        {
            $order = new WC_Order($orderId);

            try
            {
                $params = $this->getCointopayPaymentParams($orderId);
            }
            catch (Exception $e)
            {
                return $e->getMessage();
            }

            $checkoutArgs = $this->getCheckoutArguments($order, $params);

            $html = '<p>'.__('Thank you for your order, please click the button below to pay with Cointopay.', $this->id).'</p>';

            $html .= $this->generateOrderForm($checkoutArgs);

            return $html;
        }

        /**
         * default parameters passed to checkout
         * @param  WC_Order $order WC Order
         * @return array checkout params
         */
        private function getDefaultCheckoutArguments($order)
        {
            global $MVX_Cointopay_Gateway;
            $callbackUrl = $this->getRedirectUrl();

            $orderId = $order->get_order_number();

            $productinfo = "Order $orderId";
            return array(
                'key'          => $this->getSetting('key_id'),
                'name'         => get_bloginfo('name'),
                'currency'     => self::INR,
                'description'  => $productinfo,
                'notes'        => array(
                    'woocommerce_order_id' => $orderId
                ),
                'callback_url' => $callbackUrl,
                'prefill'      => $this->getCustomerInfo($order),
                '_'            => array(
                    'integration'                   => 'woocommerce',
                    'integration_version'           => $MVX_Cointopay_Gateway->version,
                    'integration_parent_version'    => WOOCOMMERCE_VERSION,
                ),
            );
        }

        /**
         * @param  WC_Order $order
         * @return string currency
         */
        private function getOrderCurrency($order)
        {
            if (version_compare(WOOCOMMERCE_VERSION, '2.7.0', '>='))
            {
                return $order->get_currency();
            }

            return $order->get_order_currency();
        }

        /**
         * Returns array of checkout params
         */
        private function getCheckoutArguments($order, $params)
        {
            $args = $this->getDefaultCheckoutArguments($order);

            $currency = $this->getOrderCurrency($order);

            // The list of valid currencies is at https://cointopay.freshdesk.com/support/solutions/articles/11000065530-what-currencies-does-cointopay-support-

            $args = array_merge($args, $params);

            return $args;
        }

        public function getCustomerInfo($order)
        {
            if (version_compare(WOOCOMMERCE_VERSION, '2.7.0', '>='))
            {
                $args = array(
                    'name'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'email'   => $order->get_billing_email(),
                    'contact' => $order->get_billing_phone(),
                );
            }
            else
            {
                $args = array(
                    'name'    => $order->billing_first_name . ' ' . $order->billing_last_name,
                    'email'   => $order->billing_email,
                    'contact' => $order->billing_phone,
                );
            }

            return $args;
        }

        protected function createCointopayOrderId($orderId, $sessionKey)
        {
            // Calls the helper function to create order data
            global $woocommerce, $MVX;

            $api = $this->getCointopayApiInstance();

            $data = $this->getOrderCreationData($orderId);
            try
            {
                $cointopayOrder = $api->order->create($data);
            }
            catch (Exception $e)
            {   
                return $e;
            }

            $cointopayOrderId = $cointopayOrder['id'];
            if (MVX_Cointopay_Gateway_Dependencies::mvx_active_check()) {
                $create_vendor_transaction = $this->create_transaction_fron_order($orderId);
                /* $is_split = get_mvx_vendor_settings('is_split', 'payment_cointopay');
                if ($cointopayOrderId && !empty($is_split)) {
                    if (!empty($create_vendor_transaction)) {
                        foreach ($create_vendor_transaction as $vendor_id => $commission_ids) {
                            $MVX->payment_gateway->payment_gateways['cointopay']->vendor = get_mvx_vendor($vendor_id);
                            $MVX->payment_gateway->payment_gateways['cointopay']->commissions = array_unique($commission_ids);
                            $MVX->payment_gateway->payment_gateways['cointopay']->transaction_mode = 'auto';
                            $MVX->payment_gateway->payment_gateways['cointopay']->record_transaction();
                        }
                    }
                } */
            }

            $woocommerce->session->set($sessionKey, $cointopayOrderId);

            //update it in order comments
            $order = new WC_Order($orderId);

            $order->add_order_note("Cointopay OrderId: $cointopayOrderId");

            return $cointopayOrderId;
        }

        public function create_transaction_fron_order($orderId = '') {
            $receivers = array();
            if(MVX_Cointopay_Gateway_Dependencies::mvx_active_check()) {
                $suborders_list = get_mvx_suborders( $orderId ); 
                if( $suborders_list ) {
                    foreach( $suborders_list as $suborder ) {
                        $vendor = get_mvx_vendor( get_post_field( 'post_author', $suborder->get_id() ) );
                        $vendor_payment_method = get_user_meta( $vendor->id, '_vendor_payment_mode', true );
                        $vendor_cointopay_account_id = get_user_meta( $vendor->id, '_vendor_cointopay_account_id', true );
                        $vendor_payment_method_check = $vendor_payment_method == 'cointopay' ? true : false;
                        $cointopay_enabled = apply_filters('mvx_cointopay_enabled', $vendor_payment_method_check);
                        if ( $cointopay_enabled && $vendor_cointopay_account_id ) {
                            $vendor_order = mvx_get_order( $suborder->get_id() );
                            $vendor_commission = round( $vendor_order->get_commission_total( 'edit' ), 2 );
                            $commission_id = get_post_meta($suborder->get_id(), '_commission_id', true) ? get_post_meta($suborder->get_id(), '_commission_id') : array();
                            if ($vendor_commission > 0 && $commission_id) {
                                $receivers[$vendor->id] = $commission_id;
                            }
                        }
                    }
                }
            }
            return $receivers;
        }

        protected function verifyOrderAmount($cointopayOrderId, $orderId)
        {
            $order = new WC_Order($orderId);

            $api = $this->getCointopayApiInstance();

            try
            {
                $cointopayOrder = $api->order->fetch($cointopayOrderId);
            }
            catch (Exception $e)
            {
                $message = $e->getMessage();
                return "COINTOPAY ERROR: Order fetch failed with the message '$message'";
            }

            $orderCreationData = $this->getOrderCreationData($orderId);

            $cointopayOrderArgs = array(
                'id'        => $cointopayOrderId,
                'amount'    => $orderCreationData['amount'],
                'currency'  => $orderCreationData['currency'],
                'receipt'   => (string) $orderId,
            );

            $orderKeys = array_keys($cointopayOrderArgs);

            foreach ($orderKeys as $key)
            {
                if ($cointopayOrderArgs[$key] !== $cointopayOrder[$key])
                {
                    return false;
                }
            }

            return true;
        }

        private function getOrderCreationData($orderId)
        {
            $order = new WC_Order($orderId);

            $data = array(
                'receipt'         => $orderId,
                'amount'          => (int) round($order->get_total() * 100),
                'currency'        => $this->getOrderCurrency($order),
                //'payment_capture' => ($this->getSetting('payment_action') === self::AUTHORIZE) ? 0 : 1,
                'app_offer'       => ($order->get_discount_total() > 0) ? 1 : 0,
                'notes'           => array(
                    self::WC_ORDER_ID  => (string) $orderId,
                ),
            );

            if (MVX_Cointopay_Gateway_Dependencies::mvx_active_check()) {
                /* $is_split = get_mvx_vendor_settings('is_split', 'payment_cointopay');
                if (!empty($is_split)) {
                    $payment_distribution_list = $this->generate_payment_distribution_list($orderId);
                    if( isset( $payment_distribution_list['transfers'] ) && !empty( $payment_distribution_list['transfers'] ) && count( $payment_distribution_list['transfers'] ) > 0 ) {
                        $data['transfers'] = $payment_distribution_list['transfers'];
                    }
                } */
            }
            
            return $data;
        }

        public function generate_payment_distribution_list($order) {
            $args = array();
            $receivers = array();
            $total_vendor_commission = 0;
            if(MVX_Cointopay_Gateway_Dependencies::mvx_active_check()) {
                $suborders_list = get_mvx_suborders( $order ); 
                if( $suborders_list ) {
                    foreach( $suborders_list as $suborder ) {
                        $vendor = get_mvx_vendor( get_post_field( 'post_author', $suborder->get_id() ) );
                        $vendor_payment_method = get_user_meta( $vendor->id, '_vendor_payment_mode', true );
                        $vendor_cointopay_account_id = get_user_meta( $vendor->id, '_vendor_cointopay_account_id', true );
                        $vendor_payment_method_check = $vendor_payment_method == 'cointopay' ? true : false;
                        $cointopay_enabled = apply_filters('mvx_cointopay_enabled', $vendor_payment_method_check);
                        if ( $cointopay_enabled && $vendor_cointopay_account_id ) {
                            $vendor_order = mvx_get_order( $suborder->get_id() );
                            $vendor_commission = round( $vendor_order->get_commission_total( 'edit' ), 2 );
                            if ($vendor_commission > 0) {
                                $receivers[] = array(
                                    'account'       => $vendor_cointopay_account_id,
                                    'amount'        => (float) $vendor_commission * 100,
                                    'currency'      => get_woocommerce_currency(),
                                );
                            }
                        }
                    }
                }
            }
            $args['transfers'] = $receivers;
            return $args;
        }


        private function enqueueCheckoutScripts($data)
        {
            /* if($data === 'checkoutForm')
            {
                wp_register_script('cointopay_wc_script', plugin_dir_url(__FILE__)  . '../assets/frontend/js/script.js',
                null, null);
            }
            else
            {
                wp_register_script('cointopay_wc_script', plugin_dir_url(__FILE__)  . '../assets/frontend/js/script.js',
                array('cointopay_checkout'));

                wp_register_script('cointopay_checkout',
                    'https://checkout.cointopay.com/v1/checkout.js',
                    null, null);
            }

            wp_localize_script('cointopay_wc_script',
                'cointopay_wc_checkout_vars',
                $data
            );

            wp_enqueue_script('cointopay_wc_script'); */
        }

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id)
        {
            global $woocommerce;
			global $MVX;
			
			$order = wc_get_order($order_id);
			$cointopay_alt_coin = get_post_meta( $order_id, 'cointopay_mvx_alt_coin', true);

			$item_names = array();

			if (sizeof($order->get_items()) > 0) : foreach ($order->get_items() as $item) :
			if ($item['qty']) $item_names[] = $item['name'] . ' x ' . $item['qty'];
			endforeach; endif;

			$item_name = sprintf( __('Order %s' , 'woocommerce'), $order->get_order_number() ) . " - " . implode(', ', $item_names);

			$data = array(
				'SecurityCode' => $this->getSetting('security_code'),
				'MerchantID' => $this->getSetting('merchant_id'),
				'Amount' => number_format($order->get_total(), 8, '.', ''),
				'AltCoinID' => $cointopay_alt_coin,
				'output' => 'json',
				'inputCurrency' => get_woocommerce_currency(),
				'CustomerReferenceNr' => $order_id,
				'returnurl' => rawurlencode(esc_url($this->get_return_url($order))),
				'transactionconfirmurl' => site_url('/?wc-api=mvx-cointopay'),
				'transactionfailurl' => rawurlencode(esc_url($order->get_cancel_order_url())),
			);


			// Sets the post params.
			$params = array(
				'body' => 'SecurityCode=' . $this->getSetting('security_code') . '&MerchantID=' . $this->getSetting('merchant_id') . '&Amount=' . number_format($order->get_total(), 8, '.', '') . '&AltCoinID='.$cointopay_alt_coin.'&output=json&inputCurrency=' . get_woocommerce_currency() . '&CustomerReferenceNr=' . $order_id . '&returnurl='.rawurlencode(esc_url($this->get_return_url($order))).'&transactionconfirmurl='.site_url('/?wc-api=mvx-cointopay') .'&transactionfailurl='.rawurlencode(esc_url($order->get_cancel_order_url())),
			);


				$url = 'https://app.cointopay.com/MerchantAPI?Checkout=true';

			/* if ('yes' == $this->debug) {
				doCointopayLog('Setting payment options with the following data: ' . print_r($data, true));
			} */

			$response = wp_safe_remote_post($url, $params);
			if (!is_wp_error($response) && 200 == $response['response']['code'] && 'OK' == $response['response']['message']) {
				$order->update_status('processing');
				$results = json_decode($response['body']);
						return array(
						'result' => 'success',
						'redirect' => $results->RedirectURL
						);
			} else {
				if ('yes' == $this->debug) {
					doCointopayLog('Failed to configure payment options: ' . print_r($response, true));
				}
			}
        }

        /**
         * Check for valid cointopay server callback
         **/
        function check_cointopay_response()
        {
            global $woocommerce;
			global $MVX_Cointopay_Gateway;
			if (isset($_GET['CommissionID']) && isset($_GET['MVXCointopay']) && isset($_GET['VendorID']) && isset($_GET['status'])) {
				if($_GET['status'] == "paid")
				  {
					    
						$mvx_ctp_gateway = new MVX_Gateway_Cointopay;
						$commission_status = 'mvx_completed';
						$transaction_args = array(
							'post_type' => 'mvx_transaction',
							'post_title' => sprintf(__('Transaction - %s', 'multivendorx'), strftime(_x('%B %e, %Y @ %I:%M %p', 'Transaction date parsed by strftime', 'multivendorx'), current_time( 'timestamp' ))),
							'post_status' => $commission_status,
							'ping_status' => 'closed',
							'post_author' => $_GET['VendorID']
						);
						$mvx_ctp_gateway->transaction_id = wp_insert_post($transaction_args);
						if (!is_wp_error($mvx_ctp_gateway->transaction_id) && $mvx_ctp_gateway->transaction_id) {
							array_push($mvx_ctp_gateway->commissions,$_GET['CommissionID']);
							$mvx_ctp_gateway->update_meta_data($commission_status);
							$mvx_ctp_gateway->email_notify($commission_status);
							$mvx_ctp_gateway->add_commission_note($mvx_ctp_gateway->commissions, sprintf(__('Commission paid via %s <a href="%s">(ID : %s)</a>', 'multivendorx'), $mvx_ctp_gateway->gateway_title, get_admin_url('mvx-transaction-details') . 'admin.php?page=mvx-transaction-details&trans_id=' . $mvx_ctp_gateway->transaction_id, $mvx_ctp_gateway->transaction_id));
						}
						get_header();
						echo '<div class="container" style="text-align: center;"><div><div>
						<br><br>
						<h2 style="color:#0fad00">Success!</h2>
						<img style="margin: auto;" src="'.$MVX_Cointopay_Gateway->plugin_url  . 'images/check.png">
						<p style="font-size:20px;color:#5C5C5C;">The payout has been received and confirmed successfully.</p>
						<a href="'.site_url().'" style="background-color: #0fad00;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >    Back     </a>
						<br><br>
						<br><br>
						</div>
						</div>
						</div>';
						get_footer();
						exit;
					} else {
					  get_header();
					  echo '<div class="container" style="text-align: center;"><div><div>
						<br><br>
						<h2 style="color:#ff0000">Failure!</h2>
						<img style="margin: auto;" src="'.$MVX_Cointopay_Gateway->plugin_url  . 'images/fail.png">
						<p style="font-size:20px;color:#5C5C5C;">We have detected different payout status ' .$_GET['status'] . ' for Commission ID ' . $_GET['CommissionID'] . '. </p>
						<a href="'.site_url().'" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >    Back     </a>
						<br><br>
						</div>
						</div>
						</div>';
						get_footer();
					  exit;
					}
				
			} elseif (isset($_GET['CustomerReferenceNr']) && isset($_GET['TransactionID']) && isset($_GET['ConfirmCode'])) { 
				$woocommerce->cart->empty_cart();
				$order_id = intval($_REQUEST['CustomerReferenceNr']);
				$o_status = sanitize_text_field($_REQUEST['status']);
				$o_TransactionID = sanitize_text_field($_REQUEST['TransactionID']);
				$o_ConfirmCode = sanitize_text_field($_REQUEST['ConfirmCode']);
				$ctpPaymentId = sanitize_text_field($_REQUEST['coinAddress']);
				$notenough = sanitize_text_field($_REQUEST['notenough']);

				$order = new WC_Order($order_id);
				$data = [ 
						   'mid' => $this->getSetting('merchant_id'), 
						   'TransactionID' => $o_TransactionID ,
						   'ConfirmCode' => $o_ConfirmCode
					  ];
			  $response = $this->validateOrder($data);
			  if($response->Status !== $o_status)
			  {
				  get_header();
				  echo '<div class="container" style="text-align: center;"><div><div>
					<br><br>
					<h2 style="color:#ff0000">Failure!</h2>
					<img style="margin: auto;" src="'.$MVX_Cointopay_Gateway->plugin_url  . 'images/fail.png">
					<p style="font-size:20px;color:#5C5C5C;">We have detected different order status. Your order has been halted.</p>
					<a href="'.site_url().'" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >    Back     </a>
					<br><br>
					</div>
					</div>
					</div>';
					get_footer();
				  exit;
			  }
			   else if($response->CustomerReferenceNr == $order_id)
			  {
					if ($o_status == 'paid' && $notenough==0) {
					// Do your magic here, and return 200 OK to Cointopay.

					if ($order->get_status() == 'completed')
					{
						$order->update_status( 'completed', sprintf( __( 'IPN: Payment completed notification from Cointopay', 'woocommerce' ) ) );
						$suborders_list = get_mvx_suborders( $order ); 
						if( $suborders_list ) {
							foreach( $suborders_list as $suborder ) {
								$suborder->update_status( 'completed', sprintf( __( 'IPN: Payment completed notification from Cointopay', 'woocommerce' ) ) );
							}
						}
					}
					else
					{
						$order->payment_complete($ctpPaymentId);
						$order->update_status( 'completed', sprintf( __( 'IPN: Payment completed notification from Cointopay', 'woocommerce' ) ) );
						$suborders_list = get_mvx_suborders( $order ); 
						if( $suborders_list ) {
							foreach( $suborders_list as $suborder ) {
								$suborder->update_status( 'completed', sprintf( __( 'IPN: Payment completed notification from Cointopay', 'woocommerce' ) ) );
							}
						}

					}
					 $order->add_order_note(__('The payment was successful.', 'mvx-cointopay-gateway'));
					 $order->payment_complete();
				
					get_header();
					echo '<div class="container" style="text-align: center;"><div><div>
					<br><br>
					<h2 style="color:#0fad00">Success!</h2>
					<img style="margin: auto;" src="'.$MVX_Cointopay_Gateway->plugin_url  . 'images/check.png">
					<p style="font-size:20px;color:#5C5C5C;">The payment has been received and confirmed successfully.</p>
					<a href="'.site_url().'" style="background-color: #0fad00;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >    Back     </a>
					<br><br>
					<br><br>
					</div>
					</div>
					</div>';
					get_footer();
				/*	echo "<script>
					setTimeout(function () {
					window.location.href= '".site_url()."';
					}, 5000);
					</script>";*/
					//header('HTTP/1.1 200 OK');
					exit;
				}
				else if ($o_status == 'failed' && $notenough == 1) {

					$order->update_status( 'on-hold', sprintf( __( 'IPN: Payment failed notification from Cointopay because notenough', 'woocommerce' ) ) );
					get_header();
					echo '<div class="container" style="text-align: center;"><div><div>
					<br><br>
					<h2 style="color:#ff0000">Failure!</h2>
					<img style="margin: auto;" src="'.$MVX_Cointopay_Gateway->plugin_url  . 'images/fail.png">
					<p style="font-size:20px;color:#5C5C5C;">The payment has been failed.</p>
					<a href="'.site_url().'" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >    Back     </a>
					<br><br>
					<br><br>
					</div>
					</div>
					</div>';
					get_footer();
				/*	echo "<script>
					setTimeout(function () {
					window.location.href= '".site_url()."';
					}, 5000);
					</script>";*/
					exit;
				}
				else{

					$order->update_status( 'failed', sprintf( __( 'IPN: Payment failed notification from Cointopay', 'woocommerce' ) ) );
					get_header();
					echo '<div class="container" style="text-align: center;"><div><div>
					<br><br>
					<h2 style="color:#ff0000">Failure!</h2>
					<img style="margin: auto;" src="'.$MVX_Cointopay_Gateway->plugin_url  . 'images/fail.png">
					<p style="font-size:20px;color:#5C5C5C;">The payment has been failed.</p>
					<a href="'.site_url().'" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >    Back     </a>
					<br><br>
					<br><br>
					</div>
					</div>
					</div>';
					get_footer();
				/*	echo "<script>
					setTimeout(function () {
					window.location.href= '".site_url()."';
					}, 5000);
					</script>";*/
					exit;
				}
			  }
			  else if($response == 'not found')
			  {
				  $order->update_status( 'failed', sprintf( __( 'We have detected different order status. Your order has not been found.', 'woocommerce' ) ) );
				  get_header();
					echo '<div class="container" style="text-align: center;"><div><div>
					<br><br>
					<h2 style="color:#ff0000">Failure!</h2>
					<img style="margin: auto;" src="'.$MVX_Cointopay_Gateway->plugin_url  . 'images/fail.png">
					<p style="font-size:20px;color:#5C5C5C;">We have detected different order status. Your order has not been found..</p>
					<a href="'.site_url().'" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >    Back     </a>
					<br><br>
					<br><br>
					</div>
					</div>
					</div>';
					get_footer();
			  }
			  else{
				  $order->update_status( 'failed', sprintf( __( 'We have detected different order status. Your order has been halted.', 'woocommerce' ) ) );
				  get_header();
					echo '<div class="container" style="text-align: center;"><div><div>
					<br><br>
					<h2 style="color:#ff0000">Failure!</h2>
					<img style="margin: auto;" src="'.$MVX_Cointopay_Gateway->plugin_url  . 'images/fail.png">
					<p style="font-size:20px;color:#5C5C5C;">We have detected different order status. Your order has been halted.</p>
					<a href="'.site_url().'" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >    Back     </a>
					<br><br>
					<br><br>
					</div>
					</div>
					</div>';
					get_footer();
			  }
			}
        }
		
	public function validateOrder($data){
	   $data_j = array(
			'MerchantID' => $data['mid'],
			'Call' => 'QA',
			'APIKey' => '_',
			'output' => 'json',
			'TransactionID' => $data['TransactionID'],
			'ConfirmCode' => $data['ConfirmCode'],
		);

		// Sets the post params.
		$params = array(
			'body' => 'MerchantID='.$data['mid'].'&Call=QA&APIKey=_&output=json&TransactionID='.$data['TransactionID'].'&ConfirmCode='.$data['ConfirmCode'],
			'authentication' => 1,
			'cache-control' => 'no-cache',
		);


			$url = 'https://app.cointopay.com/v2REAPI?';

		if ('yes' == $this->debug) {
			doCointopayLog('Setting payment options with the following data: ' . print_r($data, true));
		}

		$response = wp_safe_remote_post($url, $params);
		 $results = json_decode($response['body']);
			if($results->CustomerReferenceNr)
			{
				return $results;
			}
			else if($response == '"not found"')
			  {
				  get_header();
				   echo '<div class="container" style="text-align: center;"><div><div>
							<br><br>
							<h2 style="color:#ff0000">Failure!</h2>
							<img style="margin: auto;" src="'.plugins_url( 'assets/images/fail.png', __FILE__ ).'">
							<p style="font-size:20px;color:#5C5C5C;">Your order not found.</p>
							<a href="'.site_url().'" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >    Back     </a>
							<br><br>
		
							</div>
							</div>
							</div>';
							get_footer();
						  exit;
			  }
			  
		}
	}

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_cointopay_gateway($methods)
    {
        $methods[] = 'MVX_Cointopay_Gateway_Payment_Method';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_cointopay_gateway' );
}
