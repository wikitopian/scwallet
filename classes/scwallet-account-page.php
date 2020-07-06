<?php

require_once( 'scwallet-history-table.php' );

class SCWallet_Account_Page {

	private $scwallet;

	public function __construct( $scwallet ) {

		$this->scwallet = $scwallet;

		add_filter( 'heartbeat_settings', array( &$this, 'do_heartbeat_settings' ) );
		add_filter( 'heartbeat_received', array( &$this, 'do_heartbeat' ), 10, 2 );

		add_action( 'wp_ajax_scwallet_heartbeat', array( &$this,  'get_heartbeat' ) );

		add_action( 'wp_ajax_scwallet_history', array( &$this,  'get_history' ) );
		add_action( 'wp_ajax_scwallet_check_recipient', array( &$this, 'do_check_recipient' ) );
		add_action( 'wp_ajax_scwallet_transfer', array( &$this, 'do_transfer' ) );

		add_action( 'init', array( &$this, 'do_endpoint' ) );

		add_filter( 'query_vars', array( &$this, 'do_query_vars' ), 0 );

		add_filter( 'woocommerce_account_menu_items', array( &$this, 'do_menu_item' ) );

		add_action( 'woocommerce_account_scwallet_endpoint', array( &$this, 'do_page' ) );

	}

	public function do_heartbeat_settings( $settings ) {
		$settings['interval'] = 15;
		return $settings;
	}

	public function do_heartbeat( $response, $data ) {
		if( !isset( $data['scwallet_page'] ) ) {
			return $response;
		}

		$response['balance'] = $this->get_balance();

		$response['address'] = $this->scwallet->bank->get_address();
		$response['gas'] = $this->scwallet->bank->get_gas_balance();
		$response['gas_max'] = 10000000;

		$response['tx_count'] = $this->scwallet->get_tx_count( get_current_user_id() );

		return $response;
	}

	public function get_heartbeat() {
		check_ajax_referer( 'scwallet', 'nonce' );

		$data['scwallet_page'] = true;
		$response = $this->do_heartbeat( [], $data );

		echo wp_json_encode( $response );
		wp_die();
	}

	public function get_balance() {

		$user_id = get_current_user_id();

		return round( 100 * $this->scwallet->get_balance( $user_id ) );
	}

	public function get_address() {
		$user_id = get_current_user_id();

		return $this->scwallet->get_address( $user_id );
	}

	public function get_history() {
		check_ajax_referer( 'scwallet', 'nonce' );

		$status = array();

		$user_id = get_current_user_id();

		if( is_numeric( $_REQUEST['items_per_page'] ) ) {
			$items_per_page = floor( $_REQUEST['items_per_page'] );
		}

		if( is_numeric( $_REQUEST['page'] ) ) {
			$page = floor( $_REQUEST['page'] );
		}

		$history = new SCWallet_History_Table( $user_id, $items_per_page, $page );
		$status['tx-history'] = $history->get_table();
		$status['tx-history-page'] = $history->get_page();
		$status['tx-history-pages'] = $history->get_pages();

		echo wp_json_encode( $status );

		wp_die();
	}

	public function do_check_recipient() {
		check_ajax_referer( 'scwallet', 'nonce' );

		$status = array();
		$status['status'] = true;

		$status['rx']  =  $this->scwallet->do_verify_user( $_REQUEST['recipient'] );

		if( !$status['rx'] ) {
			$status['status'] = false;
			echo wp_json_encode( $status );
			wp_die();
		}

		$user = new WP_User( $status['rx'] );

		$status['user'] = $user->user_login;

		$status['avatar_url'] = get_avatar_url( $user->ID );

		echo wp_json_encode( $status );

		wp_die();
	}

