<?php

class SCWallet_Db {

	public static function do_install() {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = <<<SQL

CREATE TABLE IF NOT EXISTS {$wpdb->prefix}scwallet_tx (
	id mediumint(9) NOT NULL AUTO_INCREMENT,
	time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	id_from mediumint(9) NOT NULL,
	id_to mediumint(9) NOT NULL,
	tx_type VARCHAR(20) NOT NULL,
	amount  DECIMAL(11,2) DEFAULT 0.00,
	PRIMARY KEY  (id),
	INDEX user_balance (id_from, id_to)
) $charset_collate;

SQL;

		dbDelta( $sql );

		$sql = <<<SQL

CREATE TABLE IF NOT EXISTS {$wpdb->prefix}scwallet_notes (
	id mediumint(9) NOT NULL AUTO_INCREMENT,
	id_tx mediumint(9) NOT NULL,
	tx_id tinytext,
	note tinytext,
	PRIMARY KEY  (id),
	INDEX user_note (id, id_tx)
) $charset_collate;

SQL;

		dbDelta( $sql );


		$sql = <<<SQL

CREATE TABLE IF NOT EXISTS {$wpdb->prefix}scwallet_dai_addresses (
	id mediumint(9) NOT NULL AUTO_INCREMENT,
	user_id mediumint(9) NOT NULL,
	time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	address VARCHAR(45) NOT NULL,
	password VARCHAR(20) NOT NULL,
	PRIMARY KEY  (id),
	INDEX user_tokens (user_id, address)
) $charset_collate;

SQL;

		dbDelta( $sql );

		$sql = <<<SQL

CREATE TABLE IF NOT EXISTS {$wpdb->prefix}scwallet_dai_balance (
	id mediumint(9) NOT NULL AUTO_INCREMENT,
	time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	address VARCHAR(45) NOT NULL,
	balance DECIMAL(11,2) DEFAULT 0.00,
	PRIMARY KEY  (id),
	INDEX addresses (address)
) $charset_collate;

SQL;

		dbDelta( $sql );

	}

	public static function get_balance( $user_id = false ) {
		global $wpdb;

		if( !$user_id ) {
			return -1;
		}

		if( !is_int( $user_id ) ) {
			return -1;
		}

		$sql = <<<SQL

SELECT
	SUM(fc.amount) AS amount
	FROM {$wpdb->prefix}scwallet_tx AS fc
	WHERE fc.id_to = '{$user_id}';

SQL;

		$credits = $wpdb->get_var( $sql ) + 0.0;

		$sql = <<<SQL

SELECT
	SUM(fc.amount) AS amount
	FROM {$wpdb->prefix}scwallet_tx AS fc
	WHERE fc.id_from = '{$user_id}';

SQL;

		$debits = $wpdb->get_var( $sql ) + 0.0;


		return $credits - $debits;
	}

	public static function do_deposit( $user_id, $amount ) {
		global $wpdb;

		if( !is_float( $amount ) ) {
			return false;
		}

		$user = new WP_User ( $user_id );

		if( !$user->exists() ) {
			return false;
		}

		$table = $wpdb->prefix . 'scwallet_tx';

		$format = array(
			'time'    => '%s',
			'id_from' => '%d',
			'id_to'   => '%d',
			'tx_type' => '%s',
			'amount'  => '%f',
		);

		$tx_insert = array(
			'time'    => current_time( 'mysql', 1 ),
			'id_from' => -1,
			'id_to'   => $user_id,
			'tx_type' => 'deposit',
			'amount'  => floatval( $amount ),
		);

		if( !$wpdb->insert( $table, $tx_insert, $format ) ) {
			return false;
		}

		return true;
	}

