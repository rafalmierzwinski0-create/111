<?php
/**
 * Settings screen.
 *
 * One panel, one row per decision, rather than a stack of identical white
 * boxes. Six equal cards made six equal shouts and gave the eye nowhere to
 * land; here the coloured badge is the landmark, the left column says what the
 * choice is and why, and the right column is the only thing to touch.
 *
 * @package LiveSheetsTable
 *
 * @var array<string,mixed> $settings
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap lstab-admin lstab-settings">
	<?php LSTAB_Admin::render_masthead( esc_html__( 'Settings for the whole site', 'live-sheets-table' ) ); ?>

	<?php LSTAB_Admin::render_tabs( LSTAB_Admin::SETTINGS_SLUG ); ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides whether to say "saved". ?>
	<?php if ( isset( $_GET['lstab-saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'live-sheets-table' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lstab-settings-form">
		<input type="hidden" name="action" value="lstab_save_settings">
		<?php wp_nonce_field( 'lstab_save_settings' ); ?>

		<div class="lstab-panel">
			<div class="lstab-panel-head">
				<?php echo LSTAB_Icons::badge( 'globe', 'sky' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
				<span>
					<h2><?php esc_html_e( 'Language', 'live-sheets-table' ); ?></h2>
					<span class="lstab-panel-sub"><?php esc_html_e( 'Which language this plugin uses, here and on your pages', 'live-sheets-table' ); ?></span>
				</span>
			</div>

			<div class="lstab-row">
				<div class="lstab-row-say">
					<p class="lstab-row-title"><?php esc_html_e( 'Language of this plugin', 'live-sheets-table' ); ?></p>
					<p class="lstab-row-help">
						<?php esc_html_e( 'Covers these screens, the block in the editor, and the words visitors see in the table. The rest of the dashboard keeps the language set in WordPress.', 'live-sheets-table' ); ?>
					</p>
				</div>
				<div class="lstab-row-do">
					<select name="lstab_settings[locale]">
						<?php foreach ( LSTAB_Locale::choices() as $lstab_code => $lstab_language ) : ?>
							<option value="<?php echo esc_attr( $lstab_code ); ?>" <?php selected( $settings['locale'], $lstab_code ); ?>>
								<?php echo esc_html( $lstab_language ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		</div>

		<div class="lstab-panel">
			<div class="lstab-panel-head">
				<?php echo LSTAB_Icons::badge( 'shield', 'indigo' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
				<span>
					<h2><?php esc_html_e( 'Access', 'live-sheets-table' ); ?></h2>
					<span class="lstab-panel-sub"><?php esc_html_e( 'Who is trusted with the tables on this site', 'live-sheets-table' ); ?></span>
				</span>
			</div>

			<div class="lstab-row">
				<div class="lstab-row-say">
					<p class="lstab-row-title"><?php esc_html_e( 'Who can manage tables', 'live-sheets-table' ); ?></p>
					<p class="lstab-row-help">
						<?php esc_html_e( 'Anyone who can manage tables can also read every sheet they point at, including columns left out of the published table.', 'live-sheets-table' ); ?>
					</p>
				</div>
				<div class="lstab-row-do">
					<select name="lstab_settings[manage_capability]">
						<?php foreach ( LSTAB_Settings::capabilities() as $lstab_cap => $lstab_label ) : ?>
							<option value="<?php echo esc_attr( $lstab_cap ); ?>" <?php selected( $settings['manage_capability'], $lstab_cap ); ?>>
								<?php echo esc_html( $lstab_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		</div>

		<div class="lstab-panel">
			<div class="lstab-panel-head">
				<?php echo LSTAB_Icons::badge( 'refresh', 'sky' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
				<span>
					<h2><?php esc_html_e( 'Checking Google', 'live-sheets-table' ); ?></h2>
					<span class="lstab-panel-sub"><?php esc_html_e( 'How often, and how long a visitor may be made to wait', 'live-sheets-table' ); ?></span>
				</span>
			</div>

			<div class="lstab-row">
				<div class="lstab-row-say">
					<p class="lstab-row-title"><?php esc_html_e( 'How often new tables check Google', 'live-sheets-table' ); ?></p>
					<p class="lstab-row-help">
						<?php esc_html_e( 'The value given to each new table. Existing tables are not affected, and every table can be set separately.', 'live-sheets-table' ); ?>
					</p>
				</div>
				<div class="lstab-row-do">
					<select name="lstab_settings[default_interval]">
						<option value="0" <?php selected( (int) $settings['default_interval'], 0 ); ?>>
							<?php
							printf(
								/* translators: %s: human readable duration, e.g. "15 minutes". */
								esc_html__( 'The fastest allowed, currently every %s', 'live-sheets-table' ),
								esc_html( LSTAB_Locale::span( 0, LSTAB_Limits::min_interval() ) )
							);
							?>
						</option>
						<?php foreach ( LSTAB_Cron::schedule_map() as $lstab_slug => $lstab_seconds ) : ?>
							<?php if ( $lstab_seconds < LSTAB_Limits::min_interval() ) : ?>
								<?php continue; ?>
							<?php endif; ?>
							<option value="<?php echo esc_attr( (string) $lstab_seconds ); ?>" <?php selected( (int) $settings['default_interval'], $lstab_seconds ); ?>>
								<?php
								printf(
									/* translators: %s: human readable duration, e.g. "15 minutes". */
									esc_html__( 'Every %s', 'live-sheets-table' ),
									esc_html( LSTAB_Locale::span( 0, $lstab_seconds ) )
								);
								?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="lstab-row">
				<div class="lstab-row-say">
					<p class="lstab-row-title"><?php esc_html_e( 'How long to wait for Google', 'live-sheets-table' ); ?></p>
					<p class="lstab-row-help">
						<?php esc_html_e( 'Applies only when a visitor opens a table that is overdue for a check. After this time the table is drawn from the copy already stored.', 'live-sheets-table' ); ?>
					</p>
				</div>
				<div class="lstab-row-do">
					<label class="lstab-timeout">
						<input type="number"
							name="lstab_settings[view_timeout]"
							value="<?php echo esc_attr( (string) $settings['view_timeout'] ); ?>"
							min="2" max="15" step="1" inputmode="numeric">
						<span><?php esc_html_e( 'seconds', 'live-sheets-table' ); ?></span>
					</label>
				</div>
			</div>
		</div>

		<div class="lstab-panel">
			<div class="lstab-panel-head">
				<?php echo LSTAB_Icons::badge( 'brush', 'violet' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
				<span>
					<h2><?php esc_html_e( 'Appearance', 'live-sheets-table' ); ?></h2>
					<span class="lstab-panel-sub"><?php esc_html_e( 'How a new table looks before you change anything', 'live-sheets-table' ); ?></span>
				</span>
			</div>

			<div class="lstab-row">
				<div class="lstab-row-say">
					<p class="lstab-row-title"><?php esc_html_e( 'How new tables look', 'live-sheets-table' ); ?></p>
					<p class="lstab-row-help">
						<?php esc_html_e( 'The style given to each new table. Every table can still be changed afterwards.', 'live-sheets-table' ); ?>
					</p>
				</div>
				<div class="lstab-row-do">
					<select name="lstab_settings[default_style]">
						<?php foreach ( LSTAB_Styles::all() as $lstab_slug => $lstab_preset ) : ?>
							<?php if ( ! empty( $lstab_preset['pro'] ) && ! LSTAB_Limits::is_pro() ) : ?>
								<?php continue; ?>
							<?php endif; ?>
							<option value="<?php echo esc_attr( $lstab_slug ); ?>" <?php selected( (string) $settings['default_style'], $lstab_slug ); ?>>
								<?php echo esc_html( $lstab_preset['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		</div>

		<?php
		/**
		 * Fires inside the settings form, under the free plugin's own panels.
		 *
		 * An add-on printing fields here has them submitted with everything
		 * else, and can read them back on 'lstab_settings_saved' — which only
		 * fires once the capability and nonce checks have passed.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 */
		do_action( 'lstab_settings_sections', $settings );
		?>

		<div class="lstab-panel">
			<div class="lstab-panel-head">
				<?php echo LSTAB_Icons::badge( 'trash', 'rose' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
				<span>
					<h2><?php esc_html_e( 'When this plugin is deleted', 'live-sheets-table' ); ?></h2>
					<span class="lstab-panel-sub"><?php esc_html_e( 'The one setting here that cannot be undone', 'live-sheets-table' ); ?></span>
				</span>
			</div>

			<div class="lstab-row lstab-row--danger">
				<div class="lstab-row-say">
					<p class="lstab-row-title"><?php esc_html_e( 'Also delete every sheet source and setting', 'live-sheets-table' ); ?></p>
					<p class="lstab-row-help">
						<?php esc_html_e( 'Applies only when the plugin is deleted from the Plugins screen, not when it is deactivated. Your spreadsheets in Google are never touched.', 'live-sheets-table' ); ?>
					</p>
				</div>
				<div class="lstab-row-do">
					<label class="lstab-switch">
						<input type="checkbox" name="lstab_settings[delete_on_uninstall]" value="1"
							<?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?>>
						<span><?php esc_html_e( 'Delete everything', 'live-sheets-table' ); ?></span>
					</label>
				</div>
			</div>
		</div>

		<p class="lstab-submit">
			<button type="submit" class="lstab-btn"><?php esc_html_e( 'Save settings', 'live-sheets-table' ); ?></button>
		</p>
	</form>
</div>
