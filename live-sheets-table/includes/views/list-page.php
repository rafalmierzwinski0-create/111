<?php
/**
 * Sheet source list screen.
 *
 * A card per sheet rather than a row per sheet. The old table gave every fact
 * the same weight, which meant none of them had any; a card can answer the
 * three questions somebody actually arrives with — is it working, which sheet
 * is it, and how do I put it on a page — in that order.
 *
 * @package LiveSheetsTable
 *
 * @var array<int,array<string,mixed>> $sources
 */

defined( 'ABSPATH' ) || exit;

$lstab_can_add = LSTAB_Limits::can_add_source();

/*
 * The welcome screen stays until there is a sheet of the reader's own. The
 * built-in example is ours, not theirs: somebody who has only clicked "show me
 * an example" has still not connected anything, and hiding the one screen that
 * tells them how would leave them looking at a demo with no way forward.
 */
$lstab_own = array_filter(
	$sources,
	static function ( $candidate ) {
		return ! LSTAB_Example::is_example( $candidate );
	}
);

$lstab_add_button = $lstab_can_add
	? sprintf(
		'<a href="%s" class="lstab-btn">%s%s</a>',
		esc_url( admin_url( 'admin.php?page=' . LSTAB_Admin::EDIT_SLUG ) ),
		LSTAB_Icons::icon( 'plus' ),
		esc_html__( 'Add a sheet', 'live-sheets-table' )
	)
	: '';
