<?php

defined( 'ABSPATH' ) || exit;

class LSTABP_Export {
	const OPTION = 'lstabp_export_sources';
	const ACTION = 'lstabp_export';

	public function register() {
		add_filter( 'lstab_rendered_table', array( $this, 'add_buttons' ), 10, 3 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'serve' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'serve' ) );

		add_action( 'lstab_edit_pane_cards', array( $this, 'render_pane_card' ), 20, 3 );
		add_action( 'lstab_source_saved', array( $this, 'save' ) );
		add_action( 'lstab_source_deleted', array( $this, 'forget' ) );
		add_action( 'lstabp_forget_source', array( $this, 'forget' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public static function enabled_sources() {
		return array_map( 'boolval', (array) get_option( self::OPTION, array() ) );
	}

	public static function is_enabled( $source_id ) {
		$all = self::enabled_sources();

		return ! empty( $all[ (int) $source_id ] );
	}

	public function enqueue() {
		wp_register_script( 'lstabp-export', LSTABP_URL . 'assets/js/lstabp-export.js', array(), LSTABP_VERSION, true );
	}

	public static function signature( $source_id, $filter ) {
		return wp_hash( self::ACTION . '|' . (int) $source_id . '|' . $filter );
	}

	public function add_buttons( $html, $source, $args ) {
		$source_id = isset( $source['id'] ) ? (int) $source['id'] : 0;

		if ( $source_id <= 0 || '' === $html || ! self::is_enabled( $source_id ) ) {
			return $html;
		}

		$filter = isset( $args['filter'] ) ? (string) $args['filter'] : '';

		$picked = array();
		foreach ( (array) $_GET as $name => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, public navigation.
			if ( is_scalar( $value ) && preg_match( '/^lstab-f\d+-' . $source_id . '$/', (string) $name ) ) {
				$picked[ (string) $name ] = rawurlencode( sanitize_text_field( wp_unslash( (string) $value ) ) );
			}
		}

		$link = function ( $format ) use ( $source_id, $filter, $picked ) {
			return add_query_arg(
				array_merge(
					$picked,
					array(
						'action' => self::ACTION,
						'source' => $source_id,
						'filter' => rawurlencode( $filter ),
						'format' => $format,
						'sig'    => self::signature( $source_id, $filter ),
					)
				),
				admin_url( 'admin-post.php' )
			);
		};

		wp_enqueue_script( 'lstabp-export' );

		ob_start();
		?>
		<p class="lstabp-export">
			<?php if ( LSTABP_Xlsx::is_available() ) : ?>
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

		$outer = strrpos( $html, '</div>' );

		if ( false === $outer ) {
			return $html . $buttons;
		}

		$inner = strrpos( substr( $html, 0, $outer ), '</div>' );

		if ( false === $inner ) {
			return substr_replace( $html, $buttons . '</div>', $outer, strlen( '</div>' ) );
		}

		return substr_replace( $html, $buttons . '</div>', $inner, strlen( '</div>' ) );
	}

	public function serve() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- A signed public link, not a form submission.
		$source_id = isset( $_GET['source'] ) ? absint( wp_unslash( $_GET['source'] ) ) : 0;
		$filter    = isset( $_GET['filter'] ) ? sanitize_text_field( rawurldecode( sanitize_text_field( wp_unslash( $_GET['filter'] ) ) ) ) : '';
		$signature = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';
		$format    = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'csv';
		// phpcs:enable

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

		LSTABP_Facets::$exporting = true;
		$prepared                 = LSTAB_Renderer::prepare(
			$source,
			array(
				'filter'   => $filter,
				'per_page' => 0,
			)
		);
		LSTABP_Facets::$exporting = false;

		$name = sanitize_file_name( $source['title'] ? $source['title'] : 'table' );

		if ( '' === $name ) {
			$name = 'table';
		}

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
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: attachment; filename="' . $name . '.csv"' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions -- Streaming to the browser; there is no WP_Filesystem equivalent.
		$out = fopen( 'php://output', 'w' );

		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Writing to the response stream opened above.
		self::put_row( $out, $prepared['headers'] );

		foreach ( $prepared['rows'] as $row ) {
			self::put_row( $out, (array) $row );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Closing the response stream opened above.
		exit;
	}

	protected static function put_row( $handle, $cells ) {
		fputcsv( $handle, array_map( array( __CLASS__, 'defuse' ), $cells ), ',', '"', '' );
	}

	public static function defuse( $value ) {
		$value = (string) $value;

		if ( '' === $value || ! preg_match( '/^[=+\-@\t\r]/', $value ) ) {
			return $value;
		}

		if ( LSTAB_Renderer::looks_numeric( $value ) ) {
			return $value;
		}

		return "'" . $value;
	}

	public function render_pane_card( $pane, $source, $is_edit ) {
		if ( 'general' !== $pane ) {
			return;
		}

		$this->render_card( $source, $is_edit );
	}

	public function render_card( $source, $is_edit ) {
		$enabled = ( $is_edit && $source ) ? self::is_enabled( $source['id'] ) : false;
		?>
		<div class="lstab-card lstabp-export-card">
			<h2 class="lstab-card-title"><?php esc_html_e( 'Downloads and printing', 'live-sheets-table-pro' ); ?></h2>
			<p class="lstab-checkbox">
				<label>
					<input type="hidden" name="lstabp_export_present" value="1">
					<input type="checkbox" name="lstabp_export" value="1" <?php checked( $enabled ); ?>>
					<?php esc_html_e( 'Let visitors download or print this table', 'live-sheets-table-pro' ); ?>
				</label>
				<span class="lstab-help">
					<?php esc_html_e( 'Excel, CSV and Print buttons under the table. A download holds every row of the table, on every page, after its filter and the visitor’s choices under “Show only” — and only the columns you kept.', 'live-sheets-table-pro' ); ?>
				</span>
			</p>
		</div>
		<?php
	}

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

	public function forget( $source_id ) {
		$all = self::enabled_sources();

		if ( ! isset( $all[ (int) $source_id ] ) ) {
			return;
		}

		unset( $all[ (int) $source_id ] );
		update_option( self::OPTION, $all, true );
	}
}
