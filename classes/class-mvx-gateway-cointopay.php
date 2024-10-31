<?php

if (!defined('ABSPATH')) {
    exit;
}

class MVX_Gateway_Cointopay extends MVX_Payment_Gateway {

    public $id;
    public $gateway_title;
    public $message = array();
    private $test_mode = false;
    private $payout_mode = 'true';
    private $reciver_coinAddress;
    private $merchant_id;
    private $security_code;
    private $alt_coin_id = 1;
    private $apikey = '';
    private $cointag = 0;
	public $payment_gateway;
    public $transaction_mode;
    public $transaction_id;
    /* array of commission ids */
    public $commissions = array();

    public function __construct() {
        $this->id = 'mvx-cointopay';
        $this->gateway_title = apply_filters('mvx_mvx-cointopay_gateway_title', __('MVX Cointopay', 'dc-woocommerce-multi-vendor'));
        $this->enabled = (get_mvx_vendor_settings('merchant_id', 'payment_mvx_cointopay') && get_mvx_vendor_settings('security_code', 'payment_mvx_cointopay')) ? 'Enable' : false;
        $this->merchant_id = get_mvx_vendor_settings('merchant_id', 'payment_mvx_cointopay');
        $this->security_code = get_mvx_vendor_settings('security_code', 'payment_mvx_cointopay');
		$this->apikey = get_mvx_vendor_settings('apikey', 'payment_mvx_cointopay');
		$this->payment_gateway = 'mvx-cointopay';
		$this->transaction_mode = 'auto';
        //$this->alt_coin_id = (get_mvx_vendor_settings('altcoinid', 'payment_mvx_cointopay')) ? get_mvx_vendor_settings('altcoinid', 'payment_mvx_cointopay')['value'] : 1;
    }

	public function gateway_logo() { global $MVX; return $MVX->plugin_url . 'images/'.$this->id.'.png'; }

    public function process_payment($vendor, $commissions = array(), $transaction_mode = 'auto', $transfer_args = array()) {
        $this->vendor = $vendor;
        $this->commissions = $commissions;
        $this->currency = get_woocommerce_currency();
        $this->transaction_mode = $transaction_mode;
       $this->reciver_coinAddress = mvx_get_user_meta($this->vendor->id, '_vendor_cointopay_coinAddress', true);
	   $this->alt_coin_id = mvx_get_user_meta($this->vendor->id, '_vendor_cointopay_altcoinid', true);
	   $this->cointag = mvx_get_user_meta($this->vendor->id, '_vendor_cointopay_tag', true);
	   if (!empty($this->merchant_id)) {
			
				$params = array(
					'body' => 'MerchantID=' . $this->merchant_id . '&output=json',
				);
				$url = 'https://cointopay.com/CloneMasterTransaction';
				$response  = wp_safe_remote_post($url, $params);
				if (( false === is_wp_error($response) ) && ( 200 === $response['response']['code'] ) && ( 'OK' === $response['response']['message'] )) {
					$php_arr = json_decode($response['body']);
					$new_php_arr = array();

					if(!empty($php_arr))
					{
						for($i=0;$i<count($php_arr)-1;$i++)
						{
							if(($i%2)==0)
							{
								if ($this->alt_coin_id == $php_arr[$i]) {
									$this->alt_coin_id = $php_arr[$i+1];
								}
								//$new_php_arr[$php_arr[$i+1]] = $php_arr[$i];
							}
						}
					}
					
					//print_r($new_php_arr);die;
				}
			
		} 
        if ($this->validate_request()) {
           $cointopay_response = $this->process_cointopay_payout();
			if (!empty($cointopay_response['error'])) {
				//print_R($cointopay_response['error']);die;
             doProductVendorLOG(json_encode($cointopay_response['error']));
			 return $cointopay_response['error'];
			}
            if ($cointopay_response && isset($cointopay_response['success']) && !empty($cointopay_response['success'])) {
                $this->commissions = $cointopay_response['success']['commission_id'];
                $this->record_transaction();
                if ($this->transaction_id) {
                    return array('message' => __('New transaction has been initiated', 'mvx-cointopay-gateway'), 'type' => 'success', 'transaction_id' => $this->transaction_id);
                }
            } else {
                return false;
            }
        } else {
            return $this->message;
        }
    }

