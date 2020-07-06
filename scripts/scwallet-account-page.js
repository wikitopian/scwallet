jQuery(document).ready(function($) {

	/* STARTUP */

	var metamask = new Metamask();

	get_heartbeat();

	$( document ).on( 'heartbeat-tick', function ( event, data ) {
		get_refresh( data );
	});

	jQuery( document ).on( 'heartbeat-send', function ( event, data ) {
		data.scwallet_page = true;
	});

	scwallet['history_page'] = 1;
	get_history( 1, scwallet['history_items_per_page'] );

	$('#scwallet').tabs();

	/* EVENTS */

	$('input#scwallet_transfer_rx').focus( function( e ) {
		$('input#scwallet_transfer_rx').css( 'background-color', '' );
	});

	$('input#scwallet_transfer_rx').focusout( function( e ) {
		check_recipient();
	});

	$('input#scwallet_transfer_amount').focus( function( e ) {
		$('input#scwallet_transfer_amount').css( 'background-color', '' );
	});

	$('input#scwallet_transfer_amount').focusout( function( e ) {
		check_transfer_amount();
	});

	$('form#scwallet_transfer').submit( function( e ) {
		e.preventDefault();

		do_transfer();

	});

	$('input#scwallet_withdraw_address').focus( function( e ) {
		$('input#scwallet_withdraw_address').css( 'background-color', '' );
	});

	$('input#scwallet_withdraw_address').focusout( function( e ) {
		check_withdraw_address();
	});

	$('input#scwallet_withdraw_amount').focus( function( e ) {
		$('input#scwallet_withdraw_amount').css( 'background-color', '' );
	});

	$('input#scwallet_withdraw_amount').focusout( function( e ) {
		check_withdraw_amount();
	});

	$('form#scwallet_withdraw').submit( function( e ) {
		e.preventDefault();

		do_withdraw();

	});


	/* METHODS */

	function get_heartbeat() {

		var data = {
			'action': 'scwallet_heartbeat',
			'nonce': scwallet.nonce,
		};

		$.ajax({
			url: scwallet.ajaxurl,
			data: data,
			dataType: 'json',
			type: 'post',
			success: function( response ) {

				get_refresh( response );

			},
			error: function() {
			}
		});

	}

	function get_refresh( response ) {

		if( typeof response['balance'] === 'undefined' ) {
			return;
		}

		$("span#scwallet_balance").text( scwallet_dollar.format( response['balance'] / 100 ) );
		scwallet['balance'] = response['balance'];

		$('#scwallet_deposit_qr').empty();
		$('#scwallet_deposit_qr').qrcode(
			{
				width: 192,
				height: 192,
				text: response['address'],
			}
		);

		$('#scwallet_deposit_address').html( response['address'] );
		$('#scwallet_deposit_address_field').val( response['address'] );
		scwallet['eth_address'] = response['address'];

		$('#scwallet_deposit_address_copy').click(function() {

			var copy_text = document.getElementById('scwallet_deposit_address_field');
			copy_text.select();
			copy_text.setSelectionRange( 0, 999999 );
			document.execCommand("copy");

		});

		if( response['gas'] >= 100000 ) {
			$('#scwallet_gas_icon_deposit').css( 'color', '#228b22' );
			$('#scwallet_gas_icon_withdraw').css( 'color', '#228b22' );
		}

		$('#scwallet_gauges_gas_bar_deposit').progressbar({
			value: response['gas'],
			max: response['gas_max'],
		});

		$('#scwallet_gauges_gas_bar_withdraw').progressbar({
			value: response['gas'],
			max: response['gas_max'],
		});

		$('#scwallet_gas').html( scwallet_number.format( response['gas'] ) );

		if( scwallet['tx_count'] != response['tx_count'] ) {
			get_history( 1, scwallet['history_items_per_page'] );
		}

		scwallet['tx_count'] = response['tx_count'];
	}

	function get_history( page, items_per_page ) {

		var data = {
			'action': 'scwallet_history',
			'nonce': scwallet.nonce,
			'page': page,
			'items_per_page': items_per_page,
		};

		$.ajax({
			url: scwallet.ajaxurl,
			data: data,
			dataType: 'json',
			type: 'post',
			success: function(response) {

				$("fieldset.scwallet_tx_history div").html( response['tx-history'] );
				scwallet['history_page'] = response['tx-history-page'];
				scwallet['history_pages'] = response['tx-history-pages'];

				$('a[href="#scwallet_first"]').click( function() {
					get_history( 1, scwallet['history_items_per_page'] );
				});

				$('a[href="#scwallet_back"]').click( function() {
					get_history( scwallet['history_page'] - 1, scwallet['history_items_per_page'] );
				});

				$('a#scwallet_next').click( function() {
					get_history( scwallet['history_page'] + 1, scwallet['history_items_per_page'] );
				});

				$('a[href="#scwallet_final"]').click( function() {
					get_history( scwallet['history_pages'], scwallet['history_items_per_page'] );
				});

			},
			error: function() {
				$("input#scwallet_rate").val( '' );
				$("span#scwallet_balance").val( '!ERROR' );
			}
		});

	}

	function check_recipient() {

		var data = {
			'action': 'scwallet_check_recipient',
			'nonce': scwallet.nonce,
			'recipient': $('input#scwallet_transfer_rx').val(),
		};

		$.ajax({
			url: scwallet.ajaxurl,
			data: data,
			dataType: 'json',
			type: 'post',
			success: function(response) {

				if( !response['status'] ) {
					$('input#scwallet_transfer_rx').css( 'background-image', '' );
					$('input#scwallet_transfer_rx').css( 'background-color', 'pink' );
					return;
				}

				$('input#scwallet_transfer_rx').val( '@' + response['user'] );

				$('input#scwallet_transfer_rx').css(
					'background-image',
					'url("' + response['avatar_url'] + '") '
				);

				$('input#scwallet_transfer_rx').css(
					'background-repeat',
					'no-repeat'
				);

				$('input#scwallet_transfer_rx').css(
					'background-size',
					'contain'
				);

				$('input#scwallet_transfer_rx').css(
					'background-position',
					'right'
				);

			},
			error: function() {
				$('input#scwallet_transfer_rx').css( 'background-image', '' );

				$('input#scwallet_transfer_rx').css( 'background-color', 'pink' );
			}
		});

	}

	function check_transfer_amount() {

		amount = $('input#scwallet_transfer_amount').val();

		amount = amount.replace( '$', '' );

		if( !Number( amount ) ) {
			$('input#scwallet_transfer_amount').css( 'background-color', 'pink' );
			return;
		}

		if( Math.round(amount*100) > Math.round(scwallet.balance) || amount <= 0 ) {
			$('input#scwallet_transfer_amount').css( 'background-color', 'pink' );
			return;
		}

		$('input#scwallet_transfer_amount').val( scwallet_dollar.format( amount ) );

	}

	function do_transfer() {

		var amount = $('#scwallet_transfer_amount').val();
		amount = amount.replace( '$', '' );
		amount = amount * 100;
		amount = Math.round( amount );

		var data = {
			'action':  'scwallet_transfer',
			'amount':  amount,
			'name_to': $('#scwallet_transfer_rx').val(),
			'note':    $('#scwallet_transfer_note').val(),
			'nonce':   scwallet.nonce,
		};

		$.ajax({
			url: scwallet.ajaxurl,
			data: data,
			dataType: 'json',
			type: 'post',
			success: function(response) {

				get_heartbeat();

				if( response ) {
					$('#scwallet_transfer_status').html( 'Transfer Complete' );
				} else {
					$('#scwallet_transfer_status').html( 'Transfer Error' );
				}

			},
			error: function() {
				$('#scwallet_transfer_status').html( 'Transfer Error' );

			},
			complete: function() {

				$('#scwallet_transfer_rx').val('');
				$('#scwallet_transfer_amount').val('');
				$('#scwallet_transfer_note').val('');

				get_history( 1, scwallet['history_items_per_page'] );

			}
		});

	}

	function check_withdraw_address() {

		var address = $('input#scwallet_withdraw_address').val();

		if( metamask.is_address( address ) ) {
			$('input#scwallet_withdraw_address').css( 'background-color', '#c4e2ca' );
			return true;
		} else {
			$('input#scwallet_withdraw_address').css( 'background-color', 'pink' );
			return false;
		}

	}

	function Metamask() {

		ethereum.autoRefreshOnNetworkChange = false;

		this.is_address = function( address ) {

			try {
				ethers.utils.getAddress( address );
			} catch(e) {
				return false;
			}

			return true;

		}

	}

});
