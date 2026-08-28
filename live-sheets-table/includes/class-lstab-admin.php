<?php
/**
 * Dashboard screens.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI.
 */
class LSTAB_Admin {

	const MENU_SLUG = 'live-sheets-table';
	const EDIT_SLUG = 'live-sheets-table-edit';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_lstab_save_source', array( $this, 'handle_save' ) );
		add_action( 'admin_post_lstab_delete_source', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_lstab_refresh_source', array( $this, 'handle_refresh' ) );
		add_filter( 'plugin_action_links_' . LSTAB_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Add the dashboard menu.
	 *
	 * @return void
	 */
	public function add_menu() {
		$capability = LSTAB_Limits::capability();

		add_menu_page(
			__( 'Live Sheets Table', 'live-sheets-table' ),
			__( 'Sheets Tables', 'live-sheets-table' ),
			$capability,
			self::MENU_SLUG,
			array( $this, 'render_list_page' ),
			'dashicons-editor-table',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'All sheet sources', 'live-sheets-table' ),
			__( 'All sources', 'live-sheets-table' ),
			$capability,
			self::MENU_SLUG,
			array( $this, 'render_list_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Add new sheet source', 'live-sheets-table' ),
			__( 'Add new', 'live-sheets-table' ),
			$capability,
			self::EDIT_SLUG,
			array( $this, 'render_edit_page' )
		);
	}

	/**
	 * Quick links on the Plugins screen.
	 *
	 * @param array<int,string> $links Existing links.
	 * @return array<int,string>
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) . '">' . esc_html__( 'Sheet sources', 'live-sheets-table' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Load admin assets on our screens only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::MENU_SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'lstab-table' );
		wp_enqueue_style(
			'lstab-admin',
			LSTAB_URL . 'assets/css/lstab-admin.css',
			array( 'lstab-table' ),
			LSTAB_VERSION
		);

		wp_enqueue_script(
			'lstab-admin',
			LSTAB_URL . 'assets/js/lstab-admin.js',
			array( 'wp-api-fetch' ),
			LSTAB_VERSION,
			true
		);

		wp_localize_script(
			'lstab-admin',
			'lstabAdmin',
			array(
				'previewUrl' => rest_url( LSTAB_Rest::NAMESPACE_V1 . '/preview' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				// Needed so the script can clear whichever preset class is on
				// the preview before applying the newly chosen one.
				'presets'    => array_keys( LSTAB_Styles::all() ),
				'i18n'       => array(
					'loading'     => __( 'Loading preview…', 'live-sheets-table' ),
					'failed'      => __( 'Preview failed', 'live-sheets-table' ),
					'rowsFound'   => __( 'Found %1$s rows across %2$s columns.', 'live-sheets-table' ),
					'truncated'   => __( 'Showing the first 25 rows.', 'live-sheets-table' ),
					'pickTab'     => __( 'Pick the tab you want to publish:', 'live-sheets-table' ),
					'noTabs'      => __( 'Could not read the tab list — the tab from your link will be used.', 'live-sheets-table' ),
					'emptyUrl'    => __( 'Paste a Google Sheets link first.', 'live-sheets-table' ),
				),
			)
		);
	}

	/**
	 * The list screen.
	 *
	 * @return void
	 */
	public function render_list_page() {
		if ( ! current_user_can( LSTAB_Limits::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to manage sheet sources.', 'live-sheets-table' ) );
		}

		$sources = LSTAB_Storage::get_all();
		require LSTAB_PATH . 'includes/views/list-page.php';
	}

	/**
	 * The add/edit screen.
	 *
	 * @return void
	 */
	public function render_edit_page() {
		if ( ! current_user_can( LSTAB_Limits::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to manage sheet sources.', 'live-sheets-table' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$source_id = isset( $_GET['source'] ) ? absint( wp_unslash( $_GET['source'] ) ) : 0;
		$source    = $source_id ? LSTAB_Storage::get( $source_id ) : null;

		if ( ! $source && ! LSTAB_Limits::can_add_source() ) {
			require LSTAB_PATH . 'includes/views/limit-reached.php';
			return;
		}

		require LSTAB_PATH . 'includes/views/edit-page.php';
	}

	/**
	 * Create or update a source.
	 *
	 * @return void
	 */
	public function handle_save() {
		$this->guard( 'lstab_save_source' );

		$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;
		$raw_url   = isset( $_POST['sheet_url'] ) ? sanitize_text_field( wp_unslash( $_POST['sheet_url'] ) ) : '';

		$reference = LSTAB_Url::parse( $raw_url );
		if ( is_wp_error( $reference ) ) {
			$this->redirect_with_notice( $source_id, 'error', $reference->get_error_message() );
		}

		if ( ! $source_id && ! LSTAB_Limits::can_add_source() ) {
			$this->redirect_with_notice(
				0,
				'error',
				sprintf(
					/* translators: %d: number of sources allowed. */
					_n(
						'The free version stores %d sheet source. Remove the existing one, or upgrade to add more.',
						'The free version stores %d sheet sources. Remove one, or upgrade to add more.',
						LSTAB_Limits::max_sources(),
						'live-sheets-table'
					),
					LSTAB_Limits::max_sources()
				)
			);
		}

		$gid = isset( $_POST['gid'] ) ? LSTAB_Url::sanitize_gid( wp_unslash( $_POST['gid'] ) ) : $reference['gid'];

		$data = array(
			'title'            => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'sheet_url'        => esc_url_raw( $raw_url, array( 'http', 'https' ) ),
			'sheet_id'         => $reference['sheet_id'],
			'sheet_kind'       => $reference['sheet_kind'],
			'gid'              => $gid,
			'tab_name'         => isset( $_POST['tab_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tab_name'] ) ) : '',
			'sync_interval'    => isset( $_POST['sync_interval'] ) ? LSTAB_Limits::clamp_interval( wp_unslash( $_POST['sync_interval'] ) ) : LSTAB_Limits::min_interval(),
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Presence check only; the value is never used.
			'first_row_header' => empty( $_POST['first_row_header'] ) ? 0 : 1,
			'style_preset'     => isset( $_POST['style_preset'] ) ? LSTAB_Styles::sanitize( wp_unslash( $_POST['style_preset'] ) ) : 'clean',
		);

		if ( '' === $data['title'] ) {
			$data['title'] = $data['tab_name'] ? $data['tab_name'] : __( 'Untitled sheet', 'live-sheets-table' );
		}

		if ( $source_id ) {
			LSTAB_Storage::update( $source_id, $data );
		} else {
			$created = LSTAB_Storage::insert( $data );
			if ( is_wp_error( $created ) ) {
				$this->redirect_with_notice( 0, 'error', $created->get_error_message() );
			}
			$source_id = $created;
		}

		do_action( 'lstab_source_saved', $source_id );

		$sync = LSTAB_Sync::run( $source_id );

		if ( is_wp_error( $sync ) ) {
			$this->redirect_with_notice(
				$source_id,
				'warning',
				sprintf(
					/* translators: %s: error message. */
					__( 'Saved, but the first sync failed: %s', 'live-sheets-table' ),
					$sync->get_error_message()
				)
			);
		}

		$this->redirect_with_notice( $source_id, 'success', __( 'Sheet source saved and synced.', 'live-sheets-table' ), true );
	}

	/**
	 * Delete a source.
	 *
	 * @return void
	 */
	public function handle_delete() {
		$this->guard( 'lstab_delete_source' );

		$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;

		if ( $source_id ) {
			LSTAB_Storage::delete( $source_id );
		}

		$this->redirect_with_notice( 0, 'success', __( 'Sheet source deleted.', 'live-sheets-table' ), true );
	}

	/**
	 * Run a manual sync from the list screen.
	 *
	 * @return void
	 */
	public function handle_refresh() {
		$this->guard( 'lstab_refresh_source' );

		$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;
		$result    = LSTAB_Sync::run( $source_id );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice(
				0,
				'error',
				sprintf(
					/* translators: %s: error message. */
					__( 'Refresh failed: %s', 'live-sheets-table' ),
					$result->get_error_message()
				),
				true
			);
		}

		$this->redirect_with_notice( 0, 'success', __( 'Sheet refreshed from Google.', 'live-sheets-table' ), true );
	}

	/**
	 * Capability and nonce check shared by every admin-post handler.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	protected function guard( $action ) {
		if ( ! current_user_can( LSTAB_Limits::capability() ) ) {
			wp_die(
				esc_html__( 'You are not allowed to manage sheet sources.', 'live-sheets-table' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( $action );
	}

	/**
	 * Redirect back to an admin screen carrying a transient notice.
	 *
	 * @param int    $source_id Source being edited, 0 for the list screen.
	 * @param string $type      One of success, warning, error.
	 * @param string $message   Notice text.
	 * @param bool   $to_list   Redirect to the list screen instead of the editor.
	 * @return void
	 */
	protected function redirect_with_notice( $source_id, $type, $message, $to_list = false ) {
		set_transient(
			'lstab_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);

		$url = $to_list
			? admin_url( 'admin.php?page=' . self::MENU_SLUG )
			: add_query_arg(
				array(
					'page'   => self::EDIT_SLUG,
					'source' => $source_id ? $source_id : null,
				),
				admin_url( 'admin.php' )
			);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Print and clear the queued notice.
	 *
	 * @return void
	 */
	public static function print_notice() {
		$key    = 'lstab_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );

		$classes = array(
			'success' => 'notice-success',
			'warning' => 'notice-warning',
			'error'   => 'notice-error',
		);
		$class   = isset( $classes[ $notice['type'] ] ) ? $classes[ $notice['type'] ] : 'notice-info';

		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( $notice['message'] )
		);
	}

	/**
	 * Human readable sync status for a source.
	 *
	 * @param array<string,mixed> $source Source row.
	 * @return array{state:string,icon:string,text:string,detail:string} The icon is a Dashicons class.
	 */
	public static function status_for( $source ) {
		// Read this from the metadata, not the payload: the list screen loads
		// sources without their snapshots on purpose.
		$has_data = '' !== (string) $source['snapshot_hash'];

		if ( 'ok' === $source['last_status'] && $source['last_success_gmt'] ) {
			return array(
				'state'  => 'ok',
				'icon'   => 'dashicons-yes-alt',
				'text'   => sprintf(
					/* translators: %s: human readable time difference. */
					__( 'Last sync OK (%s ago)', 'live-sheets-table' ),
					human_time_diff( strtotime( $source['last_success_gmt'] . ' UTC' ), time() )
				),
				'detail' => '',
			);
		}

		if ( 'error' === $source['last_status'] ) {
			$since = $source['last_success_gmt']
				? sprintf(
					/* translators: %s: human readable time difference. */
					__( 'Failing since the last good sync %s ago', 'live-sheets-table' ),
					human_time_diff( strtotime( $source['last_success_gmt'] . ' UTC' ), time() )
				)
				: __( 'Never synced successfully', 'live-sheets-table' );

			return array(
				'state'  => $has_data ? 'stale' : 'error',
				'icon'   => 'dashicons-warning',
				'text'   => $has_data
					? __( 'Sync error — visitors still see the last good copy', 'live-sheets-table' )
					: __( 'Sync error — nothing to show yet', 'live-sheets-table' ),
				'detail' => $since . ( $source['last_error'] ? ' — ' . $source['last_error'] : '' ),
			);
		}

		return array(
			'state'  => 'never',
			'icon'   => 'dashicons-clock',
			'text'   => __( 'Not synced yet', 'live-sheets-table' ),
			'detail' => '',
		);
	}
}