    public function validate_request() {
        global $MVX;
        if ($this->enabled != 'Enable') {
            $this->message[] = array('message' => __('Invalid payment method', 'mvx-cointopay-gateway'), 'type' => 'error');
            return false;
        } else if (!$this->merchant_id && !$this->security_code) {
            $this->message[] = array('message' => __('Cointopay payout setting is not configured properly. Please contact site administrator', 'mvx-cointopay-gateway'), 'type' => 'error');
            return false;
        } else if (!$this->apikey) {
            $this->message[] = array('message' => __('Please enter API Key', 'mvx-cointopay-gateway'), 'type' => 'error');
            return false;
        } else if (!$this->reciver_coinAddress) {
            $this->message[] = array('message' => __('Please update your Cointopay Account information to receive commission', 'mvx-cointopay-gateway'), 'type' => 'error');
            return false;
        }

        if ($this->transaction_mode != 'admin') {
            /* handel thesold time */
            $threshold_time = isset($MVX->vendor_caps->payment_cap['commission_threshold_time']) && !empty($MVX->vendor_caps->payment_cap['commission_threshold_time']) ? $MVX->vendor_caps->payment_cap['commission_threshold_time'] : 0;
            if ($threshold_time > 0) {
                foreach ($this->commissions as $index => $commission) {
                    if (intval((date('U') - get_the_date('U', $commission)) / (3600 * 24)) < $threshold_time) {
                        unset($this->commissions[$index]);
                    }
                }
            }
            /* handel thesold amount */
            $thesold_amount = isset($MVX->vendor_caps->payment_cap['commission_threshold']) && !empty($MVX->vendor_caps->payment_cap['commission_threshold']) ? $MVX->vendor_caps->payment_cap['commission_threshold'] : 0;
            //if ($this->get_transaction_total() > $thesold_amount) {
                return true;
            /*} else {
                $this->message[] = array('message' => __('Minimum thesold amount to withdrawal commission is ' . $thesold_amount, 'mvx-cointopay-gateway'), 'type' => 'error');
                return false;
            }*/
        }
        return parent::validate_request();
    }

