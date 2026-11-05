<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap lstab-admin lstab-settings">
	<?php LSTAB_Admin::render_masthead( esc_html__( 'Settings for the whole site', 'tabliko' ) ); ?>

	<?php LSTAB_Admin::render_tabs( LSTAB_Admin::SETTINGS_SLUG ); ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides whether to say "saved". ?>
	<?php if ( isset( $_GET['lstab-saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'tabliko' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lstab-settings-form">
		<input type="hidden" name="action" value="lstab_save_settings">
		<?php wp_nonce_field( 'lstab_save_settings' ); ?>

		<div class="lstab-panel">
			<div class="lstab-panel-head">
				<?php echo wp_kses( LSTAB_Icons::badge( 'shield', 'indigo' ), LSTAB_Icons::allowed() ); ?>
				<span>
					<h2><?php esc_html_e( 'Access', 'tabliko' ); ?></h2>
					<span class="lstab-panel-sub"><?php esc_html_e( 'Who is trusted with the tables on this site', 'tabliko' ); ?></span>
				</span>
			</div>

			<div class="lstab-row">
				<div class="lstab-row-say">
					<p class="lstab-row-title" id="lstab-set-manage"><?php esc_html_e( 'Who can manage tables', 'tabliko' ); ?></p>
					<p class="lstab-row-help">
						<?php esc_html_e( 'These people can add, change and delete tables. They can also see everything in the sheets behind them, including columns and rows hidden from visitors.', 'tabliko' ); ?>
					</p>
				</div>
				<div class="lstab-row-do">
					<select name="lstab_settings[manage_capability]" aria-labelledby="lstab-set-manage">
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
				<?php echo wp_kses( LSTAB_Icons::badge( 'refresh', 'sky' ), LSTAB_Icons::allowed() ); ?>
				<span>
					<h2><?php esc_html_e( 'Checking Google', 'tabliko' ); ?></h2>
					<span class="lstab-panel-sub"><?php esc_html_e( 'How often tables are updated', 'tabliko' ); ?></span>
				</span>
			</div>

			<div class="lstab-row">
				<div class="lstab-row-say">
					<p class="lstab-row-title" id="lstab-set-interval"><?php esc_html_e( 'How often new tables check Google', 'tabliko' ); ?></p>
					<p class="lstab-row-help">
						<?php esc_html_e( 'The starting value for each table you add from now on. Tables you already have keep their own setting, and you can change it for any table on its own screen.', 'tabliko' ); ?>
					</p>
				</div>
				<div class="lstab-row-do">
					<?php $lstab_starting = LSTAB_Limits::starting_interval(); ?>
					<select name="lstab_settings[default_interval]" aria-labelledby="lstab-set-interval">
						<?php foreach ( LSTAB_Limits::intervals() as $lstab_seconds => $lstab_label ) : ?>
							<option value="<?php echo esc_attr( (string) $lstab_seconds ); ?>" <?php selected( $lstab_starting, $lstab_seconds ); ?>>
								<?php echo esc_html( $lstab_label ); ?>
							</option>
						<?php endforeach; ?>
						<option value="<?php echo esc_attr( (string) LSTAB_Limits::SETTING_MANUAL ); ?>" <?php selected( $lstab_starting, LSTAB_Limits::MANUAL ); ?>>
							<?php echo esc_html( LSTAB_Limits::choices()[ LSTAB_Limits::MANUAL ] ); ?>
						</option>
					</select>
				</div>
			</div>
		</div>

		<div class="lstab-panel">
			<div class="lstab-panel-head">
				<?php echo wp_kses( LSTAB_Icons::badge( 'brush', 'violet' ), LSTAB_Icons::allowed() ); ?>
				<span>
					<h2><?php esc_html_e( 'Appearance', 'tabliko' ); ?></h2>
					<span class="lstab-panel-sub"><?php esc_html_e( 'How a new table looks before you change anything', 'tabliko' ); ?></span>
				</span>
			</div>

			<div class="lstab-row">
				<div class="lstab-row-say">
					<p class="lstab-row-title" id="lstab-set-style"><?php esc_html_e( 'How new tables look', 'tabliko' ); ?></p>
					<p class="lstab-row-help">
						<?php esc_html_e( 'The style each new table starts with. You can still change it for any table on its own screen.', 'tabliko' ); ?>
					</p>
				</div>
				<div class="lstab-row-do">
					<select name="lstab_settings[default_style]" aria-labelledby="lstab-set-style">
						<?php foreach ( LSTAB_Styles::all() as $lstab_slug => $lstab_preset ) : ?>
							<option value="<?php echo esc_attr( $lstab_slug ); ?>" <?php selected( (string) $settings['default_style'], $lstab_slug ); ?>>
								<?php echo esc_html( $lstab_preset['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		</div>

		<?php
		do_action( 'lstab_settings_sections', $settings );
		?>

		<div class="lstab-panel">
			<div class="lstab-panel-head">
				<?php echo wp_kses( LSTAB_Icons::badge( 'trash', 'rose' ), LSTAB_Icons::allowed() ); ?>
				<span>
					<h2><?php esc_html_e( 'When this plugin is deleted', 'tabliko' ); ?></h2>
					<span class="lstab-panel-sub"><?php esc_html_e( 'The one setting here that cannot be undone', 'tabliko' ); ?></span>
				</span>
			</div>

			<div class="lstab-row lstab-row--danger">
				<div class="lstab-row-say">
					<p class="lstab-row-title"><?php esc_html_e( 'Also delete every table and setting', 'tabliko' ); ?></p>
					<p class="lstab-row-help">
						<?php esc_html_e( 'When this is on and you delete the plugin on the Plugins screen, all your tables and settings are removed for good. Deactivating the plugin removes nothing. Your sheets in Google are never touched.', 'tabliko' ); ?>
					</p>
				</div>
				<div class="lstab-row-do">
					<label class="lstab-switch">
						<input type="checkbox" name="lstab_settings[delete_on_uninstall]" value="1"
							<?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?>>
						<span><?php esc_html_e( 'Delete everything', 'tabliko' ); ?></span>
					</label>
				</div>
			</div>
		</div>

		<p class="lstab-submit">
			<button type="submit" class="lstab-btn"><?php esc_html_e( 'Save settings', 'tabliko' ); ?></button>
		</p>
	</form>
</div>
