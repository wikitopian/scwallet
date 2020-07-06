<?php

use EthereumRPC\EthereumRPC;

class SCWallet_Crypto {

	public  $online;

	private $geth;
	private $erc20;
	private $dai;

	public function __construct() {
		$this->online = false;

		try {
			$this->geth  = new EthereumRPC( '127.0.0.1', 8545 );
			$this->erc20 = new \ERC20\ERC20($this->geth);
			$this->dai   = $this->erc20->token( '0x6b175474e89094c44da98b954eedeac495271d0f' );

			$this->online = true;
		} catch( Exception $e ) {
			error_log( print_r( $e, true ) );
			$this->online = false;
			return;
		}

	}

	public function is_online() {
		return $this->online;
	}

	public function get_balance( $address_rx ) {
		try {
			return $this->dai->balanceOf( $address_rx );
		} catch( Exception $e ) {
			error_log( print_r( $e, true ) );
			$this->online = false;
			return false;
		}
	}

	public function get_gas_balance( $address ) {

		try {
		$eth = $this->geth->eth();
		return $eth->getBalance( $address ) * bcpow( 10, 9 );
		} catch( Exception $e ) {
			error_log( print_r( $e, true ) );
			$this->online = false;
			return false;
		}


	}

	public function create_address( $password ) {

		try {
		$personal = $this->geth->personal();
		$output = $personal->newAccount( $password );

		return $output;
		} catch( Exception $e ) {
			error_log( print_r( $e, true ) );
			$this->online = false;
			return false;
		}
	}

}

/* EOF */
