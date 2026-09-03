<?php
/**
 * Server-side table rendering, shared by the block and the shortcode.
 *
 * Everything the sheet contains is treated as untrusted text: sheet content can
 * be edited by anyone the document is shared with, so no cell value is ever
 * emitted without escaping.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renderer.
 */
class LSTAB_Renderer {

	/**
	 * Default rendering options.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'source_id'   => 0,
			'search'      => true,
			'sort'        => true,
			'show_meta'   => true,
			'style'       => '',
			'caption'     => '',
			'class'       => '',
			'layout'      => 'inherit',
			'style_vars'  => array(),
			'custom_css'  => '',
			'columns'     => null,
			'sticky'      => null,
			'filter'      => '',
			'per_page'    => null,
		);
	}

	/**
	 * Render a saved source.
	 *
	 * @param array<string,mixed> $args Rendering options.
	 * @return string HTML.
	 */
	public static function render( $args ) {
		$args      = wp_parse_args( $args, self::defaults() );
		$source_id = (int) $args['source_id'];

		if ( $source_id <= 0 ) {
			return self::notice( __( 'No sheet selected yet.', 'live-sheets-table' ) );
		}

		$source = LSTAB_Storage::get( $source_id );

		if ( ! $source ) {
			return self::notice( __( 'This sheet source no longer exists.', 'live-sheets-table' ) );
		}

		/*
		 * A page that asks for some rows must never be answered with all of
		 * them. Filtering lives in an add-on, and an add-on can be deactivated
		 * by an expired licence, a conflict or a tidy-up — at which point a
		 * page built to show one category would quietly publish the whole
		 * sheet, working rows included. Showing nothing is a visible gap
		 * someone will fix; showing everything is a disclosure nobody notices.
		 */
		if ( '' !== trim( (string) $args['filter'] ) && ! apply_filters( 'lstab_filter_supported', false ) ) {
			return self::notice(
				__( 'This table is set to show only some of its rows, but the add-on that does the filtering is not active. Nothing is shown rather than every row, which is not what this page asked for. Activate the add-on, or remove the filter from the block or shortcode.', 'live-sheets-table' )
			);
		}

		/*
		 * Sources set to refresh on view get their chance here, before a single
		 * row is drawn, so the person who waited for the fetch is the person who
		 * sees the result. It returns the stored copy unchanged when the sheet
		 * is fresh enough, when another request is already fetching, or when
		 * Google does not answer in time.
		 */
		$source = LSTAB_Sync::refresh_for_view( $source );

		if ( empty( $source['data']['headers'] ) && empty( $source['data']['rows'] ) ) {
			// Nothing has ever synced. Admins get a hint; visitors get nothing.
			return self::notice(
				sprintf(
					/* translators: %s: source title. */
					__( '“%s” has not synced yet. Open Live Sheets Table in the dashboard and choose “Refresh”.', 'live-sheets-table' ),
					$source['title']
				)
			);
		}

		return self::render_table( $source, $args );
	}

	/**
	 * Render an arbitrary table payload, used for admin previews.
	 *
	 * @param array<string,mixed> $data Parsed table {headers, rows}.
	 * @param array<string,mixed> $args Rendering options.
	 * @return string HTML.
	 */
	public static function render_preview( $data, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array_merge(
				self::defaults(),
				array(
					// Search and sorting stay on so the preview matches the
					// published table — the accent colour, for one, is only
					// visible on those controls.
					'show_meta' => false,
					'search'    => true,
					'sort'      => true,
				)
			)
		);

		$source = array(
			// Named when the preview is of a saved source, so per-source
			// settings an add-on holds elsewhere apply here too.
			'id'               => isset( $args['source_id'] ) ? absint( $args['source_id'] ) : 0,
			'title'            => isset( $args['caption'] ) ? (string) $args['caption'] : '',
			'style_preset'     => LSTAB_Styles::sanitize( $args['style'] ),
			'data'             => $data,
			'row_count'        => isset( $data['rows'] ) ? count( (array) $data['rows'] ) : 0,
			'col_count'        => isset( $data['headers'] ) ? count( (array) $data['headers'] ) : 0,
			'last_success_gmt' => null,
			'style_vars'       => isset( $args['style_vars'] ) ? $args['style_vars'] : array(),
			'custom_css'       => isset( $args['custom_css'] ) ? (string) $args['custom_css'] : '',
		);

