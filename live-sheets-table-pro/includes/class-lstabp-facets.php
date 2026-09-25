<?php
/**
 * Letting a visitor narrow the table by what is in a column.
 *
 * A search box asks somebody to guess the words in the sheet. A filter shows
 * them: "Dostępność: W magazynie (4), Brak (1), Na zamówienie (2)". That is the
 * difference between a table you can read and one you can use, and it is the
 * thing every table plugin has that a sheet published as a table did not.
 *
 * Which columns get one is the author's decision, not a guess. A column where
 * every row holds something different — a price, a description, a date — makes
 * a filter that narrows seven rows to one and offers seven ways to do it, which
 * is worse than no filter at all. The card in the editor says which columns
 * look like they would work and leaves the choice alone.
 *
 * Everything happens on the server, over the whole sheet, before a row is
 * written into the page: the same arrangement paging and searching use, and for
 * the same reason. Every control is a link, so a filtered table has its own
 * address, can be shared, and works with JavaScript switched off.
 *
 * @package LiveSheetsTablePro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Visitor-facing column filters.
 */
class LSTABP_Facets {

	/**
	 * Where the chosen columns live, keyed by source ID.
	 */
	const OPTION = 'lstabp_facets';

	/**
	 * How many distinct values one filter's menu will offer.
	 *
	 * Past this the menu has stopped being a choice and become a second table.
	 * It caps the menu, never the counting: a card that said "60 different
	 * values" about a column holding five hundred would be describing its own
	 * limit rather than the sheet.
	 */
	const MAX_VALUES = 60;

	/**
	 * How long a menu can get before it wants a way to search it.
	 */
	const LONG_MENU = 12;

	/**
	 * What each table on this page worked out, keyed by source ID.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	protected static $state = array();

	/**
	 * Columns being ticked right now, for the length of one preview request.
	 *
	 * @var array<int,array<int,string>>
	 */
	protected static $previewing = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		/*
		 * After the author's own row filter at 10 and before paging at 15: a
		 * visitor narrows what the author decided to publish, and the page
		 * count has to be of what is left after they have.
		 */
		add_filter( 'lstab_source_rows', array( $this, 'narrow' ), 12, 4 );
		add_action( 'lstab_before_table', array( $this, 'render_bar' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		// A column being ticked exists only in the form until it is saved. The
		// preview is handed it directly, so a filter bar appears as it is
		// chosen rather than after a save and a look.
		add_action( 'lstab_preview_request', array( $this, 'preview_request' ), 10, 2 );

		// The Appearance tab, beside the other things a visitor is given.
		add_action( 'lstab_edit_pane_cards', array( $this, 'render_pane_card' ), 15, 3 );
		add_action( 'lstab_source_saved', array( $this, 'save' ) );
		add_action( 'lstab_source_deleted', array( $this, 'forget' ) );
	}

	/**
	 * Register the script that makes a long menu searchable.
	 *
	 * Registered rather than enqueued: it is only wanted by a page that
	 * actually prints a filter bar, and only when one of the menus is long
	 * enough to need it.
	 *
	 * @return void
	 */
	public function enqueue() {
		wp_register_script( 'lstabp-facets', LSTABP_URL . 'assets/js/lstabp-facets.js', array(), LSTABP_VERSION, true );
	}

	/**
	 * Every stored choice.
	 *
	 * @return array<int,array<int,string>>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * The columns one source offers a filter on, by heading.
	 *
	 * @param int $source_id Source ID.
	 * @return array<int,string>
	 */
	public static function for_source( $source_id ) {
		$key = (int) $source_id;

		if ( isset( self::$previewing[ $key ] ) ) {
			return self::$previewing[ $key ];
		}

		$all = self::all();

		return isset( $all[ $key ] ) ? (array) $all[ $key ] : array();
	}

	/**
	 * Hand the preview the columns being ticked right now.
	 *
	 * @param WP_REST_Request $request   The preview request.
	 * @param int             $source_id Source being previewed.
	 * @return void
	 */
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


	/**
	 * Name of one filter's query argument.
	 *
	 * Built with the free plugin's own namer, so several tables on a page keep
	 * their filters apart and so paging and searching carry them along without
	 * knowing anything about them.
	 *
	 * @param int $column Column position in the sheet.
	 * @return string
	 */
	protected static function arg( $column ) {
		return 'f' . (int) $column;
	}

	/**
	 * Whether this rendering is one a visitor is looking at.
	 *
	 * The editor's preview has no address to carry a filter in, and a filter
	 * bar there would offer links back into the dashboard.
	 *
	 * @return bool
	 */
	protected static function on_a_page() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		return ! is_admin() && ! wp_doing_ajax();
	}

