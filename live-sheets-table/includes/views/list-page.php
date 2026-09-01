<?php
/**
 * Sheet source list screen.
 *
 * @package LiveSheetsTable
 *
 * @var array<int,array<string,mixed>> $sources
 */

defined( 'ABSPATH' ) || exit;

$lstab_can_add = LSTAB_Limits::can_add_source();
?>
<div class="wrap lstab-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Sheet sources', 'live-sheets-table' ); ?></h1>

	<?php if ( $lstab_can_add ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . LSTAB_Admin::EDIT_SLUG ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add new', 'live-sheets-table' ); ?>
		</a>
	<?php endif; ?>

	<hr class="wp-header-end">

	<?php LSTAB_Admin::print_grace_notice(); ?>
	<?php LSTAB_Admin::print_cron_notice(); ?>
	<?php LSTAB_Admin::print_notice(); ?>

	<?php if ( ! $sources ) : ?>
		<div class="lstab-empty-state">
			<h2><?php esc_html_e( 'Publish your first Google Sheet', 'live-sheets-table' ); ?></h2>
			<p>
				<?php esc_html_e( 'Share a sheet as “Anyone with the link – Viewer”, paste the link, and check the preview before you save. No API key, no Google Cloud project.', 'live-sheets-table' ); ?>
			</p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . LSTAB_Admin::EDIT_SLUG ) ); ?>" class="button button-primary button-hero">
				<?php esc_html_e( 'Add a sheet source', 'live-sheets-table' ); ?>
			</a>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped lstab-sources">
			<thead>
				<tr>
					<th scope="col" class="column-primary"><?php esc_html_e( 'Source', 'live-sheets-table' ); ?></th>
					<th scope="col" class="lstab-col-status"><?php esc_html_e( 'Sync status', 'live-sheets-table' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Size', 'live-sheets-table' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Schedule', 'live-sheets-table' ); ?></th>
					<th scope="col" class="lstab-col-shortcode"><?php esc_html_e( 'Shortcode', 'live-sheets-table' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'live-sheets-table' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $sources as $lstab_source ) :
					$lstab_status    = LSTAB_Admin::status_for( $lstab_source );
					$lstab_intervals = LSTAB_Limits::intervals();
					?>
					<tr>
						<td class="column-primary">
							<strong><?php echo esc_html( $lstab_source['title'] ); ?></strong>
							<div class="row-actions">
								<span>
									<a href="<?php echo esc_url( LSTAB_Url::edit_url( $lstab_source['sheet_id'], $lstab_source['gid'], $lstab_source['sheet_kind'] ) ); ?>" target="_blank" rel="noopener noreferrer">
										<?php esc_html_e( 'Open in Google Sheets', 'live-sheets-table' ); ?>
									</a>
								</span>
							</div>
							<?php if ( $lstab_source['tab_name'] ) : ?>
								<div class="lstab-muted">
									<?php
									printf(
										/* translators: %s: sheet tab name. */
										esc_html__( 'Tab: %s', 'live-sheets-table' ),
										esc_html( $lstab_source['tab_name'] )
									);
									?>
								</div>
							<?php endif; ?>
						</td>
						<td>
							<span class="lstab-status lstab-status--<?php echo esc_attr( $lstab_status['state'] ); ?>">
								<span class="dashicons <?php echo esc_attr( $lstab_status['icon'] ); ?>" aria-hidden="true"></span>
								<?php echo esc_html( $lstab_status['text'] ); ?>
							</span>
							<?php if ( $lstab_status['detail'] ) : ?>
								<div class="lstab-status-detail"><?php echo esc_html( $lstab_status['detail'] ); ?></div>
							<?php endif; ?>
							<?php
							/*
							 * A sheet can sync perfectly and still come back
							 * malformed. Nothing here stops the table from
							 * rendering — it just says where to look, because
							 * this is the kind of fault nobody notices until a
							 * customer does.
							 */
							if ( ! empty( $lstab_source['last_ragged'] ) ) :
								?>
								<div class="lstab-status-detail lstab-status-ragged">
									<span class="dashicons dashicons-warning" aria-hidden="true"></span>
									<?php echo esc_html( LSTAB_Admin::ragged_summary( $lstab_source['last_ragged'] ) ); ?>
								</div>
							<?php endif; ?>
						</td>
						<td>
							<?php
							printf(
								/* translators: 1: row count, 2: column count. */
								esc_html__( '%1$s rows × %2$s cols', 'live-sheets-table' ),
								esc_html( number_format_i18n( $lstab_source['row_count'] ) ),
								esc_html( number_format_i18n( $lstab_source['col_count'] ) )
							);
							?>
						</td>
						<td>
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
						</td>
						<td>
							<code class="lstab-shortcode">[sheet_table id="<?php echo esc_attr( (string) $lstab_source['id'] ); ?>"]</code>
						</td>
						<td class="lstab-row-actions">
							<a class="button button-small" href="
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
							"><?php esc_html_e( 'Edit', 'live-sheets-table' ); ?></a>

							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lstab-inline-form">
								<?php wp_nonce_field( 'lstab_refresh_source' ); ?>
								<input type="hidden" name="action" value="lstab_refresh_source">
								<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $lstab_source['id'] ); ?>">
								<button type="submit" class="button button-small button-primary">
									<?php esc_html_e( 'Refresh now', 'live-sheets-table' ); ?>
								</button>
							</form>

							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lstab-inline-form"
								onsubmit="return window.confirm( <?php echo esc_js( wp_json_encode( __( 'Delete this sheet source? Tables using it will stop rendering.', 'live-sheets-table' ) ) ); ?> );">
								<?php wp_nonce_field( 'lstab_delete_source' ); ?>
								<input type="hidden" name="action" value="lstab_delete_source">
								<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $lstab_source['id'] ); ?>">
								<button type="submit" class="button button-small button-link-delete">
									<?php esc_html_e( 'Delete', 'live-sheets-table' ); ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( ! $lstab_can_add ) : ?>
			<div class="lstab-upsell">
				<h3><?php esc_html_e( 'Need more than one sheet?', 'live-sheets-table' ); ?></h3>
				<p>
					<?php esc_html_e( 'Pro adds unlimited sources, one-minute syncing, conditional cell formatting, filtered views, premium presets and private-sheet support.', 'live-sheets-table' ); ?>
				</p>
				<a class="button button-secondary" href="<?php echo esc_url( LSTAB_Limits::upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Compare Free and Pro', 'live-sheets-table' ); ?>
				</a>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
