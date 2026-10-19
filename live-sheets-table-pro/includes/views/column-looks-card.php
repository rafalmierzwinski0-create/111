<?php

defined( 'ABSPATH' ) || exit;

$lstabp_waiting = ! $lstabp_headers;
$lstabp_fields  = LSTABP_Column_Looks::fields();

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
		<?php esc_html_e( 'Give a column a look and every cell in it is shown that way, in every row. To colour only some values, for example “Out of stock” in red, use Colour rules instead.', 'live-sheets-table-pro' ); ?>
	</p>

	<input type="hidden" name="_lstabp_looks_present" value="1">

	<?php if ( $lstabp_waiting ) : ?>
		<p class="lstab-help">
			<?php esc_html_e( 'Paste the sheet’s link and choose “Load preview” first. The columns then appear here.', 'live-sheets-table-pro' ); ?>
		</p>
	<?php else : ?>
		<ul class="lstabp-looks" data-lstabp-fold="10">
			<?php foreach ( $lstabp_headers as $lstabp_index => $lstabp_heading ) : ?>
				<?php
				$lstabp_setting = isset( $lstabp_chosen[ $lstabp_heading ] )
					? $lstabp_chosen[ $lstabp_heading ]
					: array(
						'look'  => '',
						'tint'  => '',
						'ink'   => '',
						'label' => '',
					);

				$lstabp_field = 'lstabp_looks[' . (int) $lstabp_index . ']';
				$lstabp_is    = isset( $lstabp_setting['look'] ) ? $lstabp_setting['look'] : '';
				$lstabp_tint  = '' !== $lstabp_setting['tint'] ? $lstabp_setting['tint'] : LSTABP_Column_Looks::DEFAULT_TINT;
				$lstabp_ink   = '' !== $lstabp_setting['ink'] ? $lstabp_setting['ink'] : '#06100f';
				?>
				<li class="lstabp-look<?php echo $lstabp_is ? ' is-on' : ''; ?>" data-lstabp-look="<?php echo esc_attr( $lstabp_is ); ?>">
					<input type="hidden" class="lstabp-look-heading" name="<?php echo esc_attr( $lstabp_field ); ?>[heading]" value="<?php echo esc_attr( $lstabp_heading ); ?>">
					<details class="lstabp-look-box" name="lstabp-looks">
						<summary class="lstabp-look-head">
							<span class="lstabp-look-name"><?php echo esc_html( $lstabp_heading ); ?></span>

							<?php
							$lstabp_face_look = $lstabp_is;
							$lstabp_face_tint = $lstabp_tint;
							$lstabp_face_ink  = $lstabp_ink;
							$lstabp_face_says = $lstabp_setting['label'];
							?>
							<span class="lstabp-look-now">
								<?php if ( '' !== $lstabp_is ) : ?>
									<?php require __DIR__ . '/look-faces.php'; ?>
								<?php endif; ?>
								<span class="lstabp-look-now-name"><?php echo esc_html( $lstabp_offer[ $lstabp_is ] ); ?></span>
							</span>

							<span class="lstabp-look-more" aria-hidden="true"><?php esc_html_e( 'Change', 'live-sheets-table-pro' ); ?></span>
						</summary>

						<div class="lstabp-look-body">
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
										<span class="lstabp-look-colour-name"><?php esc_html_e( 'Text colour', 'live-sheets-table-pro' ); ?></span>
										<input type="color" class="lstabp-look-ink" name="<?php echo esc_attr( $lstabp_field ); ?>[ink]"
											value="<?php echo esc_attr( $lstabp_ink ); ?>">
									</label>
								</span>

								<span class="lstabp-look-colour" data-lstabp-for="<?php echo esc_attr( implode( ' ', $lstabp_wants['label'] ) ); ?>">
									<label>
										<span class="lstabp-look-colour-name"><?php esc_html_e( 'Button text', 'live-sheets-table-pro' ); ?></span>
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

		<?php
		$lstabp_any = false;

		foreach ( $lstabp_chosen as $lstabp_set ) {
			if ( ! empty( $lstabp_set['look'] ) ) {
				$lstabp_any = true;
				break;
			}
		}
		?>
		<p class="lstabp-looks-reset-line">
			<button type="submit" class="lstab-mini lstabp-looks-reset" name="lstabp_looks_reset" value="1"
				data-lstabp-default-tint="<?php echo esc_attr( LSTABP_Column_Looks::DEFAULT_TINT ); ?>"
				<?php disabled( ! $lstabp_any ); ?>>
				<?php esc_html_e( 'Set every column back to ordinary', 'live-sheets-table-pro' ); ?>
			</button>
		</p>

		<?php
		$lstabp_how = array(
			'bar'    => __( 'The bigger the number, the longer the bar, so rows can be compared at a glance. Cells without a number stay plain.', 'live-sheets-table-pro' ),
			'pill'   => __( 'Each value sits in a small rounded label, like a tag. Best for short words such as a status or a category.', 'live-sheets-table-pro' ),
			'tint'   => __( 'Every cell in the column gets the colour you pick, so one column stands out, for example a total.', 'live-sheets-table-pro' ),
			'button' => __( 'A cell holding a web address (https://…) becomes a button with the words you type in “Button text”. Cells without an address stay as they are.', 'live-sheets-table-pro' ),
		);
		?>
		<ul class="lstabp-rules-how lstabp-looks-how">
			<?php foreach ( $lstabp_how as $lstabp_how_look => $lstabp_how_text ) : ?>
				<?php
				$lstabp_face_look = $lstabp_how_look;
				$lstabp_face_tint = LSTABP_Column_Looks::DEFAULT_TINT;
				$lstabp_face_ink  = '#06100f';
				$lstabp_face_says = '';
				?>
				<li>
					<?php require __DIR__ . '/look-faces.php'; ?>
					<span>
						<b><?php echo esc_html( $lstabp_looks[ $lstabp_how_look ] ); ?>.</b>
						<?php echo esc_html( $lstabp_how_text ); ?>
					</span>
				</li>
			<?php endforeach; ?>
			<li>
				<?php echo LSTAB_Icons::icon( 'brush' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
				<span>
					<?php esc_html_e( 'If a colour rule also matches a cell, the rule’s colour wins for that cell. That way one row can still stand out in a column that has a look.', 'live-sheets-table-pro' ); ?>
				</span>
			</li>
		</ul>
	<?php endif; ?>
</div>
