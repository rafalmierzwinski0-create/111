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

		if ( empty( $source['data']['headers'] ) && empty( $source['data']['rows'] ) ) {
			// Nothing has ever synced. Admins get a hint; visitors get nothing.
			return self::notice(
				sprintf(
					/* translators: %s: source title. */
					__( '“%s” has not synced yet. Open Live Sheets Table in the dashboard and choose “Refresh now”.', 'live-sheets-table' ),
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
					'show_meta' => false,
					'search'    => false,
					'sort'      => false,
				)
			)
		);

		$source = array(
			'id'               => 0,
			'title'            => isset( $args['caption'] ) ? (string) $args['caption'] : '',
			'style_preset'     => LSTAB_Styles::sanitize( $args['style'] ),
			'data'             => $data,
			'row_count'        => isset( $data['rows'] ) ? count( (array) $data['rows'] ) : 0,
			'col_count'        => isset( $data['headers'] ) ? count( (array) $data['headers'] ) : 0,
			'last_success_gmt' => null,
		);

		return self::render_table( $source, $args );
	}

	/**
	 * Build the table markup.
	 *
	 * @param array<string,mixed> $source Source row (or preview stand-in).
	 * @param array<string,mixed> $args   Rendering options.
	 * @return string HTML.
	 */
	protected static function render_table( $source, $args ) {
		$headers = isset( $source['data']['headers'] ) ? (array) $source['data']['headers'] : array();
		$rows    = isset( $source['data']['rows'] ) ? (array) $source['data']['rows'] : array();

		/**
		 * Filters the rows about to be rendered.
		 *
		 * The Pro add-on hooks this to paginate large tables.
		 *
		 * @param array $rows   Body rows.
		 * @param array $source Source row.
		 * @param array $args   Rendering options.
		 */
		$rows = (array) apply_filters( 'lstab_render_rows', $rows, $source, $args );

		$style     = $args['style'] ? LSTAB_Styles::sanitize( $args['style'] ) : LSTAB_Styles::sanitize( $source['style_preset'] );
		$source_id = (int) $source['id'];
		$uid       = $source_id > 0 ? (string) $source_id : uniqid();
		$table_id  = 'lstab-table-' . $uid;

		$searchable = ! empty( $args['search'] ) && $rows;
		$sortable   = ! empty( $args['sort'] ) && $rows;

		$classes = array( 'lstab', 'lstab-style-' . $style );
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

		self::enqueue_assets();

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $classes ) ) ); ?>"
			data-lstab-id="<?php echo esc_attr( (string) $source_id ); ?>">

			<?php if ( $searchable ) : ?>
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

			<div class="lstab-scroll">
				<table id="<?php echo esc_attr( $table_id ); ?>" class="lstab-table" role="table">
					<?php if ( $args['caption'] ) : ?>
						<caption class="lstab-caption"><?php echo esc_html( $args['caption'] ); ?></caption>
					<?php endif; ?>
					<thead role="rowgroup">
						<tr role="row">
							<?php foreach ( $headers as $index => $header ) : ?>
								<th scope="col" role="columnheader" data-lstab-col="<?php echo esc_attr( (string) $index ); ?>">
									<?php if ( $sortable ) : ?>
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
							<tr role="row" class="lstab-row">
								<?php foreach ( (array) $row as $col_index => $cell ) : ?>
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
										array( 'data-label' => $label ),
										(string) $cell,
										(int) $col_index,
										(int) $row_index,
										$source
									);
									?>
									<td role="cell"<?php echo self::attributes( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped in attributes(). ?>>
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
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $searchable ) : ?>
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

		return '<div class="lstab-notice"><p>' . esc_html( $message ) . '</p></div>';
	}
}