	/**
	 * Drop the rows the visitor has filtered out, and count what is there.
	 *
	 * @param array<int,array<int,string>> $rows    Body rows.
	 * @param array<int,string>            $headers Sheet headings.
	 * @param array<string,mixed>          $source  Source row.
	 * @param array<string,mixed>          $args    Rendering options.
	 * @return array<int,array<int,string>>
	 */
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
				// One value, or none: a filter offering a single answer is a
				// control that cannot change anything.
				continue;
			}

			$facets[ $position ] = array(
				'heading' => $heading,
				// The whole tally decides what a chosen value means; only the
				// menu is cut down to a length somebody can read.
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
			// What the table holds before the visitor narrows it, so the bar
			// can say what clearing the filters would give back. The row
			// counter beside the search box only ever sees what is left.
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

	/**
	 * Which sheet positions the chosen headings are at.
	 *
	 * @param array<int,string>   $headers Sheet headings.
	 * @param array<string,mixed> $source  Source row.
	 * @return array<int,string> Position to heading.
	 */
	protected static function positions( $headers, $source ) {
		$wanted = self::for_source( isset( $source['id'] ) ? $source['id'] : 0 );

		if ( ! $wanted ) {
			return array();
		}

		$map   = LSTABP_Filters::column_map( array_values( (array) $headers ), $source );
		$found = array();

		foreach ( $wanted as $heading ) {
			$key = self::fold( $heading );

			// A heading renamed in Google takes its filter with it, rather than
			// filtering on whatever column happens to be in that position now.
			if ( isset( $map[ $key ] ) ) {
				$found[ (int) $map[ $key ] ] = $heading;
			}
		}

		return $found;
	}

	/**
	 * How many rows hold each value of one column.
	 *
	 * @param array<int,array<int,string>> $rows     Rows.
	 * @param int                          $position Column position.
	 * @return array<string,int> Value to count, commonest first.
	 */
	public static function tally( $rows, $position ) {
		$counts = array();

		foreach ( $rows as $row ) {
			$value = isset( $row[ $position ] ) ? trim( (string) $row[ $position ] ) : '';

			if ( '' === $value ) {
				// An empty cell is not an answer somebody would pick.
				continue;
			}

			$counts[ $value ] = isset( $counts[ $value ] ) ? $counts[ $value ] + 1 : 1;
		}

		/*
		 * Commonest first, then alphabetically: the value most of the sheet
		 * holds is the one somebody is most likely to want, and ties have to
		 * fall in the same order on every request or the menu reshuffles
		 * itself between page loads.
		 */
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

	/**
	 * The values the address asks for on one column.
	 *
	 * @param int               $source_id Source ID.
	 * @param int               $position  Column position.
	 * @param array<int,string> $known     Values the column actually holds.
	 * @return array<int,string>
	 */
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

		/*
		 * Several values are separated by a pipe — except when the whole thing
		 * is one value that happens to contain one, which is checked first so a
		 * sheet holding "S | M | L" can still be filtered on it.
		 */
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

	/**
	 * The address with one value added to, or taken out of, a filter.
	 *
	 * @param int               $source_id Source ID.
	 * @param int               $position  Column position.
	 * @param array<int,string> $chosen    Values chosen now.
	 * @param string            $value     Value being toggled.
	 * @return string
	 */
	protected static function toggle_url( $source_id, $position, $chosen, $value ) {
		$next = in_array( $value, $chosen, true )
			? array_values( array_diff( $chosen, array( $value ) ) )
			: array_merge( $chosen, array( $value ) );

		return LSTAB_Paging::url(
			$source_id,
			array(
				self::arg( $position ) => $next ? implode( '|', $next ) : null,
				// Page four of the old selection is not page four of the new one.
				'page'                 => null,
			)
		);
	}

	/**
	 * Print the filter bar above one table.
	 *
	 * @param array<string,mixed> $source Source row.
	 * @param array<string,mixed> $args   Rendering options.
	 * @return void
	 */
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
				<?php
				/*
				 * A details element, so the menu opens and closes with no
				 * script at all — and every value inside it is a link, so a
				 * filtered table has an address somebody can send to a
				 * colleague.
				 */
				?>
				<details class="lstabp-facet<?php echo $lstabp_facet['chosen'] ? ' is-on' : ''; ?>">
					<summary>
						<?php // The colon is punctuation between two pieces of markup, not a sentence. ?>
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
							<?php
							/*
							 * A column of forty towns is a perfectly good
							 * filter and an unreadable list. Typing narrows it
							 * — and the box is hidden until the script shows
							 * it, because a search box that cannot search is
							 * worse than a long list.
							 */
							?>
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
									esc_html__( 'The %1$s commonest of %2$s values.', 'live-sheets-table-pro' ),
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
					/*
					 * With the number, because the counter beside the search
					 * box only ever sees what is left: with a filter on it
					 * reads "5 of 5 rows", which is true of the table on
					 * screen and says nothing about the two that are not.
					 */
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

	/**
	 * Print the card that chooses the columns.
	 *
	 * @param string                   $pane    Pane being drawn.
	 * @param array<string,mixed>|null $source  Source row, or null while adding.
	 * @param bool                     $is_edit Whether an existing source is being edited.
	 * @return void
	 */
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

	/**
	 * Store the columns submitted with a source.
	 *
	 * @param int $source_id Source ID.
	 * @return void
	 */
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

	/**
	 * Drop a deleted source's choices.
	 *
	 * @param int $source_id Source ID.
	 * @return void
	 */
	public function forget( $source_id ) {
		$all = self::all();

		if ( ! isset( $all[ (int) $source_id ] ) ) {
			return;
		}

		unset( $all[ (int) $source_id ] );
		update_option( self::OPTION, $all, false );
	}

	/**
	 * Case-insensitive form of a value, for comparing.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function fold( $text ) {
		$text = trim( (string) $text );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}
}
