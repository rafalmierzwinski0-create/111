<?php
/**
 * What somebody sees the first time, before there is anything to list.
 *
 * Three ways in, one of them obvious. Whoever has a link pastes it; whoever has
 * no spreadsheet yet clicks the example; whoever has a private sheet is told
 * where that lives. Nothing here asks for a name, a schedule or a tab — those
 * are questions for after the thing has been seen working.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="lstab-welcome">
	<span class="lstab-handle" aria-hidden="true"></span>

	<?php echo LSTAB_Icons::art( 'start' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG from the plugin. ?>

	<h2><?php esc_html_e( 'Your price list on the page in ten seconds', 'live-sheets-table' ); ?></h2>
	<p class="lstab-welcome-lede">
		<?php esc_html_e( 'Open your sheet, copy the address from the browser bar, paste it here. You will see the table straight away — before anything is saved and before we ask you anything else.', 'live-sheets-table' ); ?>
	</p>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="lstab-welcome-form">
		<input type="hidden" name="page" value="<?php echo esc_attr( LSTAB_Admin::EDIT_SLUG ); ?>">
		<span class="lstab-field">
			<?php echo LSTAB_Icons::icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<label class="screen-reader-text" for="lstab-welcome-url"><?php esc_html_e( 'Google Sheets link', 'live-sheets-table' ); ?></label>
			<input type="url"
				id="lstab-welcome-url"
				name="sheet_url"
				inputmode="url"
				autocomplete="off"
				placeholder="https://docs.google.com/spreadsheets/d/…">
		</span>
		<button type="submit" class="lstab-btn">
			<?php echo LSTAB_Icons::icon( 'eye' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<?php esc_html_e( 'Show me the table', 'live-sheets-table' ); ?>
		</button>
	</form>

	<div class="lstab-sells">
		<span><?php echo LSTAB_Icons::icon( 'bolt' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?><?php esc_html_e( 'Loads instantly — the page reads a local copy', 'live-sheets-table' ); ?></span>
		<span><?php echo LSTAB_Icons::icon( 'refresh' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?><?php esc_html_e( 'A change in the sheet reaches the page by itself', 'live-sheets-table' ); ?></span>
		<span><?php echo LSTAB_Icons::icon( 'shield' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?><?php esc_html_e( 'We only ever read. Never write', 'live-sheets-table' ); ?></span>
	</div>

	<div class="lstab-forks">
		<div class="lstab-fork">
			<?php echo LSTAB_Icons::icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<span>
				<b><?php esc_html_e( 'No spreadsheet yet?', 'live-sheets-table' ); ?></b>
				<span>
					<?php
					printf(
						/* translators: %s: link that adds the built-in example, already escaped. */
						esc_html__( '%s and see the whole plugin working. One click removes it again.', 'live-sheets-table' ),
						'<button type="submit" form="lstab-example-form" class="lstab-linkbutton">'
							. esc_html__( 'Add the example price list', 'live-sheets-table' )
							. '</button>'
					);
					?>
				</span>
			</span>
		</div>

		<div class="lstab-fork">
			<?php echo LSTAB_Icons::icon( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<span>
				<b>
					<?php esc_html_e( 'Is the sheet private?', 'live-sheets-table' ); ?>
					<span class="lstab-tagpro">PRO</span>
				</b>
				<span>
					<?php esc_html_e( 'Sharing by link is all the free version needs. Connecting a Google account, for sheets that cannot be shared at all, is part of Pro.', 'live-sheets-table' ); ?>
				</span>
			</span>
		</div>
	</div>

	<?php
	/*
	 * Outside the paste form, because a form cannot be nested in another one.
	 * The button above submits this by name.
	 */
	?>
	<form id="lstab-example-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lstab-hidden-form">
		<input type="hidden" name="action" value="lstab_add_example">
		<?php wp_nonce_field( 'lstab_add_example' ); ?>
	</form>
</div>