    public function process_cointopay_payout() {
        $response = array();
        $response_success = array();
        if (is_array($this->commissions)) {
            foreach ($this->commissions as $commission_id) {
                $commissionResponse = array();
                //check the order is payed with cointopay or not!!
                $vendor_order_id = mvx_get_commission_order_id($commission_id);
                //get order details
                if ($vendor_order_id) {
                    $vendor_order = wc_get_order($vendor_order_id);
                    //check for valid vendor_order
                    if ($vendor_order) {
                        //get order payment mode
                        $paymentMode = $vendor_order->get_payment_method();
                        $orderStatus = $vendor_order->get_status();
                        $parent_order = wc_get_order($vendor_order->get_parent_id());
                        $order_transaction_id = $parent_order ? $parent_order->get_transaction_id() : 0;

                        //get commission amount to be transferred and commission note
                        $commission_amount = MVX_Commission::commission_totals($commission_id, 'edit');
                        $transaction_total = (float) $commission_amount;
                        $amount_to_pay = round($transaction_total - ($this->transfer_charge($this->transaction_mode)/count($this->commissions)) - $this->gateway_charge(), 2);
                        $note = sprintf(__('Total commissions earned from %1$s as at %2$s on %3$s', 'mvx-cointopay-gateway'), get_bloginfo('name'), date('H:i:s'), date('d-m-Y'));
                        $acceptedOrderStatus = apply_filters('mvx_cointopay_payment_order_status', array('processing', 'on-hold', 'completed'));
                        //check payment mode
                        if ($paymentMode != 'mvx-cointopay') {
                            //payment method is not valid
                            $commissionResponse['message'] = "Order is not processed With Cointopay!"
                                . " Unable to Process #$vendor_order_id Order Commission!!";
                            $commissionResponse['type'] = 'error';
                        } elseif (!in_array($orderStatus, $acceptedOrderStatus)){
                            //order may not successfully paid unable to process the commission
                            $commissionResponse['message'] = "#$vendor_order_id is not paid properly or refunded!!"
                                . " Unable to Process #$commission_id Commission!!";
                            $commissionResponse['type'] = 'error';
                        } elseif ( $amount_to_pay < 1 ) {
                            $commissionResponse['message'] = "Commission Amount is less than 1 !!"
                                . " Unable to Process #$commission_id Commission!!";
                            $commissionResponse['type'] = 'error';
                        } else {
                           $final_amount_to_pay = (float) ($amount_to_pay * 100);
                            //get payment details
							$ctp_tag = 0;
                            try {
								$ctpmvxcallbackurl = site_url('/?wc-api=mvx-cointopay&MVXCointopay=1&CommissionID=' . $commission_id . '&VendorID=' . $this->vendor->id);
								$url_overwiew = 'https://app.cointopay.com/v2REAPI';
								$params_overview = array(
									'body' => array('Call' => 'BalanceOverviewWEB', 'MerchantID' => $this->merchant_id, 'APIKey' => $this->apikey, 'AltCoinID' => $this->alt_coin_id, 'output' => 'json')
							    );
								$response_overwiew = wp_safe_remote_get($url_overwiew, $params_overview);
								
								if (!is_wp_error($response_overwiew) && 200 == $response_overwiew['response']['code'] && 'OK' == $response_overwiew['response']['message']) {
									$results_overwiew = json_decode($response_overwiew['body'], true);
									if (!empty($results_overwiew)) {
										if (isset($results_overwiew[0]['id'])) {
											foreach($results_overwiew as $tag) {
												if ($tag['id'] == $this->alt_coin_id && $tag['tag'] == 1) {
													$ctp_tag = $this->cointag;
												}
											}
											// Sets the post params.
											if ($ctp_tag == 0) {
												$params = array(
													'body' => array('MerchantID' => $this->merchant_id, 'APIKey' => $this->apikey, 'AltCoinID' => $this->alt_coin_id, 'Amount' => number_format($amount_to_pay, 8, '.', ''), 'PayoutMonth' => date("n"), 'coinAddress' =>  $this->reciver_coinAddress, 'ChargingMethod' => 'fixed', 'TransactionTotal' => 1, 'inputCurrency' => get_woocommerce_currency(), 'ConfirmURL' => $ctpmvxcallbackurl, 'output' => 'json')
												);
											} else {
											$params = array(
												'body' => array('MerchantID' => $this->merchant_id, 'APIKey' => $this->apikey, 'AltCoinID' => $this->alt_coin_id, 'Amount' => number_format($amount_to_pay, 8, '.', ''), 'PayoutMonth' => date("n"), 'coinAddress' =>  $this->reciver_coinAddress, 'ChargingMethod' => 'fixed', 'TransactionTotal' => 1, 'inputCurrency' => get_woocommerce_currency(), 'tag' => $ctp_tag, 'ConfirmURL' => $ctpmvxcallbackurl, 'output' => 'json')
											);
											}
											$url = 'https://app.cointopay.com/GetSendToAddress';
											$response_ctp = wp_safe_remote_get($url, $params);
											if (!is_wp_error($response_ctp) && 200 == $response_ctp['response']['code'] && 'OK' == $response_ctp['response']['message']) {
												$results = json_decode($response_ctp['body']);
												if (is_string($results)) {
													$commissionResponse['message'] = 'Failed to configure payment options: ' . $results;
												}
												$response_success['success'] = $results;
												$response_success['commission_id'][] = $commission_id;
											} else {
												$results = json_decode($response_ctp['body']);
												if (is_string($results)) {
													$commissionResponse['message'] = 'Failed to configure payment options: ' . $results;
												} else {
												$commissionResponse['message'] = 'Failed to configure payment options: ' . print_r($results, true);
												}
												$commissionResponse['type'] = 'error';
											}
										} else {
											$commissionResponse['message'] = 'Failed to configure payment options: ' . print_r($response_overwiew, true);
											$commissionResponse['type'] = 'error';
										}
									} else {
										$commissionResponse['message'] = 'Something wrong with cointopay BalanceOverviewWEB API';
										$commissionResponse['type'] = 'error';
									}
								} else {
									$commissionResponse['message'] = 'Failed to configure payment options: ' . print_r($response_overwiew, true);
										$commissionResponse['type'] = 'error';
								}
                            } catch (Exception $e) {
								//set error message for the vendor_order id
                                $commissionResponse['message'] = 'Cointopay Comission Payment Error!!'
                                    ."\n".$e->getCode().": ".$e->getMessage();
                                $commissionResponse['type'] = "error";
                            }
                        }
                    } else {
                        //set error message for the vendor_order id
                        $commissionResponse['message'] = "Unable to get #$vendor_order_id Order Details!!";
                        $commissionResponse['type'] = "error";
                    }
                } else {
                    //set error message for the commission id
                    $commissionResponse['message'] = "Unable to get #$commission_id Commission Respective Order Id!!";
                    $commissionResponse['type'] = "error";
                }
                //set response
                $response['error'][] = $commissionResponse;
                $response['success'] = $response_success;
            }
        }
        return $response;
    }
	