	public function do_transfer() {
		check_ajax_referer( 'scwallet', 'nonce' );

		$status = array();

		$id_from = get_current_user_id();

		$to = $this->scwallet->do_verify_user( $_REQUEST['name_to'] );
		if( !$to ) {
			echo false;
			wp_die();
		}

		$to = new WP_User( $to );

		$amount = $this->scwallet->do_money_clean( floatval( $_REQUEST['amount'] / 100.0 ) );

		if( !$amount || $amount < 0.01 ) {
			echo false;
			wp_die();
		}

		echo $this->scwallet->do_transfer(
			$id_from,
			$to->ID,
			$amount,
			sanitize_textarea_field( $_REQUEST['note'] )
		);

		wp_die();
	}


	public function do_scripts() {

		if( !function_exists( 'is_user_logged_in' ) ) {
			return;
		}

		if( !is_user_logged_in() ) {
			return;
		}

		$dir = 			plugin_dir_url( __FILE__ );
		$dir = str_replace( 'classes/', '', $dir );

		$dir_path = 			plugin_dir_path( __FILE__ );
		$dir_path = str_replace( 'classes/', '', $dir_path );

		wp_enqueue_script(
			'scwallet',
			$dir . 'scripts/scwallet.js',
			array( 'jquery', 'jquery-ui-core', 'jquery-effects-core', 'heartbeat', ),
			$this->scwallet->version
		);

		$scwallet_js = array();
		$scwallet_js['ajaxurl'] = admin_url( 'admin-ajax.php' );
		$scwallet_js['nonce']   = wp_create_nonce( 'scwallet' );
		$scwallet_js['history_items_per_page'] = 10;

		$dai_abi = file_get_contents( $dir_path . 'scripts/dai-abi.json' );
		$dai_abi = json_decode( $dai_abi, true );
		$scwallet_js['dai_abi'] = $dai_abi;

		$scwallet_js['balance'] = $this->get_balance();

		wp_localize_script(
			'scwallet',
			'scwallet',
			$scwallet_js
		);

		wp_enqueue_script(
			'jquery-ui-qrcode',
			$dir . 'lib/jquery-qrcode/jquery.qrcode.min.js'
		);

		wp_enqueue_script(
			'ethers',
			$dir . 'lib/ethers.js/dist/ethers.min.js'
		);


		wp_enqueue_script(
			'scwallet-account-page',
			$dir . 'scripts/scwallet-account-page.js',
			array(
				'jquery-ui-qrcode',
				'ethers',
				'scwallet',
			),
			$this->scwallet->version
		);

	}

	public function do_endpoint() {
		add_rewrite_endpoint( 'scwallet', EP_ROOT | EP_PAGES );
	}

	public function do_query_vars( $vars ) {

		$vars[] = 'scwallet';

		return $vars;
	}

	public function do_menu_item( $items ) {

		unset( $items['dashboard'] );

		self::array_unshift_assoc( $items, 'scwallet',  'Store Credit' );
		self::array_unshift_assoc( $items, 'dashboard', 'Dashboard' );

		return $items;
	}