		/*
		 * A preview is a second rendering of a table the page may already be
		 * showing, and the once-per-request guard would swallow its rules.
		 */
		LSTAB_Custom_Css::reset_printed();

		return self::render_table( $source, $args );
	}

	/**
	 * The headings and rows a visitor would be shown, after every setting.
	 *
	 * Rendering and exporting have to agree exactly: an export that ignored a
	 * filter or a hidden column would be a way to read what the page was built
	 * not to show. Both go through here.
	 *
	 * @param array<string,mixed> $source Source row.
	 * @param array<string,mixed> $args   Rendering options.
	 * @return array{headers:array<int,string>,rows:array<int,array<int,string>>}
	 */
	public static function prepare( $source, $args ) {
		$args    = wp_parse_args( $args, self::defaults() );
		$headers = isset( $source['data']['headers'] ) ? (array) $source['data']['headers'] : array();
		$rows    = isset( $source['data']['rows'] ) ? (array) $source['data']['rows'] : array();

		/**
		 * Filters the rows before any column setting is applied.
		 *
		 * Row filtering belongs here rather than after: a condition can then
		 * name a column the table hides, which is the ordinary case — a page
		 * showing one category has no reason to repeat that category in every
		 * row. Positions in $rows still match $headers exactly as the sheet
		 * returned them.
		 *
		 * @param array $rows    Body rows.
		 * @param array $headers Sheet headings, before renaming or hiding.
		 * @param array $source  Source row.
		 * @param array $args    Rendering options.
		 */
		$rows = (array) apply_filters( 'lstab_source_rows', $rows, $headers, $source, $args );

		// Renaming and hiding happen here rather than at sync time, so the
		// stored snapshot always holds exactly what the sheet said and a
		// settings change needs no refetch.
		$columns = null !== $args['columns'] ? $args['columns'] : ( isset( $source['columns_config'] ) ? $source['columns_config'] : array() );

		if ( $columns ) {
			$configured = LSTAB_Columns::apply(
				array(
					'headers' => $headers,
					'rows'    => $rows,
				),
				$columns
			);
			$headers = $configured['headers'];
			$rows    = $configured['rows'];
			$details = isset( $configured['details'] ) ? (array) $configured['details'] : array();
		}

		/**
		 * Filters the rows about to be rendered, after column settings.
		 *
		 * Positions here are those of the rendered table, so a hidden column
		 * is already gone. Anything that has to see every column belongs on
		 * 'lstab_source_rows' instead.
		 *
		 * @param array $rows   Body rows.
		 * @param array $source Source row.
		 * @param array $args   Rendering options.
		 */
		$rows = (array) apply_filters( 'lstab_render_rows', $rows, $source, $args );

		return array(
			'headers' => $headers,
			'rows'    => $rows,
			// Positions, in the rendered table, of the columns that belong
			// under the row rather than in it. The export ignores this: a file
			// has no rows to open, so it carries every column it was given.
			'details' => isset( $details ) ? (array) $details : array(),
		);
	}

	/**
	 * Build the table markup.
	 *
	 * @param array<string,mixed> $source Source row (or preview stand-in).
	 * @param array<string,mixed> $args   Rendering options.
	 * @return string HTML.
	 */
	protected static function render_table( $source, $args ) {
		$prepared = self::prepare( $source, $args );
		$headers  = $prepared['headers'];
		$rows     = $prepared['rows'];

		/*
		 * Columns that live in the drawer under each row rather than in the
		 * table itself. Kept as a lookup by position, because every loop below
		 * walks positions.
		 */
		$details = array_flip( array_map( 'intval', isset( $prepared['details'] ) ? (array) $prepared['details'] : array() ) );

		$style     = $args['style'] ? LSTAB_Styles::sanitize( $args['style'] ) : LSTAB_Styles::sanitize( $source['style_preset'] );
		$source_id = (int) $source['id'];
		$uid       = $source_id > 0 ? (string) $source_id : uniqid();
		$table_id  = 'lstab-table-' . $uid;
		$caption_id = 'lstab-caption-' . $uid;

		$searchable = ! empty( $args['search'] ) && $rows;
		$sortable   = ! empty( $args['sort'] ) && $rows;

		// The width at which a table must stack depends on how many columns it
		// has: five columns need far more room than two. The bucket is emitted
		// as a class so the stylesheet can pick a matching breakpoint.
		// Columns in the table itself. The ones moved into the drawer are not
		// competing for width, so counting them would put a four-column table
		// into the breakpoint meant for six.
		$column_count = count( $headers ) - count( $details );
		$bucket       = max( 1, min( 6, $column_count ) );

		// Columns that hold numbers read better right-aligned with tabular figures.
		$alignments = self::detect_alignments( $headers, $rows );

		$classes = array( 'lstab', 'lstab-style-' . $style, 'lstab-cols-' . $bucket );

		$sticky = null !== $args['sticky']
			? (bool) $args['sticky']
			: ( ! isset( $source['sticky_first'] ) || (bool) $source['sticky_first'] );

		if ( $sticky ) {
			$classes[] = 'lstab-sticky-first';
		}

		// 'auto' lets the breakpoints decide; the other two pin the layout for
		// authors who know their table and their theme's column width.
		$layout = $args['layout'];
		if ( '' === $layout || 'inherit' === $layout ) {
			$layout = isset( $source['layout'] ) ? (string) $source['layout'] : 'table';
		}
		$layout = in_array( $layout, array( 'table', 'cards', 'auto' ), true ) ? $layout : 'table';
		if ( 'auto' !== $layout ) {
			$classes[] = 'lstab-layout-' . $layout;
		}

		if ( $args['class'] ) {
			$classes[] = $args['class'];
		}

		/**
		 * Filters the wrapper CSS classes.
		 *
		 * @param array $classes Class names.
		 * @param array $source  Source row.
		 * @param array $args    Rendering options.
		 */
		$classes = (array) apply_filters( 'lstab_wrapper_classes', $classes, $source, $args );

		// Per-source appearance overrides ride along as inline custom
		// properties. An inline style beats any stylesheet rule, so an explicit
		// choice also wins over the dark-scheme block.
		$overrides = $args['style_vars'];
		if ( ! $overrides && isset( $source['style_vars'] ) ) {
			$overrides = $source['style_vars'];
		}
		$inline_style = LSTAB_Customizer::is_enabled()
			? LSTAB_Customizer::inline_style( $overrides )
			: '';

		// Set by the paging filter while the rows above were being prepared.
		$paging = LSTAB_Paging::state( $source_id );
		$paged  = is_array( $paging );

		if ( $paged ) {
			$classes[] = 'lstab-paged';
		}

		self::enqueue_assets();

		// Whatever the author wrote for this table, confined to it. Printed
		// beside the table rather than in the head so it survives being
		// rendered by a block, a shortcode or the editor's preview alike.
		$custom_css = isset( $source['custom_css'] ) ? (string) $source['custom_css'] : '';

		ob_start();
		?>
		<div class="lstab-container">
		<?php echo LSTAB_Custom_Css::style_tag( $source_id, $custom_css ); // phpcs:ignore WordPress.Security.EscapeOutput -- Cleaned and rebuilt in LSTAB_Custom_Css; escaping here would print the rules rather than apply them. ?>
		<div class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $classes ) ) ); ?>"
			data-lstab-id="<?php echo esc_attr( (string) $source_id ); ?>"
			<?php if ( '' !== $inline_style ) : ?>
				style="<?php echo esc_attr( $inline_style ); ?>"
			<?php endif; ?>>

			<?php if ( $searchable && $paged ) : ?>
				<?php
				/*
				 * A paged table holds one page of rows, so searching in the
				 * browser would search that page and call it the table. This
				 * goes to the server and looks at the whole sheet.
				 */
				?>
				<div class="lstab-controls">
					<form class="lstab-search-form" method="get" action="">
						<?php foreach ( LSTAB_Paging::carried_fields( $source_id ) as $lstab_field => $lstab_value ) : ?>
							<input type="hidden" name="<?php echo esc_attr( $lstab_field ); ?>" value="<?php echo esc_attr( $lstab_value ); ?>">
						<?php endforeach; ?>
						<label class="lstab-search">
							<span class="screen-reader-text"><?php esc_html_e( 'Search this table', 'live-sheets-table' ); ?></span>
							<input type="search"
								class="lstab-search-input"
								name="<?php echo esc_attr( LSTAB_Paging::arg( $source_id, 'q' ) ); ?>"
								value="<?php echo esc_attr( $paging['request']['q'] ); ?>"
								placeholder="<?php esc_attr_e( 'Search the whole sheet…', 'live-sheets-table' ); ?>"
								autocomplete="off">
						</label>
						<button type="submit" class="lstab-search-go"><?php esc_html_e( 'Search', 'live-sheets-table' ); ?></button>
						<?php if ( '' !== $paging['request']['q'] ) : ?>
							<a class="lstab-search-clear" href="<?php echo esc_url( LSTAB_Paging::url( $source_id, array( 'q' => null, 'page' => null ) ) ); ?>">
								<?php esc_html_e( 'Clear', 'live-sheets-table' ); ?>
							</a>
						<?php endif; ?>
					</form>
					<span class="lstab-count">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: rows matching, 2: rows in the sheet. */
								__( '%1$s of %2$s rows', 'live-sheets-table' ),
								number_format_i18n( $paging['matched'] ),
								number_format_i18n( $paging['total'] )
							)
						);
						?>
					</span>
				</div>
			<?php elseif ( $searchable ) : ?>
				<div class="lstab-controls">
					<label class="lstab-search">
						<span class="screen-reader-text"><?php esc_html_e( 'Search this table', 'live-sheets-table' ); ?></span>
						<input type="search"
							class="lstab-search-input"
							placeholder="<?php esc_attr_e( 'Search…', 'live-sheets-table' ); ?>"
							aria-controls="<?php echo esc_attr( $table_id ); ?>"
							autocomplete="off">
					</label>
					<span class="lstab-count" data-lstab-count-template="<?php echo esc_attr__( '%1$s of %2$s rows', 'live-sheets-table' ); ?>"></span>
				</div>
			<?php endif; ?>

			<?php if ( $args['caption'] ) : ?>
				<p class="lstab-caption" id="<?php echo esc_attr( $caption_id ); ?>">
					<?php echo esc_html( $args['caption'] ); ?>
				</p>
			<?php endif; ?>

			<div class="lstab-scroll" tabindex="0" role="region"
				aria-label="<?php esc_attr_e( 'Table, scrollable sideways', 'live-sheets-table' ); ?>">
				<table id="<?php echo esc_attr( $table_id ); ?>" class="lstab-table" role="table"
					<?php if ( $args['caption'] ) : ?>
						aria-labelledby="<?php echo esc_attr( $caption_id ); ?>"
					<?php endif; ?>>
					<thead role="rowgroup">
						<tr role="row">
							<?php foreach ( $headers as $index => $header ) : ?>
								<?php if ( isset( $details[ $index ] ) ) : ?>
									<?php continue; ?>
								<?php endif; ?>
								<th scope="col" role="columnheader"
									data-lstab-col="<?php echo esc_attr( (string) $index ); ?>"
									data-lstab-align="<?php echo esc_attr( isset( $alignments[ $index ] ) ? $alignments[ $index ] : 'start' ); ?>">
									<?php if ( $sortable && $paged ) : ?>
										<?php
										/*
										 * Sorting one page of a long table
										 * would order the rows that happen to
										 * be on screen and leave the rest
										 * where they were, so this goes to the
										 * server like the search does.
										 */
										$lstab_active = (int) $paging['request']['sort'] === (int) $index;
										$lstab_next   = ( $lstab_active && 'asc' === $paging['request']['dir'] ) ? 'desc' : 'asc';
										?>
										<a class="lstab-sort<?php echo $lstab_active ? ' is-sorted is-' . esc_attr( $paging['request']['dir'] ) : ''; ?>"
											href="<?php echo esc_url( LSTAB_Paging::url( $source_id, array( 'sort' => $index, 'dir' => $lstab_next, 'page' => null ) ) ); ?>"
											<?php if ( $lstab_active ) : ?>
												aria-sort="<?php echo 'asc' === $paging['request']['dir'] ? 'ascending' : 'descending'; ?>"
											<?php endif; ?>>
											<span class="lstab-sort-label"><?php echo esc_html( (string) $header ); ?></span>
											<span class="lstab-sort-icon" aria-hidden="true"></span>
										</a>
									<?php elseif ( $sortable ) : ?>
										<button type="button" class="lstab-sort" aria-label="
											<?php
											echo esc_attr(
												sprintf(
													/* translators: %s: column name. */
													__( 'Sort by %s', 'live-sheets-table' ),
													(string) $header
												)
											);
											?>
										">
											<span class="lstab-sort-label"><?php echo esc_html( (string) $header ); ?></span>
											<span class="lstab-sort-icon" aria-hidden="true"></span>
										</button>
									<?php else : ?>
										<?php echo esc_html( (string) $header ); ?>
									<?php endif; ?>
								</th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody role="rowgroup">
						<?php foreach ( $rows as $row_index => $row ) : ?>
							<tr role="row" class="lstab-row"<?php echo $details ? ' data-lstab-row="' . esc_attr( (string) $row_index ) . '"' : ''; ?>>
								<?php $lstab_first_cell = true; ?>
								<?php foreach ( (array) $row as $col_index => $cell ) : ?>
									<?php if ( isset( $details[ $col_index ] ) ) : ?>
										<?php continue; ?>
									<?php endif; ?>
									<?php
									$label = isset( $headers[ $col_index ] ) ? (string) $headers[ $col_index ] : '';

									/**
									 * Filters a single rendered cell's inner HTML.
									 *
									 * Returning a string here replaces the escaped value, so any
									 * filter callback is responsible for its own escaping. The Pro
									 * add-on uses this for conditional formatting.
									 *
									 * @param string|null $html      Replacement HTML, or null for the default.
									 * @param string      $value     Raw cell value.
									 * @param int         $col_index Column index.
									 * @param int         $row_index Row index.
									 * @param array       $source    Source row.
									 */
									$custom = apply_filters( 'lstab_render_cell', null, (string) $cell, (int) $col_index, (int) $row_index, $source );

									/**
									 * Filters the attributes applied to a cell.
									 *
									 * @param array  $attributes Attribute map.
									 * @param string $value      Raw cell value.
									 * @param int    $col_index  Column index.
									 * @param int    $row_index  Row index.
									 * @param array  $source     Source row.
									 */
									$attributes = (array) apply_filters(
										'lstab_cell_attributes',
										array(
											'data-label'       => $label,
											'data-lstab-align' => isset( $alignments[ $col_index ] ) ? $alignments[ $col_index ] : 'start',
											// Named so the stylesheet can lay
											// this one cell out around the
											// button it is about to hold.
											'class'            => ( $details && $lstab_first_cell ) ? 'lstab-cell-opens' : false,
										),
										(string) $cell,
										(int) $col_index,
										(int) $row_index,
										$source
									);
									?>
									<td role="cell"<?php echo self::attributes( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped in attributes(). ?>>
										<?php if ( $details && $lstab_first_cell ) : ?>
											<?php
											/*
											 * Inside the first cell rather than
											 * in a column of its own: a column
											 * would take a share of the width,
											 * would become the pinned one on a
											 * table that scrolls sideways, and
											 * would put a heading above a thing
											 * that is not data.
											 *
											 * A real button, not a clickable
											 * row. A row that opens on any
											 * click cannot be reached from a
											 * keyboard, swallows the selection
											 * of a value somebody wanted to
											 * copy, and gives a screen reader
											 * nothing to announce.
											 */
											?>
											<button type="button" class="lstab-open"
												aria-expanded="false"
												aria-controls="<?php echo esc_attr( $table_id . '-detail-' . $row_index ); ?>"
												data-lstab-open="<?php echo esc_attr( (string) $row_index ); ?>">
												<span class="screen-reader-text"><?php esc_html_e( 'Show details', 'live-sheets-table' ); ?></span>
												<span class="lstab-open-mark" aria-hidden="true"></span>
											</button>
										<?php endif; ?>
										<?php $lstab_first_cell = false; ?>
										<?php if ( '' !== $label ) : ?>
											<span class="lstab-cell-label"><?php echo esc_html( $label ); ?></span>
										<?php endif; ?>
										<span class="lstab-cell-value">
											<?php
											if ( null !== $custom ) {
												echo wp_kses_post( $custom );
											} else {
												echo esc_html( (string) $cell );
											}
											?>
										</span>
									</td>
								<?php endforeach; ?>
							</tr>

							<?php if ( $details ) : ?>
								<?php
								/*
								 * Rendered here rather than fetched on the
								 * click. It costs a little markup and buys
								 * three things: the words are in the page, so
								 * a search engine and the table's own search
								 * box both find them; nothing has to happen
								 * over the network when somebody opens a row;
								 * and it still works with no JavaScript at all,
								 * where the drawer simply sits open.
								 */
								?>
								<tr class="lstab-detail" id="<?php echo esc_attr( $table_id . '-detail-' . $row_index ); ?>"
									data-lstab-detail-for="<?php echo esc_attr( (string) $row_index ); ?>" hidden>
									<td colspan="<?php echo esc_attr( (string) max( 1, count( $headers ) - count( $details ) ) ); ?>">
										<div class="lstab-detail-inner">
											<?php foreach ( array_keys( $details ) as $lstab_detail_index ) : ?>
												<?php
												$lstab_detail_value = isset( $row[ $lstab_detail_index ] ) ? (string) $row[ $lstab_detail_index ] : '';

												if ( '' === trim( $lstab_detail_value ) ) {
													// An empty pair is a label
													// with nothing under it,
													// which reads as a fault.
													continue;
												}

												$lstab_detail_custom = apply_filters(
													'lstab_render_cell',
													null,
													$lstab_detail_value,
													(int) $lstab_detail_index,
													(int) $row_index,
													$source
												);
												?>
												<div class="lstab-detail-pair">
													<span class="lstab-detail-key">
														<?php echo esc_html( isset( $headers[ $lstab_detail_index ] ) ? (string) $headers[ $lstab_detail_index ] : '' ); ?>
													</span>
													<span class="lstab-detail-value">
														<?php
														if ( null !== $lstab_detail_custom ) {
															echo wp_kses_post( $lstab_detail_custom );
														} else {
															echo esc_html( $lstab_detail_value );
														}
														?>
													</span>
												</div>
											<?php endforeach; ?>
										</div>
									</td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="lstab-scrollbar" hidden>
				<div class="lstab-scrollbar-track">
					<div class="lstab-scrollbar-thumb"
						role="scrollbar"
						tabindex="0"
						aria-orientation="horizontal"
						aria-controls="<?php echo esc_attr( $table_id ); ?>"
						aria-label="<?php esc_attr_e( 'Scroll the table sideways', 'live-sheets-table' ); ?>"
						aria-valuemin="0"
						aria-valuemax="100"
						aria-valuenow="0"></div>
				</div>
			</div>

			<?php if ( $paged && $paging['pages'] > 1 ) : ?>
				<nav class="lstab-pager" aria-label="<?php esc_attr_e( 'Table pages', 'live-sheets-table' ); ?>">
					<?php if ( $paging['page'] > 1 ) : ?>
						<a class="lstab-page-link" rel="prev" href="<?php echo esc_url( LSTAB_Paging::url( $source_id, array( 'page' => $paging['page'] - 1 ) ) ); ?>">
							<?php esc_html_e( 'Previous', 'live-sheets-table' ); ?>
						</a>
					<?php else : ?>
						<span class="lstab-page-link is-disabled"><?php esc_html_e( 'Previous', 'live-sheets-table' ); ?></span>
					<?php endif; ?>

					<span class="lstab-page-of">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: current page, 2: number of pages. */
								__( 'Page %1$s of %2$s', 'live-sheets-table' ),
								number_format_i18n( $paging['page'] ),
								number_format_i18n( $paging['pages'] )
							)
						);
						?>
					</span>

					<?php if ( $paging['page'] < $paging['pages'] ) : ?>
						<a class="lstab-page-link" rel="next" href="<?php echo esc_url( LSTAB_Paging::url( $source_id, array( 'page' => $paging['page'] + 1 ) ) ); ?>">
							<?php esc_html_e( 'Next', 'live-sheets-table' ); ?>
						</a>
					<?php else : ?>
						<span class="lstab-page-link is-disabled"><?php esc_html_e( 'Next', 'live-sheets-table' ); ?></span>
					<?php endif; ?>
				</nav>
			<?php endif; ?>

			<?php if ( $paged && 0 === $paging['matched'] ) : ?>
				<p class="lstab-no-results"><?php esc_html_e( 'No rows match your search.', 'live-sheets-table' ); ?></p>
			<?php elseif ( $searchable ) : ?>
				<p class="lstab-no-results" hidden><?php esc_html_e( 'No rows match your search.', 'live-sheets-table' ); ?></p>
			<?php endif; ?>

			<?php
			if ( ! empty( $args['show_meta'] ) && ! empty( $source['last_success_gmt'] ) ) :
				$timestamp = strtotime( $source['last_success_gmt'] . ' UTC' );
				?>
				<p class="lstab-meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: human readable time difference, e.g. "5 mins". */
							__( 'Updated %s ago', 'live-sheets-table' ),
							human_time_diff( $timestamp, time() )
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		</div>
		<?php

		$html = (string) ob_get_clean();

		/**
		 * Filters the complete rendered table HTML.
		 *
		 * @param string $html   Rendered markup.
		 * @param array  $source Source row.
		 * @param array  $args   Rendering options.
		 */
		return (string) apply_filters( 'lstab_rendered_table', $html, $source, $args );
	}

	/**
	 * Decide how each column should be aligned.
	 *
	 * A column whose values are overwhelmingly numeric reads far better right
	 * aligned with tabular figures, so prices and quantities line up on the
	 * decimal point instead of drifting.
	 *
	 * @param array<int,string>             $headers Column labels.
	 * @param array<int,array<int,string>>  $rows    Body rows.
	 * @return array<int,string> Column index to 'start' or 'end'.
	 */
	protected static function detect_alignments( $headers, $rows ) {
		$alignments = array();
		$sample     = array_slice( $rows, 0, 200 );

		foreach ( array_keys( $headers ) as $index ) {
			$numeric = 0;
			$filled  = 0;

			foreach ( $sample as $row ) {
				$value = isset( $row[ $index ] ) ? trim( (string) $row[ $index ] ) : '';
				if ( '' === $value ) {
					continue;
				}
				$filled++;
				if ( self::is_numeric_value( $value ) ) {
					$numeric++;
				}
			}

			// Require a clear majority so a stray number does not flip a text column.
			$alignments[ $index ] = ( $filled > 0 && ( $numeric / $filled ) >= 0.8 ) ? 'end' : 'start';
		}

		/**
		 * Filters the per-column alignment.
		 *
		 * @param array $alignments Column index to 'start' or 'end'.
		 * @param array $headers    Column labels.
		 * @param array $rows       Body rows.
		 */
		return (array) apply_filters( 'lstab_column_alignments', $alignments, $headers, $rows );
	}

	/**
	 * Whether a cell holds a number, tolerating the ways spreadsheets format them.
	 *
	 * Accepts thousands separators (including the non-breaking and narrow spaces
	 * Google emits), comma decimals, currency symbols, percentages and sign
	 * suffixes, so "1 215,50 zł" and "-4.5%" both count.
	 *
	 * @param string $value Raw cell value.
	 * @return bool
	 */
	/**
	 * Whether a value reads as a number, for anything outside this class.
	 *
	 * Server-side sorting has to answer exactly the question the alignment
	 * detection already answers, and two answers would eventually disagree.
	 *
	 * @param string $value Cell value.
	 * @return bool
	 */
	public static function looks_numeric( $value ) {
		return self::is_numeric_value( (string) $value );
	}

	/**
	 * A spreadsheet-formatted number as a float.
	 *
	 * Handles a space or a full stop as the thousands separator and a comma or
	 * a full stop as the decimal one, which is the difference between 1 215,50
	 * sorting above 349,00 and below it.
	 *
	 * @param string $value Cell value.
	 * @return float
	 */
	public static function to_number( $value ) {
		$cleaned = preg_replace( '/[\p{Sc}%\s\x{00A0}\x{202F}\x{2009}]/u', '', (string) $value );
		$cleaned = preg_replace( '/(?<=[0-9])\p{L}{1,3}$/u', '', (string) $cleaned );

		if ( null === $cleaned || '' === $cleaned ) {
			return 0.0;
		}

		$last_comma = strrpos( $cleaned, ',' );
		$last_dot   = strrpos( $cleaned, '.' );

		if ( false !== $last_comma && false !== $last_dot ) {
			// Whichever comes last is the decimal separator.
			$cleaned = $last_comma > $last_dot
				? str_replace( array( '.', ',' ), array( '', '.' ), $cleaned )
				: str_replace( ',', '', $cleaned );
		} elseif ( false !== $last_comma ) {
			$cleaned = substr_count( $cleaned, ',' ) > 1
				? str_replace( ',', '', $cleaned )
				: str_replace( ',', '.', $cleaned );
		}

		return is_numeric( $cleaned ) ? (float) $cleaned : 0.0;
	}

	protected static function is_numeric_value( $value ) {
		// Strip currency symbols, percent signs and every flavour of space.
		$cleaned = preg_replace( '/[\p{Sc}%\s\x{00A0}\x{202F}\x{2009}]/u', '', $value );

		if ( null === $cleaned || '' === $cleaned ) {
			return false;
		}

		// Many currencies are written as letters rather than a symbol, and always
		// after the amount: "12,00 zł", "100 kr", "9 PLN". Drop a short trailing
		// alphabetic tail, but only when digits came first, so a product code
		// like "A1" is not mistaken for a number.
		$cleaned = preg_replace( '/(?<=[0-9])\p{L}{1,3}$/u', '', $cleaned );

		if ( null === $cleaned || '' === $cleaned ) {
			return false;
		}

		return (bool) preg_match( '/^[-+]?[0-9]{1,3}(?:[.,][0-9]{3})*(?:[.,][0-9]+)?$/', $cleaned )
			|| (bool) preg_match( '/^[-+]?[0-9]+(?:[.,][0-9]+)?$/', $cleaned );
	}

	/**
	 * Load the front-end stylesheet, and the script only when it has work to do.
	 *
	 * Assets are registered up front but enqueued here, so a page without a
	 * table ships neither file.
	 *
	 * @return void
	 */
	protected static function enqueue_assets() {
		if ( ! wp_style_is( 'lstab-table', 'registered' ) ) {
			return;
		}

		wp_enqueue_style( 'lstab-table' );
		wp_enqueue_script( 'lstab-table' );
	}

	/**
	 * Build an escaped attribute string.
	 *
	 * @param array<string,string> $attributes Attribute map.
	 * @return string
	 */
	protected static function attributes( $attributes ) {
		$out = '';

		foreach ( (array) $attributes as $name => $value ) {
			$name = preg_replace( '#[^a-zA-Z0-9_:-]#', '', (string) $name );
			if ( '' === $name || null === $value || false === $value ) {
				continue;
			}
			$out .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
		}

		return $out;
	}

	/**
	 * A message that is only ever shown to users who can fix the problem.
	 *
	 * Visitors see nothing at all: a broken sheet must never leak an error,
	 * a stack trace or raw markup onto a public page.
	 *
	 * @param string $message Admin-facing message.
	 * @return string
	 */
	protected static function notice( $message ) {
		if ( ! current_user_can( LSTAB_Limits::capability() ) ) {
			return '';
		}

		// Every path that ends in a notice ends here without having rendered a
		// table, so the stylesheet has not been asked for yet. Without this the
		// message arrives as a bare paragraph and reads like broken content
		// rather than something addressed to whoever can fix it.
		self::enqueue_assets();

		return '<div class="lstab-notice"><p>' . esc_html( $message ) . '</p></div>';
	}
}
