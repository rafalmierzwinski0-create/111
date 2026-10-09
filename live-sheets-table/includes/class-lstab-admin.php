<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Admin {
	const MENU_SLUG     = 'live-sheets-table';
	const SOURCES_SLUG  = 'live-sheets-table-sources';
	const EDIT_SLUG     = 'live-sheets-table-edit';
	const SETTINGS_SLUG = 'live-sheets-table-settings';

	const GRACE_DISMISSED = 'lstab_grace_dismissed';

	public function register() {
		add_action( 'admin_init', array( 'LSTAB_Limits', 'note_pro_seen' ) );
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_lstab_save_source', array( $this, 'handle_save' ) );
		add_action( 'admin_post_lstab_delete_source', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_lstab_refresh_source', array( $this, 'handle_refresh' ) );
		add_action( 'admin_post_lstab_dismiss_ragged', array( $this, 'handle_dismiss_ragged' ) );
		add_action( 'admin_post_lstab_page_source', array( $this, 'handle_page_source' ) );
		add_action( 'admin_post_lstab_keep_one_page', array( $this, 'handle_keep_one_page' ) );
		add_action( 'admin_notices', array( $this, 'print_global_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'print_grace_notice' ) );
		add_action( 'wp_ajax_lstab_dismiss_grace', array( $this, 'handle_dismiss_grace' ) );
		add_filter( 'plugin_action_links_' . LSTAB_BASENAME, array( $this, 'action_links' ) );
	}

	public function add_menu() {
		$capability = LSTAB_Limits::capability();

		add_menu_page(
			__( 'Live Sheets Table', 'live-sheets-table' ),
			__( 'Sheets Tables', 'live-sheets-table' ),
			$capability,
			self::MENU_SLUG,
			array( $this, 'render_start_page' ),
			LSTAB_Icons::menu_mark(),
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Live Sheets Table', 'live-sheets-table' ),
			__( 'Start', 'live-sheets-table' ),
			$capability,
			self::MENU_SLUG,
			array( $this, 'render_start_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'All sheet sources', 'live-sheets-table' ),
			__( 'All tables', 'live-sheets-table' ),
			$capability,
			self::SOURCES_SLUG,
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

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Live Sheets Table settings', 'live-sheets-table' ),
			__( 'Settings', 'live-sheets-table' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	public static function tabs() {
		$tabs = array(
			self::MENU_SLUG    => __( 'Start', 'live-sheets-table' ),
			self::SOURCES_SLUG => __( 'Sheet sources', 'live-sheets-table' ),
		);

		if ( current_user_can( 'manage_options' ) ) {
			$tabs[ self::SETTINGS_SLUG ] = __( 'Settings', 'live-sheets-table' );
		}

		return (array) apply_filters( 'lstab_admin_tabs', $tabs );
	}

	public static function render_tabs( $current ) {
		$tabs = self::tabs();

		if ( count( $tabs ) < 2 ) {
			return;
		}

		echo '<nav class="nav-tab-wrapper lstab-tabs">';

		$icons = self::tab_icons();

		foreach ( $tabs as $slug => $label ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s">%3$s%4$s</a>',
				esc_url( admin_url( 'admin.php?page=' . $slug ) ),
				$slug === $current ? ' nav-tab-active' : '',
				LSTAB_Icons::icon( isset( $icons[ $slug ] ) ? $icons[ $slug ] : 'grid' ), // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG.
				esc_html( $label )
			);
		}

		echo '</nav>';
	}

	public static function tab_icons() {
		return apply_filters(
			'lstab_admin_tab_icons',
			array(
				self::MENU_SLUG     => 'start',
				self::SOURCES_SLUG  => 'grid',
				self::SETTINGS_SLUG => 'sliders',
			)
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'live-sheets-table' ) );
		}

		$settings = LSTAB_Settings::all();

		include LSTAB_PATH . 'includes/views/settings-page.php';
	}

	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SOURCES_SLUG ) ) . '">' . esc_html__( 'Sheet sources', 'live-sheets-table' ) . '</a>'
		);

		return $links;
	}

	public function enqueue( $hook ) {
		if ( ! LSTAB_Limits::is_pro() && LSTAB_Limits::grace_remaining() > 0 ) {
			wp_enqueue_script(
				'lstab-notice',
				LSTAB_URL . 'assets/js/lstab-notice.js',
				array(),
				LSTAB_Plugin::asset_version( 'assets/js/lstab-notice.js' ),
				true
			);

			wp_localize_script( 'lstab-notice', 'lstabNotice', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) );
		}

		if ( false === strpos( (string) $hook, self::MENU_SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'lstab-table' );

		wp_enqueue_script( 'lstab-table' );

		wp_enqueue_style(
			'lstab-admin',
			LSTAB_URL . 'assets/css/lstab-admin.css',
			array( 'lstab-table' ),
			LSTAB_Plugin::asset_version( 'assets/css/lstab-admin.css' )
		);

		wp_enqueue_script(
			'lstab-copy',
			LSTAB_URL . 'assets/js/lstab-copy.js',
			array(),
			LSTAB_Plugin::asset_version( 'assets/js/lstab-copy.js' ),
			true
		);

		wp_localize_script(
			'lstab-copy',
			'lstabCopy',
			array(
				'i18n' => array(
					'copy'   => __( 'Copy', 'live-sheets-table' ),
					'copied' => __( 'Copied', 'live-sheets-table' ),
					'failed' => __( 'Press Ctrl+C', 'live-sheets-table' ),
				),
			)
		);

		wp_enqueue_script(
			'lstab-admin',
			LSTAB_URL . 'assets/js/lstab-admin.js',
			array( 'wp-api-fetch', 'lstab-table' ),
			LSTAB_Plugin::asset_version( 'assets/js/lstab-admin.js' ),
			true
		);

		wp_localize_script(
			'lstab-admin',
			'lstabAdmin',
			array(
				'previewUrl' => rest_url( LSTAB_Rest::NAMESPACE_V1 . '/preview' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'presets'    => array_keys( LSTAB_Styles::all() ),
				'metrics'    => wp_list_pluck( LSTAB_Customizer::metrics(), 'vars' ),
				'i18n'       => array(
					'loading'     => __( 'Loading preview…', 'live-sheets-table' ),
					'failed'      => __( 'Preview failed', 'live-sheets-table' ),
					/* translators: 1: number of rows, 2: number of columns. */
					'rowsFound'   => __( 'Found %1$s rows across %2$s columns.', 'live-sheets-table' ),
					'truncated'   => __( 'Showing the first 25 rows.', 'live-sheets-table' ),
					'pickTab'     => __( 'Pick the tab you want to publish:', 'live-sheets-table' ),
					'noTabs'      => __( 'Could not read the tab list; the tab from your link will be used.', 'live-sheets-table' ),
					'emptyUrl'    => __( 'Paste a Google Sheets link first.', 'live-sheets-table' ),
					/* translators: %1$s: column number. */
					'columnNumber' => __( 'Column %1$s', 'live-sheets-table' ),
					'shown'        => __( 'Shown', 'live-sheets-table' ),
					/* translators: %1$s: number of characters. */
					'rawBytes'    => __( '%1$s characters received.', 'live-sheets-table' ),
					/* translators: %1$s: row number. */
					'rawRagged'   => __( 'Look at row %1$s: it came back with a different number of cells than the rest.', 'live-sheets-table' ),
				),
			)
		);
	}

	public function render_start_page() {
		if ( ! current_user_can( LSTAB_Limits::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to manage sheet sources.', 'live-sheets-table' ) );
		}

		$sources = LSTAB_Storage::get_all();
		require LSTAB_PATH . 'includes/views/start-page.php';
	}

	public function render_list_page() {
		if ( ! current_user_can( LSTAB_Limits::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to manage sheet sources.', 'live-sheets-table' ) );
		}

		$sources = LSTAB_Storage::get_all();
		require LSTAB_PATH . 'includes/views/list-page.php';
	}

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

	public function handle_save() {
		$this->guard();
		check_admin_referer( 'lstab_save_source' );

		$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;
		$raw_url   = isset( $_POST['sheet_url'] ) ? sanitize_text_field( wp_unslash( $_POST['sheet_url'] ) ) : '';

		$existing = $source_id ? LSTAB_Storage::get( $source_id ) : null;

		if ( $existing && LSTAB_Example::is_example( $existing ) ) {
			$reference = array(
				'sheet_id'   => '',
				'sheet_kind' => LSTAB_Example::KIND,
				'gid'        => '0',
			);
		} else {
			$reference = LSTAB_Url::parse( $raw_url );

			if ( is_wp_error( $reference ) ) {
				$this->redirect_with_notice( $source_id, 'error', $reference->get_error_message() );
			}
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

		$gid = isset( $_POST['gid'] ) ? LSTAB_Url::sanitize_gid( sanitize_text_field( wp_unslash( $_POST['gid'] ) ) ) : $reference['gid'];

		$data = array(
			'title'            => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'sheet_url'        => esc_url_raw( $raw_url, array( 'http', 'https' ) ),
			'sheet_id'         => $reference['sheet_id'],
			'sheet_kind'       => $reference['sheet_kind'],
			'gid'              => $gid,
			'tab_name'         => isset( $_POST['tab_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tab_name'] ) ) : (string) ( $existing ? $existing['tab_name'] : '' ),
			'sync_interval'    => isset( $_POST['sync_interval'] ) ? LSTAB_Limits::clamp_interval( absint( wp_unslash( $_POST['sync_interval'] ) ) ) : LSTAB_Limits::min_interval(),
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Presence check only; the value is never used.
			'first_row_header' => empty( $_POST['first_row_header'] ) ? 0 : 1,
			'style_preset'     => isset( $_POST['style_preset'] ) ? LSTAB_Styles::sanitize( sanitize_key( wp_unslash( $_POST['style_preset'] ) ) ) : 'clean',
			'layout'           => isset( $_POST['layout'] ) && in_array( sanitize_key( wp_unslash( $_POST['layout'] ) ), array( 'table', 'auto', 'cards' ), true )
				? sanitize_key( wp_unslash( $_POST['layout'] ) )
				: 'table',
			'style_vars'       => isset( $_POST['style_vars'] ) ? LSTAB_Customizer::sanitize( wp_unslash( $_POST['style_vars'] ) ) : LSTAB_Customizer::defaults(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitised field by field in LSTAB_Columns.
			'columns_config'   => isset( $_POST['columns'] ) ? LSTAB_Columns::sanitize( wp_unslash( $_POST['columns'] ) ) : array(),
			'sticky_first'     => empty( $_POST['sticky_first'] ) ? 0 : 1,
			'sticky_head'      => empty( $_POST['sticky_head'] ) ? 0 : 1,
			'link_cells'       => empty( $_POST['link_cells'] ) ? 0 : 1,
			'per_page'         => self::per_page_from_post(),
		);

		if ( isset( $_POST['_lstab_custom_css_present'] ) && LSTAB_Custom_Css::user_can_edit() ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Cleaned in LSTAB_Custom_Css::sanitize(); sanitize_text_field would eat the newlines and braces this is made of.
			$data['custom_css'] = LSTAB_Custom_Css::sanitize( wp_unslash( $_POST['custom_css'] ?? '' ) );
		}

		if ( isset( $_POST['_lstab_hidden_rows_present'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitised key by key in LSTAB_Hidden_Rows.
			$data['hidden_rows'] = isset( $_POST['hidden_rows'] ) ? LSTAB_Hidden_Rows::sanitize( wp_unslash( $_POST['hidden_rows'] ) ) : array();
		}

		if ( '' === $data['title'] ) {
			$data['title'] = $data['tab_name'] ? $data['tab_name'] : __( 'Untitled sheet', 'live-sheets-table' );
		}

		$is_new = ! $source_id;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce and capability checked at the top of this handler.
		$paging_chosen = ! empty( $_POST['paging_touched'] );

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

		$paged_for_you = $is_new ? $this->page_a_long_new_sheet( $source_id, $paging_chosen ) : '';

		list( $lstab_type, $lstab_message ) = $this->sync_outcome( $source_id, __( 'Sheet source saved and synced.', 'live-sheets-table' ) );

		if ( '' !== $paged_for_you && 'error' !== $lstab_type ) {
			$lstab_message = $paged_for_you;
		}

		$this->redirect_with_notice( $source_id, $lstab_type, $lstab_message, true );
	}

	public function handle_delete() {
		$this->guard();
		check_admin_referer( 'lstab_delete_source' );

		$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;

		if ( $source_id ) {
			LSTAB_Storage::delete( $source_id );
		}

		$this->redirect_with_notice( 0, 'success', __( 'Sheet source deleted.', 'live-sheets-table' ), true );
	}

	public function handle_refresh() {
		$this->guard();
		check_admin_referer( 'lstab_refresh_source' );

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

		list( $lstab_type, $lstab_message ) = $this->sync_outcome( $source_id, __( 'Sheet refreshed from Google.', 'live-sheets-table' ) );
		$this->redirect_with_notice( 0, $lstab_type, $lstab_message, true );
	}

	protected function sync_outcome( $source_id, $success ) {
		$source = $source_id ? LSTAB_Storage::get( $source_id ) : null;

		if ( ! $source || empty( $source['last_ragged'] ) ) {
			return array( 'success', $success );
		}

		return array(
			'warning',
			$success . ' ' . self::ragged_summary( $source['last_ragged'] ),
		);
	}

	public function print_global_notice() {
		if ( ! current_user_can( LSTAB_Limits::capability() ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( (string) $screen->id, self::MENU_SLUG ) ) {
			return;
		}

		$index = (array) get_option( LSTAB_Storage::RAGGED_OPT, array() );

		if ( ! $index ) {
			return;
		}

		$dismissed = (array) get_option( LSTAB_Storage::DISMISSED_OPT, array() );
		$pending   = array_diff( $index, $dismissed );

		if ( ! $pending ) {
			return;
		}

		$links = array();
		foreach ( array_keys( $pending ) as $source_id ) {
			$source = LSTAB_Storage::get( (int) $source_id );

			if ( ! $source || empty( $source['last_ragged'] ) ) {
				continue;
			}

			$links[] = sprintf(
				'<a href="%1$s">%2$s</a> — %3$s',
				esc_url(
					add_query_arg(
						array(
							'page'   => self::EDIT_SLUG,
							'source' => (int) $source_id,
						),
						admin_url( 'admin.php' )
					)
				),
				esc_html( $source['title'] ),
				esc_html( self::ragged_summary( $source['last_ragged'] ) )
			);
		}

		if ( ! $links ) {
			return;
		}

		$dismiss = wp_nonce_url(
			add_query_arg( 'action', 'lstab_dismiss_ragged', admin_url( 'admin-post.php' ) ),
			'lstab_dismiss_ragged'
		);

		echo '<div class="notice notice-warning"><p><strong>';
		echo esc_html__( 'Live Sheets Table: a sheet did not come back cleanly.', 'live-sheets-table' );
		echo '</strong></p><ul style="margin:0.4em 0 0.8em 1.4em;list-style:disc;">';

		foreach ( $links as $line ) {
			echo '<li>' . wp_kses_post( $line ) . '</li>';
		}

		echo '</ul><p>';
		printf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $dismiss ),
			esc_html__( 'Hide this until it happens again', 'live-sheets-table' )
		);
		echo '</p></div>';
	}

	public function handle_dismiss_ragged() {
		$this->guard();
		check_admin_referer( 'lstab_dismiss_ragged' );

		$index = (array) get_option( LSTAB_Storage::RAGGED_OPT, array() );
		update_option( LSTAB_Storage::DISMISSED_OPT, array_values( $index ), true );

		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}

	protected function guard() {
		if ( ! current_user_can( LSTAB_Limits::capability() ) ) {
			wp_die(
				esc_html__( 'You are not allowed to manage sheet sources.', 'live-sheets-table' ),
				'',
				array( 'response' => 403 )
			);
		}
	}

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
			? admin_url( 'admin.php?page=' . self::SOURCES_SLUG )
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

	public static function ragged_summary( $ragged ) {
		if ( empty( $ragged['total'] ) ) {
			return '';
		}

		$numbers = array();
		foreach ( (array) $ragged['rows'] as $entry ) {
			if ( isset( $entry['row'] ) ) {
				$numbers[] = number_format_i18n( (int) $entry['row'] );
			}
		}

		$listed = implode( ', ', $numbers );

		if ( (int) $ragged['total'] > count( $numbers ) ) {
			$listed .= ' …';
		}

		return sprintf(
			/* translators: 1: number of rows, 2: expected column count, 3: list of row numbers. */
			_n(
				'%1$d row came back with a different number of cells than the other rows (%2$d): row %3$s. A value in it may be missing or sitting in the wrong column. Most often a lone quotation mark or a comma inside a value has run two cells into one.',
				'%1$d rows came back with a different number of cells than the rest (%2$d), so values in them may be missing or sitting in the wrong column. Most often a lone quotation mark or a comma inside a value has run two cells into one. Rows: %3$s.',
				(int) $ragged['total'],
				'live-sheets-table'
			),
			(int) $ragged['total'],
			(int) $ragged['expected'],
			$listed
		);
	}

	public static function print_grace_notice() {
		if ( LSTAB_Limits::is_pro() || ! current_user_can( LSTAB_Limits::capability() ) ) {
			return;
		}

		$left = LSTAB_Limits::grace_remaining();

		if ( $left <= 0 ) {
			return;
		}

		$dismissed = (int) get_user_meta( get_current_user_id(), self::GRACE_DISMISSED, true );

		if ( $dismissed > time() - WEEK_IN_SECONDS && $left > 2 * DAY_IN_SECONDS ) {
			return;
		}

		?>
		<div class="notice notice-warning is-dismissible lstab-grace-notice" data-lstab-dismiss="<?php echo esc_attr( wp_create_nonce( self::GRACE_DISMISSED ) ); ?>">
			<p>
				<strong>
					<?php
					printf(
						/* translators: %s: human readable time difference, e.g. "6 days". */
						esc_html__( 'Columns and rows you hid will start showing again in %s.', 'live-sheets-table' ),
						esc_html( LSTAB_Locale::span( time(), time() + $left ) )
					);
					?>
				</strong>
			</p>
			<p>
				<?php esc_html_e( 'Hiding columns and rows is a Pro feature, and Pro is not active on this site. Your choices are still applied for now.', 'live-sheets-table' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) ); ?>">
					<?php esc_html_e( 'See what is hidden, and what will come back', 'live-sheets-table' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	public function handle_dismiss_grace() {
		check_ajax_referer( self::GRACE_DISMISSED );

		update_user_meta( get_current_user_id(), self::GRACE_DISMISSED, time() );

		wp_send_json_success();
	}

	public static function print_cron_notice() {
		$health = LSTAB_Cron::health();

		if ( 'ok' === $health['state'] ) {
			return;
		}

		?>
		<div class="notice notice-warning lstab-cron-notice">
			<p class="lstab-cron-line-one">
				<strong><?php echo esc_html( $health['message'] ); ?></strong>
				<?php echo esc_html( $health['calm'] ); ?>
			</p>

			<details class="lstab-cron-more">
				<summary><?php esc_html_e( 'Why it happens, and how to fix it for good', 'live-sheets-table' ); ?></summary>

				<p><?php echo esc_html( $health['detail'] ); ?></p>
				<p>
					<?php esc_html_e( 'Meanwhile you can update any sheet by hand with “Refresh”.', 'live-sheets-table' ); ?>
				</p>

				<p>
					<strong><?php esc_html_e( 'To run checks without waiting for a visitor', 'live-sheets-table' ); ?></strong><br>
					<?php esc_html_e( 'WordPress has no clock of its own: its schedule runs only when a page is requested. Most hosting panels have a “Cron jobs” screen. Paste this line into it:', 'live-sheets-table' ); ?>
				</p>
				<p class="lstab-cron-row">
					<code class="lstab-cron-line"><?php echo esc_html( LSTAB_Cron::system_cron_line() ); ?></code>
					<button type="button" class="lstab-copy" data-lstab-copy="<?php echo esc_attr( LSTAB_Cron::system_cron_line() ); ?>">
						<?php echo LSTAB_Icons::icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
						<span class="lstab-copy-label"><?php esc_html_e( 'Copy', 'live-sheets-table' ); ?></span>
					</button>
				</p>
				<p class="lstab-help">
					<?php esc_html_e( 'If your hosting has no cron screen, a free uptime monitor pointed at your home page does the same job: every visit it makes runs the schedule.', 'live-sheets-table' ); ?>
				</p>
				<p>
					<a class="lstab-quiet" href="https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/" target="_blank" rel="noopener noreferrer">
						<?php echo LSTAB_Icons::icon( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
						<?php esc_html_e( 'The WordPress guide to system cron', 'live-sheets-table' ); ?>
					</a>
				</p>
			</details>
		</div>
		<?php
	}

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

	public static function render_masthead( $sub = '', $actions = '' ) {
		?>
		<div class="lstab-masthead">
			<span class="lstab-logo"><?php echo LSTAB_Icons::mark( 38 ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG from the plugin. ?></span>
			<span class="lstab-masthead-text">
				<h1>
					<?php esc_html_e( 'Live Sheets Table', 'live-sheets-table' ); ?>
					<?php if ( LSTAB_Limits::is_pro() ) : ?>
						<span class="lstab-pro-pill"><?php echo LSTAB_Icons::icon( 'spark' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>PRO</span>
					<?php endif; ?>
				</h1>
				<?php if ( '' !== $sub ) : ?>
					<span class="lstab-masthead-sub"><?php echo esc_html( $sub ); ?></span>
				<?php endif; ?>
			</span>
			<?php if ( '' !== $actions ) : ?>
				<span class="lstab-masthead-actions"><?php echo $actions; // phpcs:ignore WordPress.Security.EscapeOutput -- Caller escapes. ?></span>
			<?php endif; ?>
		</div>
		<hr class="wp-header-end">
		<?php
	}

	public static function masthead_summary( $sources ) {
		if ( ! $sources ) {
			return __( 'No sheets yet', 'live-sheets-table' );
		}

		$rows = 0;
		foreach ( $sources as $source ) {
			$rows += (int) $source['row_count'];
		}

		$line = sprintf(
			/* translators: 1: number of sheets, 2: total number of rows. */
			_n( '%1$s sheet · %2$s rows', '%1$s sheets · %2$s rows', count( $sources ), 'live-sheets-table' ),
			number_format_i18n( count( $sources ) ),
			number_format_i18n( $rows )
		);

		$next = wp_next_scheduled( LSTAB_Cron::TICK_HOOK );

		if ( $next && $next > time() ) {
			$line .= ' · ' . sprintf(
				/* translators: %s: human readable duration, e.g. "13 minutes". */
				__( 'next check in %s', 'live-sheets-table' ),
				LSTAB_Locale::span( time(), $next )
			);
		}

		return $line;
	}

	public static function card_state( $source ) {
		if ( LSTAB_Example::is_example( $source ) ) {
			return array(
				'tone' => 'calm',
				'icon' => 'play',
				'text' => __( 'Example — not from Google', 'live-sheets-table' ),
				'note' => __( 'Built into the plugin so you can try everything. Delete it whenever you like.', 'live-sheets-table' ),
			);
		}

		$status = self::status_for( $source );

		if ( 'ok' === $status['state'] ) {
			return array(
				'tone' => 'calm',
				'icon' => 'check',
				'text' => sprintf(
					/* translators: %s: human readable time difference, e.g. "2 minutes". */
					__( 'Up to date — %s ago', 'live-sheets-table' ),
					LSTAB_Locale::span( strtotime( $source['last_success_gmt'] . ' UTC' ), time() )
				),
				'note' => '',
			);
		}

		if ( 'stale' === $status['state'] ) {
			$why = $source['last_error']
				? ' ' . (string) $source['last_error']
				: '';

			return array(
				'tone' => 'warn',
				'icon' => 'alert',
				'text' => __( 'Google did not answer', 'live-sheets-table' ),
				'note' => __( 'Visitors see the last copy that arrived, so nothing on your pages is broken. The next check runs shortly.', 'live-sheets-table' ) . $why,
			);
		}

		if ( 'error' === $status['state'] ) {
			return array(
				'tone' => 'error',
				'icon' => 'cross',
				'text' => __( 'Nothing to show yet', 'live-sheets-table' ),
				'note' => $source['last_error'] ? (string) $source['last_error'] : __( 'This sheet has never been read successfully.', 'live-sheets-table' ),
			);
		}

		return array(
			'tone' => 'idle',
			'icon' => 'clock',
			'text' => __( 'Not checked yet', 'live-sheets-table' ),
			'note' => '',
		);
	}

	public static function column_names( $source, $shown = 3 ) {
		$config = LSTAB_Columns::sanitize( isset( $source['columns_config'] ) ? $source['columns_config'] : array() );
		$names  = array();

		foreach ( $config as $index => $column ) {
			$label = '' !== $column['label'] ? $column['label'] : $column['source'];

			if ( '' === $label ) {
				$label = sprintf(
					/* translators: %s: column letter, as Google labels it. */
					__( 'Column %s', 'live-sheets-table' ),
					LSTAB_Columns::letter( (int) $index )
				);
			}

			$names[] = $label;
		}

		return array(
			'names' => array_slice( $names, 0, $shown ),
			'extra' => max( 0, count( $names ) - $shown ),
		);
	}

	public static function status_for( $source ) {
		$has_data = '' !== (string) $source['snapshot_hash'];

		if ( 'ok' === $source['last_status'] && $source['last_success_gmt'] ) {
			return array(
				'state'  => 'ok',
				'icon'   => 'dashicons-yes-alt',
				'text'   => sprintf(
					/* translators: %s: human readable time difference. */
					__( 'Last sync OK (%s ago)', 'live-sheets-table' ),
					LSTAB_Locale::span( strtotime( $source['last_success_gmt'] . ' UTC' ), time() )
				),
				'detail' => '',
			);
		}

		if ( 'error' === $source['last_status'] ) {
			$since = $source['last_success_gmt']
				? sprintf(
					/* translators: %s: human readable time difference. */
					__( 'Failing since the last good sync %s ago', 'live-sheets-table' ),
					LSTAB_Locale::span( strtotime( $source['last_success_gmt'] . ' UTC' ), time() )
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
	public function handle_page_source() {
		$this->guard();
		check_admin_referer( 'lstab_page_source' );

		$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;
		$source    = $source_id ? LSTAB_Storage::get( $source_id ) : null;

		if ( ! $source ) {
			$this->redirect_with_notice( 0, 'error', __( 'That sheet is gone.', 'live-sheets-table' ), true );
		}

		$per_page = LSTAB_Paging::auto_per_page();
		LSTAB_Storage::update( $source_id, array( 'per_page' => $per_page ) );
		LSTAB_Cache::purge( $source_id );

		$this->redirect_with_notice(
			0,
			'success',
			sprintf(
				/* translators: 1: sheet title, 2: rows per page, 3: name of the tab holding the setting. */
				__( '“%1$s” is now shown %2$s rows at a time. Change the number, or go back to one long table, under “%3$s” on its own screen.', 'live-sheets-table' ),
				$source['title'],
				number_format_i18n( $per_page ),
				__( 'Columns and rows', 'live-sheets-table' )
			),
			true
		);
	}

	public function handle_keep_one_page() {
		$this->guard();
		check_admin_referer( 'lstab_keep_one_page' );

		$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;

		if ( $source_id ) {
			LSTAB_Paging::decline( $source_id );
		}

		$this->redirect_with_notice( 0, 'success', __( 'Kept as one long table. You will not be asked about this sheet again.', 'live-sheets-table' ), true );
	}

	protected function page_a_long_new_sheet( $source_id, $paging_chosen ) {
		if ( $paging_chosen ) {
			return '';
		}

		$source = LSTAB_Storage::get( $source_id );

		if ( ! $source || ! empty( $source['per_page'] ) || ! LSTAB_Paging::is_long( $source ) ) {
			return '';
		}

		$per_page = LSTAB_Paging::auto_per_page();
		LSTAB_Storage::update( $source_id, array( 'per_page' => $per_page ) );

		return sprintf(
			/* translators: 1: number of rows in the sheet, 2: rows per page, 3: name of the tab holding the setting. */
			__( 'Saved. This sheet has %1$s rows, so it is being shown %2$s at a time — the whole thing on one page would be slow to load and hard to read. Your visitors get a search box and page buttons, and both look through every row, not just the page on screen. To show it all at once instead, turn pages off under “%3$s”.', 'live-sheets-table' ),
			number_format_i18n( (int) $source['row_count'] ),
			number_format_i18n( $per_page ),
			__( 'Columns and rows', 'live-sheets-table' )
		);
	}

	protected static function per_page_from_post() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The caller has already checked the nonce and the capability.
		if ( empty( $_POST['paging'] ) ) {
			return 0;
		}

		$per_page = isset( $_POST['per_page'] ) ? absint( wp_unslash( $_POST['per_page'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( $per_page < 1 ) {
			$per_page = 25;
		}

		return min( LSTAB_Paging::MAX_PER_PAGE, $per_page );
	}
}
