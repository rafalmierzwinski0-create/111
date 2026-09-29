<?php
/**
 * The card where a column is given a look of its own.
 *
 * One line per column, closed. The line says the column's name and shows what
 * that column looks like now — the thing itself, small, not a word for it. The
 * choices open only on the line being worked on.
 *
 * The first attempt put all five choices on every line at once. On a sheet
 * with twenty columns that is a hundred little pictures, the same five
 * repeated twenty times, and the column names — the only thing that differs
 * down the card, and the only thing anybody is looking for — were the quietest
 * marks on the screen. A chooser has to be opened to be read; a list has to be
 * read without being opened.
 *
 * @package LiveSheetsTablePro
 *
 * @var array<string,array<string,string>> $lstabp_chosen  What is stored.
 * @var array<string,string>               $lstabp_looks   The looks on offer.
 * @var array<int,string>                  $lstabp_headers The sheet's headings.
 */

defined( 'ABSPATH' ) || exit;

$lstabp_waiting = ! $lstabp_headers;
$lstabp_fields  = LSTABP_Column_Looks::fields();

/*
 * Which look wants which field, as one attribute per field. A sheet with a
 * dozen columns draws this list a dozen times, so it is worked out once.
 */
$lstabp_wants = array(
	'tint'  => array(),
	'ink'   => array(),
	'label' => array(),
);

foreach ( $lstabp_fields as $lstabp_look_key => $lstabp_look_fields ) {
	foreach ( $lstabp_look_fields as $lstabp_look_field ) {
		$lstabp_wants[ $lstabp_look_field ][] = $lstabp_look_key;
	}
}

