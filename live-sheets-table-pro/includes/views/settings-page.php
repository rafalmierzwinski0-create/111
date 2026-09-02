<?php
/**
 * Pro settings screen.
 *
 * @package LiveSheetsTablePro
 *
 * @var array<string,string>          $client
 * @var bool                          $connected
 * @var array<int,array<string,mixed>> $sources
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap lstab-admin">
	<?php LSTAB_Admin::render_masthead( esc_html__( 'Private sheets, filters and everything Pro adds', 'live-sheets-table-pro' ) ); ?>

	<?php LSTAB_Admin::render_tabs( LSTABP_Settings::PAGE_SLUG ); ?>

	<?php LSTABP_Settings::print_notice(); ?>

	<div class="lstab-panel">
		<div class="lstab-panel-head">
			<?php echo LSTAB_Icons::badge( 'lock', 'indigo' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<span>
				<h2><?php esc_html_e( 'Your Google client', 'live-sheets-table-pro' ); ?></h2>
				<span class="lstab-panel-sub"><?php esc_html_e( 'Your own credentials, so your spreadsheets never travel through anyone else\'s', 'live-sheets-table-pro' ); ?></span>
			</span>
		</div>

		<div class="lstab-row lstab-row--wide">
		<div class="lstab-row-say">
		<p class="lstab-row-help">
			<?php esc_html_e( 'To read sheets that are not shared publicly, this site signs in to Google as you. That needs an OAuth client from your own Google Cloud project — your own, deliberately, so your spreadsheets are never routed through anyone else\'s credentials.', 'live-sheets-table-pro' ); ?>
		</p>
		</p>
		<ol class="lstab-steps">
			<li><?php esc_html_e( 'Open Google Cloud Console and create a project.', 'live-sheets-table-pro' ); ?></li>
			<li><?php esc_html_e( 'Enable the Google Sheets API for it.', 'live-sheets-table-pro' ); ?></li>
			<li><?php esc_html_e( 'Create an OAuth client of type “Web application”.', 'live-sheets-table-pro' ); ?></li>
			<li>
				<span>
					<?php esc_html_e( 'Add this exact address as an authorised redirect URI:', 'live-sheets-table-pro' ); ?>
					<span class="lstab-copyline">
						<code><?php echo esc_html( LSTABP_Google_Auth::redirect_uri() ); ?></code>
						<button type="button" class="lstab-copy" data-lstab-copy="<?php echo esc_attr( LSTABP_Google_Auth::redirect_uri() ); ?>">
							<?php echo LSTAB_Icons::icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
							<span class="lstab-copy-label"><?php esc_html_e( 'Copy', 'live-sheets-table-pro' ); ?></span>
						</button>
					</span>
				</span>
			</li>
		</ol>
		</div>

		<div class="lstab-row-do">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lstabp-client-form">
				<?php wp_nonce_field( 'lstabp_save_client' ); ?>
				<input type="hidden" name="action" value="lstabp_save_client">

				<p>
					<label for="lstabp-client-id"><strong><?php esc_html_e( 'Client ID', 'live-sheets-table-pro' ); ?></strong></label>
					<input type="text" id="lstabp-client-id" name="client_id" class="large-text code"
						value="<?php echo esc_attr( $client['client_id'] ); ?>"
						autocomplete="off">
				</p>
				<p>
					<label for="lstabp-client-secret"><strong><?php esc_html_e( 'Client secret', 'live-sheets-table-pro' ); ?></strong></label>
					<input type="password" id="lstabp-client-secret" name="client_secret" class="large-text code"
						value="<?php echo esc_attr( $client['client_secret'] ); ?>"
						autocomplete="off">
				</p>
				<p>
					<button type="submit" class="lstab-btn"><?php esc_html_e( 'Save client', 'live-sheets-table-pro' ); ?></button>
				</p>
			</form>
		</div>
		</div>
	</div>

	<div class="lstab-panel">
		<div class="lstab-panel-head">
			<?php echo LSTAB_Icons::badge( 'link', 'sky' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<span>
				<h2><?php esc_html_e( 'The connected account', 'live-sheets-table-pro' ); ?></h2>
				<span class="lstab-panel-sub"><?php esc_html_e( 'Read access only — this can never change anything in Google', 'live-sheets-table-pro' ); ?></span>
			</span>
		</div>

		<div class="lstab-state-strip">
			<?php if ( ! LSTABP_Google_Auth::has_client() ) : ?>
				<span class="lstab-state lstab-state--idle">
					<?php echo LSTAB_Icons::icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
					<?php esc_html_e( 'Waiting for a client above', 'live-sheets-table-pro' ); ?>
				</span>
			<?php elseif ( $connected ) : ?>
				<span class="lstab-state lstab-state--calm">
					<?php echo LSTAB_Icons::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
					<?php esc_html_e( 'Connected. Private sheets can be read.', 'live-sheets-table-pro' ); ?>
				</span>
			<?php else : ?>
				<span class="lstab-state lstab-state--idle">
					<?php echo LSTAB_Icons::icon( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
					<?php esc_html_e( 'Not connected yet', 'live-sheets-table-pro' ); ?>
				</span>
			<?php endif; ?>
		</div>

		<div class="lstab-row">
			<div class="lstab-row-say">
				<p class="lstab-row-title"><?php esc_html_e( 'Sign in to Google', 'live-sheets-table-pro' ); ?></p>
				<p class="lstab-row-help">
					<?php esc_html_e( 'Only read access to spreadsheets is requested, so this connection cannot change or delete anything in your Google account. Disconnecting takes effect immediately; any sheet still marked private simply stops being readable until you connect again.', 'live-sheets-table-pro' ); ?>
				</p>
			</div>
			<div class="lstab-row-do">
				<?php if ( ! LSTABP_Google_Auth::has_client() ) : ?>
					<span class="lstab-row-help"><?php esc_html_e( 'Save a client above first.', 'live-sheets-table-pro' ); ?></span>
				<?php elseif ( $connected ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'lstabp_google_disconnect' ); ?>
						<input type="hidden" name="action" value="lstabp_google_disconnect">
						<button type="submit" class="lstab-quiet lstab-quiet--danger">
							<?php echo LSTAB_Icons::icon( 'cross' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
							<?php esc_html_e( 'Disconnect', 'live-sheets-table-pro' ); ?>
						</button>
					</form>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'lstabp_google_connect' ); ?>
						<input type="hidden" name="action" value="lstabp_google_connect">
						<button type="submit" class="lstab-btn">
							<?php echo LSTAB_Icons::icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
							<?php esc_html_e( 'Connect a Google account', 'live-sheets-table-pro' ); ?>
						</button>
					</form>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="lstab-panel">
		<div class="lstab-panel-head">
			<?php echo LSTAB_Icons::badge( 'shield', 'violet' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<span>
				<h2><?php esc_html_e( 'Which sheets are private', 'live-sheets-table-pro' ); ?></h2>
				<span class="lstab-panel-sub"><?php esc_html_e( 'Tick one and you can remove link sharing in Google entirely', 'live-sheets-table-pro' ); ?></span>
			</span>
		</div>

		<div class="lstab-panel-body">
		<?php if ( ! $sources ) : ?>
			<p class="lstab-help"><?php esc_html_e( 'No sheet sources yet.', 'live-sheets-table-pro' ); ?></p>
		<?php else : ?>
			<p class="lstab-help">
				<?php esc_html_e( 'Tick a sheet to read it through the connected account instead of its public link. You can then remove link sharing in Google entirely — the spreadsheet becomes private again while the table keeps working.', 'live-sheets-table-pro' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'lstabp_save_sources' ); ?>
				<input type="hidden" name="action" value="lstabp_save_sources">

				<table class="lstab-column-list">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Sheet source', 'live-sheets-table-pro' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Private', 'live-sheets-table-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sources as $source ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $source['title'] ); ?></strong></td>
								<td>
									<label>
										<input type="checkbox" name="private[<?php echo esc_attr( (string) $source['id'] ); ?>]" value="1"
											<?php checked( LSTABP_Private_Sheets::is_private( $source['id'] ) ); ?>>
										<span class="screen-reader-text"><?php esc_html_e( 'Read through the connected account', 'live-sheets-table-pro' ); ?></span>
									</label>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p>
					<button type="submit" class="lstab-btn"><?php esc_html_e( 'Save', 'live-sheets-table-pro' ); ?></button>
				</p>
			</form>
		<?php endif; ?>
		</div>
	</div>

	<div class="lstab-panel">
		<div class="lstab-panel-head">
			<?php echo LSTAB_Icons::badge( 'sliders', 'teal' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<span>
				<h2><?php esc_html_e( 'Filtered views', 'live-sheets-table-pro' ); ?></h2>
				<span class="lstab-panel-sub"><?php esc_html_e( 'One sheet, as many pages as you like', 'live-sheets-table-pro' ); ?></span>
			</span>
		</div>

		<div class="lstab-panel-body">
		<p class="lstab-help">
			<?php esc_html_e( 'One saved sheet can feed as many pages as you like. Add a filter to the shortcode and each page shows only the rows it is about — the spreadsheet stays single.', 'live-sheets-table-pro' ); ?>
		</p>
		<p><code class="lstab-shortcode">[sheet_table id="1" filter="Kategoria is Rowery"]</code></p>
		<p><code class="lstab-shortcode">[sheet_table id="1" filter="Cena netto lt 500, Dostępność is W magazynie"]</code></p>
		<p class="lstab-help">
			<?php esc_html_e( 'Conditions are separated by commas and all must match. Column names match either the heading in your sheet or the name you gave it.', 'live-sheets-table-pro' ); ?>
		</p>
		<table class="lstab-column-list">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Write', 'live-sheets-table-pro' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Meaning', 'live-sheets-table-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$lstabp_operators = array(
					'is'  => __( 'is exactly', 'live-sheets-table-pro' ),
					'not' => __( 'is anything but', 'live-sheets-table-pro' ),
					'has' => __( 'contains', 'live-sheets-table-pro' ),
					'gt'  => __( 'is greater than', 'live-sheets-table-pro' ),
					'gte' => __( 'is greater than or equal to', 'live-sheets-table-pro' ),
					'lt'  => __( 'is less than', 'live-sheets-table-pro' ),
					'lte' => __( 'is less than or equal to', 'live-sheets-table-pro' ),
				);
				foreach ( $lstabp_operators as $lstabp_word => $lstabp_meaning ) :
					?>
					<tr>
						<td><code><?php echo esc_html( $lstabp_word ); ?></code></td>
						<td><?php echo esc_html( $lstabp_meaning ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="lstab-help">
			<?php esc_html_e( 'Symbols such as = and > work too, but WordPress removes a “less than” sign from a shortcode attribute before the plugin ever sees it, so the words above are the form that always works.', 'live-sheets-table-pro' ); ?>
		</p>
		</div>
	</div>
</div>
