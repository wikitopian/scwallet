<?php

require_once( 'scwallet-db.php' );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class SCWallet_History_Table {

	private $user_id;
	private $items_per_page;
	private $item_count;
	private $page;
	private $pages;

	private $history;

	public function __construct( $user_id, $items_per_page, $page ) {
		$this->user_id        = $user_id;
		$this->items_per_page = $items_per_page;
		$this->page           = $page;

		global $scwallet;
		$this->item_count = $scwallet->get_tx_count( $user_id );

		$this->pages = CEIL( $this->item_count / $this->items_per_page );

		if( $this->pages == 0 ) {
			$this->pages = 1;
		}

		$offset = ( $this->items_per_page * $page ) - $this->items_per_page;

		$this->history = SCWallet_Db::get_history( $this->user_id, $this->items_per_page, $offset );

	}

	public function get_table() {

		$table = '';

		if( $this->page <= 1 ) {
			$back = '';
		} else {

		$page_back = $this->page - 1;
		$back = <<<BACK

<a href="#scwallet_first">&lt;&lt;</a>

<a href="#scwallet_back">&lt;</a>

BACK;

		}

		if( $this->page >= $this->pages ) {
			$next = '';
		} else {

		$page_next = $this->page + 1;
		$next = <<<NEXT

<a id="scwallet_next" href="javascript:void(0);">&gt;</a>

<a href="#scwallet_final">&gt;&gt;</a>

NEXT;

		}

		$table = <<<TABLE

<div id="scwallet_history_pagination">

	<span>{$back} Page {$this->page} of {$this->pages} {$next}</span>

</div>

TABLE;

		$table .= <<<TABLE

<table class="wp-list-table widefat fixed striped posts">

	<thead>

		<tr>

			<th scope="col" id='scwallet_history_id' class='column-title column-primary'>
				<span></span>
			</th>

			<th scope="col" id='scwallet_history_time' class='column-title column-primary'>
				<span>Time (UTC)</span>
			</th>

			<th scope="col" id='scwallet_history_from' class='column-title column-primary'>
				<span>From</span>
			</th>

			<th scope="col" id='scwallet_history_to' class='column-title column-primary'>
				<span>To</span>
			</th>

			<th scope="col" id='scwallet_history_amount' class='column-title column-primary'>
				<span>Amount</span>
			</th>

		</tr>

	</thead>
	<tbody>

TABLE;

		$dir = 			plugin_dir_url( __FILE__ );
		$dir = str_replace( 'classes/', '', $dir );

		$current_user = wp_get_current_user();

		foreach( $this->history as $row ) {

			$note = esc_attr( $row->note );

			$tx_type = '<div class="scwallet_buttons"><span class="scwallet_'
				. $row->tx_type
				. '" title="' . $row->tx_type . '" alt="' . $row->tx_type . '"></span></div>';

			if( empty( $row->id_from ) ) {

			$name_from = <<<DAI
<img src="{$dir}images/dai-token.png" alt="DAI Deposit" title="DAI Deposit" width="48" height="48" />
DAI;

			} else {

				$name_from = get_avatar( $row->id_from, 48, 'mysteryman', '@' . $row->name_from );
				if( $row->name_from != $current_user->user_login ) {
					$name_from = "<a href=\"/members/{$row->name_from}/\">{$name_from}</a>";
				}

			}

			if( empty( $row->id_to ) ) {

				$order = wc_get_order( $row->order_id );

				$name_to  = "<a href=\"{$order->get_view_order_url()}\">\n";
				$name_to .= '<img src="' . get_site_icon_url( 48 ) . '" width="48" height="48" />';
				$name_to .= "\n</a>\n";

			} else {

				$name_to = get_avatar( $row->id_to, 48, 'mysteryman', '@' . $row->name_to );
				if( $row->name_to != $current_user->user_login ) {
					$name_to = "<a href=\"/members/{$row->name_to}/\">{$name_to}</a>";
				}

			}

			setlocale(LC_MONETARY, 'en_US');
			$amount = '$' . money_format('%i', $row->amount );

			$table .= <<<TABLE

		<tr>

			<td scope="col" class='scwallet_history_id'>
				<span title="{$note}">{$row->id}</span><br />
				<span>{$tx_type}</span>
			</td>

			<td scope="col" class='scwallet_history_time'>
				<span>{$row->time}</span>
			</td>

			<td scope="col" class='scwallet_history_from'>
				<span>{$name_from}</span>
			</td>

			<td scope="col" class='scwallet_history_to'>
				<span>{$name_to}</span>
			</td>

			<td scope="col" class='scwallet_history_amount'>
				<span>{$amount}</span>
			</td>

		</tr>
TABLE;

		}

		$table .= <<<TABLE

	</tbody>

</table>

TABLE;

		$table .= <<<TABLE

<div id="scwallet_history_pagination">

	<span>{$back} Page {$this->page} of {$this->pages} {$next}</span>

</div>

TABLE;

		if( $this->item_count > 0 ) {

		$table .= <<<TABLE

<div id="scwallet_history_download">

	<span><a href="/scwallet/transactions.csv">Download</a></span>

</div>

TABLE;

		}

			return $table;

	}

	public function get_page() {
		return $this->page;
	}

	public function get_pages() {
		return $this->pages;
	}

}

/* EOF */
