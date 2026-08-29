<?php
/**
 * Conditional formatting rules, printed inside the free plugin's source form.
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

// Two spare rows, so adding a rule needs no button and no JavaScript.
$lstabp_waiting = ! $headers;
$lstabp_rows    = array_merge(
	$rules,
	array_fill( 0, 2, array( 'column' => '', 'operator' => '=', 'value' => '', 'style' => 'red', 'scope' => 'cell' ) )
);
?>
<div class="lstab-card lstabp-rules-card<?php echo $lstabp_waiting ? ' is-waiting' : ''; ?>">
	<h2 class="lstab-card-title"><?php esc_html_e( 'Colour rules', 'live-sheets-table-pro' ); ?></h2>
	<p class="lstab-help">
		<?php esc_html_e( 'Colour a cell by what is in it — “Sold out” red, “In stock” green, anything over a price in bold. The colours are worked out on the server, so they are in the page a visitor receives rather than applied afterwards by script.', 'live-sheets-table-pro' ); ?>
	</p>

	<?php if ( $lstabp_waiting ) : ?>
		<p class="lstab-columns-waiting">
			<?php
			echo esc_html(
				$is_edit
					? __( 'Waiting for a first look at the sheet, so there are no columns to choose from yet.', 'live-sheets-table-pro' )
					: __( 'Save this source first. Once its columns are known you can set rules on them.', 'live-sheets-table-pro' )
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

	<table class="lstab-column-list lstabp-rule-list">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'When this column', 'live-sheets-table-pro' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Condition', 'live-sheets-table-pro' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Value', 'live-sheets-table-pro' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Show it', 'live-sheets-table-pro' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Applies to', 'live-sheets-table-pro' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $lstabp_rows as $lstabp_index => $lstabp_rule ) : ?>
				<tr>
					<td>
						<select name="lstabp_rules[<?php echo esc_attr( (string) $lstabp_index ); ?>][column]" <?php disabled( $lstabp_waiting ); ?>>
							<option value=""><?php esc_html_e( '— no rule —', 'live-sheets-table-pro' ); ?></option>
							<?php foreach ( $headers as $lstabp_heading ) : ?>
								<option value="<?php echo esc_attr( $lstabp_heading ); ?>" <?php selected( $lstabp_rule['column'], $lstabp_heading ); ?>>
									<?php echo esc_html( $lstabp_heading ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
					<td>
						<select name="lstabp_rules[<?php echo esc_attr( (string) $lstabp_index ); ?>][operator]" <?php disabled( $lstabp_waiting ); ?>>
							<?php foreach ( $lstabp_operators as $lstabp_symbol => $lstabp_label ) : ?>
								<option value="<?php echo esc_attr( $lstabp_symbol ); ?>" <?php selected( $lstabp_rule['operator'], $lstabp_symbol ); ?>>
									<?php echo esc_html( $lstabp_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
					<td>
						<input type="text" class="regular-text"
							name="lstabp_rules[<?php echo esc_attr( (string) $lstabp_index ); ?>][value]"
							value="<?php echo esc_attr( $lstabp_rule['value'] ); ?>"
							<?php disabled( $lstabp_waiting ); ?>>
					</td>
					<td>
						<select class="lstabp-style-select" name="lstabp_rules[<?php echo esc_attr( (string) $lstabp_index ); ?>][style]" <?php disabled( $lstabp_waiting ); ?>>
							<?php foreach ( $lstabp_styles as $lstabp_key => $lstabp_style ) : ?>
								<option value="<?php echo esc_attr( $lstabp_key ); ?>" <?php selected( $lstabp_rule['style'], $lstabp_key ); ?>>
									<?php echo esc_html( $lstabp_style['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<span class="lstabp-swatch" style="<?php echo esc_attr( $lstabp_styles[ $lstabp_rule['style'] ]['css'] ); ?>"><?php esc_html_e( 'Abc', 'live-sheets-table-pro' ); ?></span>
					</td>
					<td>
						<select name="lstabp_rules[<?php echo esc_attr( (string) $lstabp_index ); ?>][scope]" <?php disabled( $lstabp_waiting ); ?>>
							<option value="cell" <?php selected( $lstabp_rule['scope'], 'cell' ); ?>><?php esc_html_e( 'That cell', 'live-sheets-table-pro' ); ?></option>
							<option value="row" <?php selected( $lstabp_rule['scope'], 'row' ); ?>><?php esc_html_e( 'The whole row', 'live-sheets-table-pro' ); ?></option>
						</select>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p class="lstab-help">
		<?php esc_html_e( 'Set a rule’s column back to “no rule” to remove it. Two spare rows are always kept at the bottom; save to get two more.', 'live-sheets-table-pro' ); ?>
	</p>
</div>
