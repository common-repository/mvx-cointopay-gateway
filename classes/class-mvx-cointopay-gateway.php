<?php

class MVX_Cointopay_Gateway {
    public $plugin_url;
    public $plugin_path;
    public $version;
    public $token;
    public $text_domain;
    private $file;
    public $license;
    public $connect_cointopay;
    public $cointopay_admin;

    public function __construct($file) {
        $this->file = $file;
        $this->plugin_url = trailingslashit(plugins_url('', $plugin = $file));
        $this->plugin_path = trailingslashit(dirname($file));
        $this->token = MVX_COINTOPAY_GATEWAY_PLUGIN_TOKEN;
        $this->text_domain = MVX_COINTOPAY_GATEWAY_TEXT_DOMAIN;
        $this->version = MVX_COINTOPAY_GATEWAY_PLUGIN_VERSION;

        require_once $this->plugin_path . 'classes/class-mvx-cointopay-payment.php';        
        add_action('init', array(&$this, 'init'), 0);
        add_filter('mvx_multi_tab_array_list', array($this, 'mvx_multi_tab_array_list_for_cointopay'));
        add_filter('mvx_settings_fields_details', array($this, 'mvx_settings_fields_details_for_cointopay'));
		add_action( 'wp_enqueue_scripts', array($this, 'mvx_ctp_enqueue_admin_script') );
		add_action( 'wp_ajax_nopriv_getMVXMerchantCoinsByAjax', array($this, 'getMVXMerchantCoinsByAjax') );
		add_action( 'wp_ajax_getMVXMerchantCoinsByAjax', array($this, 'getMVXMerchantCoinsByAjax') );
    }

public function getMVXMerchantCoinsByAjax()
	{
		$coinId = 0;
		$ctp_tag = 0;
		$coinId = sanitize_text_field($_REQUEST['coinId']);
		if(isset($coinId) && $coinId !== 0)
		{
			$option = '';
			$arr = $this->getMVXMerchantCoins($coinId);
			foreach($arr as $key => $value)
			{
				if ($value['name'] == $coinId) {
					$ctp_tag = $value['tag'];
				}
						
			}
			
			echo $ctp_tag;exit();
		}
	}
	
	
	public function getMVXMerchantCoins($coinId)
	{
		
        $merchant_id = get_mvx_vendor_settings('merchant_id', 'payment_mvx_cointopay');
		$apikey = get_mvx_vendor_settings('apikey', 'payment_mvx_cointopay');
		$php_arr = array();
		$params = array(
			'body' => 'MerchantID=' . $merchant_id . '&output=json',
		);
		$url = 'https://cointopay.com/CloneMasterTransaction';
		$response  = wp_safe_remote_post($url, $params);
		if (( false === is_wp_error($response) ) && ( 200 === $response['response']['code'] ) && ( 'OK' === $response['response']['message'] )) {
		$php_arr = json_decode($response['body']);
		}
		if (!empty($php_arr)) {
		foreach ($php_arr as $k=>$cloin) {
				if ($cloin == $coinId) {
		$paramso = array(
			'body' => array('Call' => 'BalanceOverviewWEB', 'MerchantID' => $merchant_id, 'APIKey' => $apikey, 'AltCoinID' => $k, 'output' => 'json')
		);
		$url = 'https://app.cointopay.com/v2REAPI';
		$response  = wp_safe_remote_post($url, $paramso);
		if (( false === is_wp_error($response) ) && ( 200 === $response['response']['code'] ) && ( 'OK' === $response['response']['message'] )) {
			$php_arr = json_decode($response['body'], true);
			
			
			return $php_arr;
		}
				}
		}
		}
	}
	
