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
