<?php

class SCWallet_Processor extends WC_Payment_Gateway {

	public function __construct() {

		$this->id                 = 'scwallet';
		$this->icon               = apply_filters('woocommerce_offline_icon', '');
		$this->has_fields         = false;
		$this->method_title       = 'Stablecoin Wallet';
		$this->method_description = 'Pay with Stablecoin Wallet';

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions', $this->description );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

		// Customer Emails
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}


	/**
	 * Initialize Gateway Settings Form Fields
	 */
	public function init_form_fields() {

		$this->form_fields = apply_filters( 'wc_scwallet_form_fields', array(

			'enabled' => array(
				'title'   => 'Enable/Disable',
				'type'    => 'checkbox',
				'label'   => 'Enable scwallet Payment',
				'default' => 'yes'
			),

			'title' => array(
				'title'       => 'Title',
				'type'        => 'text',
				'description' => 'This controls the title for the payment method the customer sees during checkout.',
				'default'     => 'scwallet Payment',
				'desc_tip'    => true,
			),

			'description' => array(
				'title'       => 'Description',
				'type'        => 'textarea',
				'description' => 'Payment method description that the customer will see on your checkout.',
				'default'     => 'Pay with scwallet Store Credit',
				'desc_tip'    => true,
			),

			'instructions' => array(
				'title'       => 'Instructions',
				'type'        => 'textarea',
				'description' => 'Instructions that will be added to the thank you page and emails.',
				'default'     => '',
				'desc_tip'    => true,
			),
		) );
	}


	/**
	 * Output for the order received page.
	 */
	public function thankyou_page() {
		if ( $this->instructions ) {
			echo wpautop( wptexturize( $this->instructions ) );
		}
	}


	/**
	 * Add content to the WC emails.
	 *
	 * @access public
	 * @param WC_Order $order
	 * @param bool $sent_to_admin
	 * @param bool $plain_text
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

		if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
			echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
		}
	}


	/**
	 * Process the payment and return the result
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		global $woocommerce;
		global $scwallet;

		$order = wc_get_order( $order_id );

		$ordered = $scwallet->do_order( $order );

		if( $ordered ) {

			$order->payment_complete();
			wc_reduce_stock_levels( $order_id );

			// Remove cart
			$woocommerce->cart->empty_cart();

			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);

		} else {
			wc_add_notice( 'Insufficient Funds' );
		}

	}

}

/* EOF */
