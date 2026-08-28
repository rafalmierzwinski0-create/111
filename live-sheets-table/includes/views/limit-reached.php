<?php
/**
 * Shown when the free source limit is already used up.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap lstab-admin">
	<h1><?php esc_html_e( 'Add new sheet source', 'live-sheets-table' ); ?></h1>

	<div class="lstab-upsell lstab-upsell--large">
		<h2>
			<?php
			printf(
				/* translators: %d: number of sources allowed in the free version. */
				esc_html(
					_n(
						'The free version keeps %d sheet source',
						'The free version keeps %d sheet sources',
						LSTAB_Limits::max_sources(),
						'live-sheets-table'
					)
				),
				(int) LSTAB_Limits::max_sources()
			);
			?>
		</h2>
		<p>
			<?php esc_html_e( 'Rows are never limited — a source can hold as many rows as your sheet does. To publish several different sheets at once, upgrade to Pro.', 'live-sheets-table' ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( LSTAB_Limits::upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'See what Pro adds', 'live-sheets-table' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . LSTAB_Admin::MENU_SLUG ) ); ?>">
				<?php esc_html_e( 'Back to sources', 'live-sheets-table' ); ?>
			</a>
		</p>
	</div>
</div>