	public function mvx_ctp_enqueue_admin_script()
	{
		wp_enqueue_script( 'mvx_ctp_custom_s', $this->plugin_url . 'assets/js/mvx_custom_js.js', array(), '1.0.0', true );
		wp_localize_script('mvx_ctp_custom_s',
                'ctp_mvx_ajax',
                array( 'ajaxurl' => admin_url( 'admin-ajax.php' ))
            );
	}
    public function mvx_multi_tab_array_list_for_cointopay($tab_link) {
        $tab_link['marketplace-payments'][] = array(
                'tablabel'      =>  __('Cointopay Crypto', 'mvx-cointopay-gateway'),
                'apiurl'        =>  'mvx_module/v1/save_dashpages',
                'description'   =>  __('Connect to vendors cointopay account and make hassle-free transfers as scheduled.', 'mvx-cointopay-gateway'),
                'icon'          =>  'icon-tab-stripe-connect',
                'submenu'       =>  'payment',
                'modulename'     =>  'payment-mvx-cointopay'
            );
        return $tab_link;
    }

    public function mvx_settings_fields_details_for_cointopay($settings_fileds) {
		
       
        $settings_fileds_report = [
            [
                'key'       => 'merchant_id',
                'type'      => 'text',
                'label'     => __('Merchant Id', 'mvx-cointopay-gateway'),
                'placeholder'   => __('Merchant Id', 'mvx-cointopay-gateway'),
                //'database_value' => '',
            ],
            [
                'key'       => 'security_code',
                'type'      => 'text',
                'label'     => __('Security Code', 'mvx-cointopay-gateway'),
                'placeholder'   => __('Security Code', 'mvx-cointopay-gateway'),
                'database_value' => '',
            ],
            /* [
                'key'       => 'altcoinid',
                'type'          => 'select',
                'label'         => __( 'AltCoinID', 'mvx-cointopay-gateway' ),
                'options'       => $new_php_arr,
                'description'   => __( 'Select AltCoinID.', 'mvx-cointopay-gateway' )
            ], */
            [
                'key'       => 'apikey',
                'type'          => 'textarea',
                'label'         => __( 'API Key', 'mvx-cointopay-gateway' ),
                'placeholder'   => __('API Key', 'mvx-cointopay-gateway'),
                'database_value' => '',
            ],
        ];
        $settings_fileds['payment-mvx-cointopay'] = $settings_fileds_report;
		
		
        return $settings_fileds;
    }

    /**
     * initilize plugin on WP init
     */
    function init() {
        // Init Text Domain
        $this->load_plugin_textdomain();

        if (class_exists('MVX')) {
            require_once $this->plugin_path . 'classes/class-mvx-gateway-cointopay.php';
            $this->connect_cointopay = new MVX_Gateway_Cointopay();

            require_once $this->plugin_path . 'classes/class-mvx-cointopay-gateway-admin.php';
            $this->cointopay_admin = new MVX_Cointopay_Gateway_Admin();

            add_filter('mvx_payment_gateways', array(&$this, 'add_mvx_cointopay_payment_gateway'));
			//add_action('template_redirect', 'cointopay_mvx_callback');
        }
    }

    public function add_mvx_cointopay_payment_gateway($load_gateways) {
        $load_gateways[] = 'MVX_Gateway_Cointopay';
        return $load_gateways;
    }

    /**
     * Load Localisation files.
     *
     * Note: the first-loaded translation file overrides any following ones if the same translation is present
     *
     * @access public
     * @return void
     */
    public function load_plugin_textdomain() {
        $locale = is_admin() && function_exists('get_user_locale') ? get_user_locale() : get_locale();
        $locale = apply_filters('plugin_locale', $locale, 'mvx-cointopay-gateway');
        load_textdomain('mvx-cointopay-gateway', WP_LANG_DIR . '/mvx-cointopay-gateway/mvx-cointopay-gateway-' . $locale . '.mo');
        load_plugin_textdomain('mvx-cointopay-gateway', false, plugin_basename(dirname(dirname(__FILE__))) . '/languages');
    }
	
	public function cointopay_mvx_callback()
	{
		
	}

}