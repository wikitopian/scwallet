<?php
/*
 * Plugin Name: Stablecoin Wallet
 * Version: 20200705
 */

require_once( 'classes/scwallet-admin.php' );

require_once( 'classes/scwallet-db.php' );
require_once( 'classes/scwallet-bank.php' );
require_once( 'classes/scwallet-account-page.php' );
require_once( 'classes/scwallet-history-table.php' );

require_once( 'classes/scwallet-crypto.php' );
require_once( 'vendor/autoload.php' );

register_activation_hook( __FILE__, array( 'SCWallet_Db', 'do_install' ) );

class SCWallet {

	public $debug;
	public $version;
	public $settings;

	public  $admin;
	public  $bank;
	private $account_page;
	private $processor;

	public $dir;

	public function __construct( $debug = false, $version = '20190330' ) {
		$this->debug = $debug;

		$this->settings = get_option( 'scwallet' );

		if( !$this->settings ) {

			$this->settings =
			[
				'debug' => $this->debug,
				'host'  => '127.0.0.1',
				'port'  => '8545',
			];

		update_option( 'scwallet', $this->settings );

		}

		if( $this->debug ) {
			$version = rand( 14, 148814 );
		}
		$this->version = $version;

		$this->admin        = new SCWallet_Admin( $this );

		$this->bank         = new SCWallet_Bank( $this );

		$this->account_page = new SCWallet_Account_Page( $this );

		add_action( 'wp_enqueue_scripts',           array( &$this, 'do_scripts' ), 20 );
		add_action( 'plugins_loaded',               array( &$this, 'do_processor' ), 11 );
		add_filter( 'woocommerce_payment_gateways', array( &$this, 'add_processor' ) );
		add_action( 'template_redirect',            array( &$this, 'do_tx_download' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( &$this, 'add_processor_plugin_links' ) );

		add_filter( 'get_avatar', array( &$this, 'add_avatar_title' ) );

		add_filter( 'woocommerce_checkout_fields' , array( &$this, 'remove_billing_details' ) );

	}

	public function do_scripts() {

		if( !function_exists( 'is_user_logged_in' ) ) {
			return;
		}

		if( !is_user_logged_in() ) {
			return;
		}

		wp_enqueue_style(
			'scwallet',
			plugin_dir_url( __FILE__ ) . 'styles/scwallet.css',
			array(),
			$this->version
		);

		wp_enqueue_style(
			'jquery-ui',
			plugin_dir_url( __FILE__ ) . 'lib/jquery-ui/themes/base/all.css',
			array(),
			$this->version
		);


		wp_enqueue_script( 'jquery-ui-tabs' );
		wp_enqueue_script( 'jquery-ui-progressbar' );

	}

	public function add_processor( $gateways ) {
		$gateways[] = 'scwallet_Processor';
		return $gateways;
	}

	public function add_processor_plugin_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=scwallet' ) . '">' . 'Configure' . '</a>'
		);
		return array_merge( $plugin_links, $links );
	}

	public function do_processor() {
		require_once( 'classes/scwallet-processor.php' );

		$this->processor = new SCWallet_Processor( $this->debug, $this->version );
	}

	public function do_order( $order ) {
		return $this->bank->do_order( $order->get_id(), $order->get_total(), $order->get_customer_id() );
	}

	public function do_transfer( $id_from, $id_to, $amount, $note ) {

		return $this->bank->do_transfer( $id_from, $id_to, $amount, $note );
	}

	public function get_balance( $user_id = false ) {

		if( !$user_id ) {
			$user_id = get_current_user_id();
		}

		return $this->bank->get_balance( $user_id );
	}

	public function get_history( $count, $offset ) {

		return $this->bank->get_history( false, $count, $offset );
	}

	public function do_verify_user( $user ) {

		$user = str_replace( '@', '', $user );

		$user = new WP_User( $user );

		$current = wp_get_current_user();

		if( $user->ID == $current->ID ) {
			return 0;
		}

		if( !$user->exists() ) {
			return 0;
		}

		return $user->ID;

	}

	public static function do_money_clean( $amount ) {

		$amount = floatval( preg_replace( "/[^-0-9\.]/", "", $amount ) );

		return round( $amount, 2, PHP_ROUND_HALF_DOWN );
	}

	public function do_tx_download() {

		if ( $_SERVER['REQUEST_URI'] == '/scwallet/transactions.csv' ) {

			// verify logged in
			$user = wp_get_current_user();

			if( !$user->exists() ) {
				exit();
			}

			header( 'Content-type: application/x-msdownload', true, 200 );
			header( "Content-Disposition: attachment; filename={$user->user_login}_transactions.csv" );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );

			echo $this->bank->get_csv( $user->ID );

			exit();
		}

	}

	public function get_tx_count( $user_id ) {
		return $this->bank->get_tx_count( $user_id );
	}

	public function remove_billing_details( $fields ) {
		unset( $fields['billing']['billing_first_name'] );
		unset( $fields['billing']['billing_last_name'] );
		unset( $fields['billing']['billing_company'] );
		unset( $fields['billing']['billing_address_1'] );
		unset( $fields['billing']['billing_address_2'] );
		unset( $fields['billing']['billing_city'] );
		unset( $fields['billing']['billing_postcode'] );
		unset( $fields['billing']['billing_country'] );
		unset( $fields['billing']['billing_state'] );
		unset( $fields['billing']['billing_phone'] );
		unset( $fields['order']['order_comments'] );
		unset( $fields['billing']['billing_address_2'] );
		unset( $fields['billing']['billing_postcode'] );
		unset( $fields['billing']['billing_company'] );
		unset( $fields['billing']['billing_email'] );
		unset( $fields['billing']['billing_city'] );
		return $fields;
	}

	function add_avatar_title( $text ) {
		$text = preg_replace( "/alt='([^']+)'/", "alt='$1' title='$1'" , $text );

		return $text;
	}

}

$scwallet = new SCWallet( true );

/* EOF */
