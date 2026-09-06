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
		<?php esc_html_e( 'A search box asks somebody to guess the words in your sheet. A filter shows them what is in it: “Dostępność: in stock (4), none (1)”. Choose the columns worth offering — the counts below are from the copy the plugin holds now.', 'live-sheets-table-pro' ); ?>
	</p>

	<?php if ( $lstabp_waiting ) : ?>
		<p class="lstab-columns-waiting">
			<?php
			echo esc_html(
				$is_edit
					? __( 'Waiting for a first look at the sheet, so there are no columns to choose from yet.', 'live-sheets-table-pro' )
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
					$lstabp_says = __( 'Every row says the same thing, so a filter here could not narrow anything.', 'live-sheets-table-pro' );
				} elseif ( $lstabp_kinds === $lstabp_filled ) {
					$lstabp_says = __( 'Every row is different, so each choice would leave one row. A search box does this better.', 'live-sheets-table-pro' );
				} elseif ( ! $lstabp_suits ) {
					$lstabp_says = sprintf(
						/* translators: 1: how many different values, 2: how many rows hold one. */
						__( '%1$s different values across %2$s rows — barely more than one row behind each. A search box does this better.', 'live-sheets-table-pro' ),
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
						__( '%1$s different values, about %2$s rows behind each — a good filter, but a long list. Visitors can type to narrow it. Commonest: %3$s', 'live-sheets-table-pro' ),
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

		<p class="lstab-help">
			<?php esc_html_e( 'Filters appear above the table as buttons a visitor opens. Each one is an ordinary link, so a filtered table has an address of its own that can be shared — and it works with searching and pages, over the whole sheet rather than the page on screen.', 'live-sheets-table-pro' ); ?>
		</p>
	<?php endif; ?>
</div>
