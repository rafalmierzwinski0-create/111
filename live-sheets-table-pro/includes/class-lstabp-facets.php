<?php

defined( 'ABSPATH' ) || exit;

class LSTABP_Facets {
	const OPTION = 'lstabp_facets';

	const MAX_VALUES = 60;

	const LONG_MENU = 12;

	protected static $state = array();

	protected static $previewing = array();

	public function register() {
		add_filter( 'lstab_source_rows', array( $this, 'narrow' ), 12, 4 );
		add_action( 'lstab_before_table', array( $this, 'render_bar' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		add_action( 'lstab_preview_request', array( $this, 'preview_request' ), 10, 2 );

		add_action( 'lstab_edit_pane_cards', array( $this, 'render_pane_card' ), 15, 3 );
		add_action( 'lstab_source_saved', array( $this, 'save' ) );
		add_action( 'lstab_source_deleted', array( $this, 'forget' ) );
		add_action( 'lstabp_forget_source', array( $this, 'forget' ) );
	}

	public function enqueue() {
		wp_register_script( 'lstabp-facets', LSTABP_URL . 'assets/js/lstabp-facets.js', array(), LSTABP_VERSION, true );
	}

	public static function all() {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	public static function for_source( $source_id ) {
		$key = (int) $source_id;

		if ( isset( self::$previewing[ $key ] ) ) {
			return self::$previewing[ $key ];
		}

		$all = self::all();

		return isset( $all[ $key ] ) ? (array) $all[ $key ] : array();
	}

	public function preview_request( $request, $source_id ) {
		$facets = $request->get_param( 'facets' );

		if ( ! is_array( $facets ) ) {
			return;
		}

		$clean = array();

		foreach ( $facets as $heading ) {
			$heading = sanitize_text_field( (string) $heading );

			if ( '' !== $heading && ! in_array( $heading, $clean, true ) ) {
				$clean[] = $heading;
			}
		}

		self::$previewing[ (int) $source_id ] = $clean;
	}

	protected static function arg( $column ) {
		return 'f' . (int) $column;
	}

	public static $exporting = false;

	protected static function on_a_page() {
		if ( self::$exporting ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		return ! is_admin() && ! wp_doing_ajax();
	}

	public function narrow( $rows, $headers, $source, $args ) {
		$source_id = isset( $source['id'] ) ? (int) $source['id'] : 0;

		unset( self::$state[ $source_id ] );

		if ( $source_id <= 0 || ! self::on_a_page() ) {
			return $rows;
		}

		$columns = self::positions( $headers, $source );

		if ( ! $columns ) {
			return $rows;
		}

		$rows   = array_values( (array) $rows );
		$facets = array();

		foreach ( $columns as $position => $heading ) {
			$tally = self::tally( $rows, $position );

			if ( count( $tally ) < 2 ) {
				continue;
			}

			$facets[ $position ] = array(
				'heading' => $heading,
				'values'  => array_slice( $tally, 0, self::MAX_VALUES, true ),
				'kinds'   => count( $tally ),
				'chosen'  => self::chosen( $source_id, $position, array_keys( $tally ) ),
			);
		}

		if ( ! $facets ) {
			return $rows;
		}

		self::$state[ $source_id ] = array(
			'facets' => $facets,
			'total'  => count( $rows ),
		);

		foreach ( $facets as $position => $facet ) {
			if ( ! $facet['chosen'] ) {
				continue;
			}

			$wanted = array_map( array( __CLASS__, 'fold' ), $facet['chosen'] );

			$rows = array_values(
				array_filter(
					$rows,
					static function ( $row ) use ( $position, $wanted ) {
						$cell = isset( $row[ $position ] ) ? (string) $row[ $position ] : '';

						return in_array( LSTABP_Facets::fold( $cell ), $wanted, true );
					}
				)
			);
		}

		return $rows;
	}

	protected static function positions( $headers, $source ) {
		$wanted = self::for_source( isset( $source['id'] ) ? $source['id'] : 0 );

		if ( ! $wanted ) {
			return array();
		}

		$map   = LSTABP_Filters::column_map( array_values( (array) $headers ), $source );
		$found = array();

		foreach ( $wanted as $heading ) {
			$key = self::fold( $heading );

			if ( isset( $map[ $key ] ) ) {
				$found[ (int) $map[ $key ] ] = $heading;
			}
		}

		return $found;
	}

	public static function tally( $rows, $position ) {
		$counts = array();

		foreach ( $rows as $row ) {
			$value = isset( $row[ $position ] ) ? trim( (string) $row[ $position ] ) : '';

			if ( '' === $value ) {
				continue;
			}

			$counts[ $value ] = isset( $counts[ $value ] ) ? $counts[ $value ] + 1 : 1;
		}

		uksort(
			$counts,
			static function ( $one, $two ) use ( $counts ) {
				if ( $counts[ $one ] !== $counts[ $two ] ) {
					return $counts[ $two ] - $counts[ $one ];
				}

				return strnatcasecmp( (string) $one, (string) $two );
			}
		);

		return $counts;
	}

	protected static function chosen( $source_id, $position, $known ) {
		$name = LSTAB_Paging::arg( $source_id, self::arg( $position ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation of public data.
		$raw = isset( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $name ] ) ) : '';

		if ( '' === $raw ) {
			return array();
		}

		$lookup = array();
		foreach ( $known as $value ) {
			$lookup[ self::fold( (string) $value ) ] = (string) $value;
		}

		$tokens = isset( $lookup[ self::fold( $raw ) ] ) ? array( $raw ) : explode( '|', $raw );
		$picked = array();

		foreach ( $tokens as $token ) {
			$key = self::fold( trim( $token ) );

			if ( isset( $lookup[ $key ] ) && ! in_array( $lookup[ $key ], $picked, true ) ) {
				$picked[] = $lookup[ $key ];
			}
		}

		return $picked;
	}

	protected static function toggle_url( $source_id, $position, $chosen, $value ) {
		$next = in_array( $value, $chosen, true )
			? array_values( array_diff( $chosen, array( $value ) ) )
			: array_merge( $chosen, array( $value ) );

		return LSTAB_Paging::url(
			$source_id,
			array(
				self::arg( $position ) => $next ? implode( '|', $next ) : null,
				'page'                 => null,
			)
		);
	}

	public function render_bar( $source, $args ) {
		$source_id = isset( $source['id'] ) ? (int) $source['id'] : 0;

		if ( ! isset( self::$state[ $source_id ] ) ) {
			return;
		}

		$facets = self::$state[ $source_id ]['facets'];
		$total  = (int) self::$state[ $source_id ]['total'];
		$any    = false;

		foreach ( $facets as $facet ) {
			if ( $facet['chosen'] ) {
				$any = true;
				break;
			}
		}

		$clear = array( 'page' => null );
		$long  = false;

		foreach ( $facets as $position => $facet ) {
			$clear[ self::arg( $position ) ] = null;

			if ( $facet['kinds'] > self::LONG_MENU ) {
				$long = true;
			}
		}

		if ( $long ) {
			wp_enqueue_script( 'lstabp-facets' );
		}
		?>
		<div class="lstabp-facets">
			<span class="lstabp-facets-label"><?php esc_html_e( 'Show only:', 'live-sheets-table-pro' ); ?></span>

			<?php foreach ( $facets as $lstabp_position => $lstabp_facet ) : ?>
				<details class="lstabp-facet<?php echo $lstabp_facet['chosen'] ? ' is-on' : ''; ?>">
					<summary>
						<b><?php echo esc_html( $lstabp_facet['heading'] ); ?>:</b>
						<span class="lstabp-facet-now">
							<?php
							echo esc_html(
								$lstabp_facet['chosen']
									? implode( ', ', $lstabp_facet['chosen'] )
									: __( 'any', 'live-sheets-table-pro' )
							);
							?>
						</span>
					</summary>

					<div class="lstabp-facet-menu">
						<?php if ( $lstabp_facet['kinds'] > self::LONG_MENU ) : ?>
							<input type="search" class="lstabp-facet-find" hidden
								placeholder="<?php esc_attr_e( 'Type to narrow this list…', 'live-sheets-table-pro' ); ?>"
								aria-label="<?php esc_attr_e( 'Find a value', 'live-sheets-table-pro' ); ?>">
						<?php endif; ?>

						<?php foreach ( $lstabp_facet['values'] as $lstabp_value => $lstabp_count ) : ?>
							<?php $lstabp_picked = in_array( (string) $lstabp_value, $lstabp_facet['chosen'], true ); ?>
							<a class="lstabp-facet-value<?php echo $lstabp_picked ? ' is-picked' : ''; ?>"
								rel="nofollow"
								href="<?php echo esc_url( self::toggle_url( $source_id, $lstabp_position, $lstabp_facet['chosen'], (string) $lstabp_value ) ); ?>">
								<span class="lstabp-facet-box" aria-hidden="true"></span>
								<span class="lstabp-facet-text"><?php echo esc_html( $lstabp_value ); ?></span>
								<span class="lstabp-facet-count"><?php echo esc_html( number_format_i18n( $lstabp_count ) ); ?></span>
							</a>
						<?php endforeach; ?>

						<?php if ( $lstabp_facet['kinds'] > count( $lstabp_facet['values'] ) ) : ?>
							<p class="lstabp-facet-more">
								<?php
								printf(
									/* translators: 1: how many values are listed, 2: how many the column holds. */
									esc_html__( 'The %1$s most common of %2$s values.', 'live-sheets-table-pro' ),
									esc_html( number_format_i18n( count( $lstabp_facet['values'] ) ) ),
									esc_html( number_format_i18n( $lstabp_facet['kinds'] ) )
								);
								?>
							</p>
						<?php endif; ?>

						<p class="lstabp-facet-none" hidden><?php esc_html_e( 'Nothing here matches that.', 'live-sheets-table-pro' ); ?></p>
					</div>
				</details>
			<?php endforeach; ?>

			<?php if ( $any ) : ?>
				<a class="lstabp-facets-clear" rel="nofollow" href="<?php echo esc_url( LSTAB_Paging::url( $source_id, $clear ) ); ?>">
					<?php
					printf(
						/* translators: %s: how many rows the table holds unfiltered. */
						esc_html__( 'Clear filters — show all %s', 'live-sheets-table-pro' ),
						esc_html( number_format_i18n( $total ) )
					);
					?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	public function render_pane_card( $pane, $source, $is_edit ) {
		if ( 'look' !== $pane ) {
			return;
		}

		$source_id = ( $is_edit && $source ) ? (int) $source['id'] : 0;
		$headers   = ( $is_edit && $source && ! empty( $source['data']['headers'] ) )
			? array_values( (array) $source['data']['headers'] )
			: array();
		$rows      = ( $is_edit && $source && ! empty( $source['data']['rows'] ) )
			? array_values( (array) $source['data']['rows'] )
			: array();
		$chosen    = $source_id ? self::for_source( $source_id ) : array();

		require LSTABP_PATH . 'includes/views/facets-card.php';
	}

	public function save( $source_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['_lstabp_facets_present'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Sanitised below.
		$raw = isset( $_POST['lstabp_facets'] ) ? (array) wp_unslash( $_POST['lstabp_facets'] ) : array();

		$clean = array();
		foreach ( $raw as $heading ) {
			$heading = sanitize_text_field( (string) $heading );

			if ( '' !== $heading && ! in_array( $heading, $clean, true ) ) {
				$clean[] = $heading;
			}
		}

		$all                     = self::all();
		$all[ (int) $source_id ] = $clean;

		if ( ! $clean ) {
			unset( $all[ (int) $source_id ] );
		}

		update_option( self::OPTION, $all, false );
	}

	public function forget( $source_id ) {
		$all = self::all();

		if ( ! isset( $all[ (int) $source_id ] ) ) {
			return;
		}

		unset( $all[ (int) $source_id ] );
		update_option( self::OPTION, $all, false );
	}

	public static function fold( $text ) {
		$text = trim( (string) $text );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}
}
