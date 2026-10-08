<?php
/**
 * The screen the plugin opens on.
 *
 * One screen for everybody, so that somebody's tenth visit looks like their
 * first with the obvious next step filled in. Only the large block on the left
 * changes: before there is a sheet of one's own it is the way to add one, and
 * after that it says how the tables are doing — the ones that need somebody by
 * name, the rest only as a number, because a list of fifteen healthy tables is
 * not news. The three steps and the two cards below stay where they were.
 *
 * @package LiveSheetsTable
 *
 * @var array<int,array<string,mixed>> $sources All sources.
 */

defined( 'ABSPATH' ) || exit;

/*
 * The example is ours, not the reader's: somebody who has only tried it has
 * still not connected anything, so they get the first-time block.
 */
$lstab_own = array_values(
	array_filter(
		$sources,
		static function ( $candidate ) {
			return ! LSTAB_Example::is_example( $candidate );
		}
	)
);

$lstab_example = null;
foreach ( $sources as $lstab_candidate ) {
	if ( LSTAB_Example::is_example( $lstab_candidate ) ) {
		$lstab_example = $lstab_candidate;
		break;
	}
}

$lstab_failing = array_values(
	array_filter(
		$lstab_own,
		static function ( $candidate ) {
			return 'error' === $candidate['last_status'];
		}
	)
);

$lstab_rows = 0;
foreach ( $lstab_own as $lstab_candidate ) {
	$lstab_rows += (int) $lstab_candidate['row_count'];
}

// Named only up to three; the rest are a link to the list.
$lstab_named = array_slice( $lstab_failing, 0, 3 );
$lstab_more  = count( $lstab_failing ) - count( $lstab_named );

$lstab_edit_url = static function ( $id ) {
	return add_query_arg(
		array(
			'page'   => LSTAB_Admin::EDIT_SLUG,
			'source' => (int) $id,
		),
		admin_url( 'admin.php' )
	);
};

$lstab_list_url = admin_url( 'admin.php?page=' . LSTAB_Admin::SOURCES_SLUG );

$lstab_sub = $lstab_own
	? LSTAB_Admin::masthead_summary( $lstab_own )
	: __( 'Google Sheets on your pages, kept up to date by themselves', 'live-sheets-table' );

$lstab_add_button = ( $lstab_own && LSTAB_Limits::can_add_source() )
	? sprintf(
		'<a href="%s" class="lstab-btn">%s%s</a>',
		esc_url( admin_url( 'admin.php?page=' . LSTAB_Admin::EDIT_SLUG ) ),
		LSTAB_Icons::icon( 'plus' ),
		esc_html__( 'Add a sheet', 'live-sheets-table' )
	)
	: '';
