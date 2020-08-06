<?php

class SCWallet_Bank {

	private $scwallet;

	public function __construct( $scwallet ) {
		$this->scwallet = $scwallet;
	}

	public static function get_address( $user_id = false ) {

		if( !$user_id ) {
			$user_id = get_current_user_id();
		}

		return scwallet_Db::get_address( $user_id );
	}

	public static function get_balance( $user_id = false ) {

		if( !$user_id ) {
			$user_id = get_current_user_id();
		}

		$crypto = new SCWallet_Crypto();

		$dai_addresses = SCWallet_Db::get_addresses( $user_id );

		// create a a new deposit address if they're empty
		if( empty( $dai_addresses ) ) {

			$password = bin2hex( openssl_random_pseudo_bytes( 6 ) );

			// TODO
			//$address_new = $crypto->create_address( $password );
			$address_new = '0x52e9e064Cec195d976d4f1E115B9Dcf77Fc921DB';

			if( !empty( $address_new ) ) {
				SCWallet_Db::set_address( $user_id, $address_new, $password );
				$dai_addresses = scwallet_Db::get_addresses( $user_id );
			} else {
				error_log( 'create_address fail' );
				return 0;
			}

		} else {
			$dai_addresses = scwallet_Db::get_addresses( $user_id );
		}

		$crypto_balance = 0.0;
		foreach( $dai_addresses as &$dai_address ) {
			$crypto_balance += $crypto->get_balance( $dai_address->address );
			$dai_address->balance = $crypto_balance;
		}

		// protect from an office space attack
		$crypto_balance *= 100;
		$crypto_balance  = floor( $crypto_balance );
		$crypto_balance /= 100;

		// if geth > db, record a new deposit transaction

		$old_balance = scwallet_Db::get_dai_balance( $user_id );
		if( $crypto_balance > $old_balance ) {
			// add deposit tx

			$diff = $crypto_balance - $old_balance;

			scwallet_Db::do_deposit( $user_id, $diff );

			scwallet_Db::set_dai_balance( $user_id, $dai_addresses );
		}

		return scwallet_Db::get_balance( $user_id );

	}

	public static function get_gas_balance( $user_id = false ) {

		if( !$user_id ) {
			$user_id = get_current_user_id();
		}

		$crypto = new SCWallet_Crypto();

		$dai_addresses = scwallet_Db::get_addresses( $user_id );

		// create a a new deposit address if they're empty
		if( empty( $dai_addresses ) ) {

			$password = bin2hex( openssl_random_pseudo_bytes( 6 ) );

			// TODO
			//$address_new = $crypto->create_address( $password );
			$address_new = '0x52e9e064Cec195d976d4f1E115B9Dcf77Fc921DB';

			if( !empty( $address_new ) ) {
				scwallet_Db::set_address( $user_id, $address_new, $password );
				$dai_addresses = scwallet_Db::get_addresses( $user_id );
			} else {
				error_log( 'create_address fail' );
				return 0;
			}

		} else {
			$dai_addresses = scwallet_Db::get_addresses( $user_id );
		}

		$gas_balance = 0.0;
		foreach( $dai_addresses as &$dai_address ) {
			$gas_balance += $crypto->get_gas_balance( $dai_address->address );
			$dai_address->gas_balance = $gas_balance;
		}

		return $gas_balance;
	}

	public static function get_history( $user_id = false, $count, $offset ) {

		if( !$user_id ) {
			$user_id = get_current_user_id();
		}

		return scwallet_Db::get_history( $user_id, $count, $offset );

	}

	public static function get_tx_count( $user_id = false ) {

		if( !$user_id ) {
			$user_id = get_current_user_id();
		}

		return scwallet_Db::get_history_count( $user_id );
	}

	public function do_transfer( $id_from, $id_to, $amount, $note ) {

		return scwallet_Db::do_transfer(
			'transfer',
			md5( $id_from.'-'.$id_to.'-'.$amount.'-'.$note ),
			$amount,
			$id_from,
			$id_to,
			$note
		);
	}

	public function do_order( $order_id, $amount = 0.0, $id_from ) {

		return scwallet_Db::do_transfer(
			'order',
			$order_id,
			floatval( $amount ),
			$id_from,
			-1,
			''
		);

	}

	public function get_csv( $id ) {

		$data_raw = scwallet_Db::get_csv( $id );

		$output = fopen( 'php://temp', 'r+' );

		fputcsv( $output, array( 'ID', 'DateTime', 'Type', 'From', 'To', 'Amount', 'TX_ID', 'Note' ) );

		foreach( $data_raw as $key => $value ) {

			fputcsv( $output, array(
				$value->id,
				$value->time,
				$value->tx_type,
				$value->name_from,
				$value->name_to,
				$value->amount,
				$value->tx_id,
				$value->note,
			) );

		}

		rewind( $output );
		$data = stream_get_contents( $output );

		return $data;
	}

}

/* EOF */
