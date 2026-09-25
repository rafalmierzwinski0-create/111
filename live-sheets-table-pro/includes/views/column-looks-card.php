<?php
/**
 * The card where a column is given a look of its own.
 *
 * @package LiveSheetsTablePro
 *
 * @var array<string,array<string,string>> $lstabp_chosen  What is stored.
 * @var array<string,string>               $lstabp_looks   The looks on offer.
 * @var array<int,string>                  $lstabp_headers The sheet's headings.
 */

defined( 'ABSPATH' ) || exit;

$lstabp_waiting = ! $lstabp_headers;
?>
<div class="lstab-card lstabp-looks-card<?php echo $lstabp_waiting ? ' is-waiting' : ''; ?>">
	<h2 class="lstab-card-title"><?php esc_html_e( 'Column looks', 'live-sheets-table-pro' ); ?></h2>
	<p class="lstab-help">
		<?php esc_html_e( 'A colour rule says one value is special. This says what a whole column is: a measurement to be seen at a glance, or a way through to somewhere else.', 'live-sheets-table-pro' ); ?>
	</p>

	<?php // Present even when nothing is ticked, so clearing the last one saves. ?>
	<input type="hidden" name="_lstabp_looks_present" value="1">

	<?php if ( $lstabp_waiting ) : ?>
		<p class="lstab-help">
			<?php esc_html_e( 'Load the sheet first — a column cannot be given a look before its columns are known.', 'live-sheets-table-pro' ); ?>
		</p>
	<?php else : ?>
		<ul class="lstabp-looks">
			<?php foreach ( $lstabp_headers as $lstabp_heading ) : ?>
				<?php
				$lstabp_setting = isset( $lstabp_chosen[ $lstabp_heading ] )
					? $lstabp_chosen[ $lstabp_heading ]
					: array(
						'look'  => '',
						'tint'  => '',
						'ink'   => '',
						'label' => '',
					);

				$lstabp_field = 'lstabp_looks[' . $lstabp_heading . ']';
				$lstabp_is    = isset( $lstabp_setting['look'] ) ? $lstabp_setting['look'] : '';
				?>
				<li class="lstabp-look<?php echo $lstabp_is ? ' is-on' : ''; ?>" data-lstabp-look="<?php echo esc_attr( $lstabp_is ); ?>">
					<span class="lstabp-look-name"><?php echo esc_html( $lstabp_heading ); ?></span>

					<select class="lstabp-look-pick" name="<?php echo esc_attr( $lstabp_field ); ?>[look]">
						<option value=""><?php esc_html_e( 'Ordinary', 'live-sheets-table-pro' ); ?></option>
						<?php foreach ( $lstabp_looks as $lstabp_key => $lstabp_label ) : ?>
							<option value="<?php echo esc_attr( $lstabp_key ); ?>" <?php selected( $lstabp_is, $lstabp_key ); ?>>
								<?php echo esc_html( $lstabp_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<?php
					/*
					 * Both colours are offered for both looks and only the ones
					 * that mean something are shown, by the script. A bar has
					 * no words of its own, so it has no ink; a button has both.
					 */
					?>
					<span class="lstabp-look-colour" data-lstabp-for="bar button">
						<label>
							<span class="lstabp-look-colour-name"><?php esc_html_e( 'Colour', 'live-sheets-table-pro' ); ?></span>
							<input type="color" name="<?php echo esc_attr( $lstabp_field ); ?>[tint]"
								value="<?php echo esc_attr( '' !== $lstabp_setting['tint'] ? $lstabp_setting['tint'] : '#5fe3cf' ); ?>">
						</label>
					</span>

					<span class="lstabp-look-colour" data-lstabp-for="button">
						<label>
							<span class="lstabp-look-colour-name"><?php esc_html_e( 'Text', 'live-sheets-table-pro' ); ?></span>
							<input type="color" name="<?php echo esc_attr( $lstabp_field ); ?>[ink]"
								value="<?php echo esc_attr( '' !== $lstabp_setting['ink'] ? $lstabp_setting['ink'] : '#06100f' ); ?>">
						</label>
					</span>

					<span class="lstabp-look-colour" data-lstabp-for="button">
						<label>
							<span class="lstabp-look-colour-name"><?php esc_html_e( 'Says', 'live-sheets-table-pro' ); ?></span>
							<input type="text" class="lstabp-look-label" name="<?php echo esc_attr( $lstabp_field ); ?>[label]"
								value="<?php echo esc_attr( $lstabp_setting['label'] ); ?>"
								maxlength="<?php echo esc_attr( (string) LSTABP_Column_Looks::MAX_LABEL ); ?>"
								placeholder="<?php esc_attr_e( 'Open', 'live-sheets-table-pro' ); ?>">
						</label>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>

		<ul class="lstabp-rules-how">
			<li>
				<?php echo LSTAB_Icons::icon( 'layers' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
				<?php esc_html_e( 'A bar is as long as its number is large, measured against the largest in the column. A column that never goes below zero is measured from zero, so numbers that are all much the same do not look wildly different.', 'live-sheets-table-pro' ); ?>
			</li>
			<li>
				<?php echo LSTAB_Icons::icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
				<?php esc_html_e( 'Only a cell holding a web address becomes a button. A note or a blank in the same column is left as it is, because a button that goes nowhere is worse than the note it replaced.', 'live-sheets-table-pro' ); ?>
			</li>
		</ul>
	<?php endif; ?>
</div>
