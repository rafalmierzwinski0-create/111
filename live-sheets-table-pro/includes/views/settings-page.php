<?php

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
				<span class="lstab-panel-sub"><?php esc_html_e( 'Needed only for private sheets. Sheets shared by link work without it.', 'live-sheets-table-pro' ); ?></span>
			</span>
		</div>

		<div class="lstab-row lstab-row--wide">
		<div class="lstab-row-say">
		<p class="lstab-row-help">
			<?php esc_html_e( 'You need this only for a sheet that must stay private, not shared by link. To read such a sheet, this site signs in to Google with your account. Google allows that only through a “client” that you create, free of charge, in your own Google Cloud project. It takes about ten minutes, once:', 'live-sheets-table-pro' ); ?>
		</p>
		<ol class="lstab-steps">
			<li><?php esc_html_e( 'Go to console.cloud.google.com, sign in and create a new project. Any name will do.', 'live-sheets-table-pro' ); ?></li>
			<li><?php esc_html_e( 'In APIs & Services → Library, find “Google Sheets API” and choose Enable.', 'live-sheets-table-pro' ); ?></li>
			<li><?php esc_html_e( 'Open the OAuth consent screen (also called Google Auth Platform). Give the app a name and your e-mail address, and choose the audience “External”. Then publish the app, so that its status is “In production”. While it is “In testing”, Google ends the connection after seven days.', 'live-sheets-table-pro' ); ?></li>
			<li>
				<span>
					<?php esc_html_e( 'Create an OAuth client of the type “Web application”. Under “Authorised redirect URIs” add this exact address:', 'live-sheets-table-pro' ); ?>
					<span class="lstab-copyline">
						<code><?php echo esc_html( LSTABP_Google_Auth::redirect_uri() ); ?></code>
						<button type="button" class="lstab-copy" data-lstab-copy="<?php echo esc_attr( LSTABP_Google_Auth::redirect_uri() ); ?>">
							<?php echo LSTAB_Icons::icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
							<span class="lstab-copy-label"><?php esc_html_e( 'Copy', 'live-sheets-table-pro' ); ?></span>
						</button>
					</span>
				</span>
			</li>
			<li><?php esc_html_e( 'Google then shows a Client ID and a Client secret. Copy both into the fields here and choose “Save client”.', 'live-sheets-table-pro' ); ?></li>
			<li><?php esc_html_e( 'Choose “Connect a Google account” below.', 'live-sheets-table-pro' ); ?></li>
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
						value=""
						<?php if ( '' !== $client['client_secret'] ) : ?>
							placeholder="<?php esc_attr_e( 'Saved. Leave empty to keep it.', 'live-sheets-table-pro' ); ?>"
						<?php endif; ?>
						autocomplete="new-password">
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
				<span class="lstab-panel-sub"><?php esc_html_e( 'Permission to read spreadsheets only — it can never change anything in Google', 'live-sheets-table-pro' ); ?></span>
			</span>
		</div>

		<div class="lstab-state-strip">
			<?php if ( ! LSTABP_Google_Auth::has_client() ) : ?>
				<span class="lstab-state lstab-state--idle">
					<?php echo LSTAB_Icons::icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
					<?php esc_html_e( 'No client saved yet', 'live-sheets-table-pro' ); ?>
				</span>
			<?php elseif ( ! $connected && LSTABP_Google_Auth::has_expired() ) : ?>
				<span class="lstab-state lstab-state--warn">
					<?php echo LSTAB_Icons::icon( 'alert' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
					<?php esc_html_e( 'Google ended the connection. Sign in again below.', 'live-sheets-table-pro' ); ?>
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
					<?php esc_html_e( 'Sign in with a Google account that can open your private sheets. Google may warn that the app is not verified: it is the app you have just created, so choose “Advanced” and then “Go to” followed by its name. Only permission to read spreadsheets is asked for, so nothing in your Google account can be changed or deleted. If you disconnect, private sheets stop updating at once.', 'live-sheets-table-pro' ); ?>
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
				<span class="lstab-panel-sub"><?php esc_html_e( 'Tables read through your Google account instead of a public link', 'live-sheets-table-pro' ); ?></span>
			</span>
		</div>

		<div class="lstab-panel-body">
		<p class="lstab-help">
			<?php esc_html_e( 'A ticked table is read through the connected Google account, so its sheet does not need to be shared by link. You rarely need to tick anything yourself: when a sheet stops being shared by link and somebody chooses “Refresh” for its table, the table switches over and is ticked here automatically. To make a sheet private:', 'live-sheets-table-pro' ); ?>
		</p>
		<ol class="lstab-steps">
			<li><?php esc_html_e( 'Add the table with the sheet’s link, as usual.', 'live-sheets-table-pro' ); ?></li>
			<li><?php esc_html_e( 'Connect a Google account above, one that can open the sheet.', 'live-sheets-table-pro' ); ?></li>
			<li><?php esc_html_e( 'In Google Sheets choose Share → General access → “Restricted”.', 'live-sheets-table-pro' ); ?></li>
			<li><?php esc_html_e( 'Go to All tables and choose “Refresh” next to the table. It is ticked below and keeps updating. Do not remove the link from the table’s settings: the plugin still needs it to know which sheet to read.', 'live-sheets-table-pro' ); ?></li>
		</ol>
		<?php if ( ! $sources ) : ?>
			<p class="lstab-help"><?php esc_html_e( 'No tables yet.', 'live-sheets-table-pro' ); ?></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'lstabp_save_sources' ); ?>
				<input type="hidden" name="action" value="lstabp_save_sources">

				<table class="lstab-column-list">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Table', 'live-sheets-table-pro' ); ?></th>
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
				<h2><?php esc_html_e( 'Words a filter understands', 'live-sheets-table-pro' ); ?></h2>
				<span class="lstab-panel-sub"><?php esc_html_e( 'How to show only some rows of a sheet', 'live-sheets-table-pro' ); ?></span>
			</span>
		</div>

		<div class="lstab-panel-body">
		<p class="lstab-help">
			<?php esc_html_e( 'A filter makes a table show only the rows that match, so one sheet can feed several pages, for example one price list per category. Add it to the shortcode as below, or type the same text into the Filter field of the block or the Elementor widget. Write the column name, then a word from the list below, then the value:', 'live-sheets-table-pro' ); ?>
		</p>
		<p><code class="lstab-shortcode">[sheet_table id="1" filter="Category is Bikes"]</code></p>
		<p><code class="lstab-shortcode">[sheet_table id="1" filter="Net price lt 500, Availability is In stock"]</code></p>
		<p class="lstab-help">
			<?php esc_html_e( 'To use several conditions, join them with “and” or a comma. A row is shown only if it matches all of them. Each condition holds one value, so “Category is Bikes, Helmets” does not mean bikes or helmets. For the column you can write its name in the sheet or the new name you gave it. Capital letters and extra spaces make no difference.', 'live-sheets-table-pro' ); ?>
		</p>
		<p class="lstab-help">
			<?php esc_html_e( 'A filter chooses rows, not columns. Which columns are shown is set once for each table, on its own screen, and every page that shows the table shows the same columns.', 'live-sheets-table-pro' ); ?>
		</p>
		<table class="lstab-attribute-list">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Write', 'live-sheets-table-pro' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Meaning', 'live-sheets-table-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$lstabp_operators = array(
					'is'  => __( 'is', 'live-sheets-table-pro' ),
					'not' => __( 'is not; you can also write “is not”', 'live-sheets-table-pro' ),
					'has' => __( 'contains, anywhere in the cell', 'live-sheets-table-pro' ),
					'gt'  => __( 'is more than', 'live-sheets-table-pro' ),
					'gte' => __( 'is at least', 'live-sheets-table-pro' ),
					'lt'  => __( 'is less than', 'live-sheets-table-pro' ),
					'lte' => __( 'is at most', 'live-sheets-table-pro' ),
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
			<?php esc_html_e( 'The last four compare numbers. Currency signs and spaces are ignored, so “$1 200” counts as 1200. A cell without a number never matches them.', 'live-sheets-table-pro' ); ?>
		</p>
		<p class="lstab-help">
			<?php esc_html_e( 'A shortcode cannot contain square brackets or a backslash. If a column’s name has either, give the column a plain name under Columns and rows on the table’s own screen, and use that name in the filter.', 'live-sheets-table-pro' ); ?>
		</p>
		</div>
	</div>
</div>