$lstabp_offer = array( '' => __( 'Ordinary', 'live-sheets-table-pro' ) ) + $lstabp_looks;
?>
<div class="lstab-card lstabp-looks-card<?php echo $lstabp_waiting ? ' is-waiting' : ''; ?>">
	<h2 class="lstab-card-title"><?php esc_html_e( 'Column looks', 'live-sheets-table-pro' ); ?></h2>
	<p class="lstab-help">
		<?php esc_html_e( 'A colour rule says one value is special. This says what a whole column is: a measurement to be seen at a glance, a set of labels, a column that belongs in a colour of its own, or a way through to somewhere else.', 'live-sheets-table-pro' ); ?>
	</p>

	<?php // Present even when nothing is ticked, so clearing the last one saves. ?>
	<input type="hidden" name="_lstabp_looks_present" value="1">

	<?php if ( $lstabp_waiting ) : ?>
		<p class="lstab-help">
			<?php esc_html_e( 'Load the sheet first — a column cannot be given a look before its columns are known.', 'live-sheets-table-pro' ); ?>
		</p>
	<?php else : ?>
		<ul class="lstabp-looks" data-lstabp-fold="10">
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
				$lstabp_tint  = '' !== $lstabp_setting['tint'] ? $lstabp_setting['tint'] : LSTABP_Column_Looks::DEFAULT_TINT;
				$lstabp_ink   = '' !== $lstabp_setting['ink'] ? $lstabp_setting['ink'] : '#06100f';
				?>
				<li class="lstabp-look<?php echo $lstabp_is ? ' is-on' : ''; ?>" data-lstabp-look="<?php echo esc_attr( $lstabp_is ); ?>">
					<?php
					/*
					 * Named, so the browser closes the last one when the next
					 * is opened. Twenty columns all open at once is the wall
					 * this card was rewritten to stop being, and nobody sets
					 * two columns at the same moment. A browser too old to
					 * know the attribute simply lets them all stay open, which
					 * is the behaviour this replaced and still works.
					 */
					?>
					<details class="lstabp-look-box" name="lstabp-looks">
						<summary class="lstabp-look-head">
							<span class="lstabp-look-name"><?php echo esc_html( $lstabp_heading ); ?></span>

							<?php
							/*
							 * What this column looks like now, drawn rather
							 * than named — and named as well, because a bar
							 * and a painted cell are easy to tell apart and a
							 * pill and a button are not, at this size.
							 */
							$lstabp_face_look = $lstabp_is;
							$lstabp_face_tint = $lstabp_tint;
							$lstabp_face_ink  = $lstabp_ink;
							$lstabp_face_says = $lstabp_setting['label'];
							?>
							<span class="lstabp-look-now">
								<?php
								/*
								 * Nothing is drawn for a column that is
								 * ordinary. Most of them are, and twenty
								 * identical grey boxes saying the same number
								 * is the very repetition this card was
								 * rewritten to stop: a drawing earns its place
								 * when there is something to show.
								 */
								?>
								<?php if ( '' !== $lstabp_is ) : ?>
									<?php require __DIR__ . '/look-faces.php'; ?>
								<?php endif; ?>
								<span class="lstabp-look-now-name"><?php echo esc_html( $lstabp_offer[ $lstabp_is ] ); ?></span>
							</span>

							<span class="lstabp-look-more" aria-hidden="true"><?php esc_html_e( 'Change', 'live-sheets-table-pro' ); ?></span>
						</summary>

						<div class="lstabp-look-body">
							<?php
							/*
							 * A radio group rather than a select. Both submit
							 * the same one value under the same name, so the
							 * save and the preview never knew the difference —
							 * but only one of them can show what it offers.
							 */
							?>
							<span class="lstabp-look-picks" role="radiogroup"
								aria-label="
								<?php
								printf(
									/* translators: %s: a column heading. */
									esc_attr__( 'What the %s column looks like', 'live-sheets-table-pro' ),
									esc_attr( $lstabp_heading )
								);
								?>
								">
								<?php
								foreach ( $lstabp_offer as $lstabp_key => $lstabp_label ) :
									$lstabp_face_look = (string) $lstabp_key;
									$lstabp_face_tint = $lstabp_tint;
									$lstabp_face_ink  = $lstabp_ink;
									$lstabp_face_says = $lstabp_setting['label'];
									?>
									<label class="lstabp-look-opt<?php echo $lstabp_is === (string) $lstabp_key ? ' is-picked' : ''; ?>">
										<input type="radio" class="lstabp-look-pick"
											name="<?php echo esc_attr( $lstabp_field ); ?>[look]"
											value="<?php echo esc_attr( (string) $lstabp_key ); ?>"
											<?php checked( $lstabp_is, (string) $lstabp_key ); ?>>
										<?php require __DIR__ . '/look-faces.php'; ?>
										<span class="lstabp-look-word"><?php echo esc_html( $lstabp_label ); ?></span>
									</label>
								<?php endforeach; ?>
							</span>

							<?php
							/*
							 * Every field is offered to every look and only the
							 * ones that mean something are shown, by the
							 * stylesheet reading the look off the row. A bar
							 * has no words of its own, so it has no ink and
							 * nothing to say.
							 */
							?>
							<span class="lstabp-look-fields">
								<span class="lstabp-look-colour" data-lstabp-for="<?php echo esc_attr( implode( ' ', $lstabp_wants['tint'] ) ); ?>">
									<label>
										<span class="lstabp-look-colour-name"><?php esc_html_e( 'Colour', 'live-sheets-table-pro' ); ?></span>
										<input type="color" class="lstabp-look-tint" name="<?php echo esc_attr( $lstabp_field ); ?>[tint]"
											value="<?php echo esc_attr( $lstabp_tint ); ?>">
									</label>
								</span>

								<span class="lstabp-look-colour" data-lstabp-for="<?php echo esc_attr( implode( ' ', $lstabp_wants['ink'] ) ); ?>">
									<label>
										<span class="lstabp-look-colour-name"><?php esc_html_e( 'Text', 'live-sheets-table-pro' ); ?></span>
										<input type="color" class="lstabp-look-ink" name="<?php echo esc_attr( $lstabp_field ); ?>[ink]"
											value="<?php echo esc_attr( $lstabp_ink ); ?>">
									</label>
								</span>

								<span class="lstabp-look-colour" data-lstabp-for="<?php echo esc_attr( implode( ' ', $lstabp_wants['label'] ) ); ?>">
									<label>
										<span class="lstabp-look-colour-name"><?php esc_html_e( 'Says', 'live-sheets-table-pro' ); ?></span>
										<input type="text" class="lstabp-look-label" name="<?php echo esc_attr( $lstabp_field ); ?>[label]"
											value="<?php echo esc_attr( $lstabp_setting['label'] ); ?>"
											maxlength="<?php echo esc_attr( (string) LSTABP_Column_Looks::MAX_LABEL ); ?>"
											placeholder="<?php esc_attr_e( 'Open', 'live-sheets-table-pro' ); ?>">
									</label>
								</span>
							</span>
						</div>
					</details>
				</li>
			<?php endforeach; ?>
		</ul>

		<ul class="lstabp-rules-how">
			<li>
				<?php echo LSTAB_Icons::icon( 'layers' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
				<?php esc_html_e( 'A bar is as long as its number is large, measured against the largest in the column. A column that never goes below zero is measured from zero, so numbers that are all much the same do not look wildly different.', 'live-sheets-table-pro' ); ?>
			</li>
			<li>
				<?php echo LSTAB_Icons::icon( 'brush' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
				<?php esc_html_e( 'A pill and a whole-column colour dress every row the same, whatever the value says — that is the difference from a colour rule, which dresses only the rows that match it. Where both have something to say about the same cell the rule wins, so a rule can still single one row out of a column that already has a look.', 'live-sheets-table-pro' ); ?>
			</li>
			<li>
				<?php echo LSTAB_Icons::icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
				<?php esc_html_e( 'Only a cell holding a web address becomes a button. A note or a blank in the same column is left as it is, because a button that goes nowhere is worse than the note it replaced.', 'live-sheets-table-pro' ); ?>
			</li>
		</ul>
	<?php endif; ?>
</div>