	public function record_transaction() {
        $commission_status = 'mvx_processing';
        $transaction_args = array(
            'post_type' => 'mvx_transaction',
            'post_title' => sprintf(__('Transaction - %s', 'multivendorx'), strftime(_x('%B %e, %Y @ %I:%M %p', 'Transaction date parsed by strftime', 'multivendorx'), current_time( 'timestamp' ))),
            'post_status' => $commission_status,
            'ping_status' => 'closed',
            'post_author' => $this->vendor->term_id
        );
        $this->transaction_id = wp_insert_post($transaction_args);
        if (!is_wp_error($this->transaction_id) && $this->transaction_id) {
            $this->update_meta_data($commission_status);
            $this->email_notify($commission_status);
            $this->add_commission_note($this->commissions, sprintf(__('Commission paid via %s <a href="%s">(ID : %s)</a>', 'multivendorx'), $this->gateway_title, get_admin_url('mvx-transaction-details') . 'admin.php?page=mvx-transaction-details&trans_id=' . $this->transaction_id, $this->transaction_id));
        }
    }
	
	public function update_meta_data($commission_status = 'mvx_processing') {
        update_post_meta($this->transaction_id, 'transaction_mode', $this->payment_gateway);
        update_post_meta($this->transaction_id, 'payment_mode', $this->transaction_mode);
        $transfar_charge = $this->transfer_charge($this->transaction_mode);
        update_post_meta($this->transaction_id, 'transfer_charge', $transfar_charge);
        $gateway_charge = $this->gateway_charge();
        update_post_meta($this->transaction_id, 'gateway_charge', $gateway_charge);
        $transaction_amount = $this->get_transaction_total();
        update_post_meta($this->transaction_id, 'amount', $transaction_amount);
        $total_amount = $transaction_amount - $transfar_charge - $gateway_charge;
        update_post_meta($this->transaction_id, 'total_amount', $total_amount);
        update_post_meta($this->transaction_id, 'commission_detail', $this->commissions);

        foreach ($this->commissions as $commission) {
            update_post_meta($commission, '_paid_request', $this->payment_gateway);
            if ($commission_status == 'mvx_completed') {
                mvx_paid_commission_status($commission);
                update_post_meta($this->transaction_id, 'paid_date', date("Y-m-d H:i:s"));
            }
        }
        do_action('mvx_transaction_update_meta', $commission_status, $this->transaction_id, $this->vendor, $this);
    }
}
