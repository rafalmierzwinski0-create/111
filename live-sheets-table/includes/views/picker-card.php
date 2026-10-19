<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="lstab-card lstab-picker-card">
	<h2 class="lstab-card-title"><?php esc_html_e( 'Hide columns and rows', 'live-sheets-table' ); ?></h2>
	<p class="lstab-help">
		<?php esc_html_e( 'Click a column name to hide that column, or a line number to hide that row. Click it again to bring it back. Changes apply when you save. Your sheet in Google is not changed.', 'live-sheets-table' ); ?>
	</p>

	<div class="notice notice-info inline lstab-picker-note">
		<p>
			<strong><?php esc_html_e( 'Changing the order in Google can bring a hidden column or row back', 'live-sheets-table' ); ?></strong>
		</p>
		<p>
			<?php esc_html_e( 'The plugin remembers a hidden column by its place and its name, and a hidden row by its line number and what it holds. If you move columns, rename a column or add or remove rows above a hidden row in Google, they no longer match.', 'live-sheets-table' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'When that happens, the column or row is shown again, so that a different one is never hidden by mistake. A notice in the dashboard tells you which table to check, and you can hide it again here.', 'live-sheets-table' ); ?>
		</p>
	</div>

	<p class="lstab-picker-legend">
		<span class="lstab-picker-legend-chip"><span class="lstab-picker-legend-mark" aria-hidden="true">×</span><?php esc_html_e( 'Click a column name or a line number to hide it', 'live-sheets-table' ); ?></span>
		<span class="lstab-picker-legend-chip"><span class="lstab-picker-legend-mark lstab-picker-legend-mark--back" aria-hidden="true">↺</span><?php esc_html_e( 'Click it again to put it back', 'live-sheets-table' ); ?></span>
		<span class="lstab-picker-legend-chip"><span class="lstab-picker-legend-mark lstab-picker-legend-mark--detail" aria-hidden="true"></span><?php esc_html_e( 'Click the arrow to tuck a column away: visitors open it under each row with “Show details”', 'live-sheets-table' ); ?></span>
	</p>

	<p class="lstab-help lstab-picker-lines">
		<?php esc_html_e( 'The numbers down the left are the row numbers in Google Sheets. Line 1 is usually the row of column names.', 'live-sheets-table' ); ?>
	</p>

	<div class="lstab-picker-scroll">
		<table class="lstab-picker" id="lstab-picker">
			<thead>
				<tr>
					<th class="lstab-picker-corner" scope="col">
						<span class="screen-reader-text"><?php esc_html_e( 'Line', 'live-sheets-table' ); ?></span>
					</th>
					<?php foreach ( $headers as $lstab_i => $lstab_head ) : ?>
						<?php
						$lstab_col_hidden = ! empty( $columns[ $lstab_i ]['hidden'] );
						$lstab_col_detail = ! empty( $columns[ $lstab_i ]['detail'] );
						$lstab_col_name   = '' === trim( (string) $lstab_head )
							/* translators: %s: column letter, as Google labels it. */
							? sprintf( __( 'Column %s', 'live-sheets-table' ), LSTAB_Columns::letter( $lstab_i ) )
							: (string) $lstab_head;
						?>
						<th scope="col" class="lstab-picker-col<?php echo $lstab_col_hidden ? ' is-hidden' : ''; ?><?php echo $lstab_col_detail ? ' is-detail' : ''; ?>">
							<button type="button"
								class="lstab-picker-toggle"
								data-lstab-pick-column="<?php echo esc_attr( (string) $lstab_i ); ?>"
								aria-pressed="<?php echo $lstab_col_hidden ? 'true' : 'false'; ?>">
								<span class="lstab-picker-name"><?php echo esc_html( $lstab_col_name ); ?></span>
								<span class="lstab-picker-mark" aria-hidden="true"></span>
							</button>
							<button type="button"
								class="lstab-picker-detail"
								data-lstab-pick-detail="<?php echo esc_attr( (string) $lstab_i ); ?>"
								aria-pressed="<?php echo $lstab_col_detail ? 'true' : 'false'; ?>"
								<?php disabled( $lstab_col_hidden ); ?>
								title="<?php echo esc_attr( sprintf( /* translators: %s: column name. */ __( 'Move “%s” under the row, behind an arrow', 'live-sheets-table' ), $lstab_col_name ) ); ?>">
								<span class="screen-reader-text">
									<?php
									printf(
										/* translators: %s: column name. */
										esc_html__( 'Move “%s” under the row, behind an arrow', 'live-sheets-table' ),
										esc_html( $lstab_col_name )
									);
									?>
								</span>
								<span class="lstab-picker-chevron" aria-hidden="true"></span>
							</button>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $shown as $lstab_r => $lstab_row ) : ?>
					<?php
					$lstab_off  = isset( $dropped[ $lstab_r ] );
					$lstab_line = $offset + $lstab_r + 1;
					?>
					<tr class="lstab-picker-row<?php echo $lstab_off ? ' is-hidden' : ''; ?>"
						data-lstab-pick-index="<?php echo esc_attr( (string) $lstab_r ); ?>"
						data-lstab-pick-name="<?php echo esc_attr( LSTAB_Hidden_Rows::key_for( $lstab_row ) ); ?>"
						data-lstab-pick-sig="<?php echo esc_attr( LSTAB_Hidden_Rows::signature( $lstab_row ) ); ?>"
						data-lstab-pick-label="<?php echo esc_attr( LSTAB_Hidden_Rows::describe( $lstab_row ) ); ?>"
						data-lstab-pick-line="<?php echo esc_attr( (string) $lstab_line ); ?>">
						<th scope="row" class="lstab-picker-handle">
							<button type="button"
								class="lstab-picker-toggle"
								data-lstab-pick-row="<?php echo esc_attr( (string) $lstab_r ); ?>"
								aria-pressed="<?php echo $lstab_off ? 'true' : 'false'; ?>">
								<span class="lstab-picker-number"><?php echo esc_html( (string) $lstab_line ); ?></span>
								<span class="lstab-picker-mark" aria-hidden="true"></span>
							</button>
						</th>
						<?php foreach ( $headers as $lstab_i => $lstab_unused ) : ?>
							<?php
							$lstab_col_hidden = ! empty( $columns[ $lstab_i ]['hidden'] );
							$lstab_col_detail = ! empty( $columns[ $lstab_i ]['detail'] );
							?>
							<td class="lstab-picker-cell<?php echo $lstab_col_hidden ? ' is-hidden' : ''; ?><?php echo $lstab_col_detail ? ' is-detail' : ''; ?>"
								data-lstab-pick-column="<?php echo esc_attr( (string) $lstab_i ); ?>">
								<?php echo esc_html( isset( $lstab_row[ $lstab_i ] ) ? $lstab_row[ $lstab_i ] : '' ); ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php if ( count( $rows ) > LSTAB_Picker::MAX_ROWS ) : ?>
		<p class="lstab-help">
			<?php
			printf(
				/* translators: 1: rows shown here, 2: rows in the sheet. */
				esc_html__( 'Showing the first %1$d of %2$d rows.', 'live-sheets-table' ),
				(int) LSTAB_Picker::MAX_ROWS,
				count( $rows )
			);
			?>
		</p>
	<?php endif; ?>

	<div class="lstab-picker-paging" id="lstab-picker-paging" hidden>
		<span class="lstab-picker-page-group" data-lstab-pick-page-for="rows" hidden>
			<button type="button" class="lstab-mini" data-lstab-pick-step="rows:-1">&larr;</button>
			<span class="lstab-picker-page-label" data-lstab-pick-page-label="rows"></span>
			<button type="button" class="lstab-mini" data-lstab-pick-step="rows:1">&rarr;</button>
		</span>
		<span class="lstab-picker-page-group" data-lstab-pick-page-for="cols" hidden>
			<button type="button" class="lstab-mini" data-lstab-pick-step="cols:-1">&larr;</button>
			<span class="lstab-picker-page-label" data-lstab-pick-page-label="cols"></span>
			<button type="button" class="lstab-mini" data-lstab-pick-step="cols:1">&rarr;</button>
		</span>
	</div>

	<div class="lstab-picker-summary">
		<p class="lstab-picker-empty"<?php echo $hidden ? ' hidden' : ''; ?>>
			<?php esc_html_e( 'No rows hidden yet.', 'live-sheets-table' ); ?>
		</p>
		<ul class="lstab-picker-chips" id="lstab-hidden-rows-chips"></ul>
	</div>

	<input type="hidden" name="_lstab_hidden_rows_present" value="1">

	<div id="lstab-hidden-rows-fields" hidden>
		<?php foreach ( $hidden as $lstab_index => $lstab_entry ) : ?>
			<?php $lstab_live = LSTAB_Hidden_Rows::still_there( $lstab_entry, $rows ); ?>
			<?php foreach ( array( 'index', 'name', 'sig', 'label' ) as $lstab_part ) : ?>
				<input type="hidden"
					name="hidden_rows[<?php echo esc_attr( (string) $lstab_index ); ?>][<?php echo esc_attr( $lstab_part ); ?>]"
					value="<?php echo esc_attr( (string) $lstab_entry[ $lstab_part ] ); ?>"
					<?php if ( 'index' === $lstab_part ) : ?>
						data-lstab-pick-present="<?php echo $lstab_live ? '1' : '0'; ?>"
						data-lstab-pick-name="<?php echo esc_attr( $lstab_entry['name'] ); ?>"
						data-lstab-pick-sig="<?php echo esc_attr( $lstab_entry['sig'] ); ?>"
						data-lstab-pick-label="<?php echo esc_attr( $lstab_entry['label'] ); ?>"
						data-lstab-pick-line="<?php echo esc_attr( (string) ( $offset + (int) $lstab_entry['index'] + 1 ) ); ?>"
					<?php endif; ?>
				>
			<?php endforeach; ?>
		<?php endforeach; ?>
	</div>
</div>
