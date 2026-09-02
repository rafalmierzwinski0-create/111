<?php
/**
 * The sample table somebody can try before they have a sheet of their own.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * A price list that ships inside the plugin.
 *
 * The people most likely to give up in the first minute are the ones who
 * installed the plugin to see what it does and have no spreadsheet yet. Sending
 * them off to Google to make one is where they stop. This gives them a working
 * table in one click instead, and the decision about their own sheet can wait
 * until they have seen the thing work.
 *
 * It is built here in PHP rather than read from a bundled CSV so that it is
 * translated like everything else — an English shop should not be shown a
 * Polish price list to explain the plugin.
 *
 * Nothing about it touches the network. It is not a file on our server and not
 * a document in Google: it cannot go missing, cannot be slow, and sends nothing
 * anywhere.
 */
class LSTAB_Example {

	/**
	 * The sheet_kind that marks a source as this sample.
	 *
	 * Syncing skips it — there is nothing to fetch — and the dashboard uses it
	 * to label the row as an example rather than as somebody's real data.
	 */
	const KIND = 'example';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_lstab_add_example', array( $this, 'handle_add' ) );
	}

	/**
	 * Whether a source row is the sample.
	 *
	 * @param array<string,mixed> $source Source row.
	 * @return bool
	 */
	public static function is_example( $source ) {
		return isset( $source['sheet_kind'] ) && self::KIND === $source['sheet_kind'];
	}

	/**
	 * Whether the site already has one.
	 *
	 * @return bool
	 */
	public static function exists() {
		foreach ( LSTAB_Storage::get_all() as $source ) {
			if ( self::is_example( $source ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The table itself.
	 *
	 * Every column is here to demonstrate something. "Cena netto" is numbers,
	 * so it shows right alignment and figures that line up. "Dostępność" has
	 * three repeating values, which is exactly what conditional colouring and
	 * filtering need. "Uwagi" runs long, so it shows wrapping and the card
	 * layout on a phone. "Czas" is the column somebody would naturally want
	 * gone, so hiding has something to be tried on straight away.
	 *
	 * @return array{headers:array<int,string>,rows:array<int,array<int,string>>,offset:int}
	 */
	public static function table() {
		$headers = array(
			__( 'Service', 'live-sheets-table' ),
			__( 'Time', 'live-sheets-table' ),
			__( 'Price', 'live-sheets-table' ),
			__( 'Availability', 'live-sheets-table' ),
			__( 'Notes', 'live-sheets-table' ),
		);

		$in_stock = __( 'In stock', 'live-sheets-table' );
		$to_order = __( 'To order', 'live-sheets-table' );
		$none     = __( 'Unavailable', 'live-sheets-table' );

		$rows = array(
			array(
				__( 'Basic service', 'live-sheets-table' ),
				__( '45 min', 'live-sheets-table' ),
				'120,00',
				$in_stock,
				__( 'No parts replaced', 'live-sheets-table' ),
			),
			array(
				__( 'Suspension overhaul', 'live-sheets-table' ),
				__( '2 h', 'live-sheets-table' ),
				'340,00',
				$to_order,
				__( 'Up to five working days', 'live-sheets-table' ),
			),
			array(
				__( 'Wheel truing', 'live-sheets-table' ),
				__( '30 min', 'live-sheets-table' ),
				'89,00',
				$in_stock,
				'—',
			),
			array(
				__( 'Chain replacement', 'live-sheets-table' ),
				__( '20 min', 'live-sheets-table' ),
				'1 215,50',
				$none,
				__( 'Part on back order', 'live-sheets-table' ),
			),
			array(
				__( 'Rack fitting', 'live-sheets-table' ),
				__( '1 h', 'live-sheets-table' ),
				'87,00',
				$in_stock,
				__( 'Seatpost or frame mount', 'live-sheets-table' ),
			),
			array(
				__( 'Gear adjustment', 'live-sheets-table' ),
				__( '25 min', 'live-sheets-table' ),
				'69,00',
				$in_stock,
				__( 'Cables included, housing extra', 'live-sheets-table' ),
			),
			array(
				__( 'Brake pads', 'live-sheets-table' ),
				__( '40 min', 'live-sheets-table' ),
				'149,00',
				$to_order,
				__( 'Organic or metallic compound', 'live-sheets-table' ),
			),
			array(
				__( 'Season preparation', 'live-sheets-table' ),
				__( '3 h', 'live-sheets-table' ),
				'459,00',
				$in_stock,
				__( 'Full check, wash and lubrication', 'live-sheets-table' ),
			),
		);

		return array(
			'headers' => $headers,
			'rows'    => $rows,
			// The heading occupies line 1 in Google, so the first stored row is
			// line 2 — the same arithmetic a real sheet goes through, so the
			// line numbers on the hiding screen are right here too.
			'offset'  => 1,
		);
	}

	/**
	 * Create the sample source, or return the one already there.
	 *
	 * @return int Source ID, or 0 when it could not be created.
	 */
	public static function install() {
		foreach ( LSTAB_Storage::get_all() as $source ) {
			if ( self::is_example( $source ) ) {
				return (int) $source['id'];
			}
		}

		$id = LSTAB_Storage::insert(
			array(
				'title'      => __( 'Example price list', 'live-sheets-table' ),
				'sheet_url'  => '',
				'sheet_id'   => '',
				'sheet_kind' => self::KIND,
				'gid'        => '0',
				'tab_name'   => __( 'Example', 'live-sheets-table' ),
			)
		);

		if ( ! $id ) {
			return 0;
		}

		$table = self::table();

		LSTAB_Storage::record_success( $id, $table );

		/*
		 * Recording the headings the way a real sync would, so the example
		 * behaves like a real source everywhere: the dashboard can name its
		 * columns, and hiding one has a heading to check itself against.
		 */
		LSTAB_Storage::update(
			$id,
			array( 'columns_config' => LSTAB_Columns::reconcile( array(), $table['headers'] ) )
		);

		return (int) $id;
	}

	/**
	 * Handle the "show me an example" button.
	 *
	 * @return void
	 */
	public function handle_add() {
		if ( ! current_user_can( LSTAB_Limits::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'live-sheets-table' ) );
		}

		check_admin_referer( 'lstab_add_example' );

		$id = self::install();

		wp_safe_redirect(
			$id
				? add_query_arg(
					array(
						'page'   => LSTAB_Admin::EDIT_SLUG,
						'source' => $id,
					),
					admin_url( 'admin.php' )
				)
				: admin_url( 'admin.php?page=' . LSTAB_Admin::MENU_SLUG )
		);
		exit;
	}
}