?>
<div class="wrap lstab-admin">
	<?php LSTAB_Admin::render_masthead( LSTAB_Admin::masthead_summary( $sources ), $lstab_add_button ); ?>

	<?php LSTAB_Admin::render_tabs( LSTAB_Admin::MENU_SLUG ); ?>

	<?php LSTAB_Admin::print_cron_notice(); ?>
	<?php LSTAB_Admin::print_notice(); ?>

	<?php if ( ! $lstab_own ) : ?>
		<?php require LSTAB_PATH . 'includes/views/welcome.php'; ?>
	<?php endif; ?>

	<?php if ( $sources ) : ?>
		<div class="lstab-src-list">
			<?php
			$lstab_intervals = LSTAB_Limits::intervals();

			foreach ( $sources as $lstab_source ) :
				$lstab_state     = LSTAB_Admin::card_state( $lstab_source );
				$lstab_columns   = LSTAB_Admin::column_names( $lstab_source );
				$lstab_places    = LSTAB_Usage::places( (int) $lstab_source['id'] );
				$lstab_history   = LSTAB_Storage::history( $lstab_source );
				$lstab_shortcode = '[sheet_table id="' . (int) $lstab_source['id'] . '"]';
				$lstab_is_sample = LSTAB_Example::is_example( $lstab_source );
				?>
				<article class="lstab-src lstab-src--<?php echo esc_attr( $lstab_state['tone'] ); ?>">
					<div class="lstab-src-body">
						<div class="lstab-src-top">
							<h2 class="lstab-src-title">
								<?php echo LSTAB_Icons::icon( 'grid' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
								<?php echo esc_html( $lstab_source['title'] ); ?>
							</h2>

							<?php
							/*
							 * The two things anybody came here to do, level with
							 * the name. At the foot of the card they sat below
							 * five lines of detail nobody had to read first.
							 */
							?>
							<span class="lstab-src-actions">
								<a class="lstab-mini lstab-mini--strong" href="
									<?php
									echo esc_url(
										add_query_arg(
											array(
												'page'   => LSTAB_Admin::EDIT_SLUG,
												'source' => $lstab_source['id'],
											),
											admin_url( 'admin.php' )
										)
									);
									?>
								"><?php echo LSTAB_Icons::icon( 'pencil' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?><?php esc_html_e( 'Edit', 'live-sheets-table' ); ?></a>

								<?php if ( ! $lstab_is_sample ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lstab-inline-form">
										<?php wp_nonce_field( 'lstab_refresh_source' ); ?>
										<input type="hidden" name="action" value="lstab_refresh_source">
										<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $lstab_source['id'] ); ?>">
										<button type="submit" class="lstab-mini">
											<?php echo LSTAB_Icons::icon( 'refresh' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
											<?php esc_html_e( 'Refresh', 'live-sheets-table' ); ?>
										</button>
									</form>
								<?php endif; ?>
							</span>
						</div>

						<div class="lstab-src-facts">
							<?php
							/*
							 * The state reads as one of the facts rather than as
							 * a banner in the corner, because that is what it is.
							 */
							?>
							<span class="lstab-state lstab-state--<?php echo esc_attr( $lstab_state['tone'] ); ?>">
								<?php echo LSTAB_Icons::icon( $lstab_state['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
								<?php echo esc_html( $lstab_state['text'] ); ?>
							</span>

							<span class="lstab-fact">
								<?php echo LSTAB_Icons::icon( 'columns' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
								<?php
								printf(
									/* translators: 1: row count, 2: column count. */
									esc_html__( '%1$s rows × %2$s columns', 'live-sheets-table' ),
									'<b>' . esc_html( number_format_i18n( $lstab_source['row_count'] ) ) . '</b>',
									'<b>' . esc_html( number_format_i18n( $lstab_source['col_count'] ) ) . '</b>'
								);
								?>
							</span>

							<?php if ( ! $lstab_is_sample ) : ?>
								<span class="lstab-fact">
									<?php echo LSTAB_Icons::icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
									<?php
									echo esc_html(
										isset( $lstab_intervals[ $lstab_source['sync_interval'] ] )
											? $lstab_intervals[ $lstab_source['sync_interval'] ]
											: sprintf(
												/* translators: %s: duration. */
												__( 'Every %s', 'live-sheets-table' ),
												human_time_diff( 0, $lstab_source['sync_interval'] )
											)
									);
									?>
								</span>
							<?php endif; ?>

							<?php if ( $lstab_source['tab_name'] ) : ?>
								<span class="lstab-fact">
									<?php echo LSTAB_Icons::icon( 'layers' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
									<?php echo esc_html( $lstab_source['tab_name'] ); ?>
								</span>
							<?php endif; ?>

							<?php if ( count( $lstab_history ) > 1 ) : ?>
								<span class="lstab-spark" title="<?php esc_attr_e( 'The last few checks, oldest first', 'live-sheets-table' ); ?>">
									<?php foreach ( $lstab_history as $lstab_i => $lstab_mark ) : ?>
										<i class="lstab-bar lstab-bar--<?php echo esc_attr( $lstab_mark ); ?>"
											style="--lstab-bar-h: <?php echo esc_attr( (string) ( 45 + ( ( $lstab_i * 37 ) % 55 ) ) ); ?>%"></i>
									<?php endforeach; ?>
								</span>
							<?php endif; ?>
						</div>

						<?php if ( $lstab_state['note'] ) : ?>
							<p class="lstab-src-note"><?php echo esc_html( $lstab_state['note'] ); ?></p>
						<?php endif; ?>

						<?php if ( ! empty( $lstab_source['last_ragged'] ) ) : ?>
							<p class="lstab-src-note lstab-src-note--warn">
								<?php echo LSTAB_Icons::icon( 'alert' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
								<?php echo esc_html( LSTAB_Admin::ragged_summary( $lstab_source['last_ragged'] ) ); ?>
							</p>
						<?php endif; ?>

						<?php if ( $lstab_columns['names'] ) : ?>
							<div class="lstab-colchips">
								<?php foreach ( $lstab_columns['names'] as $lstab_name ) : ?>
									<span class="lstab-colchip"><?php echo esc_html( $lstab_name ); ?></span>
								<?php endforeach; ?>
								<?php if ( $lstab_columns['extra'] > 0 ) : ?>
									<span class="lstab-colchip lstab-colchip--more">+<?php echo esc_html( number_format_i18n( $lstab_columns['extra'] ) ); ?></span>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php
						/*
						 * The scariest question in this screen is "can I delete
						 * this?". Nobody can answer it from a list of names, so
						 * everybody keeps everything. This answers it.
						 */
						?>
						<p class="lstab-used">
							<?php echo LSTAB_Icons::icon( 'doc' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
							<?php if ( $lstab_places ) : ?>
								<?php esc_html_e( 'Used on', 'live-sheets-table' ); ?>
								<?php
								$lstab_links = array();
								foreach ( array_slice( $lstab_places, 0, 3 ) as $lstab_place ) {
									$lstab_links[] = $lstab_place['url']
										? '<a href="' . esc_url( $lstab_place['url'] ) . '">' . esc_html( $lstab_place['title'] ) . '</a>'
										: esc_html( $lstab_place['title'] );
								}

								echo wp_kses( implode( ', ', $lstab_links ), array( 'a' => array( 'href' => array() ) ) );

								if ( count( $lstab_places ) > 3 ) {
									echo esc_html(
										sprintf(
											/* translators: %s: number of further pages. */
											__( ' and %s more', 'live-sheets-table' ),
											number_format_i18n( count( $lstab_places ) - 3 )
										)
									);
								}
								?>
							<?php else : ?>
								<span class="lstab-used-none"><?php esc_html_e( 'Not on any page yet — safe to delete', 'live-sheets-table' ); ?></span>
							<?php endif; ?>
						</p>

						<div class="lstab-src-use">
							<code class="lstab-shortcode"><?php echo esc_html( $lstab_shortcode ); ?></code>

							<?php
							/*
							 * The single most repeated action in the plugin.
							 * It used to be a piece of text you had to select
							 * by hand without overshooting either bracket.
							 */
							?>
							<button type="button"
								class="lstab-copy"
								data-lstab-copy="<?php echo esc_attr( $lstab_shortcode ); ?>">
								<?php echo LSTAB_Icons::icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
								<span class="lstab-copy-label"><?php esc_html_e( 'Copy', 'live-sheets-table' ); ?></span>
							</button>

							<span class="lstab-src-spare">
								<?php if ( ! $lstab_is_sample ) : ?>
									<a class="lstab-quiet" target="_blank" rel="noopener noreferrer"
										href="<?php echo esc_url( LSTAB_Url::edit_url( $lstab_source['sheet_id'], $lstab_source['gid'], $lstab_source['sheet_kind'] ) ); ?>">
										<?php echo LSTAB_Icons::icon( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
										<?php esc_html_e( 'In Google', 'live-sheets-table' ); ?>
									</a>
								<?php endif; ?>

								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lstab-inline-form"
									onsubmit="return window.confirm( <?php echo esc_js( wp_json_encode( __( 'Delete this sheet source? Tables using it will stop rendering.', 'live-sheets-table' ) ) ); ?> );">
									<?php wp_nonce_field( 'lstab_delete_source' ); ?>
									<input type="hidden" name="action" value="lstab_delete_source">
									<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $lstab_source['id'] ); ?>">
									<button type="submit" class="lstab-quiet lstab-quiet--danger">
										<?php echo LSTAB_Icons::icon( 'trash' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
										<?php esc_html_e( 'Delete', 'live-sheets-table' ); ?>
									</button>
								</form>
							</span>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>

		<?php if ( ! LSTAB_Example::exists() && count( $sources ) < 3 ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lstab-example-offer">
				<input type="hidden" name="action" value="lstab_add_example">
				<?php wp_nonce_field( 'lstab_add_example' ); ?>
				<?php echo LSTAB_Icons::icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
				<span><?php esc_html_e( 'Want somewhere safe to try the settings? Add the built-in example price list — it never touches Google.', 'live-sheets-table' ); ?></span>
				<button type="submit" class="lstab-mini lstab-mini--strong"><?php esc_html_e( 'Add the example', 'live-sheets-table' ); ?></button>
			</form>
		<?php endif; ?>

		<?php if ( ! $lstab_can_add ) : ?>
			<div class="lstab-upsell">
				<h3><?php esc_html_e( 'Need more than one sheet?', 'live-sheets-table' ); ?></h3>
				<p>
					<?php esc_html_e( 'Pro adds unlimited sources, one-minute syncing, conditional cell formatting, filtered views, premium presets and private-sheet support.', 'live-sheets-table' ); ?>
				</p>
				<a class="lstab-btn lstab-btn--quiet" href="<?php echo esc_url( LSTAB_Limits::upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Compare Free and Pro', 'live-sheets-table' ); ?>
				</a>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