	public static function do_transfer( $tx_type, $tx_id, $amount, $from_id, $to_id, $note ) {

		if( !is_float( $amount ) ) {
			return false;
		}

		if( $amount <= 0.0 ) {
			return false;
		}

		$amount = scwallet::do_money_clean( $amount );

		$from = new WP_User ( $from_id );

		if( $from_id > 0 && !$from->exists() ) {
			return false;
		}

		if( $to_id >= 0 ) {

			$to = new WP_User ( $to_id );

			if( !$to->exists() ) {
				return false;
			}

		}

		if( round( $amount * 100 ) > round( self::get_balance( $from_id ) * 100 ) ) {
			return false;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'scwallet_tx';

		$format = array(
			'time'    => '%s',
			'id_from' => '%d',
			'id_to'   => '%d',
			'tx_type' => '%s',
			'amount'  => '%f',
		);

		$tx_insert = array(
			'time'    => current_time( 'mysql', 1 ),
			'id_from' => intval( $from_id ),
			'id_to'   => intval( $to_id ),
			'tx_type' => $tx_type,
			'amount'  => floatval( $amount ),
		);

		if( !$wpdb->insert( $table, $tx_insert, $format ) ) {
			return false;
		}

		$id_tx = $wpdb->insert_id;

		$table = $wpdb->prefix . 'scwallet_notes';

		$format = array(
			'id_tx' => '%s',
			'tx_id' => '%s',
			'note'  => '%s',
		);

		$tx_insert = array(
			'id_tx' => $id_tx,
			'tx_id' => $tx_id,
			'note'  => $note,
		);

		$wpdb->insert( $table, $tx_insert, $format );

		if( 0 > self::get_balance( $from_id ) ) {

			$table = $wpdb->prefix . 'scwallet_tx';
			$wpdb->delete( $table, array( 'id' => $id_tx ) );

			return false;
		}


		return true;
	}

	public static function get_history_count( $user_id = false ) {
		global $wpdb;

		if( !$user_id ) {
			return -1;
		}

		if( !is_int( $user_id ) ) {
			return -1;
		}

		$sql = <<<SQL

SELECT
	COUNT(*) AS txs
	FROM {$wpdb->prefix}scwallet_tx AS fc
	WHERE fc.id_from = '{$user_id}'
	   OR fc.id_to   = '{$user_id}';

SQL;

		return $wpdb->get_var( $sql ) + 0.0;

	}

	public static function get_history( $user_id = false, $count, $offset ) {
		global $wpdb;

		if( !$user_id ) {
			return -1;
		}

		if( !is_int( $user_id ) ) {
			return -1;
		}

		if( !is_numeric( $count ) || !is_numeric( $offset ) ) {
			return -1;
		}

		if( $count <= 0 || $offset < 0 ) {
			return -1;
		}

		$sql = <<<SQL

SELECT
	fc.id,
	fc.time,
	fc.tx_type,
	fr.ID as id_from,
	fr.user_login AS name_from,
	tx.ID as id_to,
	tx.user_login   AS name_to,
	fc.amount,
	nt.note,
	nt.tx_id AS order_id
	FROM {$wpdb->prefix}scwallet_tx AS fc
	LEFT JOIN {$wpdb->prefix}users AS fr
	ON   fc.id_from = fr.ID
	LEFT JOIN {$wpdb->prefix}users AS tx
	ON   fc.id_to   = tx.ID
	LEFT JOIN {$wpdb->prefix}scwallet_notes AS nt
	ON   fc.id = nt.id_tx
	WHERE fc.id_from = '{$user_id}'
	   OR fc.id_to   = '{$user_id}'
	ORDER BY fc.time DESC
	LIMIT {$count} OFFSET {$offset};

SQL;

		return $wpdb->get_results( $sql );

	}

	public static function get_csv( $user_id = false ) {
		global $wpdb;

		if( !$user_id ) {
			return -1;
		}

		if( !is_int( $user_id ) ) {
			return -1;
		}

		$sql = <<<SQL

SELECT
	fc.id,
	fc.time,
	fc.tx_type,
	fr.user_login AS name_from,
	tx.user_login AS name_to,
	fc.amount,
	nt.tx_id,
	nt.note
	FROM {$wpdb->prefix}scwallet_tx AS fc
	LEFT JOIN {$wpdb->prefix}users AS tx
	ON   fc.id_to   = tx.ID
	LEFT JOIN {$wpdb->prefix}users AS fr
	ON   fc.id_from = fr.ID
	LEFT JOIN {$wpdb->prefix}scwallet_notes AS nt
	ON   fc.id = nt.id_tx
	WHERE fc.id_from = '{$user_id}'
	   OR fc.id_to   = '{$user_id}'
	ORDER BY fc.time DESC;

SQL;

		return $wpdb->get_results( $sql );

	}

	public static function set_address( $user_id, $address, $password ) {

		global $wpdb;

		$table = $wpdb->prefix . 'scwallet_dai_addresses';

		$format = array(
			'time'     => '%s',
			'user_id'  => '%d',
			'address'  => '%s',
			'password' => '%s',
		);

		$tx_insert = array(
			'time'     => current_time( 'mysql', 1 ),
			'user_id'  => $user_id,
			'address'  => $address,
			'password' => $password,
		);

		if( !$wpdb->insert( $table, $tx_insert, $format ) ) {
			return false;
		}

		return true;
	}

	public static function get_address( $user_id ) {

		global $wpdb;

		if( !$user_id ) {
			return [];
		}

		if( !is_int( $user_id ) ) {
			return [];
		}

		$sql = <<<SQL

SELECT
	dai.address
	FROM {$wpdb->prefix}scwallet_dai_addresses AS dai
	WHERE dai.user_id = '{$user_id}'
	ORDER BY dai.time DESC
	LIMIT 1;

SQL;

		return $wpdb->get_var( $sql );
	}


	public static function get_addresses( $user_id ) {

		global $wpdb;

		if( !$user_id ) {
			return [];
		}

		if( !is_int( $user_id ) ) {
			return [];
		}

		$sql = <<<SQL

SELECT
	dai.user_id,
	dai.address
	FROM {$wpdb->prefix}scwallet_dai_addresses AS dai
	WHERE dai.user_id = '{$user_id}'
	ORDER BY dai.time DESC

SQL;

		return $wpdb->get_results( $sql );
	}

	public static function get_dai_balance( $user_id = false ) {
		global $wpdb;

		if( !$user_id ) {
			return [];
		}

		if( !is_int( $user_id ) ) {
			return [];
		}

		$sql = <<<SQL

SELECT
	SUM(bal.balance)
	FROM {$wpdb->prefix}scwallet_dai_addresses AS adr
	LEFT JOIN (
		SELECT
			MAX(mxt_bal.time) AS time,
			mxt_bal.address
			FROM {$wpdb->prefix}scwallet_dai_balance AS mxt_bal
			GROUP BY mxt_bal.address
	) AS mxt
	ON  adr.address = mxt.address
	LEFT JOIN {$wpdb->prefix}scwallet_dai_balance AS bal
	ON  adr.address = bal.address
	AND mxt.time    = bal.time
	WHERE adr.user_id = '{$user_id}'

SQL;

		return $wpdb->get_var( $sql ) + 0.0;


	}

	public static function set_dai_balance( $user_id, $addresses ) {
		global $wpdb;

		foreach( $addresses as $address ) {

			$table = $wpdb->prefix . 'scwallet_dai_balance';

			$format = array(
				'time'    => '%s',
				'address' => '%s',
				'balance' => '%f',
			);

			$address->balance *= 100;
			$address->balance = floor( $address->balance );
			$address->balance /= 100;

			$tx_insert = array(
				'time'    => current_time( 'mysql', 1 ),
				'address' => $address->address,
				'balance' => $address->balance,
			);

			if( !$wpdb->insert( $table, $tx_insert, $format ) ) {
				return false;
			}

		}

	}

}

/* EOF */
