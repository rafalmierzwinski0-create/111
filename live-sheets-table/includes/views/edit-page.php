<?php
/**
 * Add / edit sheet source screen.
 *
 * @package LiveSheetsTable
 *
 * @var array<string,mixed>|null $source
 * @var int                      $source_id
 */

defined( 'ABSPATH' ) || exit;

$lstab_is_edit    = (bool) $source;
$lstab_defaults   = LSTAB_Storage::defaults();
$lstab_values     = $lstab_is_edit ? $source : $lstab_defaults;
$lstab_intervals  = LSTAB_Limits::intervals();
$lstab_presets    = LSTAB_Styles::all();
$lstab_is_pro     = LSTAB_Limits::is_pro();
$lstab_first_row  = $lstab_is_edit ? (bool) $source['first_row_header'] : true;
?>
<div class="wrap lstab-admin lstab-editor">
	<h1>
		<?php
		echo $lstab_is_edit
			? esc_html__( 'Edit sheet source', 'live-sheets-table' )
			: esc_html__( 'Add new sheet source', 'live-sheets-table' );
		?>
	</h1>

	<?php LSTAB_Admin::print_cron_notice(); ?>
	<?php LSTAB_Admin::print_notice(); ?>

	<div class="lstab-editor-grid">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lstab-form" id="lstab-source-form">
			<?php wp_nonce_field( 'lstab_save_source' ); ?>
			<input type="hidden" name="action" value="lstab_save_source">
			<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) ( $lstab_is_edit ? $source['id'] : 0 ) ); ?>">
			<input type="hidden" name="gid" id="lstab-gid" value="<?php echo esc_attr( (string) $lstab_values['gid'] ); ?>">
			<input type="hidden" name="tab_name" id="lstab-tab-name" value="<?php echo esc_attr( (string) $lstab_values['tab_name'] ); ?>">

			<div class="lstab-card">
				<h2 class="lstab-card-title"><?php esc_html_e( '1. Point at your sheet', 'live-sheets-table' ); ?></h2>

				<p class="lstab-help">
					<?php esc_html_e( 'In Google Sheets choose Share → General access → “Anyone with the link”, role “Viewer”, then copy the link from your browser. No API key or Google Cloud project is needed.', 'live-sheets-table' ); ?>
				</p>

				<p>
					<label for="lstab-sheet-url"><strong><?php esc_html_e( 'Google Sheets link', 'live-sheets-table' ); ?></strong></label>
					<input type="url"
						id="lstab-sheet-url"
						name="sheet_url"
						class="large-text code"
						required
						placeholder="https://docs.google.com/spreadsheets/d/…/edit"
						value="<?php echo esc_attr( (string) $lstab_values['sheet_url'] ); ?>">
				</p>

				<p>
					<button type="button" class="button button-primary" id="lstab-preview-button">
						<?php esc_html_e( 'Load preview', 'live-sheets-table' ); ?>
					</button>
					<span class="spinner lstab-spinner" id="lstab-spinner"></span>
				</p>

				<div id="lstab-tabs-wrap" class="lstab-tabs" hidden>
					<label for="lstab-tabs"><strong><?php esc_html_e( 'Sheet tab', 'live-sheets-table' ); ?></strong></label>
					<select id="lstab-tabs"></select>
				</div>

				<p class="lstab-checkbox">
					<label>
						<input type="checkbox" name="first_row_header" id="lstab-first-row-header" value="1" <?php checked( $lstab_first_row ); ?>>
						<?php esc_html_e( 'The first row contains column headings', 'live-sheets-table' ); ?>
					</label>
				</p>
			</div>

			<div class="lstab-card">
				<h2 class="lstab-card-title"><?php esc_html_e( '2. Name it and set the schedule', 'live-sheets-table' ); ?></h2>

				<p>
					<label for="lstab-title"><strong><?php esc_html_e( 'Title', 'live-sheets-table' ); ?></strong></label>
					<input type="text"
						id="lstab-title"
						name="title"
						class="regular-text"
						placeholder="<?php esc_attr_e( 'Price list', 'live-sheets-table' ); ?>"
						value="<?php echo esc_attr( (string) $lstab_values['title'] ); ?>">
					<span class="lstab-help"><?php esc_html_e( 'Only shown in the dashboard, to tell sources apart.', 'live-sheets-table' ); ?></span>
				</p>

				<p>
					<label for="lstab-interval"><strong><?php esc_html_e( 'Check Google for changes', 'live-sheets-table' ); ?></strong></label>
					<select id="lstab-interval" name="sync_interval">
						<?php foreach ( $lstab_intervals as $lstab_seconds => $lstab_label ) : ?>
							<option value="<?php echo esc_attr( (string) $lstab_seconds ); ?>" <?php selected( (int) $lstab_values['sync_interval'], (int) $lstab_seconds ); ?>>
								<?php echo esc_html( $lstab_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php if ( ! $lstab_is_pro ) : ?>
						<span class="lstab-help">
							<?php esc_html_e( 'Pages always render from the local copy, so visitors never wait for Google. Pro syncs as often as every minute.', 'live-sheets-table' ); ?>
						</span>
					<?php endif; ?>
				</p>
			</div>

			<div class="lstab-card">
				<h2 class="lstab-card-title"><?php esc_html_e( '3. Pick a look', 'live-sheets-table' ); ?></h2>

				<p>
					<label for="lstab-layout"><strong><?php esc_html_e( 'On screens too narrow for the whole table', 'live-sheets-table' ); ?></strong></label>
					<select id="lstab-layout" name="layout">
						<?php
						$lstab_layouts = array(
							'table' => __( 'Keep the table and add a slider to scroll it sideways', 'live-sheets-table' ),
							'auto'  => __( 'Turn each row into a labelled card', 'live-sheets-table' ),
							'cards' => __( 'Always use cards, at every width', 'live-sheets-table' ),
						);
						$lstab_layout  = $lstab_is_edit && ! empty( $source['layout'] ) ? $source['layout'] : 'table';
						foreach ( $lstab_layouts as $lstab_layout_key => $lstab_layout_label ) :
							?>
							<option value="<?php echo esc_attr( $lstab_layout_key ); ?>" <?php selected( $lstab_layout, $lstab_layout_key ); ?>>
								<?php echo esc_html( $lstab_layout_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<span class="lstab-help">
						<?php esc_html_e( 'The slider is always visible while there is more table to see, unlike the browser\'s own scrollbar. Use the width buttons beside the preview to check it.', 'live-sheets-table' ); ?>
					</span>
				</p>

				<p class="lstab-checkbox">
					<label>
						<input type="checkbox" name="sticky_first" value="1"
							<?php checked( ! $lstab_is_edit || ! empty( $source['sticky_first'] ) ); ?>>
						<?php esc_html_e( 'Keep the first column in view while the table scrolls sideways', 'live-sheets-table' ); ?>
					</label>
					<span class="lstab-help">
						<?php esc_html_e( 'Useful when the first column names the row — a product, a person, a date. Turn it off if your first column is long text, where pinning it would take up most of a phone screen.', 'live-sheets-table' ); ?>
					</span>
				</p>

				<div class="lstab-presets">
					<?php foreach ( $lstab_presets as $lstab_slug => $lstab_preset ) : ?>
						<?php $lstab_locked = ! empty( $lstab_preset['pro'] ) && ! $lstab_is_pro; ?>
						<label class="lstab-preset <?php echo $lstab_locked ? 'is-locked' : ''; ?>">
							<input type="radio"
								name="style_preset"
								value="<?php echo esc_attr( $lstab_slug ); ?>"
								<?php checked( $lstab_values['style_preset'], $lstab_slug ); ?>
								<?php disabled( $lstab_locked ); ?>>
							<span class="lstab-preset-body">
								<span class="lstab-preset-name">
									<?php echo esc_html( $lstab_preset['label'] ); ?>
									<?php if ( $lstab_locked ) : ?>
										<span class="lstab-pro-badge"><?php esc_html_e( 'Pro', 'live-sheets-table' ); ?></span>
									<?php endif; ?>
								</span>
								<span class="lstab-preset-desc"><?php echo esc_html( $lstab_preset['description'] ); ?></span>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<?php if ( LSTAB_Customizer::is_enabled() ) : ?>
				<?php
				$lstab_vars = $lstab_is_edit
					? LSTAB_Customizer::sanitize( $source['style_vars'] )
					: LSTAB_Customizer::defaults();
				?>
				<div class="lstab-card lstab-appearance">
					<h2 class="lstab-card-title"><?php esc_html_e( '4. Fine-tune the look', 'live-sheets-table' ); ?></h2>
					<p class="lstab-help">
						<?php esc_html_e( 'Optional. Anything you leave untouched follows the preset above, so you can change one colour without redefining the rest. The preview updates as you go.', 'live-sheets-table' ); ?>
					</p>

					<div class="lstab-swatches">
						<?php foreach ( LSTAB_Customizer::colors() as $lstab_key => $lstab_color ) : ?>
							<?php $lstab_value = isset( $lstab_vars[ $lstab_key ] ) ? $lstab_vars[ $lstab_key ] : ''; ?>
							<div class="lstab-swatch" data-lstab-token="<?php echo esc_attr( $lstab_key ); ?>" data-lstab-var="<?php echo esc_attr( $lstab_color['var'] ); ?>">
								<label for="lstab-color-<?php echo esc_attr( $lstab_key ); ?>">
									<?php echo esc_html( $lstab_color['label'] ); ?>
								</label>
								<div class="lstab-swatch-controls">
									<input type="color"
										id="lstab-color-<?php echo esc_attr( $lstab_key ); ?>"
										class="lstab-color-input"
										value="<?php echo esc_attr( $lstab_value ? $lstab_value : '#ffffff' ); ?>"
										<?php echo $lstab_value ? '' : 'data-lstab-unset="1"'; ?>>
									<input type="hidden"
										class="lstab-color-value"
										name="style_vars[<?php echo esc_attr( $lstab_key ); ?>]"
										value="<?php echo esc_attr( $lstab_value ); ?>">
									<button type="button" class="button-link lstab-color-clear"
										<?php disabled( '' === $lstab_value ); ?>>
										<?php esc_html_e( 'Reset', 'live-sheets-table' ); ?>
									</button>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="lstab-metrics">
						<?php foreach ( LSTAB_Customizer::metrics() as $lstab_key => $lstab_metric ) : ?>
							<p class="lstab-metric">
								<label for="lstab-metric-<?php echo esc_attr( $lstab_key ); ?>">
									<strong><?php echo esc_html( $lstab_metric['label'] ); ?></strong>
								</label>
								<select id="lstab-metric-<?php echo esc_attr( $lstab_key ); ?>"
									class="lstab-metric-input"
									name="style_vars[<?php echo esc_attr( $lstab_key ); ?>]"
									data-lstab-token="<?php echo esc_attr( $lstab_key ); ?>">
									<?php foreach ( $lstab_metric['choices'] as $lstab_choice => $lstab_choice_label ) : ?>
										<option value="<?php echo esc_attr( $lstab_choice ); ?>"
											<?php selected( isset( $lstab_vars[ $lstab_key ] ) ? $lstab_vars[ $lstab_key ] : 'normal', $lstab_choice ); ?>>
											<?php echo esc_html( $lstab_choice_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</p>
						<?php endforeach; ?>
					</div>

					<p>
						<button type="button" class="button" id="lstab-reset-appearance">
							<?php esc_html_e( 'Reset everything to the preset', 'live-sheets-table' ); ?>
						</button>
					</p>
				</div>
			<?php endif; ?>

			<p class="lstab-submit">
				<button type="submit" class="button button-primary button-large">
					<?php
					echo $lstab_is_edit
						? esc_html__( 'Save changes and sync', 'live-sheets-table' )
						: esc_html__( 'Save source and sync', 'live-sheets-table' );
					?>
				</button>
				<a class="button button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . LSTAB_Admin::MENU_SLUG ) ); ?>">
					<?php esc_html_e( 'Cancel', 'live-sheets-table' ); ?>
				</a>
			</p>
		</form>

		<div class="lstab-preview-pane">
			<h2><?php esc_html_e( 'Preview', 'live-sheets-table' ); ?></h2>
			<p class="lstab-help">
				<?php esc_html_e( 'This is exactly what the parser sees. Check the headings and a few rows before you save — wrong tab, merged cells or a shifted header row show up here, not on your live page.', 'live-sheets-table' ); ?>
			</p>
			<div id="lstab-preview-status" class="lstab-preview-status" role="status" aria-live="polite"></div>

			<div class="lstab-widths" role="group" aria-label="<?php esc_attr_e( 'Preview width', 'live-sheets-table' ); ?>">
				<span class="lstab-widths-label"><?php esc_html_e( 'Width:', 'live-sheets-table' ); ?></span>
				<?php
				$lstab_widths = array(
					''    => __( 'Full width', 'live-sheets-table' ),
					'650' => __( 'Narrow column', 'live-sheets-table' ),
					'390' => __( 'Phone', 'live-sheets-table' ),
				);
				foreach ( $lstab_widths as $lstab_width => $lstab_width_label ) :
					?>
					<button type="button"
						class="button lstab-width-button<?php echo '' === $lstab_width ? ' is-active' : ''; ?>"
						data-lstab-width="<?php echo esc_attr( $lstab_width ); ?>"
						aria-pressed="<?php echo '' === $lstab_width ? 'true' : 'false'; ?>">
						<?php echo esc_html( $lstab_width_label ); ?>
					</button>
				<?php endforeach; ?>
			</div>

			<p class="lstab-help">
				<?php esc_html_e( 'A wide table becomes one card per row once its column gets too narrow. Use these to check both before you publish.', 'live-sheets-table' ); ?>
			</p>

			<div id="lstab-preview" class="lstab-preview">
				<div id="lstab-preview-stage" class="lstab-preview-stage">
					<p class="lstab-placeholder"><?php esc_html_e( 'Paste a link and choose “Load preview”.', 'live-sheets-table' ); ?></p>
				</div>
			</div>
		</div>
	</div>

	<?php
	$lstab_columns = $lstab_is_edit && ! empty( $source['columns_config'] ) ? $source['columns_config'] : array();
	$lstab_drift   = $lstab_columns && ! empty( $source['data']['headers'] )
		? LSTAB_Columns::drift( $lstab_columns, $source['data']['headers'] )
		: array();
	?>
	<?php if ( $lstab_is_edit && $lstab_columns ) : ?>
		<div class="lstab-card lstab-columns-card">
			<h2 class="lstab-card-title"><?php esc_html_e( 'Columns', 'live-sheets-table' ); ?></h2>
			<p class="lstab-help">
				<?php esc_html_e( 'Rename a column for your visitors, or leave it out of the table entirely. Nothing here is written back to Google — your spreadsheet keeps its own headings, including working names nobody should see.', 'live-sheets-table' ); ?>
			</p>

			<?php if ( $lstab_drift ) : ?>
				<div class="notice notice-warning inline lstab-drift">
					<p><strong><?php esc_html_e( 'The columns in your sheet have moved.', 'live-sheets-table' ); ?></strong></p>
					<p>
						<?php esc_html_e( 'Settings below are matched by position, so a column added or removed in Google shifts them. Check that each row still points at the right column:', 'live-sheets-table' ); ?>
					</p>
					<ul>
						<?php foreach ( $lstab_drift as $lstab_moved ) : ?>
							<li>
								<?php
								printf(
									/* translators: 1: column number, 2: heading that used to be there, 3: heading there now. */
									esc_html__( 'Column %1$d was “%2$s”, now “%3$s”', 'live-sheets-table' ),
									(int) $lstab_moved['index'] + 1,
									esc_html( $lstab_moved['was'] ),
									esc_html( '' === $lstab_moved['now'] ? __( '(no longer there)', 'live-sheets-table' ) : $lstab_moved['now'] )
								);
								?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<table class="lstab-column-list">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'In your sheet', 'live-sheets-table' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Shown as', 'live-sheets-table' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Include', 'live-sheets-table' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $lstab_columns as $lstab_index => $lstab_column ) : ?>
						<tr>
							<td>
								<code><?php echo esc_html( '' === $lstab_column['source'] ? sprintf( /* translators: %d: column number. */ __( 'Column %d', 'live-sheets-table' ), (int) $lstab_index + 1 ) : $lstab_column['source'] ); ?></code>
								<input type="hidden" name="columns[<?php echo esc_attr( (string) $lstab_index ); ?>][source]"
									value="<?php echo esc_attr( $lstab_column['source'] ); ?>">
							</td>
							<td>
								<input type="text" class="regular-text"
									name="columns[<?php echo esc_attr( (string) $lstab_index ); ?>][label]"
									value="<?php echo esc_attr( $lstab_column['label'] ); ?>"
									placeholder="<?php echo esc_attr( $lstab_column['source'] ); ?>">
							</td>
							<td>
								<label>
									<input type="checkbox"
										name="columns[<?php echo esc_attr( (string) $lstab_index ); ?>][visible]"
										value="1" <?php checked( empty( $lstab_column['hidden'] ) ); ?>>
									<span class="screen-reader-text"><?php esc_html_e( 'Show this column', 'live-sheets-table' ); ?></span>
								</label>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<?php if ( $lstab_is_edit ) : ?>
		<div class="lstab-card lstab-usage">
			<h2 class="lstab-card-title"><?php esc_html_e( 'Put it on a page', 'live-sheets-table' ); ?></h2>
			<p>
				<?php esc_html_e( 'Use the “Google Sheets Table” block, or paste this shortcode into any editor, widget or page builder:', 'live-sheets-table' ); ?>
			</p>
			<p><code class="lstab-shortcode">[sheet_table id="<?php echo esc_attr( (string) $source['id'] ); ?>"]</code></p>
			<p class="lstab-help">
				<?php esc_html_e( 'Optional attributes: search="no", sort="no", meta="no", style="striped", caption="My table".', 'live-sheets-table' ); ?>
			</p>
		</div>
	<?php endif; ?>
</div>