?>
<div class="wrap lstab-admin lstab-start-page">
	<?php LSTAB_Admin::render_masthead( $lstab_sub, $lstab_add_button ); ?>

	<?php LSTAB_Admin::render_tabs( LSTAB_Admin::MENU_SLUG ); ?>

	<?php LSTAB_Admin::print_cron_notice(); ?>
	<?php LSTAB_Admin::print_notice(); ?>

	<div class="lstab-start">
		<div class="lstab-start-main">
			<?php if ( ! $lstab_own ) : ?>
				<span class="lstab-start-label"><?php esc_html_e( 'Welcome', 'live-sheets-table' ); ?></span>
				<h2><?php esc_html_e( 'Your first table in under a minute', 'live-sheets-table' ); ?></h2>
				<p class="lstab-start-lede">
					<?php esc_html_e( 'Copy the address of a Google Sheet from your browser and paste it below. You will see the table straight away — nothing is saved until you say so.', 'live-sheets-table' ); ?>
				</p>
			<?php else : ?>
				<span class="lstab-start-label"><?php esc_html_e( 'Your tables', 'live-sheets-table' ); ?></span>
				<h2><?php esc_html_e( 'Welcome back', 'live-sheets-table' ); ?></h2>
				<p class="lstab-start-figures">
					<span>
						<?php
						printf(
							/* translators: %s: number of tables, wrapped in <b>. */
							esc_html( _n( '%s table', '%s tables', count( $lstab_own ), 'live-sheets-table' ) ),
							'<b>' . esc_html( number_format_i18n( count( $lstab_own ) ) ) . '</b>'
						);
						?>
					</span>
					<span>
						<?php
						printf(
							/* translators: %s: number of rows, wrapped in <b>. */
							esc_html( _n( '%s row', '%s rows', $lstab_rows, 'live-sheets-table' ) ),
							'<b>' . esc_html( number_format_i18n( $lstab_rows ) ) . '</b>'
						);
						?>
					</span>
				</p>

				<?php if ( $lstab_failing ) : ?>
					<div class="lstab-start-health">
						<div class="lstab-start-health-head">
							<span class="lstab-start-dot" aria-hidden="true"></span>
							<span>
								<b>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: number of tables. */
											_n( '%s table is not updating', '%s tables are not updating', count( $lstab_failing ), 'live-sheets-table' ),
											number_format_i18n( count( $lstab_failing ) )
										)
									);
									?>
								</b>
								<?php esc_html_e( 'Visitors still see the last good copy.', 'live-sheets-table' ); ?>
							</span>
						</div>
						<?php foreach ( $lstab_named as $lstab_bad ) : ?>
							<a class="lstab-start-health-row" href="<?php echo esc_url( $lstab_edit_url( $lstab_bad['id'] ) ); ?>">
								<span><?php echo esc_html( $lstab_bad['title'] ); ?></span>
								<em><?php esc_html_e( 'Fix', 'live-sheets-table' ); ?> →</em>
							</a>
						<?php endforeach; ?>
						<?php if ( $lstab_more > 0 ) : ?>
							<a class="lstab-start-health-row lstab-start-health-more" href="<?php echo esc_url( $lstab_list_url ); ?>">
								<span>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: number of further tables. */
											_n( 'and %s more', 'and %s more', $lstab_more, 'live-sheets-table' ),
											number_format_i18n( $lstab_more )
										)
									);
									?>
								</span>
								<em><?php esc_html_e( 'See all', 'live-sheets-table' ); ?> →</em>
							</a>
						<?php endif; ?>
						<p class="lstab-start-health-cause">
							<?php esc_html_e( 'Most often the sheet is no longer shared by link. Open it in Google, choose Share, and check it says “Anyone with the link”.', 'live-sheets-table' ); ?>
						</p>
					</div>
				<?php endif; ?>

				<?php $lstab_fine = count( $lstab_own ) - count( $lstab_failing ); ?>
				<?php if ( $lstab_fine > 0 ) : ?>
					<a class="lstab-start-fine" href="<?php echo esc_url( $lstab_list_url ); ?>">
						<span class="lstab-start-dot lstab-start-dot--ok" aria-hidden="true"></span>
						<span>
							<?php
							if ( $lstab_failing ) {
								printf(
									/* translators: %s: number of tables, wrapped in <b>. */
									esc_html( _n( '%s table is up to date.', '%s tables are up to date.', $lstab_fine, 'live-sheets-table' ) ),
									'<b>' . esc_html( number_format_i18n( $lstab_fine ) ) . '</b>'
								);
							} else {
								esc_html_e( 'Every table is up to date.', 'live-sheets-table' );
							}
							?>
						</span>
						<em><?php esc_html_e( 'All tables', 'live-sheets-table' ); ?> →</em>
					</a>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( LSTAB_Limits::can_add_source() ) : ?>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="lstab-start-form">
					<input type="hidden" name="page" value="<?php echo esc_attr( LSTAB_Admin::EDIT_SLUG ); ?>">
					<span class="lstab-field">
						<?php echo LSTAB_Icons::icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
						<label class="screen-reader-text" for="lstab-start-url"><?php esc_html_e( 'Google Sheets link', 'live-sheets-table' ); ?></label>
						<input type="url" id="lstab-start-url" name="sheet_url" inputmode="url" autocomplete="off"
							placeholder="<?php echo esc_attr( $lstab_own ? __( 'Paste another Google Sheets link', 'live-sheets-table' ) : 'https://docs.google.com/spreadsheets/d/…' ); ?>">
					</span>
					<button type="submit" class="lstab-btn">
						<?php echo LSTAB_Icons::icon( $lstab_own ? 'plus' : 'eye' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
						<?php echo esc_html( $lstab_own ? __( 'Add a table', 'live-sheets-table' ) : __( 'Show me the table', 'live-sheets-table' ) ); ?>
					</button>
				</form>
			<?php endif; ?>

			<?php if ( ! $lstab_own ) : ?>
				<ul class="lstab-start-ticks">
					<li><?php echo LSTAB_Icons::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?><?php esc_html_e( 'No API key', 'live-sheets-table' ); ?></li>
					<li><?php echo LSTAB_Icons::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?><?php esc_html_e( 'No row limit', 'live-sheets-table' ); ?></li>
					<li><?php echo LSTAB_Icons::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?><?php esc_html_e( 'Read-only — nothing is written to Google', 'live-sheets-table' ); ?></li>
				</ul>
			<?php endif; ?>
		</div>

		<aside class="lstab-start-steps">
			<span class="lstab-start-label"><?php esc_html_e( 'Three steps', 'live-sheets-table' ); ?></span>
			<ol>
				<li class="<?php echo $lstab_own ? '' : 'is-now'; ?>">
					<b><?php esc_html_e( 'Share the sheet by link', 'live-sheets-table' ); ?></b>
					<span><?php esc_html_e( 'In Google Sheets: Share → General access →', 'live-sheets-table' ); ?></span>
					<span class="lstab-start-share"><?php esc_html_e( 'Anyone with the link', 'live-sheets-table' ); ?> <em><?php esc_html_e( 'Viewer', 'live-sheets-table' ); ?></em></span>
				</li>
				<li>
					<b><?php esc_html_e( 'Paste the link here', 'live-sheets-table' ); ?></b>
					<span><?php esc_html_e( 'Pick the tab, give it a name. The preview shows exactly what the plugin reads.', 'live-sheets-table' ); ?></span>
				</li>
				<li>
					<b><?php esc_html_e( 'Put it on a page', 'live-sheets-table' ); ?></b>
					<span><?php esc_html_e( 'The “Google Sheets Table” block, the Elementor widget or a shortcode.', 'live-sheets-table' ); ?></span>
				</li>
			</ol>
		</aside>
	</div>

	<div class="lstab-start-cards">
		<div class="lstab-start-card">
			<span class="lstab-start-ico"><?php echo LSTAB_Icons::icon( 'grid' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?></span>
			<h3><?php esc_html_e( 'No spreadsheet yet?', 'live-sheets-table' ); ?></h3>
			<p><?php esc_html_e( 'Add a ready example price list and try every setting on it. It never contacts Google, and one click removes it.', 'live-sheets-table' ); ?></p>
			<?php if ( $lstab_example ) : ?>
				<a class="lstab-btn lstab-btn--quiet" href="<?php echo esc_url( $lstab_edit_url( $lstab_example['id'] ) ); ?>"><?php esc_html_e( 'Open the example', 'live-sheets-table' ); ?></a>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="lstab_add_example">
					<?php wp_nonce_field( 'lstab_add_example' ); ?>
					<button type="submit" class="lstab-btn lstab-btn--quiet"><?php esc_html_e( 'Add the example', 'live-sheets-table' ); ?></button>
				</form>
			<?php endif; ?>
		</div>

		<?php if ( ! LSTAB_Limits::is_pro() ) : ?>
			<div class="lstab-start-card lstab-start-card--pro">
				<span class="lstab-start-ico"><?php echo LSTAB_Icons::icon( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?></span>
				<h3><?php esc_html_e( 'Sheet must stay private?', 'live-sheets-table' ); ?> <span class="lstab-tagpro">PRO</span></h3>
				<p><?php esc_html_e( 'Connect your own Google account and the table is read without a public link. Pro also syncs every minute, colours cells by rules, lets visitors filter and download, and keeps any number of tables.', 'live-sheets-table' ); ?></p>
				<a class="lstab-btn" href="<?php echo esc_url( LSTAB_Limits::upgrade_url() ); ?>" target="_blank" rel="noopener">
					<?php echo LSTAB_Icons::icon( 'spark' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
					<?php esc_html_e( 'Buy Pro', 'live-sheets-table' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
</div>
