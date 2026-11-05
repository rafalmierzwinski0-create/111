<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="lstab-welcome">
	<span class="lstab-handle" aria-hidden="true"></span>

	<?php echo LSTAB_Icons::art( 'start' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG from the plugin. ?>

	<h2><?php esc_html_e( 'Your first table in under a minute', 'tabliko' ); ?></h2>
	<p class="lstab-welcome-lede">
		<?php esc_html_e( 'Open your sheet in Google Sheets, copy the address from the browser’s address bar and paste it here. The table appears straight away, and nothing is saved until you choose to.', 'tabliko' ); ?>
	</p>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="lstab-welcome-form">
		<input type="hidden" name="page" value="<?php echo esc_attr( LSTAB_Admin::EDIT_SLUG ); ?>">
		<span class="lstab-field">
			<?php echo LSTAB_Icons::icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<label class="screen-reader-text" for="lstab-welcome-url"><?php esc_html_e( 'Google Sheets link', 'tabliko' ); ?></label>
			<input type="url"
				id="lstab-welcome-url"
				name="sheet_url"
				inputmode="url"
				autocomplete="off"
				placeholder="https://docs.google.com/spreadsheets/d/…">
		</span>
		<button type="submit" class="lstab-btn">
			<?php echo LSTAB_Icons::icon( 'eye' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<?php esc_html_e( 'Show me the table', 'tabliko' ); ?>
		</button>
	</form>

	<div class="lstab-sells">
		<span><?php echo LSTAB_Icons::icon( 'bolt' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?><?php esc_html_e( 'Loads fast — the page uses a copy kept on your site', 'tabliko' ); ?></span>
		<span><?php echo LSTAB_Icons::icon( 'refresh' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?><?php esc_html_e( 'Changes in the sheet appear on the page by themselves', 'tabliko' ); ?></span>
		<span><?php echo LSTAB_Icons::icon( 'shield' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?><?php esc_html_e( 'Read-only — nothing is ever written to your sheet', 'tabliko' ); ?></span>
	</div>

	<div class="lstab-forks">
		<div class="lstab-fork">
			<?php echo LSTAB_Icons::icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<span>
				<b><?php esc_html_e( 'No spreadsheet yet?', 'tabliko' ); ?></b>
				<span>
					<?php
					printf(
						/* translators: %s: link that adds the built-in example, already escaped. */
						esc_html__( '%s and see the whole plugin working. You can delete it at any time.', 'tabliko' ),
						'<button type="submit" form="lstab-example-form" class="lstab-linkbutton">'
							. esc_html__( 'Add the example price list', 'tabliko' )
							. '</button>'
					);
					?>
				</span>
			</span>
		</div>

		<div class="lstab-fork">
			<?php echo LSTAB_Icons::icon( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<span>
				<b><?php esc_html_e( 'Is the sheet private?', 'tabliko' ); ?></b>
				<span>
					<?php esc_html_e( 'Share it by link first. In Google Sheets choose Share → General access → “Anyone with the link”, with the role “Viewer”. The plugin only reads the sheet and never changes it.', 'tabliko' ); ?>
				</span>
			</span>
		</div>
	</div>

	<form id="lstab-example-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lstab-hidden-form">
		<input type="hidden" name="action" value="lstab_add_example">
		<?php wp_nonce_field( 'lstab_add_example' ); ?>
	</form>
</div>
