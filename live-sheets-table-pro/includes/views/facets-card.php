<?php
/**
 * Choosing which columns a visitor may filter the table by.
 *
 * The card says what each column would actually offer, because that is the
 * whole decision: a filter on "Dostępność" gives three useful answers, and one
 * on "Cena netto" gives seven values that each narrow the table to a single
 * row. Nothing here guesses for you — it counts, and says what it counted.
 *
 * @package LiveSheetsTablePro
 *
 * @var int                            $source_id Source being edited, 0 while adding.
 * @var array<int,string>              $headers   Sheet headings.
 * @var array<int,array<int,string>>   $rows      Stored rows.
 * @var array<int,string>              $chosen    Headings already chosen.
 * @var bool                           $is_edit   Whether an existing source is being edited.
 */

defined( 'ABSPATH' ) || exit;

$lstabp_waiting = ! $headers;
$lstabp_total   = count( $rows );
?>
<div class="lstab-card lstabp-facets-card">
	<h2 class="lstab-card-title"><?php esc_html_e( 'Let visitors narrow the table', 'live-sheets-table-pro' ); ?></h2>
	<p class="lstab-help">
		<?php esc_html_e( 'Tick a column and a menu of its values appears above the table. A visitor opens the menu, picks a value, and the table keeps only the rows that match.', 'live-sheets-table-pro' ); ?>
	</p>

	<?php
	/*
	 * Told in words, this is the feature people asked what it was for. Shown,
	 * it explains itself in a second: this is the bar, these are the menus,
	 * this is the count changing. Built from the sheet's own headings where
	 * there are any, so it is their table rather than an imaginary one.
	 */
	$lstabp_demo = array();

	foreach ( $headers as $lstabp_demo_index => $lstabp_demo_head ) {
		if ( count( $lstabp_demo ) >= 2 ) {
			break;
		}

		$lstabp_demo_tally = LSTABP_Facets::tally( $rows, $lstabp_demo_index );
		$lstabp_demo_kinds = count( $lstabp_demo_tally );
		$lstabp_demo_each  = $lstabp_demo_kinds > 0 ? array_sum( $lstabp_demo_tally ) / $lstabp_demo_kinds : 0;

		// The same test the badge below uses, so the illustration cannot be
		// drawn with the very columns the list then advises against.
		if ( $lstabp_demo_kinds < 2 || $lstabp_demo_each < 2 || $lstabp_demo_kinds > LSTABP_Facets::LONG_MENU ) {
			continue;
		}

		arsort( $lstabp_demo_tally );

		$lstabp_demo[] = array(
			'heading' => (string) $lstabp_demo_head,
			'value'   => (string) key( $lstabp_demo_tally ),
			'rows'    => (int) current( $lstabp_demo_tally ),
		);
	}

	if ( ! $lstabp_demo ) {
		$lstabp_demo[] = array(
			'heading' => __( 'Availability', 'live-sheets-table-pro' ),
			'value'   => __( 'In stock', 'live-sheets-table-pro' ),
			'rows'    => 0,
		);
	}
	?>
	<div class="lstabp-facets-demo">
		<span class="lstabp-facets-demo-caption">
			<?php esc_html_e( 'This is what a visitor sees above the table:', 'live-sheets-table-pro' ); ?>
		</span>

		<span class="lstabp-facets-demo-bar" aria-hidden="true">
			<span class="lstabp-facets-demo-label"><?php esc_html_e( 'Show only:', 'live-sheets-table-pro' ); ?></span>

			<?php foreach ( $lstabp_demo as $lstabp_demo_at => $lstabp_demo_facet ) : ?>
				<span class="lstabp-facets-demo-pick<?php echo 0 === $lstabp_demo_at ? ' is-on' : ''; ?>">
					<b><?php echo esc_html( $lstabp_demo_facet['heading'] ); ?>:</b>
					<?php
					echo esc_html(
						0 === $lstabp_demo_at
							? $lstabp_demo_facet['value']
							: __( 'any', 'live-sheets-table-pro' )
					);
					?>
					<span class="lstabp-facets-demo-arrow">▾</span>
				</span>
			<?php endforeach; ?>

			<?php if ( $lstabp_demo[0]['rows'] ) : ?>
				<span class="lstabp-facets-demo-clear">
					<?php
					printf(
						/* translators: %s: how many rows the table holds in all. */
						esc_html__( 'Clear filters — show all %s', 'live-sheets-table-pro' ),
						esc_html( number_format_i18n( $lstabp_total ) )
					);
					?>
				</span>
			<?php endif; ?>
		</span>

		<?php if ( $lstabp_demo[0]['rows'] ) : ?>
			<span class="lstabp-facets-demo-rows">
				<?php
				printf(
					/* translators: 1: rows left after the filter, 2: rows in all. */
					esc_html__( 'The table then shows %1$s of its %2$s rows.', 'live-sheets-table-pro' ),
					esc_html( number_format_i18n( $lstabp_demo[0]['rows'] ) ),
					esc_html( number_format_i18n( $lstabp_total ) )
				);
				?>
			</span>
		<?php endif; ?>
	</div>

	<p class="lstab-help">
		<?php esc_html_e( 'Every choice has an address of its own, so a filtered table can be sent to somebody as a link. Filters work together with the search box and with pages, over the whole sheet rather than the page on screen.', 'live-sheets-table-pro' ); ?>
	</p>

	<p class="lstab-help">
		<?php esc_html_e( 'The counts beside each column below come from the copy the plugin holds now.', 'live-sheets-table-pro' ); ?>
	</p>

	<?php if ( $lstabp_waiting ) : ?>
		<p class="lstab-columns-waiting">
			<?php
			echo esc_html(
				$is_edit
					? __( 'The sheet has not been read yet, so there are no columns to choose from.', 'live-sheets-table-pro' )
					: __( 'Load the preview first. Once the columns are known you can offer filters on them.', 'live-sheets-table-pro' )
			);
			?>
		</p>
	<?php else : ?>
		<?php // Without this a save from a screen that never showed the card would read as "every filter removed". ?>
		<input type="hidden" name="_lstabp_facets_present" value="1">

		<ul class="lstabp-facet-picks">
			<?php foreach ( $headers as $lstabp_index => $lstabp_heading ) : ?>
				<?php
				$lstabp_tally  = LSTABP_Facets::tally( $rows, $lstabp_index );
				$lstabp_kinds  = count( $lstabp_tally );
				$lstabp_filled = array_sum( $lstabp_tally );
				$lstabp_on     = in_array( (string) $lstabp_heading, $chosen, true );

				/*
				 * Worth offering when the values repeat — which is a ratio, not
				 * a count. Forty towns across five hundred rows is twelve rows
				 * behind every choice and a fine filter; four values across
				 * seven rows is not. A column where every row differs offers as
				 * many choices as it has rows and narrows the table to one of
				 * them, which is a worse search box.
				 */
				$lstabp_each  = $lstabp_kinds > 0 ? $lstabp_filled / $lstabp_kinds : 0;
				$lstabp_suits = $lstabp_kinds >= 2 && $lstabp_each >= 2;
				$lstabp_long  = $lstabp_kinds > LSTABP_Facets::LONG_MENU;

				$lstabp_top = implode(
					', ',
					array_map(
						static function ( $lstabp_value, $lstabp_count ) {
							return $lstabp_value . ' (' . number_format_i18n( $lstabp_count ) . ')';
						},
						array_slice( array_keys( $lstabp_tally ), 0, 3 ),
						array_slice( array_values( $lstabp_tally ), 0, 3 )
					)
				) . ( $lstabp_kinds > 3 ? ' …' : '' );

				if ( 0 === $lstabp_kinds ) {
					$lstabp_says = __( 'This column is empty.', 'live-sheets-table-pro' );
				} elseif ( 1 === $lstabp_kinds ) {
					$lstabp_says = __( 'Every row holds the same value, so a filter here would narrow nothing.', 'live-sheets-table-pro' );
				} elseif ( $lstabp_kinds === $lstabp_filled ) {
					$lstabp_says = __( 'Every row is different, so each choice would leave one row. A search box does this better.', 'live-sheets-table-pro' );
				} elseif ( ! $lstabp_suits ) {
					$lstabp_says = sprintf(
						/* translators: 1: how many different values, 2: how many rows hold one. */
						__( '%1$s different values across %2$s rows, close to one per row. A search box suits this column better.', 'live-sheets-table-pro' ),
						number_format_i18n( $lstabp_kinds ),
						number_format_i18n( $lstabp_filled )
					);
				} elseif ( $lstabp_long ) {
					/*
					 * The case this card used to have nothing to say about: a
					 * column that filters well and has too many values to read
					 * at a glance. It is a good filter and a long menu, and
					 * both halves of that are worth knowing before you tick it.
					 */
					$lstabp_says = sprintf(
						/* translators: 1: how many different values, 2: rows behind each one on average, 3: the commonest few, already counted. */
						__( '%1$s different values, about %2$s rows each. A useful filter, but a long list; visitors can type to narrow it. Commonest: %3$s', 'live-sheets-table-pro' ),
						number_format_i18n( $lstabp_kinds ),
						number_format_i18n( (int) round( $lstabp_each ) ),
						$lstabp_top
					);
				} else {
					$lstabp_says = sprintf(
						/* translators: 1: how many different values, 2: the commonest few, already counted. */
						__( '%1$s different values: %2$s', 'live-sheets-table-pro' ),
						number_format_i18n( $lstabp_kinds ),
						$lstabp_top
					);
				}
				?>
				<li class="lstabp-facet-pick<?php echo $lstabp_suits ? ' is-suited' : ''; ?>">
					<label>
						<input type="checkbox"
							name="lstabp_facets[]"
							value="<?php echo esc_attr( $lstabp_heading ); ?>"
							<?php checked( $lstabp_on ); ?>>
						<span class="lstabp-facet-name">
							<?php echo esc_html( $lstabp_heading ); ?>
							<?php if ( $lstabp_suits ) : ?>
								<span class="lstabp-facet-flag"><?php esc_html_e( 'Suits a filter', 'live-sheets-table-pro' ); ?></span>
							<?php endif; ?>
						</span>
						<span class="lstabp-facet-says"><?php echo esc_html( $lstabp_says ); ?></span>
					</label>
				</li>
			<?php endforeach; ?>
		</ul>

	<?php endif; ?>
</div>