	public function do_page() {
		$this->do_scripts();

		$dir = 			plugin_dir_url( __FILE__ );
		$dir = str_replace( 'classes/', '', $dir );

		echo <<<PAGE

<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide scwallet">
	<span name="scwallet_balance" id="scwallet_balance"></span>
</p>

<div id="scwallet">

	<ul>

		<li>
			<a class="scwallet_deposit" name="scwallet_deposit" href="#scwallet_deposit">
				Deposit
			</a>
		</li>

		<li>
			<a class="scwallet_transfer" name="scwallet_transfer" href="#scwallet_transfer">
				Transfer
			</a>
		</li>

		<li>
			<a class="scwallet_withdraw" name="scwallet_withdraw" href="#scwallet_withdraw">
				Withdraw
			</a>
		</li>

	</ul>

	<div id="scwallet_deposit">

		<div id="scwallet_deposit_top">

			<img src="{$dir}images/dai-token.png" alt="DAI Token" title="DAI Token" />
			<div id="scwallet_deposit_qr"></div>
			<img src="{$dir}images/dai-token.png" alt="DAI Token" title="DAI Token" />

		</div>

		<img src="{$dir}/images/deposit-dai-tokens.png" class="metamask-enabled" id="metamask-deposit-tokens" />

		<span id="scwallet_deposit_address"></span><span id="scwallet_deposit_address_copy" title="Copy Address to Clipboard">&#xF0C5;</span>
		<input type="text" id="scwallet_deposit_address_field" name="scwallet_deposit_address_field" />

		<div id="scwallet_deposit_bottom">

			<div id="scwallet_gauges_gas">
				<span id="scwallet_gas_icon_deposit" title="Send $2 (USD) worth of Ethereum to help anonymize your transactions (optional)">&#xF52F;</span>

				<span title="Send $2 (USD) worth of Ethereum to help anonymize your transactions (optional)">
					Gas Gauge -- <span id="scwallet_gas"></span>
					<tiny><i>gwei</i></tiny>
				</span>

				<div id="scwallet_gauges_gas_bar_deposit" title="Send $2 (USD) worth of Ethereum to help anonymize your transactions (optional)"></div>
			</div>

		</div>

	</div>
	<div id="scwallet_transfer">

		<form id="scwallet_transfer">
			<table>

				<tr id="scwallet_transfer_fields">
					<td>

						Recipient:
						<input
							type="text"
							placeholder="@parrott"
							id="scwallet_transfer_rx"
							/>

					</td>
					<td>
						Amount:

						<input
							type="text"
							placeholder="$12.34"
							id="scwallet_transfer_amount"
							/>

					</td>
				</tr>

				<tr>
					<td colspan="2">
						<textarea id="scwallet_transfer_note" placeholder="Note..."></textarea>
					</td>
				</tr>

				<tr>
					<td colspan="2">
						<input type="submit" value="Transfer Store Credit" />
					</td>
				</tr>

				<tr>
					<td colspan="2">
						<span id="scwallet_transfer_status"></span>
					</td>
				</tr>

			</table>
		</form>

	</div>
	<div id="scwallet_withdraw">

		<form id="scwallet_withdraw">

			<div id="scwallet_withdraw_header">
				
			</div>

			<div id="scwallet_withdraw_address">
				<span>Address:</span>

				<input
					type="text"
					placeholder="$12.34"
					id="scwallet_withdraw_address"
					/>

			</div>

			<div id="scwallet_withdraw_amountgas">

				<div id="scwallet_withdraw_amount">
					<span>Amount:</span>

					<input
						type="text"
						placeholder="$12.34"
						id="scwallet_withdraw_amount"
						/>

				</div>

				<div id="scwallet_withdraw_gas">
					<span id="scwallet_gas_icon_withdraw" title="Send $2 (USD) worth of Ethereum to help anonymize your transactions (optional)">&#xF52F;</span>

					<span title="Send $2 (USD) worth of Ethereum to help anonymize your transactions (optional)">
						Gas Gauge -- <span id="scwallet_gas"></span>
						<tiny><i>gwei</i></tiny>
					</span>

					<div id="scwallet_gauges_gas_bar_withdraw" title="Send $2 (USD) worth of Ethereum to help anonymize your transactions (optional)"></div>

					<span id="scwallet_gas_refund">
						<input type="checkbox" name="scwallet_gas_refund" id="scwallet_gas_refund" />
						Gas Refund
					<span>

				</div>

			</div>

			<div id="scwallet_withdraw_subtotals">
				SUBTOTALS
			</div>

			<div id="scwallet_withdraw_submit">
				<input type="submit" value="Withdraw DAI Tokens" />
			</div>

		</form>


	</div>
</div>

	<fieldset class="scwallet_tx_history">
		<legend><h3>Transaction History</h3></legend>
			<div></div>
	</fieldset>

PAGE;

	}

	private static function array_unshift_assoc(&$arr, $key, $val) {
		$arr = array_reverse($arr, true);
		$arr[$key] = $val;
		$arr = array_reverse($arr, true);
		return count($arr);
	}

}

/* EOF */
