<?php
/**
 * Letting visitors take the table with them.
 *
 * The export is generated from exactly what the page shows: the same filter,
 * the same hidden columns, the same renamed headings. An export that reached
 * past those would be a way to read what the page was built not to show, so
 * the link carries a signature and the endpoint rebuilds the table through the
 * renderer's own preparation rather than reading the sheet directly.
 *
 * @package LiveSheetsTablePro
 */

defined( 'ABSPATH' ) || exit;

/**
 * CSV and print export.
 */
class LSTABP_Export {

	const OPTION = 'lstabp_export_sources';
	const ACTION = 'lstabp_export';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'lstab_rendered_table', array( $this, 'add_buttons' ), 10, 3 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'serve' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'serve' ) );

		add_action( 'lstab_edit_page_settings', array( $this, 'render_card' ), 20, 2 );
		add_action( 'lstab_source_saved', array( $this, 'save' ) );
		add_action( 'lstab_source_deleted', array( $this, 'forget' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Sources whose tables offer the buttons.
	 *
	 * @return array<int,bool>
	 */
	public static function enabled_sources() {
		return array_map( 'boolval', (array) get_option( self::OPTION, array() ) );
	}

	/**
	 * Whether one source offers them.
	 *
	 * @param int $source_id Source ID.
	 * @return bool
	 */
	public static function is_enabled( $source_id ) {
		$all = self::enabled_sources();

		return ! empty( $all[ (int) $source_id ] );
	}

	/**
	 * Front-end script for the print button.
	 *
	 * @return void
	 */
	public function enqueue() {
		wp_register_script( 'lstabp-export', LSTABP_URL . 'assets/js/lstabp-export.js', array(), LSTABP_VERSION, true );
	}

	/**
	 * A signature tying a download link to the table it came from.
	 *
	 * Without it the filter could be edited in the address bar, and a page
	 * built to show one category would hand over the whole sheet.
	 *
	 * @param int    $source_id Source ID.
	 * @param string $filter    Filter expression the table was rendered with.
	 * @return string
	 */
	public static function signature( $source_id, $filter ) {
		return wp_hash( self::ACTION . '|' . (int) $source_id . '|' . $filter );
	}

	/**
	 * Put the buttons under a table that offers them.
	 *
	 * @param string              $html   Rendered table.
	 * @param array<string,mixed> $source Source row.
	 * @param array<string,mixed> $args   Rendering options.
	 * @return string
	 */
	public function add_buttons( $html, $source, $args ) {
		$source_id = isset( $source['id'] ) ? (int) $source['id'] : 0;

		if ( $source_id <= 0 || '' === $html || ! self::is_enabled( $source_id ) ) {
			return $html;
		}

		$filter = isset( $args['filter'] ) ? (string) $args['filter'] : '';

		$link = function ( $format ) use ( $source_id, $filter ) {
			return add_query_arg(
				array(
					'action' => self::ACTION,
					'source' => $source_id,
					'filter' => rawurlencode( $filter ),
					'format' => $format,
					'sig'    => self::signature( $source_id, $filter ),
				),
				admin_url( 'admin-post.php' )
			);
		};

		wp_enqueue_script( 'lstabp-export' );

		ob_start();
		?>
		<p class="lstabp-export">
			<?php if ( LSTABP_Xlsx::is_available() ) : ?>
				<?php // First, because it is what most people mean by "download the table". ?>
				<a class="lstabp-export-button" href="<?php echo esc_url( $link( 'xlsx' ) ); ?>" rel="nofollow">
					<?php esc_html_e( 'Download for Excel', 'live-sheets-table-pro' ); ?>
				</a>
			<?php endif; ?>
			<a class="lstabp-export-button" href="<?php echo esc_url( $link( 'csv' ) ); ?>" rel="nofollow">
				<?php esc_html_e( 'Download CSV', 'live-sheets-table-pro' ); ?>
			</a>
			<button type="button" class="lstabp-export-button" data-lstabp-print="<?php echo esc_attr( (string) $source_id ); ?>">
				<?php esc_html_e( 'Print', 'live-sheets-table-pro' ); ?>
			</button>
		</p>
		<?php
		$buttons = (string) ob_get_clean();

		// Inside the wrapper, so the buttons inherit the table's own colours
		// and disappear with it when printing.
		$closing = strrpos( $html, '</div>' );

		return false === $closing ? $html . $buttons : substr_replace( $html, $buttons . '</div>', $closing, strlen( '</div>' ) );
	}

	/**
	 * Stream the table as a file.
	 *
	 * @return void
	 */
	public function serve() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- A signed public link, not a form submission.
		$source_id = isset( $_GET['source'] ) ? absint( wp_unslash( $_GET['source'] ) ) : 0;
		$filter    = isset( $_GET['filter'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['filter'] ) ) ) : '';
		$signature = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';
		$format    = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'csv';
		// phpcs:enable

		// The format decides how the same rows are written, never which rows
		// they are, so it is outside the signature — and anything unrecognised
		// is the format the links have always used.
		if ( 'xlsx' !== $format || ! LSTABP_Xlsx::is_available() ) {
			$format = 'csv';
		}

		$source = $source_id ? LSTAB_Storage::get( $source_id ) : null;

		if ( ! $source || ! self::is_enabled( $source_id ) ) {
			wp_die( esc_html__( 'This table cannot be downloaded.', 'live-sheets-table-pro' ), '', array( 'response' => 404 ) );
		}

		if ( ! hash_equals( self::signature( $source_id, $filter ), $signature ) ) {
			wp_die( esc_html__( 'This download link is not valid.', 'live-sheets-table-pro' ), '', array( 'response' => 403 ) );
		}

		// Through the renderer's own preparation, so the file holds exactly the
		// rows and columns the page held — no more.
		$prepared = LSTAB_Renderer::prepare( $source, array( 'filter' => $filter ) );

		$name = sanitize_file_name( $source['title'] ? $source['title'] : 'table' );

		if ( 'xlsx' === $format ) {
			$file = LSTABP_Xlsx::build( $prepared['headers'], $prepared['rows'], (string) $source['title'] );

			if ( is_wp_error( $file ) ) {
				wp_die( esc_html( $file->get_error_message() ), '', array( 'response' => 500 ) );
			}

			nocache_headers();
			header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
			header( 'Content-Disposition: attachment; filename="' . $name . '.xlsx"' );
			header( 'Content-Length: ' . filesize( $file ) );

			// phpcs:ignore WordPress.WP.AlternativeFunctions -- Streaming a file to the browser, not reading it into a string.
			readfile( $file );
			wp_delete_file( $file );
			exit;
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '.csv"' );

		$out = fopen( 'php://output', 'w' );

		// A BOM, or Excel opens a Polish price list as mojibake.
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, $prepared['headers'] );

		foreach ( $prepared['rows'] as $row ) {
			fputcsv( $out, (array) $row );
		}

		fclose( $out );
		exit;
	}

	/**
	 * Print the setting on the source screen.
	 *
	 * @param array<string,mixed>|null $source  Source row.
	 * @param bool                     $is_edit Editing an existing source.
	 * @return void
	 */
	public function render_card( $source, $is_edit ) {
		$enabled = ( $is_edit && $source ) ? self::is_enabled( $source['id'] ) : false;
		?>
		<div class="lstab-card lstabp-export-card">
			<h2 class="lstab-card-title"><?php esc_html_e( 'Let visitors take it away', 'live-sheets-table-pro' ); ?></h2>
			<p class="lstab-checkbox">
				<label>
					<input type="hidden" name="lstabp_export_present" value="1">
					<input type="checkbox" name="lstabp_export" value="1" <?php checked( $enabled ); ?>>
					<?php esc_html_e( 'Let visitors download or print this table', 'live-sheets-table-pro' ); ?>
				</label>
				<span class="lstab-help">
					<?php esc_html_e( 'Buttons under the table: one Excel file, one CSV, and Print. The file holds exactly what the page shows — the same rows after any filter, and only the columns you left in — and never reaches past them. The Excel file opens in one click with the numbers already numbers, whatever language the person opening it uses. Printing uses the browser, so there is no extra software and no watermark.', 'live-sheets-table-pro' ); ?>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Store the setting.
	 *
	 * @param int $source_id Source ID.
	 * @return void
	 */
	public function save( $source_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the free plugin before this fires.
		if ( ! isset( $_POST['lstabp_export_present'] ) ) {
			return;
		}

		$all = self::enabled_sources();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['lstabp_export'] ) ) {
			unset( $all[ (int) $source_id ] );
		} else {
			$all[ (int) $source_id ] = true;
		}

		update_option( self::OPTION, $all, true );
	}

	/**
	 * Forget a deleted source.
	 *
	 * @param int $source_id Source ID.
	 * @return void
	 */
	public function forget( $source_id ) {
		$all = self::enabled_sources();

		if ( ! isset( $all[ (int) $source_id ] ) ) {
			return;
		}

		unset( $all[ (int) $source_id ] );
		update_option( self::OPTION, $all, true );
	}
}
