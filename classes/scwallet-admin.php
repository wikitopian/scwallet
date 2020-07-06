<?php

class SCWallet_Admin {

	private $settings;

	public function __construct() {

		$this->settings = get_option( 'scwallet' );

		add_action( 'admin_init', array( &$this, 'do_save' ) );

		add_action( 'admin_menu', array( &$this, 'do_menu' ) );

	}

	public function do_save() {

		if( !isset( $_REQUEST['scwallet-verify'] ) ) {
			return;
		}

		if( !wp_verify_nonce( $_REQUEST['scwallet-verify'], 'scwallet-verify' ) ) {
			return;
		}

		$settings = $_REQUEST['scwallet'];

		foreach( $settings as $setting => $value ) {
			$this->settings[$setting] = $value;
		}

		update_option( 'scwallet', $this->settings );

	}

	public function do_menu() {

		add_options_page(
			'Stablecoin Wallet',
			'SC Wallet',
			'manage_options',
			'scwallet-admin',
			array( &$this, 'do_menu_page' ),
			100
		);

	}

	public function do_menu_page() {

		$nonce = wp_create_nonce( 'scwallet-verify' );

		$submit = get_submit_button();

		echo <<<FORM

<div class="wrap">

	<h2>Stablecoin Wallet</h2>

	<form method="POST">
		<input type="hidden" name="scwallet-verify" value="{$nonce}" />

		<table>

			<tbody>

FORM;

		$this->do_setting( 'Debug', 'debug', true );

		$this->do_setting( 'Ethereum Host', 'host' );
		$this->do_setting( 'Ethereum Port', 'port' );

		echo <<<FORM

			</tbody>

		</table>

		{$submit}

	</form>

</div>

FORM;

	}

	public function do_setting( $title, $setting, $bool = false ) {

		echo <<<SETTING

<tr>

	<th>
		{$title}
	</th>

	<td>

SETTING;

	if( $bool ) {

		if( $this->settings[$setting] ) {
			$checked = ' CHECKED="CHECKED" ';
		} else {
			$checked = ' ';
		}

		echo <<<SETTING

		<input
			type="hidden"
			name="scwallet[{$setting}]"
			value="0"
			/>

		<input
			type="checkbox"
			name="scwallet[{$setting}]"
			value="1"
			{$checked}
			/>

SETTING;

	} else {

		echo <<<SETTING

		<input
			type="text"
			name="scwallet[{$setting}]"
			value="{$this->settings[$setting]}"
			/>

SETTING;

	}

		echo "</td>\n</tr>\n";

	}

}

/* EOF */
