<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Settings {
	const OPTION = 'lstab_settings';

	public static function defaults() {
		return array(
			'manage_capability' => 'edit_pages',
			'default_interval'  => 0,
			'default_style'     => 'clean',
			'view_timeout'      => LSTAB_Sync::VIEW_TIMEOUT,
			'delete_on_uninstall' => false,
		);
	}

	public static function all() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function get( $key, $default = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	public static function save( $input ) {
		$clean        = self::defaults();
		$capabilities = array_keys( self::capabilities() );

		if ( isset( $input['manage_capability'] ) && in_array( $input['manage_capability'], $capabilities, true ) ) {
			$clean['manage_capability'] = (string) $input['manage_capability'];
		}

		if ( isset( $input['default_interval'] ) ) {
			$seconds = (int) $input['default_interval'];
			$clean['default_interval'] = ( 0 === $seconds || in_array( $seconds, LSTAB_Cron::schedule_map(), true ) )
				? $seconds
				: 0;
		}

		if ( isset( $input['default_style'] ) ) {
			$clean['default_style'] = LSTAB_Styles::sanitize( (string) $input['default_style'] );
		}

		if ( isset( $input['view_timeout'] ) ) {
			$clean['view_timeout'] = max( 2, min( 15, (int) $input['view_timeout'] ) );
		}

		$clean['delete_on_uninstall'] = ! empty( $input['delete_on_uninstall'] );

		update_option( self::OPTION, $clean, true );

		return $clean;
	}

	public static function capabilities() {
		return array(
			'edit_pages'      => __( 'Editors and above', 'live-sheets-table' ),
			'manage_options'  => __( 'Administrators only', 'live-sheets-table' ),
		);
	}

	public function register() {
		add_filter( 'lstab_manage_capability', array( __CLASS__, 'capability' ) );
		add_action( 'admin_post_lstab_save_settings', array( $this, 'handle_save' ) );
	}

	public static function capability( $capability ) {
		$chosen = self::get( 'manage_capability' );

		return array_key_exists( (string) $chosen, self::capabilities() ) ? (string) $chosen : $capability;
	}

	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'live-sheets-table' ) );
		}

		check_admin_referer( 'lstab_save_settings' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Every value is checked against a known list in save().
		self::save( isset( $_POST['lstab_settings'] ) ? (array) wp_unslash( $_POST['lstab_settings'] ) : array() );

		do_action( 'lstab_settings_saved' );

		wp_safe_redirect(
			add_query_arg(
				'lstab-saved',
				'1',
				admin_url( 'admin.php?page=' . LSTAB_Admin::SETTINGS_SLUG )
			)
		);
		exit;
	}
}
