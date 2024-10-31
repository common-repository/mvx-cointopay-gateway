<?php

class MVX_Cointopay_Gateway_Admin {

    public function __construct() {
        add_filter( 'automatic_payment_method', array( $this, 'admin_cointopay_payment_mode'), 20);
        add_filter( 'mvx_vendor_payment_mode', array( $this, 'vendor_cointopay_payment_mode' ), 20);
       add_filter("settings_vendors_payment_tab_options", array( $this, 'mvx_setting_cointopay_tag' ), 90, 2 );
        add_filter("settings_vendors_payment_tab_options", array( $this, 'mvx_setting_cointopay_altcoinid' ), 90, 2 );
        add_filter("settings_vendors_payment_tab_options", array( $this, 'mvx_setting_cointopay_coinAddress' ), 90, 2 );
        add_action( 'settings_page_payment_cointopay_tab_init', array( &$this, 'payment_cointopay_init' ), 10, 2 );
        add_filter('mvx_tabsection_payment', array( $this, 'mvx_tabsection_payment_cointopay' ) );
        add_filter('mvx_vendor_user_fields', array( $this, 'mvx_vendor_user_fields_for_cointopay' ), 10, 2 );
        add_action('mvx_after_vendor_billing', array($this, 'mvx_after_vendor_billing_for_cointopay'));
    }
	
	

    public function mvx_after_vendor_billing_for_cointopay() {
        global $MVX;
       $user_array = $MVX->user->get_vendor_fields( get_current_user_id() );
		$new_php_arr = array();
		$option_name = 'mvx_payment_mvx_cointopay_tab_settings';
        $merchant_id = get_mvx_vendor_settings('merchant_id', 'payment_mvx_cointopay');
		$apikey = get_mvx_vendor_settings('apikey', 'payment_mvx_cointopay');
		
        if (!empty($merchant_id)) {
			
				$params = array(
					'body' => 'MerchantID=' . $merchant_id . '&output=json',
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
								$new_php_arr[] = array(
                        'value'=> $php_arr[$i+1],
                        'label'=> $php_arr[$i],
                        'key'=> $php_arr[$i],
                    );
								//$new_php_arr[$php_arr[$i+1]] = $php_arr[$i];
							}
						}
					}
					
