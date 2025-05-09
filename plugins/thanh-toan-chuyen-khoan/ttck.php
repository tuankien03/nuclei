<?php

/**
 * Plugin Name: Thanh Toán Quét Mã QR Code Tự Động cho WooCommerce
 * Plugin URI: https://bck.haibasoft.com
 * Description: Tự động xác nhận thanh toán quét mã QR Code MoMo, ViettelPay, VNPay, Vietcombank, Vietinbank, Techcombank, MB, ACB, VPBank, TPBank..
 * Author: TTCK Team
 * Author URI: https://bck.haibasoft.com
 * Text Domain: thanh-toan-chuyen-khoan
 * Domain Path: /languages
 * Version: 1.0.0
 * License: GNU General Public License v3.0
 */

if (!defined('ABSPATH')) {
	exit;
}
define('TTCK_DIR', plugin_dir_path(__FILE__));
define('TTCK_URL', plugins_url('/', __FILE__));
define('TTCK_TEST', 0);
//require(__DIR__."/lib/phpqrcode/qrlib.php");
require(__DIR__."/inc/functions.php");

class TTCKPayment
{
	
	static $oauth_settings = array(
		//'email' => '',
	);
	static $default_settings = array(

		'bank_transfer'         =>
		array(
			'case_insensitive' => 'yes',
			'enabled' => 'yes',
			'title' => 'Chuyển khoản ngân hàng 24/7',
			'secure_token' => '',
			'transaction_prefix' => 'ABC',
			'acceptable_difference' => 1000,
			'authorization_code' => '',
			'viet_qr' => 'yes',

		),
		'bank_transfer_accounts' =>
		array(
			/*array(
				'account_name'   => '',
				'account_number' => '',
				'bank_name'      => '',
				'bin'      => 0,
				'connect_status'      => 0,
				'plan_status'      => 0,
				'is_show'      => 'yes',
			),*/
		),
		'order_status' =>
		array(
			'order_status_after_paid'   => 'wc-completed',
			'order_status_after_underpaid' => 'wc-processing',
		),

	);
	
	
	public function __construct()
	{
		// get the settings of the old version
		$this->domain = 'thanh-toan-chuyen-khoan';
		add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));

		add_action('init', array($this, 'init'));
		register_activation_hook( __FILE__, array($this,'activate') );
		register_deactivation_hook( __FILE__, array($this,'deactivate') );
		$this->settings = self::get_settings();
	}
	function activate() {
		if( version_compare(phpversion(), '5.6', '<')  ) {
			wp_die('You need to update your PHP version. Require: PHP 5.6+');
		}
		if(!extension_loaded('gd')) wp_die('Please activate PHP GD library.');
		if(!class_exists('WooCommerce')) wp_die('Please activate woocommerce plugin');
		wp_redirect(admin_url('admin.php?page=qhtp'));
	}
	function deactivate() {
		;
	}
	
	public function init()
	{
		if (class_exists('WooCommerce')) {
			// Run this plugin normally if WooCommerce is active
			// Load the localization featureUnderpaid

			$this->main();
			// Add "Settings" link when the plugin is active
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
				$settings = array('<a href="https://bck.haibasoft.com" target="_blank">' . __('Docs', 'woocommerce') . '</a>');
				$links    = array_reverse(array_merge($links, $settings));

				return $links;
			});
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
				#$settings = array('<a href="https://wordpress.org/support/plugin/ttck/reviews/" target="_blank">' . __('Review', 'woocommerce') . '</a>');
				#$links    = array_reverse(array_merge($links, $settings));
				return $links;
			});
			// Đăng kí thêm trạng thái 
			add_filter('wc_order_statuses', array($this, 'add_order_statuses'));
			register_post_status('wc-paid', array(
				'label'                     => __('Paid', 'thanh-toan-chuyen-khoan'),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop(__('Paid', 'thanh-toan-chuyen-khoan') . ' (%s)', __('Paid', 'thanh-toan-chuyen-khoan') . ' (%s)')
			));
			register_post_status('wc-underpaid', array(
				'label'                     =>  __('Underpaid', 'thanh-toan-chuyen-khoan'),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop(__('Underpaid', 'thanh-toan-chuyen-khoan') . ' (%s)', __('Underpaid', 'thanh-toan-chuyen-khoan') . ' (%s)')
			));
			wp_enqueue_style('ttck-style', plugins_url('assets/css/style.css', __FILE__), array(), false, 'all');
			wp_enqueue_script('ttck-qrcode', plugins_url('assets/js/easy.qrcode.js', __FILE__), array('jquery'), '', true);
			if(is_admin() && isset($_GET['page']) && $_GET['page']=='ttck') {
				wp_enqueue_script('ttck-js', plugins_url('assets/js/js.js', __FILE__), array('jquery'), '', true);
			}
			
			add_action('wp_ajax_nopriv_fetch_order_status_ttck', array($this, 'fetch_order_status'));
			add_action('wp_ajax_fetch_order_status_ttck', array($this, 'fetch_order_status'));
			
			add_action('wp_ajax_nopriv_paid_order_ttck', array($this, 'pc_payment_handler'));
			add_action('wp_ajax_paid_order_ttck', array($this, 'pc_payment_handler'));

			//add_action('wp_ajax_nopriv_auth_app_ttck', array($this, 'auth_app_ttck'));
			//add_action('wp_ajax_auth_app_ttck', array($this, 'auth_app_ttck'));
			
			add_action('wp_ajax_nopriv_auth_sync_status_ttck', array($this, 'auth_sync_status_ttck'));
			add_action('wp_ajax_auth_sync_status_ttck', array($this, 'auth_sync_status_ttck'));

		} else {
			// Throw a notice if WooCommerce is NOT active
			add_action('admin_notices', array($this, 'notice_if_not_woocommerce'));
		}
	}

	//health check
	public function auth_sync_status_ttck() {
		wp_send_json(['oauth_status'=>!empty(self::oauth_get_settings()), 'timestamp'=> time()]);
		die();
	}

	
	public function fetch_order_status()
	{
		if(empty($_REQUEST['order_id']) || !is_numeric($_REQUEST['order_id'])) {
			echo 'wc-pending';die();
		}
		$order = wc_get_order($_REQUEST['order_id']);
		$order_data = $order->get_data();
		$status = esc_attr($order_data['status']);
		echo 'wc-' . esc_html($status);
		die();
	}
	public function add_order_statuses($order_statuses)
	{
		$new_order_statuses = array();
		// add new order status after processing
		foreach ($order_statuses as $key => $status) {
			$new_order_statuses[$key] = $status;
		}
		$new_order_statuses['wc-paid'] = __('Paid', 'thanh-toan-chuyen-khoan');
		$new_order_statuses['wc-underpaid'] = __('Underpaid', 'thanh-toan-chuyen-khoan');
		return $new_order_statuses;
	}
	//Hàm này có thể giúp tạo ra một class Bank mới.
	public function gen_payment_gateway($gatewayName)
	{
		// $newClass = new class extends WC_Gateway_TTCK_Base
		// {
		// }; //create an anonymous class
		// $newClassName = get_class($newClass); //get the name PHP assigns the anonymous class
		// class_alias($newClassName, $gatewayName); //alias the anonymous class with your class name
	}


	public function main()
	{

		if (is_admin()) {
			include(TTCK_DIR . 'inc/class-ttck-admin-page.php');
			$this->Admin_Page = new TTCK_Admin_Page();
		}
		$settings = self::get_settings();
		$this->settings = $settings;
		//add_action('woocommerce_api_' . self::$webhook_oauth2, array($this, 'oauth2_handler'));
		//add_action('woocommerce_api_' . self::$webhook_route, array($this, 'pc_payment_handler'));

		if ('yes' == $settings['bank_transfer']['enabled'] ) {
			// chỗ này e tách ra ngoài code cho clean mà nó k nhận (gộp woocommerce_payment_gateways)
			
			require_once(TTCK_DIR . 'inc/banks/class-ttck-acb.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-mbbank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-techcombank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-timoplus.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-vpbank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-vietinbank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-ocb.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-tpbank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-vietcombank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-bidv.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-agribank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-lienviet.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-hdbank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-msb.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-sacombank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-shb.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-vib.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-scb.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-abbank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-bacabank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-eximbank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-namabank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-ncb.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-seabank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-vietcapitalbank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-cake.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-tnex.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-cimbbank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-dongabank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-hsbc.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-baovietbank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-oceanbank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-vietabank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-vietbank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-saigonbank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-kienlongbank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-pvcombank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-pulicbank.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-vrbank.php');
			//require_once(TTCK_DIR . 'inc/banks/class-ttck-moca.php');
			//require_once(TTCK_DIR . 'inc/banks/class-ttck-shopeepay.php');
			//require_once(TTCK_DIR . 'inc/banks/class-ttck-smartpay.php');			
			require_once(TTCK_DIR . 'inc/banks/class-ttck-vinid.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-vnpay.php');
			//require_once(TTCK_DIR . 'inc/banks/class-ttck-zalopay.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-momo.php');
			require_once(TTCK_DIR . 'inc/banks/class-ttck-viettelpay.php');

			#require_once(TTCK_DIR . 'inc/banks/class-ttck-vnptpay.php');
			//require_once(TTCK_DIR . 'inc/banks/class-ttck-mobifonepay.php');
			//require_once(TTCK_DIR . 'inc/banks/class-ttck-vtcpay.php');
			//require_once(TTCK_DIR . 'inc/banks/class-ttck-vimo.php');

			/*foreach ($settings['bank_transfer_accounts'] as $account) {
				//$bank_name = explode('-',$account);
				//if (isset($account['is_show']) && $account['is_show'] == 'yes') {
					if (strtolower($account['bank_name']) == 'momo')
						
					if (strtolower($account['bank_name']) == 'acb')
						
					if (strtolower($account['bank_name']) == 'mbbank')
						
					if (strtolower($account['bank_name']) == 'techcombank')
						
					if (strtolower($account['bank_name']) == 'timoplus')
						
					if (strtolower($account['bank_name']) == 'vpbank')
						
					if (strtolower($account['bank_name']) == 'vietinbank')
						
					if (strtolower($account['bank_name']) == 'ocb')
						
					if (strtolower($account['bank_name']) == 'tpbank')
						
					if (strtolower($account['bank_name']) == 'vietcombank')
						
					if (strtolower($account['bank_name']) == 'bidv')
						
					if (strtolower($account['bank_name']) == 'agribank')
						
				//}
			}*/
			add_filter('woocommerce_payment_gateways', function ($gateways) {
				$settings = self::get_settings();
				#$gateways[] = 'WC_Gateway_TTCK_Phone';
				$gateways[] = 'WC_Gateway_TTCK_ACB';
				$gateways[] = 'WC_Gateway_TTCK_Mbbank';
				$gateways[] = 'WC_Gateway_TTCK_Techcombank';
				$gateways[] = 'WC_Gateway_TTCK_TimoPlus';
				$gateways[] = 'WC_Gateway_TTCK_Vpbank';
				$gateways[] = 'WC_Gateway_TTCK_Vietinbank';
				$gateways[] = 'WC_Gateway_TTCK_OCB';
				$gateways[] = 'WC_Gateway_TTCK_TPbank';
				$gateways[] = 'WC_Gateway_TTCK_Vietcombank';
				$gateways[] = 'WC_Gateway_TTCK_BIDV';
				$gateways[] = 'WC_Gateway_TTCK_Agribank';
				$gateways[] = 'WC_Gateway_TTCK_Lienviet';
				$gateways[] = 'WC_Gateway_TTCK_Hdbank';				
				$gateways[] = 'WC_Gateway_TTCK_MSB';
				$gateways[] = 'WC_Gateway_TTCK_Sacombank';
				$gateways[] = 'WC_Gateway_TTCK_SHB';
				$gateways[] = 'WC_Gateway_TTCK_SCB';
				$gateways[] = 'WC_Gateway_TTCK_ABBank';
				$gateways[] = 'WC_Gateway_TTCK_BacABank';
				$gateways[] = 'WC_Gateway_TTCK_Eximbank';
				$gateways[] = 'WC_Gateway_TTCK_NamABank';
				$gateways[] = 'WC_Gateway_TTCK_NCB';
				$gateways[] = 'WC_Gateway_TTCK_SeABank';
				$gateways[] = 'WC_Gateway_TTCK_VietCapitalBank';
				$gateways[] = 'WC_Gateway_TTCK_Cake';
				$gateways[] = 'WC_Gateway_TTCK_Tnex';
				$gateways[] = 'WC_Gateway_TTCK_CIMBBank';
				$gateways[] = 'WC_Gateway_TTCK_DongABank';
				$gateways[] = 'WC_Gateway_TTCK_HSBC';
				$gateways[] = 'WC_Gateway_TTCK_BaovietBank';
				$gateways[] = 'WC_Gateway_TTCK_OceanBank';
				$gateways[] = 'WC_Gateway_TTCK_VietABank';
				$gateways[] = 'WC_Gateway_TTCK_VietBank';
				$gateways[] = 'WC_Gateway_TTCK_SaigonBank';
				$gateways[] = 'WC_Gateway_TTCK_Kienlongbank';
				$gateways[] = 'WC_Gateway_TTCK_PVcomBank';
				$gateways[] = 'WC_Gateway_TTCK_PulicBank';
				$gateways[] = 'WC_Gateway_TTCK_VRBank';
				
				$gateways[] = 'WC_Gateway_TTCK_ViettelPay';
				//$gateways[] = 'WC_Gateway_TTCK_Moca';
				$gateways[] = 'WC_Gateway_TTCK_Momo';
				//$gateways[] = 'WC_Gateway_QHpay_Shopeepay';
				//$gateways[] = 'WC_Gateway_QHpay_Smartpay';
				$gateways[] = 'WC_Gateway_TTCK_VIB';
				$gateways[] = 'WC_Gateway_TTCK_Vinid';
				$gateways[] = 'WC_Gateway_TTCK_Vnpay';
				//$gateways[] = 'WC_Gateway_QHpay_Zalopay';

				#$gateways[] = 'WC_Gateway_QHpay_VNPTPay';
				//$gateways[] = 'WC_Gateway_QHpay_MobiFonePay';
				//$gateways[] = 'WC_Gateway_QHpay_Vtcpay';
				//$gateways[] = 'WC_Gateway_QHpay_Vimo';
				

				/*foreach ($settings['bank_transfer_accounts'] as $account) {
					#if (strtolower($account['bank_name']) == 'momo')
						
					#if (strtolower($account['bank_name']) == 'acb')
						
					#if (strtolower($account['bank_name']) == 'mbbank')
						
					#if (strtolower($account['bank_name']) == 'techcombank')
						
					#if (strtolower($account['bank_name']) == 'timoplus')
						
					#if (strtolower($account['bank_name']) == 'vpbank')
						
					#if (strtolower($account['bank_name']) == 'vietinbank')
						
					#if (strtolower($account['bank_name']) == 'ocb')
						
					#if (strtolower($account['bank_name']) == 'tpbank')
						
					#if (strtolower($account['bank_name']) == 'vietcombank')
						
					#if (strtolower($account['bank_name']) == 'bidv')
						
					#if (strtolower($account['bank_name']) == 'agribank')
						
				}*/
				// print_r ($gateways);
				return $gateways;
			});
		}
	}
	public function notice_if_not_woocommerce()
	{
		$class = 'notice notice-warning';
		$name = basename(__DIR__);
		$message = $name.' '.__(
			'not running because WooCommerce is not active. Please activate both plugins.',
			'ttck'
		);
		printf('<div class="%1$s"><p><strong>%2$s</strong></p></div>', $class, $message);
	}
	static function get_settings()
	{
		$settings = get_option('ttck', self::$default_settings);
		$settings = wp_parse_args($settings, self::$default_settings);
		return $settings;
	}
	static function update_settings(array $data) {
		if(!empty($data)) update_option('ttck', $data);
	}
	static function oauth_get_settings()
	{
		$settings = get_option('ttck_oauth', self::$oauth_settings);
		$settings = wp_parse_args($settings, self::$oauth_settings);
		return $settings;
	}
	static function get_bank_icon($name, $img=false) {
		//if(true || is_dir(TTCK_DIR.'/assets/'.$name.'.png')) return; 
		$url = TTCK_URL.'/assets/'.strtolower($name).'.png';
		return $img? '<img class="ttck-bank-icon" title="'.strtoupper($name).'" src="'.$url.'"/>': $url;
	}
	static function noQRBankLogo($name) {
		return !in_array($name, ['momo','viettelpay']);
	}
	static function get_list_banks()
	{
		$banks = array(
			'acb' => 'ACB',
			'bidv' => 'BIDV',
			'mbbank' => 'MB Bank',
			'momo' => 'Momo',
			'ocb' => 'OCB',
			'timoplus' => 'Timo Plus',
			'tpbank' => 'TPBank',
			'vietcombank' => 'Vietcombank',
			'vpbank' => 'VPBank',
			'vietinbank' => 'Vietinbank',
			'techcombank' => 'Techcombank',
			'agribank' => 'Agribank',
			'viettelpay'=> 'ViettelPay',
			'hdbank'=> 'HDBank',
			'moca'=> 'Moca',
			'msb'=> 'MSB',
			'sacombank'=> 'Sacombank',
			'shb'=> 'SHB',
			'shopeepay'=> 'ShopeePay',
			'smartpay'=> 'SmartPay',
			'vib'=> 'VIB',
			'vinid'=> 'VinID',
			'vnpay'=> 'VNPay',
			'zalopay'=> 'ZaloPay',
		);
		return $banks;
	}

	static function get_list_bin()
	{
		$banks = array(
			'970416' => 'acb',
			'970418' => 'bidv',
			'970422' => 'mbbank',
			'970448' => 'ocb',
			'970454' => 'timoplus',
			'970423' => 'tpbank',
			'970436' => 'vietcombank',
			'970432' => 'vpbank',
			'970415' => 'vietinbank',
			'970407' => 'techcombank',
			'970405' => 'agribank',
			'970449' => 'lvp',
			'970437'=> 'hdbank',
			'970426'=> 'msb',
			'970429'=> 'sacombank',
			'970443'=> 'shb',
			'970441'=> 'vib',
			'970425' => 'abbank',
			'970409' => 'bacabank',
			'970438' => 'baovietbank',
			'422589' => 'cimbbank',
			'970406' => 'dongabank',
			'970431' => 'eximbank',
			'458761' => 'hsbc',
			'970452' => 'kienlongbank',
			'970422' => 'mbbank',
			'970428' => 'namabank',
			'970419' => 'ncb',
			'970414' => 'oceanbank',
			'970439' => 'pulicbank',
			'970412' => 'pvcombank',
			'970400' => 'saigonbank',
			'970429' => 'scb',
			'970440' => 'seabank',
			'970423' => 'tpbank',
			'970427' => 'vietabank',
			'970433' => 'vietbank',
			'970454' => 'vietcapitalbank',
			'970421' => 'vrbank',
		);
		return $banks;
	}
	static function connect_status_banks()
	{
		$status = array(
			'0' => __('Inactive', 'thanh-toan-chuyen-khoan'),
			'1' =>  array(
				'0' => __('Active', 'thanh-toan-chuyen-khoan'),
				'1' => __('Trial', 'thanh-toan-chuyen-khoan'),
				'2' => __('Out of money', 'thanh-toan-chuyen-khoan')
			)
		);
		return $status;
	}
	static function transaction_text($code, $settings) {
		if($settings==null) $settings = self::get_settings();
		$texts = !empty($settings['bank_transfer']['extra_text'])? $settings['bank_transfer']['extra_text']: '';
		if($texts) {
			$texts = array_filter(explode("\n", $texts));
			if(count($texts)) {
				return $texts[array_rand($texts)].' '. $code;
				//return (array_rand([1,0])==1)? $text. ' '. $code : $code.' '.$text;
			}
		}
		return $code;
	}

	public function add_settings_link($links)
	{
		$settings = array('<a href="' . admin_url('admin.php?page=ttck') . '">' . __('Settings', 'thanh-toan-chuyen-khoan') . '</a>');
		$links    = array_reverse(array_merge($links, $settings));

		return $links;
	}

	//run by webhook
	public function pc_payment_handler()
	{
		$txtBody = file_get_contents('php://input');
		$jsonBody = json_decode($txtBody); //convert JSON into array
		if (!$txtBody || !$jsonBody) {
			wp_send_json(['error'=>"Missing body"]) ;
			die();
		}
		if (isset($jsonBody->error) && $jsonBody->error != 0) {
			wp_send_json(['error'=> "An error occurred"]);
			die();
		}
		$header = ttck_getHeader();
		$token = isset($header["Secure-Token"])? $header["Secure-Token"]: '';
		if (strcasecmp($token, $this->settings['bank_transfer']['secure_token']) !== 0) {
			wp_send_json(['error'=> "Missing secure_token or wrong secure_token"]);
			die();
		}
		$result = ['msg'=>[],'error'=>1,'rawInput'=> $txtBody];
		$bankMsg = "";
		$domain = parse_url(home_url(),PHP_URL_HOST);

		if(!empty($jsonBody->data))
		foreach ($jsonBody->data as $key => $transaction) {
			$result['_ok']=1;	//detect webhook ok
			$des = $transaction->description;
			if(ttck_is_JSON($des)) {
				$desJson = is_string($des)? json_decode($des, true): $des;
				if(is_array($desJson)) {
					if(isset($desJson['code'])) {
						$des = $desJson['code'];
						//$update['bank_transfer']['code'] = $desJson['code'];
					}
					//if(isset($desJson['app'])) $update['bank_transfer']['app'] = $desJson['app'];
				}
			}
			//message for telegram: Amount: %s\nDesc: %s\nDate: %s
			$bankMsg = sprintf("Thông báo giao dịch:\nTrang web: %s\nSố tiền: %s\nMã: %s\nTin nhắn: %s",
					$domain,
					number_format($transaction->amount),
					ttck_parse_code($des,$this->settings['bank_transfer']['transaction_prefix'], $this->settings['bank_transfer']['case_insensitive']), 
					$transaction->description//$transaction->when
				);
			$order_id = ttck_parse_order_id($des, $this->settings['bank_transfer']['transaction_prefix'], $this->settings['bank_transfer']['case_insensitive']);
			if (is_null($order_id)) {
				wp_send_json (['error'=>"Order ID not found from transaction content: " . $des . "\n"]);
				continue;
			}
			//echo ("Start processing orders with transaction code " . $order_id . "...\n");
			$order = wc_get_order($order_id);
			if (!$order) {
				continue;
			}
			if($order->get_status()=='completed') {
				$result['error']=0;
				$result['msg'][]= ("Transaction processed before " . $order_id . " success\n");
				break;
			}
			//echo(var_dump(wc_get_order_statuses()));
			$money = $order->get_total();
			$paid = $transaction->amount;
			/*$today = date_create(date("Y-m-d"));
			$date_transaction = date_create($transaction->when);
			$interval = date_diff($today, $date_transaction);
			if ($interval->format('%R%a') < -2) {
				# code...Giao dịch quá cũ, không xử lý
				wp_send_json (['error'=>__('Transaction is too old, not processed', 'thanh-toan-chuyen-khoan')]);
				die();
			}*/
			$total = number_format($transaction->amount, 0);
			$order_note = "QH Testpay thông báo nhận <b>{$total}</b> VND, nội dung <B>{$des}</B> chuyển vào <b>STK {$transaction->subAccId}</b>";
			$order->add_order_note($order_note);
			$order->update_meta_data('ttck_ndck', $des);

			// $order_note_overpay = " thông báo <b>{$total}</b> VND, nội dung <b>$des</b> chuyển khoản dư vào <b>STK {$transaction->subAccId}</b>";
			$acceptable_difference = abs($this->settings['bank_transfer']['acceptable_difference']);
			if ($paid < ($money  - $acceptable_difference>0? $money  - $acceptable_difference: $money )) {
				$order->add_order_note(__('The order is underpaid so it is not completed', 'thanh-toan-chuyen-khoan'));
				$status_after_underpaid = $this->settings['order_status']['order_status_after_underpaid'];

				if ($status_after_underpaid && $status_after_underpaid != "wc-default") {
					$status = substr($this->settings['order_status']['order_status_after_underpaid'], 3);
					$order->update_status($status);
				}
				$result['error']=1;
				$result['msg'][] = __('The order is underpaid so it is not completed', 'thanh-toan-chuyen-khoan');

			} else {
				$order->payment_complete();
				wc_reduce_stock_levels($order_id);
				$status_after_paid = $this->settings['order_status']['order_status_after_paid'];

				if ($status_after_paid && $status_after_paid != "wc-default") {
					$order->update_status($status_after_paid);
				}
				//NEU THANH TOAN DU THI GHI THEM 1 cai NOTE 
				if ($paid > $money + $acceptable_difference) {
					$order->add_order_note(__('Order has been overpaid', 'thanh-toan-chuyen-khoan'));
					$result['msg'][] = __('Order has been overpaid', 'thanh-toan-chuyen-khoan');
				}
				$result['error']=0;
				$result['msg'][]= ("Transaction processing  " . $order_id . " success\n");
			}
			
			//$result['success']=1;
			$order->save();
			if(empty($result['error'])) break;
		}
		//telegram bot
		if(!empty($this->settings['telegram_token']) && !empty($this->settings['telegram_chatid'])) {
			$token = trim($this->settings['telegram_token']);
			$chatid = trim($this->settings['telegram_chatid']);
			$text = substr($bankMsg,0,4000);//$jsonBody->data? json_encode($jsonBody->data):'[]';
			if(substr( $token, 0, 3 ) != "bot") $token='bot'.$token;
			$response = wp_remote_get( "https://api.telegram.org/{$token}/sendMessage?chat_id={$chatid}&text=".urlencode($text), array(
			    'timeout'     => 120,
			    'httpversion' => '1.1',
			    'headers' => array(
			      )
			));
			#$result['msg'][] = wp_remote_retrieve_body( $response );
		}
		//other webhook
		if(!empty($this->settings['webhook']) && filter_var($this->settings['webhook'], FILTER_VALIDATE_URL)) {
			$resp = wp_remote_post ($this->settings['webhook'], [
				'method' => 'POST',
				'timeout' => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking' => false,//true
				'headers' => array('Content-Type: application/json'),
				'sslverify' => false,
				'body'=> $txtBody,//json_decode($txtBody,true)
			]);
			if ( is_wp_error( $resp ) ) $result['msg'][] = $resp->get_error_message();
		}
		//end
		$result['msg'] = join(". ", $result['msg']);
		wp_send_json($result);
		die();
		//TODO: Nghiên cứu việc gửi mail thông báo đơn hàng thanh toán hoàn tất.
	}
	
	function load_plugin_textdomain()
	{
		load_plugin_textdomain($this->domain, false, dirname(plugin_basename(__FILE__))  . '/languages');
	}
}
new TTCKPayment();
