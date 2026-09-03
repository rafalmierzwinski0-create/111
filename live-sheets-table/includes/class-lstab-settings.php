<?php
/**
 * Site-wide settings.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * The handful of choices that belong to the site rather than to one table.
 *
 * Everything here has a defensible default, so the screen can be ignored
 * entirely. Nothing is put here because it was hard to place: a setting that
 * exists only because nobody could decide is a question passed to someone who
 * has less context than the person who wrote it.
 */
class LSTAB_Settings {

	const OPTION = 'lstab_settings';

	/**
	 * Defaults, and the whole list of what may be stored.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			// Editors publish the pages these tables go on, so they are the
			// people who need them. Site owners who disagree can raise it.
			'manage_capability' => 'edit_pages',
			'default_interval'  => 0,
			// The look a table is given the moment it is added, so somebody who
			// has settled on one is not choosing it again for every sheet.
			'default_style'     => 'clean',
			// How long a page draw may wait for Google before falling back to
			// the stored copy. Four seconds suits nearly everyone; a very large
			// sheet on slow hosting is why it can be raised.
			'view_timeout'      => LSTAB_Sync::VIEW_TIMEOUT,
			// A page cache has no way of knowing a sheet arrived, so the plugin
			// tells it. Only the pages the table is on, so one price moving does
			// not throw away the whole site's cache.
			'purge_cache'       => 'pages',
			'delete_on_uninstall' => false,
		);
	}

	/**
	 * Every setting, defaults filled in.
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	/**
	 * One setting.
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $default Value if it has never been set.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Store settings, keeping out anything not on the list.
	 *
	 * @param array<string,mixed> $input Submitted values.
	 * @return array<string,mixed> What was stored.
	 */
	public static function save( $input ) {
		$clean        = self::defaults();
		$capabilities = array_keys( self::capabilities() );

		if ( isset( $input['manage_capability'] ) && in_array( $input['manage_capability'], $capabilities, true ) ) {
			$clean['manage_capability'] = (string) $input['manage_capability'];
		}

		if ( isset( $input['default_interval'] ) ) {
			$seconds = (int) $input['default_interval'];
			// schedule_map() is keyed by slug, so it is the values that hold the
			// lengths a site may choose from.
			$clean['default_interval'] = ( 0 === $seconds || in_array( $seconds, LSTAB_Cron::schedule_map(), true ) )
				? $seconds
				: 0;
		}

		if ( isset( $input['default_style'] ) ) {
			$clean['default_style'] = LSTAB_Styles::sanitize( (string) $input['default_style'] );
		}

		if ( isset( $input['view_timeout'] ) ) {
			// Below two seconds nothing on a real connection ever arrives in
			// time; above fifteen the page is broken by any reasonable measure.
			$clean['view_timeout'] = max( 2, min( 15, (int) $input['view_timeout'] ) );
		}

		if ( isset( $input['purge_cache'] ) && array_key_exists( (string) $input['purge_cache'], LSTAB_Cache::modes() ) ) {
			$clean['purge_cache'] = (string) $input['purge_cache'];
		}

		$clean['delete_on_uninstall'] = ! empty( $input['delete_on_uninstall'] );

		update_option( self::OPTION, $clean, true );

		return $clean;
	}

	/**
	 * Who may be trusted with the tables.
	 *
	 * @return array<string,string>
	 */
	public static function capabilities() {
		return array(
			'edit_pages'      => __( 'Editors and above', 'live-sheets-table' ),
			'manage_options'  => __( 'Administrators only', 'live-sheets-table' ),
		);
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'lstab_manage_capability', array( __CLASS__, 'capability' ) );
		add_action( 'admin_post_lstab_save_settings', array( $this, 'handle_save' ) );
	}

	/**
	 * The capability the plugin's screens require.
	 *
	 * @param string $capability Current value.
	 * @return string
	 */
	public static function capability( $capability ) {
		$chosen = self::get( 'manage_capability' );

		return array_key_exists( (string) $chosen, self::capabilities() ) ? (string) $chosen : $capability;
	}

	/**
	 * Save the settings form.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'live-sheets-table' ) );
		}

		check_admin_referer( 'lstab_save_settings' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Every value is checked against a known list in save().
		self::save( isset( $_POST['lstab_settings'] ) ? (array) wp_unslash( $_POST['lstab_settings'] ) : array() );

		/**
		 * Fires after the settings screen is saved, so an add-on can store its
		 * own fields under the same nonce and capability check.
		 */
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