					//print_r($new_php_arr);die;
				}
			
		} 
		
        ?>
        <div class="payment-gateway payment-gateway-mvx-cointopay <?php //echo apply_filters('mvx_vendor_paypal_email_container_class', ''); ?>">
            <!--<div class="form-group">
                <label for="vendor_cointopay_merchant_id" class="control-label col-sm-3 col-md-3"><?php //esc_html_e('Cointopay Merchant Id', 'mvx-cointopay-gateway'); ?></label>
                <div class="col-md-6 col-sm-9">
                    <input id="vendor_cointopay_merchant_id" class="form-control" type="text" name="vendor_cointopay_merchant_id" value="<?php //echo isset($user_array['vendor_cointopay_merchant_id']['value']) ? $user_array['vendor_cointopay_merchant_id']['value'] : ''; ?>"  placeholder="<?php //esc_attr_e('Cointopay Merchant Id', 'mvx-cointopay-gateway'); ?>">
                </div>
            </div>-->
			
			<div class="form-group">
                <label for="vendor_cointopay_altcoinid" class="control-label col-sm-3 col-md-3"><?php esc_html_e('Coin Name', 'mvx-cointopay-gateway'); ?></label>
                <div class="col-md-6 col-sm-9">
                    <select id="vendor_cointopay_altcoinid" class="form-control" name="vendor_cointopay_altcoinid">
					<?php 
					foreach ($new_php_arr as $cloin) {
						$selected_val = (isset($user_array['vendor_cointopay_altcoinid']['value']) && $cloin['label'] == $user_array['vendor_cointopay_altcoinid']['value']) ? 'selected="selected"' : '';
						echo '<option vlue="'.$cloin['value'].'" '.$selected_val.'>'.$cloin['label'].'</option>';
					}
					?>
					</select>
                </div>
            </div>
			<div class="form-group">
                <label for="vendor_cointopay_coinAddress" class="control-label col-sm-3 col-md-3"><?php esc_html_e('Coin Address', 'mvx-cointopay-gateway'); ?></label>
                <div class="col-md-6 col-sm-9">
                    <input id="vendor_cointopay_coinAddress" class="form-control" type="text" name="vendor_cointopay_coinAddress" value="<?php echo isset($user_array['vendor_cointopay_coinAddress']['value']) ? $user_array['vendor_cointopay_coinAddress']['value'] : ''; ?>"  placeholder="<?php esc_attr_e('coinAddress', 'mvx-cointopay-gateway'); ?>">
                </div>
            </div>
			<?php $url_overwiew = 'https://app.cointopay.com/v2REAPI';
			
			foreach ($new_php_arr as $cloin) {
				if (isset($user_array['vendor_cointopay_altcoinid']['value']) && $cloin['label'] == $user_array['vendor_cointopay_altcoinid']['value']) {
				$params_overview = array(
						'body' => array('Call' => 'BalanceOverviewWEB', 'MerchantID' => $merchant_id, 'APIKey' => $apikey, 'AltCoinID' => $cloin['value'], 'output' => 'json')
			);
				$response_overwiew = wp_safe_remote_get($url_overwiew, $params_overview);
				
				if (!is_wp_error($response_overwiew) && 200 == $response_overwiew['response']['code'] && 'OK' == $response_overwiew['response']['message']) {
					$results_overwiew = json_decode($response_overwiew['body'], true);
					if (!empty($results_overwiew)) {
						if (isset($results_overwiew[0]['id'])) {
							foreach($results_overwiew as $tag) {
								if ($tag['id'] == $cloin['value']) {
									$ctp_tag = $tag['tag'];
								}
							}
							
							// Sets the post params.
							if ($ctp_tag == 1) {
				?>
			<div class="form-group">
                <label for="vendor_cointopay_tag" class="control-label col-sm-3 col-md-3"><?php esc_html_e('Coin Tag', 'mvx-cointopay-gateway'); ?></label>
                <div class="col-md-6 col-sm-9">
                    <input id="vendor_cointopay_tag" class="form-control" type="text" name="vendor_cointopay_tag" value="<?php echo isset($user_array['vendor_cointopay_tag']['value']) ? $user_array['vendor_cointopay_tag']['value'] : ''; ?>"  placeholder="<?php esc_attr_e('Coin Tag', 'mvx-cointopay-gateway'); ?>">
                </div>
            </div>
			<?php }}}}} else {?>
			
			<?php }}?>
        </div>
        <?php
    }

    public function mvx_vendor_user_fields_for_cointopay($fields, $vendor_id) {
		$vendor = get_mvx_vendor($vendor_id);
		$new_php_arr = array();
		$option_name = 'mvx_payment_mvx_cointopay_tab_settings';
        $merchant_id = get_mvx_vendor_settings('merchant_id', 'payment_mvx_cointopay');
        if (!empty($merchant_id)) {
			
				$params = array(
					'body' => 'MerchantID=' . $merchant_id . '&output=json',
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
								$new_php_arr[] = array(
                        'value'=> $php_arr[$i+1],
                        'label'=> $php_arr[$i],
                        'key'=> $php_arr[$i],
                    );
								//$new_php_arr[$php_arr[$i+1]] = $php_arr[$i];
							}
						}
					}
					
					//print_r($new_php_arr);die;
				}
			
		}
		$fields["vendor_cointopay_coinAddress"] = array(
            'label' => __('Coin Address', 'mvx-cointopay-gateway'),
            'type' => 'text',
            'value' => $vendor->cointopay_coinAddress,
            'class' => "user-profile-fields regular-text"
        );
		$fields["vendor_cointopay_altcoinid"] = array(
            'label' => __('Coin Name', 'mvx-cointopay-gateway'),
            'type' => 'select',
            'value' => $vendor->cointopay_altcoinid,
			'options' => $new_php_arr,
            'class' => "user-profile-fields regular-text"
        );
         $fields["vendor_cointopay_tag"] = array(
            'label' => __('Coin Tag', 'mvx-cointopay-gateway'),
            'type' => 'text',
            'value' => $vendor->cointopay_tag,
            'class' => "user-profile-fields regular-text"
        );
        return $fields;
    }

    public function admin_cointopay_payment_mode( $arg ) {
        unset($arg['mvx-cointopay_block']);
        $admin_payment_mode_select = array_merge( $arg, array( 'mvx-cointopay' => __('Cointopay', 'mvx-cointopay-gateway') ) );
        return $admin_payment_mode_select;
    }

    public function vendor_cointopay_payment_mode($payment_mode) {
        //if (mvx_is_module_active('mvx-cointopay')) {
            $payment_mode['mvx-cointopay'] = __('Cointopay Crypto', 'mvx-cointopay-gateway');
       // }
        return $payment_mode;
    }

     public function mvx_setting_cointopay_tag( $payment_tab_options, $vendor_obj ) {
        $payment_tab_options['vendor_mvx-cointopay_tag'] = array('label' => __('Coin Tag', 'mvx-cointopay-gateway'), 'type' => 'text', 'id' => 'vendor_cointopay_tag', 'label_for' => 'vendor_cointopay_tag', 'name' => 'vendor_cointopay_tag', 'value' => $vendor_obj->cointopay_tag, 'wrapper_class' => 'payment-gateway-cointopay payment-gateway');
        return $payment_tab_options;
    }
	
	public function mvx_setting_cointopay_altcoinid( $payment_tab_options, $vendor_obj ) {
        $payment_tab_options['vendor_mvx-cointopay_altcoinid'] = array('label' => __('Coin Name', 'mvx-cointopay-gateway'), 'type' => 'select', 'id' => 'vendor_cointopay_altcoinid', 'label_for' => 'vendor_cointopay_altcoinid', 'name' => 'vendor_cointopay_altcoinid', 'value' => $vendor_obj->cointopay_altcoinid, 'wrapper_class' => 'payment-gateway-cointopay payment-gateway');
        return $payment_tab_options;
    }
	
	public function mvx_setting_cointopay_coinAddress( $payment_tab_options, $vendor_obj ) {
        $payment_tab_options['vendor_mvx-cointopay_coinAddress'] = array('label' => __('Coin Address', 'mvx-cointopay-gateway'), 'type' => 'text', 'id' => 'vendor_cointopay_coinAddress', 'label_for' => 'vendor_cointopay_coinAddress', 'name' => 'vendor_cointopay_coinAddress', 'value' => $vendor_obj->cointopay_coinAddress, 'wrapper_class' => 'payment-gateway-cointopay payment-gateway');
        return $payment_tab_options;
    }

    public function payment_cointopay_init( $tab, $subsection ) {
        global $MVX_Cointopay_Gateway;
        require_once $MVX_Cointopay_Gateway->plugin_path . 'admin/class-mvx-settings-payment-cointopay.php';
        new MVX_Settings_Payment_Cointopay( $tab, $subsection );
    }

    public function mvx_tabsection_payment_cointopay($tabsection_payment) {
        if ( 'Enable' === get_mvx_vendor_settings( 'payment_method_mvx-cointopay', 'payment' ) ) {
            $tabsection_payment['mvx-cointopay'] = array( 'title' => __( 'Cointopay', 'mvx-cointopay-gateway' ), 'icon' => 'dashicons-admin-settings' );
        }
        return $tabsection_payment;
    }
}