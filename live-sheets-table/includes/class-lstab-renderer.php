<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Renderer {
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
			'sticky_head' => null,
			'filter'      => '',
			'per_page'    => null,
			'keep_current' => array(),
		);
	}

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

		if ( '' !== trim( (string) $args['filter'] ) && ! apply_filters( 'lstab_filter_supported', false ) ) {
			return self::notice(
				__( 'This table shows only some of its rows, but the add-on that filters them is not active. No rows are shown rather than all of them. Activate the add-on, or remove the filter from the block or shortcode.', 'live-sheets-table' )
			);
		}

		$source = LSTAB_Sync::refresh_for_view( $source );

		LSTAB_Usage::saw( $source_id );

		if ( empty( $source['data']['headers'] ) && empty( $source['data']['rows'] ) ) {
			return self::notice(
				sprintf(
					/* translators: %s: source title. */
					__( '“%s” has not synced yet. Open Live Sheets Table in the dashboard and choose “Refresh”.', 'live-sheets-table' ),
					$source['title']
				)
			);
		}

		$args['keep_current'] = LSTAB_Freshness::applies( $source ) ? LSTAB_Freshness::attributes( $source ) : array();

		LSTAB_Paging::begin_instance( $source_id, $args );
		$html = self::render_table( $source, $args );
		LSTAB_Paging::end_instance( $source_id );

		return $html;
	}

	public static function render_preview( $data, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array_merge(
				self::defaults(),
				array(
					'show_meta' => false,
					'search'    => true,
					'sort'      => true,
				)
			)
		);

		$source = array(
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

		LSTAB_Custom_Css::reset_printed();

		return self::render_table( $source, $args );
	}

	public static function prepare( $source, $args ) {
		$args    = wp_parse_args( $args, self::defaults() );
		$headers = isset( $source['data']['headers'] ) ? (array) $source['data']['headers'] : array();
		$rows    = isset( $source['data']['rows'] ) ? (array) $source['data']['rows'] : array();

		$rows = (array) apply_filters( 'lstab_source_rows', $rows, $headers, $source, $args );

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

		$rows = (array) apply_filters( 'lstab_render_rows', $rows, $source, $args );

		return array(
			'headers' => $headers,
			'rows'    => $rows,
			'details' => isset( $details ) ? (array) $details : array(),
		);
	}

	protected static function render_table( $source, $args ) {
		$prepared = self::prepare( $source, $args );
		$headers  = $prepared['headers'];
		$rows     = $prepared['rows'];

		$details = array_flip( array_map( 'intval', isset( $prepared['details'] ) ? (array) $prepared['details'] : array() ) );

		$style     = $args['style'] ? LSTAB_Styles::sanitize( $args['style'] ) : LSTAB_Styles::sanitize( $source['style_preset'] );
		$source_id = (int) $source['id'];
		$uid       = $source_id > 0 ? $source_id . LSTAB_Paging::element_suffix( $source_id ) : uniqid();
		$table_id  = 'lstab-table-' . $uid;
		$caption_id = 'lstab-caption-' . $uid;

		$searchable = ! empty( $args['search'] ) && $rows;
		$sortable   = ! empty( $args['sort'] ) && $rows;

		$column_count = count( $headers ) - count( $details );
		$bucket       = max( 1, min( 6, $column_count ) );

		$alignments = self::detect_alignments( $headers, $rows );

		$classes = array( 'lstab', 'lstab-style-' . $style, 'lstab-cols-' . $bucket );

		$sticky = null !== $args['sticky']
			? (bool) $args['sticky']
			: ( ! isset( $source['sticky_first'] ) || (bool) $source['sticky_first'] );

		if ( $sticky ) {
			$classes[] = 'lstab-sticky-first';
		}

		$sticky_head = null !== $args['sticky_head']
			? (bool) $args['sticky_head']
			: ( ! isset( $source['sticky_head'] ) || (bool) $source['sticky_head'] );

		if ( $sticky_head ) {
			$classes[] = 'lstab-sticky-head';
		}

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

		$classes = (array) apply_filters( 'lstab_wrapper_classes', $classes, $source, $args );

		$overrides = $args['style_vars'];
		if ( ! $overrides && isset( $source['style_vars'] ) ) {
			$overrides = $source['style_vars'];
		}
		$inline_style = LSTAB_Customizer::is_enabled()
			? LSTAB_Customizer::inline_style( $overrides )
			: '';

		$paging = LSTAB_Paging::state( $source_id );
		$paged  = is_array( $paging );

		if ( $paged ) {
			$classes[] = 'lstab-paged';
		}

		if ( $paged && '' !== $paging['request']['q'] ) {
			LSTAB_Highlight::begin( $paging['request']['q'] );
		}

		self::enqueue_assets();

		$custom_css = isset( $source['custom_css'] ) ? (string) $source['custom_css'] : '';

		ob_start();
		?>
		<div class="lstab-container">
		<?php echo LSTAB_Custom_Css::style_tag( $source_id, $custom_css ); // phpcs:ignore WordPress.Security.EscapeOutput -- Cleaned and rebuilt in LSTAB_Custom_Css; escaping here would print the rules rather than apply them. ?>
		<div class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $classes ) ) ); ?>"
			data-lstab-id="<?php echo esc_attr( (string) $source_id ); ?>"
			<?php echo empty( $args['keep_current'] ) ? '' : self::attributes( $args['keep_current'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- Built by attributes(), which escapes every name and value. ?>
			<?php if ( '' !== $inline_style ) : ?>
				style="<?php echo esc_attr( $inline_style ); ?>"
			<?php endif; ?>>

			<?php if ( $searchable && $paged ) : ?>
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
								placeholder="<?php esc_attr_e( 'Search all rows…', 'live-sheets-table' ); ?>"
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
					<span class="lstab-count" data-lstab-count-template="<?php
					/* translators: 1: rows shown, 2: rows in the table. */
					echo esc_attr__( '%1$s of %2$s rows', 'live-sheets-table' );
					?>"></span>
				</div>
			<?php endif; ?>

			<?php
			do_action( 'lstab_before_table', $source, $args );
			?>

			<?php if ( $args['caption'] ) : ?>
				<p class="lstab-caption" id="<?php echo esc_attr( $caption_id ); ?>">
					<?php echo esc_html( $args['caption'] ); ?>
				</p>
			<?php endif; ?>

			<?php
			$lstab_named = '' !== (string) $args['caption']
				? (string) $args['caption']
				: implode( ', ', array_slice( array_filter( array_map( 'strval', array_diff_key( $headers, (array) $details ) ), 'strlen' ), 0, 3 ) );
			$lstab_region = '' !== $lstab_named
				/* translators: %s: the table's caption, or the names of its first columns. */
				? sprintf( __( 'Table: %s, scrollable sideways', 'live-sheets-table' ), $lstab_named )
				: __( 'Table, scrollable sideways', 'live-sheets-table' );
			?>
			<div class="lstab-scroll" tabindex="0" role="region"
				aria-label="<?php echo esc_attr( $lstab_region ); ?>">
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
								<?php
								$lstab_head_attributes = (array) apply_filters(
									'lstab_heading_attributes',
									array(
										'data-lstab-col'   => (string) $index,
										'data-lstab-align' => isset( $alignments[ $index ] ) ? $alignments[ $index ] : 'start',
									),
									(string) $header,
									(int) $index,
									$source
								);
								?>
								<th scope="col" role="columnheader"<?php echo self::attributes( $lstab_head_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped in attributes(). ?>>
									<?php if ( $sortable && $paged ) : ?>
										<?php
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
										<?php
										/* translators: %s: column name. */
										$lstab_sort_label = sprintf( __( 'Sort by %s', 'live-sheets-table' ), (string) $header );
										?>
										<button type="button" class="lstab-sort" aria-label="<?php echo esc_attr( $lstab_sort_label ); ?>">
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
							<?php
							echo '<tr role="row" class="lstab-row"' . ( $details ? ' data-lstab-row="' . esc_attr( (string) $row_index ) . '"' : '' ) . '>';

							$lstab_first_cell = true;

							foreach ( (array) $row as $col_index => $cell ) {
								if ( isset( $details[ $col_index ] ) ) {
									continue;
								}

								$label = isset( $headers[ $col_index ] ) ? (string) $headers[ $col_index ] : '';

								$custom = apply_filters( 'lstab_render_cell', null, (string) $cell, (int) $col_index, (int) $row_index, $source );

								$attributes = (array) apply_filters(
									'lstab_cell_attributes',
									array(
										'data-label'       => $label,
										'data-lstab-align' => isset( $alignments[ $col_index ] ) ? $alignments[ $col_index ] : 'start',
										'class'            => ( $details && $lstab_first_cell ) ? 'lstab-cell-opens' : false,
									),
									(string) $cell,
									(int) $col_index,
									(int) $row_index,
									$source
								);

								echo '<td role="cell"' . self::attributes( $attributes ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped in attributes().

								if ( $details && $lstab_first_cell ) {
									echo '<button type="button" class="lstab-open" aria-expanded="false" aria-controls="' . esc_attr( $table_id . '-detail-' . $row_index ) . '" data-lstab-open="' . esc_attr( (string) $row_index ) . '">'
										. '<span class="screen-reader-text">' . esc_html__( 'Show details', 'live-sheets-table' ) . '</span>'
										. '<span class="lstab-open-mark" aria-hidden="true"></span>'
										. '</button>';
								}

								$lstab_first_cell = false;

								if ( '' !== $label ) {
									echo '<span class="lstab-cell-label">' . esc_html( $label ) . '</span>';
								}

								echo '<span class="lstab-cell-value">' . ( null !== $custom ? wp_kses_post( $custom ) : esc_html( (string) $cell ) ) . '</span></td>';
							}

							echo "</tr>\n";
							?>

							<?php if ( $details ) : ?>
								<?php
								$lstab_detail_attributes = (array) apply_filters(
									'lstab_detail_attributes',
									array(),
									(int) $row_index,
									$source
								);
								?>
								<tr class="lstab-detail" id="<?php echo esc_attr( $table_id . '-detail-' . $row_index ); ?>"
									data-lstab-detail-for="<?php echo esc_attr( (string) $row_index ); ?>"
									<?php echo self::attributes( $lstab_detail_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped in attributes(). ?> hidden>
									<td colspan="<?php echo esc_attr( (string) max( 1, count( $headers ) - count( $details ) ) ); ?>">
										<div class="lstab-detail-inner">
											<?php foreach ( array_keys( $details ) as $lstab_detail_index ) : ?>
												<?php
												$lstab_detail_value = isset( $row[ $lstab_detail_index ] ) ? (string) $row[ $lstab_detail_index ] : '';

												if ( '' === trim( $lstab_detail_value ) ) {
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
			<div class="lstab-scrollbar-end" aria-hidden="true"></div>

			<?php if ( $paged && 0 === $paging['matched'] ) : ?>
				<p class="lstab-no-results"><?php esc_html_e( 'No rows match your search.', 'live-sheets-table' ); ?></p>
			<?php elseif ( $searchable ) : ?>
				<p class="lstab-no-results" hidden><?php esc_html_e( 'No rows match your search.', 'live-sheets-table' ); ?></p>
			<?php endif; ?>

			<?php
			$lstab_has_pager = $paged && $paging['pages'] > 1;
			$lstab_fresh     = ! empty( $args['show_meta'] ) ? LSTAB_Freshness::checked_at( $source ) : 0;
			?>

			<?php if ( $lstab_has_pager || $lstab_fresh ) : ?>
				<div class="lstab-foot<?php echo $lstab_has_pager ? ' has-pager' : ''; ?>">
					<?php if ( $lstab_has_pager ) : ?>
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

					<?php if ( $lstab_fresh ) : ?>
						<?php
						$lstab_said = empty( $args['keep_current'] ) ? '' : wp_json_encode(
							array(
								/* translators: %s: human readable duration, e.g. "5 minutes". */
								't' => __( 'Updated %s ago', 'live-sheets-table' ),
								'u' => LSTAB_Locale::span_words(),
							)
						);
						?>
						<p class="lstab-meta"<?php echo '' === $lstab_said ? '' : ' data-lstab-said="' . esc_attr( $lstab_said ) . '"'; ?>>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: human readable time difference, e.g. "5 mins". */
									__( 'Updated %s ago', 'live-sheets-table' ),
									LSTAB_Locale::span( $lstab_fresh, time() )
								)
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		</div>
		<?php

		$html = (string) ob_get_clean();

		LSTAB_Highlight::end();

		return (string) apply_filters( 'lstab_rendered_table', $html, $source, $args );
	}

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

			$alignments[ $index ] = ( $filled > 0 && ( $numeric / $filled ) >= 0.8 ) ? 'end' : 'start';
		}

		return (array) apply_filters( 'lstab_column_alignments', $alignments, $headers, $rows );
	}

	public static function looks_numeric( $value ) {
		return self::is_numeric_value( (string) $value );
	}

	public static function to_moment( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return null;
		}

		if ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{4}|\d{2})(?:[\s,]+(\d{1,2}):([0-5]\d)(?::([0-5]\d))?)?$/', $value, $found ) ) {
			$day   = (int) $found[1];
			$month = (int) $found[2];

			if ( $day < 1 || $day > 31 || $month < 1 || $month > 12 ) {
				return null;
			}

			$minutes = 0;

			if ( isset( $found[4] ) && '' !== $found[4] ) {
				$hour = (int) $found[4];

				if ( $hour > 23 ) {
					return null;
				}

				$minutes = $hour * 60 + (int) $found[5] + ( isset( $found[6] ) && '' !== $found[6] ? (int) $found[6] / 60 : 0 );
			}

			$year = (int) $found[3];

			if ( 2 === strlen( $found[3] ) ) {
				$year += $year < 30 ? 2000 : 1900;
			}

			return array(
				'kind'  => 'date',
				'value' => (float) ( $year * 10000 + $month * 100 + $day ) + $minutes / 1440,
			);
		}

		if ( preg_match( '/^(\d{1,2}):([0-5]\d)(?::([0-5]\d))?$/', $value, $found ) ) {
			$hour = (int) $found[1];

			if ( $hour > 23 ) {
				return null;
			}

			return array(
				'kind'  => 'clock',
				'value' => (float) ( $hour * 60 + (int) $found[2] ) + ( isset( $found[3] ) && '' !== $found[3] ? (int) $found[3] / 60 : 0 ),
			);
		}

		if ( preg_match( '/^(\d{1,2})(?:[:.]([0-5]\d))?(?::([0-5]\d))?\s*([ap])\.?\s?m\.?$/i', $value, $found ) ) {
			$hour = (int) $found[1];

			if ( $hour < 1 || $hour > 12 ) {
				return null;
			}

			$hour = ( $hour % 12 ) + ( 'p' === strtolower( $found[4] ) ? 12 : 0 );

			return array(
				'kind'  => 'clock',
				'value' => (float) ( $hour * 60 + ( isset( $found[2] ) && '' !== $found[2] ? (int) $found[2] : 0 ) )
					+ ( isset( $found[3] ) && '' !== $found[3] ? (int) $found[3] / 60 : 0 ),
			);
		}

		return null;
	}

	public static function moment_rank( $moment ) {
		if ( null === $moment ) {
			return 2;
		}

		return 'date' === $moment['kind'] ? 0 : 1;
	}

	public static function to_number( $value ) {
		$cleaned = preg_replace( '/[\p{Sc}%\s\x{00A0}\x{202F}\x{2009}]/u', '', (string) $value );
		$cleaned = preg_replace( '/(?<=[0-9])\p{L}{1,3}$/u', '', (string) $cleaned );

		if ( null === $cleaned || '' === $cleaned ) {
			return 0.0;
		}

		$last_comma = strrpos( $cleaned, ',' );
		$last_dot   = strrpos( $cleaned, '.' );

		if ( false !== $last_comma && false !== $last_dot ) {
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
		$cleaned = preg_replace( '/[\p{Sc}%\s\x{00A0}\x{202F}\x{2009}]/u', '', $value );

		if ( null === $cleaned || '' === $cleaned ) {
			return false;
		}

		$cleaned = preg_replace( '/(?<=[0-9])\p{L}{1,3}$/u', '', $cleaned );

		if ( null === $cleaned || '' === $cleaned ) {
			return false;
		}

		return (bool) preg_match( '/^[-+]?[0-9]{1,3}(?:[.,][0-9]{3})*(?:[.,][0-9]+)?$/', $cleaned )
			|| (bool) preg_match( '/^[-+]?[0-9]+(?:[.,][0-9]+)?$/', $cleaned );
	}

	protected static function enqueue_assets() {
		if ( ! wp_style_is( 'lstab-table', 'registered' ) ) {
			return;
		}

		wp_enqueue_style( 'lstab-table' );
		wp_enqueue_script( 'lstab-table' );
	}

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

	protected static function notice( $message ) {
		if ( ! current_user_can( LSTAB_Limits::capability() ) ) {
			return '';
		}

		self::enqueue_assets();

		return '<div class="lstab-notice"><p>' . esc_html( $message ) . '</p></div>';
	}
}
