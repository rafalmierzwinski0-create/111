<?php
/**
 * The sheet drawn as a control: click what you want gone.
 *
 * @package LiveSheetsTablePro
 *
 * @var array<int,string>            $headers Sheet headings.
 * @var array<int,array<int,string>> $rows    Every row of the sheet.
 * @var array<int,array<int,string>> $shown   The rows offered for clicking.
 * @var array<int,array>             $columns Column settings.
 * @var array<int,string>            $hidden  Keys of hidden rows.
 * @var array<string,int>            $counts  How many rows answer to each key.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="lstab-card lstabp-picker-card">
	<h2 class="lstab-card-title"><?php esc_html_e( 'Hide columns and rows', 'live-sheets-table-pro' ); ?></h2>
	<p class="lstab-help">
		<?php esc_html_e( 'Click a heading to drop that column, or a row to drop that row. Click again to bring it back. Nothing is written to Google — your spreadsheet keeps everything, the table just stops showing it.', 'live-sheets-table-pro' ); ?>
	</p>

	<div class="lstabp-picker-scroll">
		<table class="lstabp-picker" id="lstabp-picker">
			<thead>
				<tr>
					<th class="lstabp-picker-corner" scope="col">
						<span class="screen-reader-text"><?php esc_html_e( 'Row', 'live-sheets-table-pro' ); ?></span>
					</th>
					<?php foreach ( $headers as $lstabp_i => $lstabp_head ) : ?>
						<?php $lstabp_col_hidden = ! empty( $columns[ $lstabp_i ]['hidden'] ); ?>
						<th scope="col" class="lstabp-picker-col<?php echo $lstabp_col_hidden ? ' is-hidden' : ''; ?>">
							<button type="button"
								class="lstabp-picker-toggle"
								data-lstabp-column="<?php echo esc_attr( (string) $lstabp_i ); ?>"
								aria-pressed="<?php echo $lstabp_col_hidden ? 'true' : 'false'; ?>">
								<span class="lstabp-picker-name">
									<?php
									echo esc_html(
										'' === trim( (string) $lstabp_head )
											/* translators: %d: column number. */
											? sprintf( __( 'Column %d', 'live-sheets-table-pro' ), (int) $lstabp_i + 1 )
											: $lstabp_head
									);
									?>
								</span>
								<span class="lstabp-picker-mark" aria-hidden="true"></span>
							</button>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $shown as $lstabp_r => $lstabp_row ) : ?>
					<?php
					$lstabp_key    = LSTAB_Hidden_Rows::key_for( $lstabp_row );
					$lstabp_off    = LSTAB_Hidden_Rows::is_hidden( $lstabp_row, $hidden );
					$lstabp_shared = isset( $counts[ $lstabp_key ] ) ? (int) $counts[ $lstabp_key ] : 1;
					?>
					<tr class="lstabp-picker-row<?php echo $lstabp_off ? ' is-hidden' : ''; ?>"
						data-lstabp-key="<?php echo esc_attr( $lstabp_key ); ?>"
						data-lstabp-shared="<?php echo esc_attr( (string) $lstabp_shared ); ?>">
						<th scope="row" class="lstabp-picker-handle">
							<button type="button"
								class="lstabp-picker-toggle"
								data-lstabp-row="<?php echo esc_attr( $lstabp_key ); ?>"
								aria-pressed="<?php echo $lstabp_off ? 'true' : 'false'; ?>"
								<?php disabled( '' === $lstabp_key ); ?>
								<?php if ( $lstabp_shared > 1 ) : ?>
									title="<?php echo esc_attr( sprintf( /* translators: %d: number of rows. */ __( '%d rows say this, and they are one choice: hiding it hides all of them.', 'live-sheets-table-pro' ), $lstabp_shared ) ); ?>"
								<?php endif; ?>>
								<span class="lstabp-picker-number"><?php echo esc_html( (string) ( $lstabp_r + 1 ) ); ?></span>
								<?php if ( $lstabp_shared > 1 ) : ?>
									<span class="lstabp-picker-shared" aria-hidden="true">×<?php echo esc_html( (string) $lstabp_shared ); ?></span>
								<?php endif; ?>
								<span class="lstabp-picker-mark" aria-hidden="true"></span>
							</button>
						</th>
						<?php foreach ( $headers as $lstabp_i => $lstabp_unused ) : ?>
							<?php $lstabp_col_hidden = ! empty( $columns[ $lstabp_i ]['hidden'] ); ?>
							<td class="lstabp-picker-cell<?php echo $lstabp_col_hidden ? ' is-hidden' : ''; ?>"
								data-lstabp-column="<?php echo esc_attr( (string) $lstabp_i ); ?>">
								<?php echo esc_html( isset( $lstabp_row[ $lstabp_i ] ) ? $lstabp_row[ $lstabp_i ] : '' ); ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php if ( count( $rows ) > LSTABP_Picker::MAX_ROWS ) : ?>
		<p class="lstab-help">
			<?php
			printf(
				/* translators: 1: rows shown here, 2: rows in the sheet. */
				esc_html__( 'Showing the first %1$d of %2$d rows. Picking rows one at a time past that point is not really the tool for the job — the filter above is.', 'live-sheets-table-pro' ),
				(int) LSTABP_Picker::MAX_ROWS,
				count( $rows )
			);
			?>
		</p>
	<?php endif; ?>

	<div class="lstabp-picker-summary">
		<p class="lstabp-picker-empty"<?php echo $hidden ? ' hidden' : ''; ?>>
			<?php esc_html_e( 'No rows hidden.', 'live-sheets-table-pro' ); ?>
		</p>
		<ul class="lstabp-chips" id="lstabp-hidden-rows-chips"></ul>
	</div>

	<?php
	/*
	 * Rewritten by the script as you click, and submitted with the rest of the
	 * form. A key that no longer matches anything in the sheet is kept rather
	 * than dropped: the row may be back tomorrow, and silently forgetting a
	 * choice someone made is worse than carrying one that does nothing today.
	 */
	?>
	<?php
	/*
	 * Says that the picker was on the screen. Without it the free plugin leaves
	 * the stored list alone, so someone editing a source on a site where this
	 * add-on is inactive does not silently lose rows they hid.
	 */
	?>
	<input type="hidden" name="_lstab_hidden_rows_present" value="1">

	<div id="lstabp-hidden-rows-fields" hidden>
		<?php foreach ( $hidden as $lstabp_key ) : ?>
			<input type="hidden" name="hidden_rows[]"
				value="<?php echo esc_attr( $lstabp_key ); ?>"
				data-lstabp-present="<?php echo isset( $counts[ $lstabp_key ] ) ? '1' : '0'; ?>">
		<?php endforeach; ?>
	</div>
</div>
