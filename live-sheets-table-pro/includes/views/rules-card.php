<?php
/**
 * Conditional formatting rules, printed inside the free plugin's source form.
 *
 * Each rule is laid out as one sentence rather than as five cells of a table.
 * A table made you read across five headings and assemble the sentence in your
 * head — and the headings could not say it either: "Show it" meant the colour,
 * "Applies to" meant whether the colour lands on one cell or the whole row.
 * Both were only guessable from having tried it.
 *
 * @package LiveSheetsTablePro
 *
 * @var array<int,array<string,mixed>> $rules   Stored rules.
 * @var array<int,string>              $headers Sheet headings, empty before the first sync.
 * @var bool                           $is_edit Whether an existing source is being edited.
 */

defined( 'ABSPATH' ) || exit;

$lstabp_styles    = LSTABP_Rules::styles();
$lstabp_operators = LSTABP_Rules::operators();

/*
 * Two spare lines, so adding a rule needs no button and no JavaScript, and two
 * can be added in one go. They are drawn as outlines rather than as filled-in
 * rules, so an empty card does not look like a form somebody half-completed.
 */
$lstabp_waiting = ! $headers;
$lstabp_saved   = count( $rules );
$lstabp_rows    = array_merge(
	$rules,
	array_fill( 0, 2, array( 'column' => '', 'operator' => '=', 'value' => '', 'style' => 'red', 'scope' => 'cell' ) )
);
?>
<div class="lstab-card lstabp-rules-card<?php echo $lstabp_waiting ? ' is-waiting' : ''; ?>">
	<h2 class="lstab-card-title"><?php esc_html_e( 'Colour rules', 'live-sheets-table-pro' ); ?></h2>
	<p class="lstab-help">
		<?php esc_html_e( 'Colour a cell, or its whole row, by what the cell says. Every row of your sheet is checked against every rule here, and the colour is worked out on the server — so it is already in the page a visitor receives rather than painted on afterwards by script.', 'live-sheets-table-pro' ); ?>
	</p>

	<?php
	/*
	 * The two questions this card used to answer only by experiment: what
	 * happens when two rules match the same cell, and what "the whole row"
	 * does to a cell that has a colour of its own.
	 */
	?>
	<ul class="lstabp-rules-how">
		<li>
			<?php echo LSTAB_Icons::icon( 'layers' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<?php esc_html_e( 'Rules are read from the top down. If two of them colour the same place, the lower one wins — so put the general rule first and the exception under it.', 'live-sheets-table-pro' ); ?>
		</li>
		<li>
			<?php echo LSTAB_Icons::icon( 'brush' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<?php esc_html_e( 'A colour on one cell sits on top of a colour on its row, so “the whole row grey, that one cell red” is two rules and works as it reads.', 'live-sheets-table-pro' ); ?>
		</li>
		<li>
			<?php echo LSTAB_Icons::icon( 'sliders' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<?php esc_html_e( '“is” and “is not” compare text and ignore case and spacing. The four number comparisons read a price as a number, so 1 215,50 and 1215.5 are the same figure and a currency after it makes no difference.', 'live-sheets-table-pro' ); ?>
		</li>
	</ul>

	<?php if ( $lstabp_waiting ) : ?>
		<p class="lstab-columns-waiting">
			<?php
			echo esc_html(
				$is_edit
					? __( 'Waiting for a first look at the sheet, so there are no columns to choose from yet.', 'live-sheets-table-pro' )
					: __( 'Load the preview first. Once the columns are known you can set rules on them.', 'live-sheets-table-pro' )
			);
			?>
		</p>
	<?php endif; ?>

	<?php
	/*
	 * Only when the card can actually be filled in. Its controls are disabled
	 * while there are no columns to choose from, so they submit nothing — and
	 * a marker without them would read as "the user cleared every rule" and
	 * wipe a rule set the screen never showed.
	 */
	if ( ! $lstabp_waiting ) :
		?>
		<input type="hidden" name="_lstabp_rules_present" value="1">
	<?php endif; ?>

	<ol class="lstabp-rules">
		<?php foreach ( $lstabp_rows as $lstabp_index => $lstabp_rule ) : ?>
			<?php $lstabp_is_new = $lstabp_index >= $lstabp_saved; ?>
			<li class="lstabp-rule<?php echo $lstabp_is_new ? ' is-new' : ''; ?>">
				<span class="lstabp-rule-line">
					<span class="lstabp-rule-word"><?php esc_html_e( 'When', 'live-sheets-table-pro' ); ?></span>

					<select class="lstabp-rule-column" name="lstabp_rules[<?php echo esc_attr( (string) $lstabp_index ); ?>][column]" <?php disabled( $lstabp_waiting ); ?>>
						<option value="">
							<?php
							echo esc_html(
								$lstabp_is_new
									? __( '— pick a column —', 'live-sheets-table-pro' )
									: __( '— remove this rule —', 'live-sheets-table-pro' )
							);
							?>
						</option>
						<?php foreach ( $headers as $lstabp_heading ) : ?>
							<option value="<?php echo esc_attr( $lstabp_heading ); ?>" <?php selected( $lstabp_rule['column'], $lstabp_heading ); ?>>
								<?php echo esc_html( $lstabp_heading ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<select name="lstabp_rules[<?php echo esc_attr( (string) $lstabp_index ); ?>][operator]" <?php disabled( $lstabp_waiting ); ?>>
						<?php foreach ( $lstabp_operators as $lstabp_symbol => $lstabp_label ) : ?>
							<option value="<?php echo esc_attr( $lstabp_symbol ); ?>" <?php selected( $lstabp_rule['operator'], $lstabp_symbol ); ?>>
								<?php echo esc_html( $lstabp_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<input type="text" class="lstabp-rule-value"
						name="lstabp_rules[<?php echo esc_attr( (string) $lstabp_index ); ?>][value]"
						value="<?php echo esc_attr( $lstabp_rule['value'] ); ?>"
						placeholder="<?php esc_attr_e( 'what it says', 'live-sheets-table-pro' ); ?>"
						<?php disabled( $lstabp_waiting ); ?>>

					<span class="lstabp-rule-word lstabp-rule-then"><?php esc_html_e( 'paint', 'live-sheets-table-pro' ); ?></span>

					<select name="lstabp_rules[<?php echo esc_attr( (string) $lstabp_index ); ?>][scope]" <?php disabled( $lstabp_waiting ); ?>>
						<option value="cell" <?php selected( $lstabp_rule['scope'], 'cell' ); ?>><?php esc_html_e( 'that cell', 'live-sheets-table-pro' ); ?></option>
						<option value="row" <?php selected( $lstabp_rule['scope'], 'row' ); ?>><?php esc_html_e( 'the whole row', 'live-sheets-table-pro' ); ?></option>
					</select>

					<select class="lstabp-style-select" name="lstabp_rules[<?php echo esc_attr( (string) $lstabp_index ); ?>][style]" <?php disabled( $lstabp_waiting ); ?>>
						<?php foreach ( $lstabp_styles as $lstabp_key => $lstabp_style ) : ?>
							<option value="<?php echo esc_attr( $lstabp_key ); ?>" <?php selected( $lstabp_rule['style'], $lstabp_key ); ?>>
								<?php echo esc_html( $lstabp_style['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<?php // What that choice actually looks like, rather than its name. ?>
					<span class="lstabp-swatch" style="<?php echo esc_attr( $lstabp_styles[ $lstabp_rule['style'] ]['css'] ); ?>">
						<?php echo esc_html( '' !== $lstabp_rule['value'] ? $lstabp_rule['value'] : __( 'Abc', 'live-sheets-table-pro' ) ); ?>
					</span>
				</span>
			</li>
		<?php endforeach; ?>
	</ol>

	<p class="lstab-help">
		<?php esc_html_e( 'The empty line at the bottom is the next rule; save to get another one. To take a rule away, set its column back to “remove this rule”.', 'live-sheets-table-pro' ); ?>
	</p>
</div>
