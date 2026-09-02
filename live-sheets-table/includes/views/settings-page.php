<?php
/**
 * Settings screen.
 *
 * @package LiveSheetsTable
 *
 * @var array<string,mixed> $settings Stored settings, defaults filled in.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap lstab-admin lstab-settings">
	<h1><?php esc_html_e( 'Live Sheets Table', 'live-sheets-table' ); ?></h1>

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

		<div class="lstab-card">
			<h2 class="lstab-card-title"><?php esc_html_e( 'Who can manage tables', 'live-sheets-table' ); ?></h2>
			<p class="lstab-help">
				<?php esc_html_e( 'Whoever can manage tables can also read every sheet they point at, including any column left out of the published table. Editors are the default because they are the people publishing the pages these tables go on.', 'live-sheets-table' ); ?>
			</p>
			<p>
				<select name="lstab_settings[manage_capability]">
					<?php foreach ( LSTAB_Settings::capabilities() as $lstab_cap => $lstab_label ) : ?>
						<option value="<?php echo esc_attr( $lstab_cap ); ?>" <?php selected( $settings['manage_capability'], $lstab_cap ); ?>>
							<?php echo esc_html( $lstab_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
		</div>

		<div class="lstab-card">
			<h2 class="lstab-card-title"><?php esc_html_e( 'How often new tables check Google', 'live-sheets-table' ); ?></h2>
			<p class="lstab-help">
				<?php esc_html_e( 'Every table has its own “Check Google for changes” setting. This is only the value a table is given the moment you add it, so that you are not choosing the same thing over and over. It changes nothing about the tables you already have, and any table can be set differently afterwards.', 'live-sheets-table' ); ?>
			</p>
			<p>
				<select name="lstab_settings[default_interval]">
					<option value="0" <?php selected( (int) $settings['default_interval'], 0 ); ?>>
						<?php
						printf(
							/* translators: %s: human readable duration, e.g. "15 minutes". */
							esc_html__( 'As often as allowed — every %s at present', 'live-sheets-table' ),
							esc_html( human_time_diff( 0, LSTAB_Limits::min_interval() ) )
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
								esc_html( human_time_diff( 0, $lstab_seconds ) )
							);
							?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
		</div>

		<?php
		/**
		 * Fires inside the settings form, under the free plugin's own cards.
		 *
		 * An add-on printing fields here has them submitted with everything
		 * else, and can read them back on 'lstab_settings_saved' — which only
		 * fires once the capability and nonce checks have passed.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 */
		do_action( 'lstab_settings_sections', $settings );
		?>

		<div class="lstab-card lstab-card-danger">
			<h2 class="lstab-card-title"><?php esc_html_e( 'When this plugin is deleted', 'live-sheets-table' ); ?></h2>
			<p class="lstab-checkbox">
				<label>
					<input type="checkbox" name="lstab_settings[delete_on_uninstall]" value="1"
						<?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?>>
					<?php esc_html_e( 'Also delete every sheet source and setting', 'live-sheets-table' ); ?>
				</label>
				<span class="lstab-help">
					<?php esc_html_e( 'Off by default, because deleting a plugin to reinstall it is a normal thing to do and losing every table for it would not be. This only applies when the plugin is deleted from the Plugins screen, not when it is deactivated. Your spreadsheets in Google are never touched either way.', 'live-sheets-table' ); ?>
				</span>
			</p>
		</div>

		<p class="lstab-submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'live-sheets-table' ); ?></button>
		</p>
	</form>
</div>
