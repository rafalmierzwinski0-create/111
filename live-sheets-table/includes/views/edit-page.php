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
$lstab_is_sample  = $lstab_is_edit && LSTAB_Example::is_example( $source );

/*
 * A link pasted on the welcome screen arrives here in the address. Carrying it
 * through means the first thing somebody sees after pasting is their own table,
 * not the same empty field again.
 */
if ( ! $lstab_is_edit ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only prefill of a field the person just typed.
	$lstab_pasted = isset( $_GET['sheet_url'] ) ? esc_url_raw( wp_unslash( $_GET['sheet_url'] ) ) : '';

	if ( '' !== $lstab_pasted ) {
		$lstab_values['sheet_url'] = $lstab_pasted;
	}
}
?>
<div class="wrap lstab-admin lstab-editor">
	<?php
	LSTAB_Admin::render_masthead(
		$lstab_is_edit
			? esc_html__( 'Editing a sheet', 'live-sheets-table' )
			: esc_html__( 'Paste a link and see the table before you save', 'live-sheets-table' )
	);
	?>

	<?php LSTAB_Admin::print_cron_notice(); ?>
	<?php LSTAB_Admin::print_notice(); ?>

	<?php if ( $lstab_is_edit && ! empty( $source['last_ragged'] ) ) : ?>
		<div class="notice notice-warning inline lstab-ragged-notice">
			<p><strong><?php esc_html_e( 'Some rows of this sheet could not be read.', 'live-sheets-table' ); ?></strong></p>
			<p><?php echo esc_html( LSTAB_Admin::ragged_summary( $source['last_ragged'] ) ); ?></p>
			<p>
				<?php esc_html_e( 'Usually an unclosed quotation mark, or a comma inside a value that was not quoted. Open those rows in your sheet and compare them with the rest.', 'live-sheets-table' ); ?>
			</p>
			<p><?php esc_html_e( 'Your page still shows the copy that arrived. Fix the rows in Google, then choose “Save changes and sync”.', 'live-sheets-table' ); ?></p>
		</div>
	<?php endif; ?>

	<?php
	/*
	 * The form wraps the whole editor, not just the left column, so that the
	 * column settings below the grid are part of it and the save button sits
	 * under everything it saves.
	 */
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="lstab-source-form">

	<?php
	/*
	 * One form, three panes. Splitting it into three saved screens would mean
	 * three chances to lose unsaved work and three round trips to change two
	 * things; this way the save button still saves everything, wherever you
	 * happened to be standing.
	 */
	?>
	<nav class="lstab-panes" id="lstab-panes" role="tablist">
		<?php
		$lstab_panes = array(
			'general' => array( 'sliders', __( 'General', 'live-sheets-table' ) ),
			'look'    => array( 'brush', __( 'Appearance', 'live-sheets-table' ) ),
			'hide'    => array( 'columns', __( 'Columns and rows', 'live-sheets-table' ) ),
		);

		foreach ( $lstab_panes as $lstab_pane => $lstab_meta ) :
			?>
			<button type="button"
				class="lstab-pane-tab<?php echo 'general' === $lstab_pane ? ' is-on' : ''; ?>"
				data-lstab-goto="<?php echo esc_attr( $lstab_pane ); ?>"
				role="tab"
				aria-selected="<?php echo 'general' === $lstab_pane ? 'true' : 'false'; ?>">
				<?php echo LSTAB_Icons::icon( $lstab_meta[0] ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
				<?php echo esc_html( $lstab_meta[1] ); ?>
			</button>
		<?php endforeach; ?>
	</nav>

	<div class="lstab-editor-grid" data-lstab-panes="general look hide">
		<div class="lstab-form">
			<?php wp_nonce_field( 'lstab_save_source' ); ?>
			<input type="hidden" name="action" value="lstab_save_source">
			<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) ( $lstab_is_edit ? $source['id'] : 0 ) ); ?>">
			<input type="hidden" name="gid" id="lstab-gid" value="<?php echo esc_attr( (string) $lstab_values['gid'] ); ?>">
			<input type="hidden" name="tab_name" id="lstab-tab-name" value="<?php echo esc_attr( (string) $lstab_values['tab_name'] ); ?>">

			<div class="lstab-pane" data-lstab-pane="general">

			<?php if ( $lstab_is_sample ) : ?>
				<div class="lstab-card">
					<h2 class="lstab-card-title"><?php esc_html_e( 'This is the built-in example', 'live-sheets-table' ); ?></h2>
					<p class="lstab-help">
						<?php esc_html_e( 'A built-in sheet that never contacts Google. Every other setting works as it does for a real one.', 'live-sheets-table' ); ?>
					</p>
				</div>
			<?php else : ?>
			<div class="lstab-card">
				<h2 class="lstab-card-title"><?php esc_html_e( 'Point at your sheet', 'live-sheets-table' ); ?></h2>

				<p class="lstab-help">
					<?php esc_html_e( 'In Google Sheets: Share → General access → “Anyone with the link”, role “Viewer”. Then copy the address from the browser. No API key needed.', 'live-sheets-table' ); ?>
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
					<button type="button" class="lstab-btn" id="lstab-preview-button">
						<?php esc_html_e( 'Load preview', 'live-sheets-table' ); ?>
					</button>
					<span class="spinner lstab-spinner" id="lstab-spinner"></span>
				</p>

				<?php
				/*
				 * Drawn already carrying the tab this source is set to, rather
				 * than hidden until Google answers with the list of them. The
				 * editor re-reads the sheet on opening, so the field used to
				 * appear a second or three after the screen did — below the
				 * button somebody had just finished looking at, which is a
				 * good way of never being found. The rest of the tabs are put
				 * into it when the answer arrives.
				 */
				$lstab_tab_known = '' !== trim( (string) $lstab_values['tab_name'] );
				?>
				<div id="lstab-tabs-wrap" class="lstab-tabs" <?php echo $lstab_tab_known ? '' : 'hidden'; ?>>
					<label for="lstab-tabs"><strong><?php esc_html_e( 'Sheet tab', 'live-sheets-table' ); ?></strong></label>
					<select id="lstab-tabs">
						<?php if ( $lstab_tab_known ) : ?>
							<?php // On one line: an option's text is whatever is between the tags, indentation included. ?>
							<option value="<?php echo esc_attr( (string) $lstab_values['gid'] ); ?>" selected><?php echo esc_html( $lstab_values['tab_name'] ); ?></option>
						<?php endif; ?>
					</select>
					<p class="lstab-help lstab-tabs-note" id="lstab-tabs-note" hidden></p>
				</div>

				<p class="lstab-checkbox">
					<label>
						<input type="checkbox" name="first_row_header" id="lstab-first-row-header" value="1" <?php checked( $lstab_first_row ); ?>>
						<?php esc_html_e( 'The first row contains column headings', 'live-sheets-table' ); ?>
					</label>
				</p>
			</div>
			<?php endif; ?>

			<div class="lstab-card">
				<h2 class="lstab-card-title"><?php esc_html_e( 'Name it and set the schedule', 'live-sheets-table' ); ?></h2>

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

				<?php if ( ! $lstab_is_sample ) : ?>
				<p>
					<label for="lstab-interval"><strong><?php esc_html_e( 'Check Google for changes', 'live-sheets-table' ); ?></strong></label>
					<select id="lstab-interval" name="sync_interval">
						<?php foreach ( $lstab_intervals as $lstab_seconds => $lstab_label ) : ?>
							<option value="<?php echo esc_attr( (string) $lstab_seconds ); ?>" <?php selected( (int) $lstab_values['sync_interval'], (int) $lstab_seconds ); ?>>
								<?php echo esc_html( $lstab_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<span class="lstab-help">
						<?php
						printf(
							/* translators: %d: how many seconds a visitor may be made to wait. */
							esc_html__( 'How old the data on your page may be. Checks run in the background, and a table past its time is also checked when somebody opens the page.', 'live-sheets-table' ),
							(int) LSTAB_Sync::VIEW_TIMEOUT
						);
						?>
						<?php if ( ! $lstab_is_pro ) : ?>
							<?php esc_html_e( 'Pro syncs as often as every minute.', 'live-sheets-table' ); ?>
						<?php endif; ?>
					</span>
				</p>
				<?php endif; ?>

			</div>

			<?php if ( $lstab_is_edit ) : ?>
				<?php $lstab_shortcode = '[sheet_table id="' . (int) $source['id'] . '"]'; ?>
				<div class="lstab-card lstab-usage">
					<h2 class="lstab-card-title"><?php esc_html_e( 'Put it on a page', 'live-sheets-table' ); ?></h2>
					<p>
						<?php esc_html_e( 'Use the “Google Sheets Table” block, or paste this shortcode:', 'live-sheets-table' ); ?>
					</p>
					<p class="lstab-usage-code">
						<code class="lstab-shortcode"><?php echo esc_html( $lstab_shortcode ); ?></code>
						<button type="button" class="lstab-copy" data-lstab-copy="<?php echo esc_attr( $lstab_shortcode ); ?>">
							<?php echo LSTAB_Icons::icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
							<span class="lstab-copy-label"><?php esc_html_e( 'Copy', 'live-sheets-table' ); ?></span>
						</button>
					</p>
					<?php
					/*
					 * The list used to be five pieces of code and no meanings,
					 * which is a puzzle rather than a reference: nothing on the
					 * screen said what meta="no" would do to the page. Where an
					 * attribute takes one of a fixed set of words, those words
					 * are printed too — style="striped" is no use to somebody
					 * who cannot know what else may go in there.
					 */
					$lstab_style_words = array();
					foreach ( LSTAB_Styles::all() as $lstab_slug => $lstab_preset ) {
						if ( empty( $lstab_preset['pro'] ) || LSTAB_Limits::is_pro() ) {
							$lstab_style_words[] = $lstab_slug;
						}
					}

					$lstab_attributes = array(
						array(
							'write' => 'search="no"',
							'means' => __( 'Hide the search box', 'live-sheets-table' ),
						),
						array(
							'write' => 'sort="no"',
							'means' => __( 'Turn off sorting by column', 'live-sheets-table' ),
						),
						array(
							'write' => 'meta="no"',
							'means' => __( 'Hide the “updated … ago” line', 'live-sheets-table' ),
						),
						array(
							'write'  => 'style="striped"',
							'means'  => __( 'Use a table style other than this table\'s own', 'live-sheets-table' ),
							'values' => $lstab_style_words,
						),
						array(
							/* translators: the words inside the quotes are an example a reader replaces; keep the caption=" " around them. */
							'write' => __( 'caption="My table"', 'live-sheets-table' ),
							'means' => __( 'Put a caption above the table', 'live-sheets-table' ),
						),
					);

					/**
					 * Attributes the shortcode understands, for the reference
					 * on this screen.
					 *
					 * The add-on adds its own here rather than documenting them
					 * on a screen of its own, so there is one list to read and
					 * it is beside the shortcode it belongs to.
					 *
					 * @param array $lstab_attributes Rows of write/means/values.
					 * @param array $source           The source being edited.
					 */
					$lstab_attributes = apply_filters( 'lstab_shortcode_attributes', $lstab_attributes, $source );
					?>
					<p class="lstab-help">
						<?php esc_html_e( 'You can add any of these inside the brackets:', 'live-sheets-table' ); ?>
					</p>
					<table class="lstab-attribute-list">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Write', 'live-sheets-table' ); ?></th>
								<th scope="col"><?php esc_html_e( 'What it does', 'live-sheets-table' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $lstab_attributes as $lstab_row ) : ?>
								<tr>
									<td><code><?php echo esc_html( $lstab_row['write'] ); ?></code></td>
									<td>
										<?php echo esc_html( $lstab_row['means'] ); ?>
										<?php if ( ! empty( $lstab_row['values'] ) ) : ?>
											<span class="lstab-attribute-values">
												<?php esc_html_e( 'Choose from:', 'live-sheets-table' ); ?>
												<?php foreach ( $lstab_row['values'] as $lstab_word ) : ?>
													<code><?php echo esc_html( $lstab_word ); ?></code>
												<?php endforeach; ?>
											</span>
										<?php endif; ?>
										<?php if ( ! empty( $lstab_row['note'] ) ) : ?>
											<span class="lstab-attribute-values"><?php echo esc_html( $lstab_row['note'] ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			</div><!-- /pane general -->

			<div class="lstab-pane" data-lstab-pane="look" hidden>
			<div class="lstab-card">
				<h2 class="lstab-card-title"><?php esc_html_e( 'Look and behaviour', 'live-sheets-table' ); ?></h2>

				<?php
				/*
				 * Three choices side by side rather than a dropdown. A dropdown
				 * shows one line at a time, so the two card options — the one
				 * that waits until the screen is narrow and the one that never
				 * waits — read as the same sentence twice and the difference
				 * between them was invisible. Picking one also moves the
				 * preview to the width where that choice shows itself: at desk
				 * width two of the three look identical, so the screen appeared
				 * not to react at all.
				 */
				$lstab_layout  = $lstab_is_edit && ! empty( $source['layout'] ) ? $source['layout'] : 'table';
				$lstab_layouts = array(
					'table' => array(
						'label' => __( 'Table with a slider', 'live-sheets-table' ),
						'desc'  => __( 'Columns stay columns at every width. A slider under the table moves it sideways.', 'live-sheets-table' ),
					),
					'auto'  => array(
						'label' => __( 'Cards when it stops fitting', 'live-sheets-table' ),
						'desc'  => __( 'A table on a computer. On a phone each row becomes a block with its heading beside every value.', 'live-sheets-table' ),
						'note'  => __( 'Suggested', 'live-sheets-table' ),
					),
					'cards' => array(
						'label' => __( 'Always cards', 'live-sheets-table' ),
						'desc'  => __( 'Blocks at every width, including wide screens. Suits profiles and listings rather than figures to compare.', 'live-sheets-table' ),
					),
				);
				?>
				<div class="lstab-choice-head">
					<strong><?php esc_html_e( 'Behaviour on narrow screens', 'live-sheets-table' ); ?></strong>
					<span class="lstab-help">
						<?php esc_html_e( 'The preview switches to the width at which the difference is visible.', 'live-sheets-table' ); ?>
					</span>
				</div>

				<div class="lstab-presets lstab-layouts">
					<?php foreach ( $lstab_layouts as $lstab_layout_key => $lstab_layout_meta ) : ?>
						<label class="lstab-preset">
							<input type="radio"
								name="layout"
								value="<?php echo esc_attr( $lstab_layout_key ); ?>"
								data-lstab-layout
								<?php checked( $lstab_layout, $lstab_layout_key ); ?>>
							<span class="lstab-preset-body">
								<span class="lstab-preset-name">
									<?php echo esc_html( $lstab_layout_meta['label'] ); ?>
									<?php if ( isset( $lstab_layout_meta['note'] ) ) : ?>
										<span class="lstab-hint-badge"><?php echo esc_html( $lstab_layout_meta['note'] ); ?></span>
									<?php endif; ?>
								</span>
								<span class="lstab-preset-desc"><?php echo esc_html( $lstab_layout_meta['desc'] ); ?></span>
							</span>
						</label>
					<?php endforeach; ?>
				</div>

				<p class="lstab-checkbox">
					<label>
						<input type="checkbox" name="sticky_first" value="1"
							<?php checked( ! $lstab_is_edit || ! empty( $source['sticky_first'] ) ); ?>>
						<?php esc_html_e( 'Keep the first column in view', 'live-sheets-table' ); ?>
					</label>
					<span class="lstab-help">
						<?php esc_html_e( 'Useful when the first column names the row. Turn it off if that column holds long text.', 'live-sheets-table' ); ?>
					</span>
				</p>

				<p class="lstab-checkbox">
					<label>
						<input type="checkbox" name="sticky_head" value="1"
							<?php checked( ! $lstab_is_edit || ! empty( $source['sticky_head'] ) ); ?>>
						<?php esc_html_e( 'Keep the headings in view', 'live-sheets-table' ); ?>
					</label>
					<span class="lstab-help">
						<?php esc_html_e( 'The heading row stays visible while the page scrolls. Turn it off if your theme already pins something to the top of the screen.', 'live-sheets-table' ); ?>
					</span>
				</p>

				<p class="lstab-checkbox">
					<label>
						<input type="checkbox" name="link_cells" value="1"
							<?php checked( ! $lstab_is_edit || ! empty( $source['link_cells'] ) ); ?>>
						<?php esc_html_e( 'Turn addresses in cells into links', 'live-sheets-table' ); ?>
					</label>
					<span class="lstab-help">
						<?php esc_html_e( 'Applies to http, https and e-mail addresses only.', 'live-sheets-table' ); ?>
					</span>
				</p>

				<?php
				/*
				 * Paging used to be one unlabelled number box holding 0, which
				 * is how you turn a feature off by accident and never find it
				 * again: nothing on the screen said the word "pages", so there
				 * was nothing to look for. It is now a switch that says what it
				 * does, with the row count only shown once it is on — and the
				 * stored value is still the same single number, 0 for off.
				 */
				$lstab_per_page = $lstab_is_edit && isset( $source['per_page'] ) ? (int) $source['per_page'] : 0;
				?>
				<div class="lstab-paging">
					<h3 class="lstab-subhead"><?php esc_html_e( 'Pagination', 'live-sheets-table' ); ?></h3>

					<p class="lstab-checkbox">
						<label>
							<input type="checkbox" name="paging" id="lstab-paging" value="1" <?php checked( $lstab_per_page > 0 ); ?>>
							<?php esc_html_e( 'Split the table into pages', 'live-sheets-table' ); ?>
						</label>
						<span class="lstab-help">
							<?php esc_html_e( 'Page numbers appear under the table. Searching and sorting then cover the whole sheet, not only the page on screen.', 'live-sheets-table' ); ?>
						</span>
					</p>

					<p class="lstab-field lstab-paging-rows" id="lstab-paging-rows" <?php echo $lstab_per_page > 0 ? '' : 'hidden'; ?>>
						<label for="lstab-per-page"><?php esc_html_e( 'Rows on each page', 'live-sheets-table' ); ?></label>
						<input type="number" id="lstab-per-page" name="per_page" min="1" max="500" step="1"
							class="small-text"
							value="<?php echo esc_attr( (string) ( $lstab_per_page > 0 ? $lstab_per_page : 25 ) ); ?>">
					</p>
				</div>

				<h3 class="lstab-subhead"><?php esc_html_e( 'Table style', 'live-sheets-table' ); ?></h3>

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
					<h2 class="lstab-card-title"><?php esc_html_e( 'Fine-tune the look', 'live-sheets-table' ); ?></h2>
					<p class="lstab-help">
						<?php esc_html_e( 'Optional. Anything left empty follows the style chosen above.', 'live-sheets-table' ); ?>
					</p>

					<div class="lstab-swatches">
						<?php foreach ( LSTAB_Customizer::colors() as $lstab_key => $lstab_color ) : ?>
							<?php $lstab_value = isset( $lstab_vars[ $lstab_key ] ) ? $lstab_vars[ $lstab_key ] : ''; ?>
							<div class="lstab-swatch" data-lstab-token="<?php echo esc_attr( $lstab_key ); ?>" data-lstab-var="<?php echo esc_attr( $lstab_color['var'] ); ?>">
								<label for="lstab-color-<?php echo esc_attr( $lstab_key ); ?>">
									<?php echo esc_html( $lstab_color['label'] ); ?>
								</label>
								<?php if ( ! empty( $lstab_color['note'] ) ) : ?>
									<?php // "Accent" and "Lines" name nothing a reader can point at; the note says what they paint. ?>
									<span class="lstab-swatch-note"><?php echo esc_html( $lstab_color['note'] ); ?></span>
								<?php endif; ?>
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
						<button type="button" class="lstab-mini" id="lstab-reset-appearance">
							<?php esc_html_e( 'Reset everything to the chosen style', 'live-sheets-table' ); ?>
						</button>
					</p>
				</div>
			<?php endif; ?>

			<?php
			/**
			 * Fires inside the "Appearance" pane of the source form.
			 *
			 * @param string     $pane    Pane slug: 'general', 'look' or 'hide'.
			 * @param array|null $source  Source row, or null while adding.
			 * @param bool       $is_edit Whether an existing source is being edited.
			 */
			do_action( 'lstab_edit_pane_cards', 'look', $lstab_is_edit ? $source : null, $lstab_is_edit );
			?>

			<?php if ( LSTAB_Custom_Css::user_can_edit() ) : ?>
				<div class="lstab-card lstab-css-card">
					<h2 class="lstab-card-title"><?php esc_html_e( 'Your own CSS', 'live-sheets-table' ); ?></h2>
					<p class="lstab-help">
						<?php esc_html_e( 'Ordinary CSS rules, confined to this table automatically. Write & for the table element itself, as in &.lstab-paged.', 'live-sheets-table' ); ?>
					</p>

					<input type="hidden" name="_lstab_custom_css_present" value="1">
					<textarea id="lstab-custom-css"
						name="custom_css"
						class="lstab-css-field"
						rows="8"
						spellcheck="false"
						autocapitalize="off"
						autocorrect="off"
						placeholder="<?php echo esc_attr( "th { letter-spacing: 0.04em; }\ntd:first-child { font-weight: 600; }" ); ?>"><?php echo esc_textarea( $lstab_is_edit && isset( $source['custom_css'] ) ? $source['custom_css'] : '' ); ?></textarea>

					<p class="lstab-help lstab-css-foot">
						<?php
						printf(
							/* translators: %s: the CSS selector this table's rules are given, e.g. [data-lstab-id="7"]. */
							esc_html__( 'Each rule is saved with %s in front, so it only ever affects this table.', 'live-sheets-table' ),
							'<code>' . esc_html( LSTAB_Custom_Css::selector( $lstab_is_edit ? (int) $source['id'] : 0 ) ) . '</code>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>
			</div><!-- /pane look -->

			<div class="lstab-pane" data-lstab-pane="hide" hidden>
	<?php
			$lstab_columns = ! empty( $source['columns_config'] ) ? $source['columns_config'] : array();
			$lstab_drift   = $lstab_columns && ! empty( $source['data']['headers'] )
				? LSTAB_Columns::drift( $lstab_columns, $source['data']['headers'] )
				: array();

			/*
			 * Until a sheet has been read once there are no columns to list. The card
			 * still appears, spelling out what it is waiting for: a control that
			 * materialises later is harder to find than one that was always in view,
			 * and people went looking for this in the wrong places.
			 */
			$lstab_waiting = ! $lstab_columns;
			$lstab_rows    = $lstab_waiting
				? array_fill( 0, 3, array( 'source' => '', 'label' => '', 'hidden' => false ) )
				: $lstab_columns;
			?>
				<div class="lstab-card lstab-columns-card<?php echo $lstab_waiting ? ' is-waiting' : ''; ?>">
					<h2 class="lstab-card-title"><?php esc_html_e( 'Columns', 'live-sheets-table' ); ?></h2>
					<p class="lstab-help">
						<?php esc_html_e( 'Renames a column for visitors. Your spreadsheet keeps its own headings.', 'live-sheets-table' ); ?>
						<?php if ( ! LSTAB_Limits::is_pro() ) : ?>
							<?php esc_html_e( 'Hiding columns and rows is part of Pro; you choose them by clicking your own sheet.', 'live-sheets-table' ); ?>
						<?php endif; ?>
					</p>

					<?php if ( $lstab_waiting ) : ?>
						<p class="lstab-columns-waiting">
							<?php
							echo esc_html(
								$lstab_is_edit
									? __( 'This sheet has not been read yet. Choose “Refresh” on the sources list.', 'live-sheets-table' )
									: __( 'Save this source first. It is read straight away, and your real columns appear here.', 'live-sheets-table' )
							);
							?>
						</p>
					<?php endif; ?>

					<?php if ( $lstab_drift ) : ?>
						<div class="notice notice-warning inline lstab-drift">
							<p><strong><?php esc_html_e( 'The columns in your sheet have moved.', 'live-sheets-table' ); ?></strong></p>
							<p>
								<?php esc_html_e( 'Settings are matched by position, so a column added or removed in Google shifts them. Check each row:', 'live-sheets-table' ); ?>
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
								<th scope="col"><?php esc_html_e( 'In the table', 'live-sheets-table' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $lstab_rows as $lstab_index => $lstab_column ) : ?>
								<tr>
									<td>
										<code><?php echo esc_html( '' === $lstab_column['source'] ? sprintf( /* translators: %d: column number. */ __( 'Column %d', 'live-sheets-table' ), (int) $lstab_index + 1 ) : $lstab_column['source'] ); ?></code>
										<input type="hidden" <?php disabled( $lstab_waiting ); ?>
											name="columns[<?php echo esc_attr( (string) $lstab_index ); ?>][source]"
											value="<?php echo esc_attr( $lstab_column['source'] ); ?>">
									</td>
									<td>
										<input type="text" class="regular-text"
											<?php disabled( $lstab_waiting ); ?>
											name="columns[<?php echo esc_attr( (string) $lstab_index ); ?>][label]"
											value="<?php echo esc_attr( $lstab_column['label'] ); ?>"
											placeholder="<?php echo esc_attr( $lstab_column['source'] ); ?>">
									</td>
									<td class="lstab-column-state">
										<?php
										/*
										 * Whether a column is left out is the add-on's to
										 * change; this only reports it and carries it back
										 * unchanged, so saving from here can never quietly
										 * put a column back on a public page.
										 */
										?>
										<input type="hidden" <?php disabled( $lstab_waiting ); ?>
											name="columns[<?php echo esc_attr( (string) $lstab_index ); ?>][hidden]"
											value="<?php echo empty( $lstab_column['hidden'] ) ? '0' : '1'; ?>">
										<input type="hidden" <?php disabled( $lstab_waiting ); ?>
											name="columns[<?php echo esc_attr( (string) $lstab_index ); ?>][detail]"
											value="<?php echo empty( $lstab_column['detail'] ) ? '0' : '1'; ?>">
										<?php if ( ! empty( $lstab_column['hidden'] ) ) : ?>
											<span class="lstab-state-hidden"><?php esc_html_e( 'Hidden', 'live-sheets-table' ); ?></span>
										<?php elseif ( ! empty( $lstab_column['detail'] ) ) : ?>
											<span class="lstab-state-detail"><?php esc_html_e( 'In the details', 'live-sheets-table' ); ?></span>
										<?php else : ?>
											<span class="lstab-state-shown"><?php esc_html_e( 'Shown', 'live-sheets-table' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div><!-- /pane hide -->


		</div>

		<div class="lstab-preview-pane">
			<?php
			/*
			 * The pane is the grid's second column and stretches with it, so
			 * both columns always end level whichever tab is open; this holds
			 * what is in it and sticks to the top of the screen while the form
			 * beside it scrolls. Sticking the column itself cannot work — a
			 * stretched grid item has no room to move inside.
			 */
			?>
			<div class="lstab-preview-stick">
			<h2><?php esc_html_e( 'Preview', 'live-sheets-table' ); ?></h2>
			<p class="lstab-help">
				<?php esc_html_e( 'Exactly what the plugin read from your sheet. Check the headings and a few rows before saving.', 'live-sheets-table' ); ?>
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
						class="lstab-mini lstab-width-button<?php echo '' === $lstab_width ? ' is-active' : ''; ?>"
						data-lstab-width="<?php echo esc_attr( $lstab_width ); ?>"
						aria-pressed="<?php echo '' === $lstab_width ? 'true' : 'false'; ?>">
						<?php echo esc_html( $lstab_width_label ); ?>
					</button>
				<?php endforeach; ?>
			</div>

			<p class="lstab-help">
				<?php esc_html_e( 'A wide table becomes one card per row when its column is too narrow.', 'live-sheets-table' ); ?>
			</p>

			<div id="lstab-preview" class="lstab-preview">
				<div id="lstab-preview-stage" class="lstab-preview-stage" data-lstab-preview="stage">
					<?php
					/*
					 * Drawn from the copy already stored, so the table is on
					 * screen the moment the page is. Waiting for a round trip
					 * to Google left this box empty for a second on every
					 * visit — and permanently empty for the bundled example,
					 * which has nothing to fetch and so never filled it in.
					 */
					if ( $lstab_is_edit && ! empty( $source['data']['rows'] ) ) {
						echo LSTAB_Renderer::render_preview( // phpcs:ignore WordPress.Security.EscapeOutput -- Renderer escapes every cell.
							$source['data'],
							array(
								'source_id'   => (int) $source['id'],
								'style'       => (string) $source['style_preset'],
								'style_vars'  => $source['style_vars'],
								'layout'      => (string) $source['layout'],
								// render_preview() builds a source of its own
								// from the data it is handed, and these are not
								// in it — so without naming them the preview
								// pinned the first column whatever the form
								// said, and the setting looked broken.
								'sticky'      => ! empty( $source['sticky_first'] ),
								'sticky_head' => ! empty( $source['sticky_head'] ),
							)
						);
					} else {
						?>
						<p class="lstab-placeholder"><?php esc_html_e( 'Paste a link and choose “Load preview”.', 'live-sheets-table' ); ?></p>
						<?php
					}
					?>
				</div>
			</div>

			<?php
			/*
			 * When a table comes out wrong the first question is whether the
			 * sheet or the plugin is at fault, and nothing else answers it.
			 * Kept closed, because it is only ever needed when something is
			 * already wrong.
			 */
			?>
			<details id="lstab-raw-wrap" class="lstab-raw" hidden>
				<summary><?php esc_html_e( 'What Google actually sent', 'live-sheets-table' ); ?></summary>
				<p class="lstab-help">
					<?php esc_html_e( 'The exported text as it arrived, before the plugin read it. If a value is wrong here too, fix it in the sheet.', 'live-sheets-table' ); ?>
				</p>
				<p id="lstab-raw-meta" class="lstab-help"></p>
				<textarea id="lstab-raw" class="lstab-raw-text" rows="12" readonly spellcheck="false"></textarea>
			</details>
			</div>
		</div>
	</div>



	<div class="lstab-pane" data-lstab-pane="hide" hidden>

	<?php
	/**
	 * Fires inside the source form, below the column settings.
	 *
	 * An add-on printing fields here has them submitted with everything else,
	 * and can read them back on 'lstab_source_saved' — which only fires after
	 * the capability and nonce checks have passed.
	 *
	 * @param array|null $source  Source row, or null while adding.
	 * @param bool       $is_edit Whether an existing source is being edited.
	 */
	do_action( 'lstab_edit_page_settings', $lstab_is_edit ? $source : null, $lstab_is_edit );

	/** This documented in the Appearance pane above. */
	do_action( 'lstab_edit_pane_cards', 'hide', $lstab_is_edit ? $source : null, $lstab_is_edit );
	?>

	</div><!-- /pane hide -->

	<p class="lstab-submit">
		<button type="submit" class="lstab-btn">
			<?php
			if ( $lstab_is_sample ) {
				// Nothing to sync: promising a sync that cannot happen is the
				// kind of small lie that makes people distrust the big claims.
				esc_html_e( 'Save changes', 'live-sheets-table' );
			} else {
				echo $lstab_is_edit
					? esc_html__( 'Save changes and sync', 'live-sheets-table' )
					: esc_html__( 'Save source and sync', 'live-sheets-table' );
			}
			?>
		</button>
		<a class="lstab-quiet" href="<?php echo esc_url( admin_url( 'admin.php?page=' . LSTAB_Admin::MENU_SLUG ) ); ?>">
			<?php esc_html_e( 'Cancel', 'live-sheets-table' ); ?>
		</a>
	</p>
	</form>

</div>
