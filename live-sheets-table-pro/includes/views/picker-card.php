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
 * @var array<int,array>             $hidden  Stored choices about rows.
 * @var array<int,bool>              $dropped Positions currently taken out.
 * @var int                          $offset  Lines of the sheet above the first row.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="lstab-card lstabp-picker-card">
	<h2 class="lstab-card-title"><?php esc_html_e( 'Hide columns and rows', 'live-sheets-table-pro' ); ?></h2>
	<p class="lstab-help">
		<?php esc_html_e( 'Click a heading to take that column out of the table, or a line number to take that row out. Click again to put it back. Nothing is written to Google — your spreadsheet keeps everything, the table just stops showing it.', 'live-sheets-table-pro' ); ?>
	</p>

	<?php
	/*
	 * Said before anything is clicked, not after something has gone wrong. A
	 * choice is remembered as a line and a heading, so moving either in Google
	 * breaks it — and someone who knows that in advance can decide whether to
	 * reorder their sheet, which is a much better position than finding out
	 * from a notice afterwards.
	 */
	?>
	<div class="notice notice-info inline lstabp-picker-note">
		<p>
			<strong><?php esc_html_e( 'If you move it in Google, it comes back.', 'live-sheets-table-pro' ); ?></strong>
		</p>
		<p>
			<?php esc_html_e( 'A column is remembered by its heading and a row by the line it is on. Reordering your columns, renaming a heading, or inserting a line above a hidden row means the choice no longer matches what is there — so nothing is taken out rather than the wrong thing, that row or column is on the page again, and the dashboard tells you which. Point at it again to put it back.', 'live-sheets-table-pro' ); ?>
		</p>
	</div>

	<div class="lstabp-picker-scroll">
		<table class="lstabp-picker" id="lstabp-picker">
			<thead>
				<tr>
					<th class="lstabp-picker-corner" scope="col">
						<span class="screen-reader-text"><?php esc_html_e( 'Line', 'live-sheets-table-pro' ); ?></span>
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
											/* translators: %s: column letter, as Google labels it. */
											? sprintf( __( 'Column %s', 'live-sheets-table-pro' ), LSTAB_Columns::letter( $lstabp_i ) )
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
					$lstabp_off  = isset( $dropped[ $lstabp_r ] );
					$lstabp_line = $offset + $lstabp_r + 1;
					?>
					<tr class="lstabp-picker-row<?php echo $lstabp_off ? ' is-hidden' : ''; ?>"
						data-lstabp-index="<?php echo esc_attr( (string) $lstabp_r ); ?>"
						data-lstabp-name="<?php echo esc_attr( LSTAB_Hidden_Rows::key_for( $lstabp_row ) ); ?>"
						data-lstabp-sig="<?php echo esc_attr( LSTAB_Hidden_Rows::signature( $lstabp_row ) ); ?>"
						data-lstabp-label="<?php echo esc_attr( LSTAB_Hidden_Rows::describe( $lstabp_row ) ); ?>"
						data-lstabp-line="<?php echo esc_attr( (string) $lstabp_line ); ?>">
						<th scope="row" class="lstabp-picker-handle">
							<button type="button"
								class="lstabp-picker-toggle"
								data-lstabp-row="<?php echo esc_attr( (string) $lstabp_r ); ?>"
								aria-pressed="<?php echo $lstabp_off ? 'true' : 'false'; ?>">
								<span class="lstabp-picker-number"><?php echo esc_html( (string) $lstabp_line ); ?></span>
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
			<?php esc_html_e( 'No rows taken out.', 'live-sheets-table-pro' ); ?>
		</p>
		<ul class="lstabp-chips" id="lstabp-hidden-rows-chips"></ul>
	</div>

	<?php
	/*
	 * Says the picker was on the screen, so that a save made anywhere else
	 * leaves the stored list alone rather than reading "no fields submitted"
	 * as "nothing is hidden".
	 */
	?>
	<input type="hidden" name="_lstab_hidden_rows_present" value="1">

	<div id="lstabp-hidden-rows-fields" hidden>
		<?php foreach ( $hidden as $lstabp_index => $lstabp_entry ) : ?>
			<?php $lstabp_live = LSTAB_Hidden_Rows::still_there( $lstabp_entry, $rows ); ?>
			<?php foreach ( array( 'index', 'name', 'sig', 'label' ) as $lstabp_part ) : ?>
				<input type="hidden"
					name="hidden_rows[<?php echo esc_attr( (string) $lstabp_index ); ?>][<?php echo esc_attr( $lstabp_part ); ?>]"
					value="<?php echo esc_attr( (string) $lstabp_entry[ $lstabp_part ] ); ?>"
					<?php if ( 'index' === $lstabp_part ) : ?>
						data-lstabp-present="<?php echo $lstabp_live ? '1' : '0'; ?>"
						data-lstabp-name="<?php echo esc_attr( $lstabp_entry['name'] ); ?>"
						data-lstabp-sig="<?php echo esc_attr( $lstabp_entry['sig'] ); ?>"
						data-lstabp-label="<?php echo esc_attr( $lstabp_entry['label'] ); ?>"
						data-lstabp-line="<?php echo esc_attr( (string) ( $offset + (int) $lstabp_entry['index'] + 1 ) ); ?>"
					<?php endif; ?>
				>
			<?php endforeach; ?>
		<?php endforeach; ?>
	</div>
</div>
