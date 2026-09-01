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
		// Records that the add-on is running, so that if it later stops, the
		// choices it made can be honoured for a while rather than dropped.
		add_action( 'admin_init', array( 'LSTAB_Limits', 'note_pro_seen' ) );
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_lstab_save_source', array( $this, 'handle_save' ) );
		add_action( 'admin_post_lstab_delete_source', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_lstab_refresh_source', array( $this, 'handle_refresh' ) );
		add_action( 'admin_post_lstab_dismiss_ragged', array( $this, 'handle_dismiss_ragged' ) );
		add_action( 'admin_notices', array( $this, 'print_global_notice' ) );
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

		// The preview renders the real table markup, so it needs the real
		// behaviour too: without this its search box and sort buttons are inert.
		wp_enqueue_script( 'lstab-table' );

		wp_enqueue_style(
			'lstab-admin',
			LSTAB_URL . 'assets/css/lstab-admin.css',
			array( 'lstab-table' ),
			LSTAB_Plugin::asset_version( 'assets/css/lstab-admin.css' )
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
				// Needed so the script can clear whichever preset class is on
				// the preview before applying the newly chosen one.
				'presets'    => array_keys( LSTAB_Styles::all() ),
				// The metric mapping lives in PHP; the script mirrors it rather
				// than keeping a second copy that could drift.
				'metrics'    => wp_list_pluck( LSTAB_Customizer::metrics(), 'vars' ),
				'i18n'       => array(
					'loading'     => __( 'Loading preview…', 'live-sheets-table' ),
					'failed'      => __( 'Preview failed', 'live-sheets-table' ),
					'rowsFound'   => __( 'Found %1$s rows across %2$s columns.', 'live-sheets-table' ),
					'truncated'   => __( 'Showing the first 25 rows.', 'live-sheets-table' ),
					'pickTab'     => __( 'Pick the tab you want to publish:', 'live-sheets-table' ),
					'noTabs'      => __( 'Could not read the tab list — the tab from your link will be used.', 'live-sheets-table' ),
					'emptyUrl'    => __( 'Paste a Google Sheets link first.', 'live-sheets-table' ),
					'rawBytes'    => __( '%1$s characters received.', 'live-sheets-table' ),
					'rawRagged'   => __( 'Look at row %1$s: it came back with a different number of cells than the rest.', 'live-sheets-table' ),
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
			'layout'           => isset( $_POST['layout'] ) && in_array( sanitize_key( wp_unslash( $_POST['layout'] ) ), array( 'table', 'auto', 'cards' ), true )
				? sanitize_key( wp_unslash( $_POST['layout'] ) )
				: 'table',
			// LSTAB_Customizer::sanitize() drops anything it does not recognise
			// and hex-checks every colour, so the raw array is safe to hand over.
			'style_vars'       => isset( $_POST['style_vars'] ) ? LSTAB_Customizer::sanitize( wp_unslash( $_POST['style_vars'] ) ) : LSTAB_Customizer::defaults(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitised field by field in LSTAB_Columns.
			'columns_config'   => isset( $_POST['columns'] ) ? LSTAB_Columns::sanitize( wp_unslash( $_POST['columns'] ) ) : array(),
			'sticky_first'     => empty( $_POST['sticky_first'] ) ? 0 : 1,
			'link_cells'       => empty( $_POST['link_cells'] ) ? 0 : 1,
			'per_page'         => isset( $_POST['per_page'] ) ? min( LSTAB_Paging::MAX_PER_PAGE, absint( wp_unslash( $_POST['per_page'] ) ) ) : 0,
		);

		/*
		 * Only when the control that edits this was actually on the screen. An
		 * empty list means "nothing hidden" when the picker submitted it, and
		 * means nothing at all when the picker was not there — and treating the
		 * second as the first would quietly publish rows someone hid.
		 */
		if ( isset( $_POST['_lstab_hidden_rows_present'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitised key by key in LSTAB_Hidden_Rows.
			$data['hidden_rows'] = isset( $_POST['hidden_rows'] ) ? LSTAB_Hidden_Rows::sanitize( wp_unslash( $_POST['hidden_rows'] ) ) : array();
		}

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

		list( $lstab_type, $lstab_message ) = $this->sync_outcome( $source_id, __( 'Sheet source saved and synced.', 'live-sheets-table' ) );
		$this->redirect_with_notice( $source_id, $lstab_type, $lstab_message, true );
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

		list( $lstab_type, $lstab_message ) = $this->sync_outcome( $source_id, __( 'Sheet refreshed from Google.', 'live-sheets-table' ) );
		$this->redirect_with_notice( 0, $lstab_type, $lstab_message, true );
	}

	/**
	 * What to say after a sync that worked.
	 *
	 * The fetch succeeding and the sheet arriving intact are two different
	 * things, and a plain "saved and synced" after a malformed sheet would say
	 * the second when it only knows the first.
	 *
	 * @param int    $source_id Source that was synced.
	 * @param string $success   Message for a clean result.
	 * @return array{0:string,1:string} Notice type and message.
	 */
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

	/**
	 * Warn anywhere in the dashboard when a sheet came back malformed.
	 *
	 * The plugin's own screens say it inline, so this is for everywhere else:
	 * a table quietly showing shifted values is not something to find out about
	 * only on the day you happen to open the plugin.
	 *
	 * @return void
	 */
	public function print_global_notice() {
		if ( ! current_user_can( LSTAB_Limits::capability() ) ) {
			return;
		}

		// The plugin's screens carry the warning beside the source it belongs
		// to, which says more than a summary would.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( (string) $screen->id, self::MENU_SLUG ) ) {
			return;
		}

		// An autoloaded option, so a clean site pays nothing for this.
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
			// Built from escaped parts just above.
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

	/**
	 * Silence the current findings, and only the current findings.
	 *
	 * @return void
	 */
	public function handle_dismiss_ragged() {
		$this->guard( 'lstab_dismiss_ragged' );

		$index = (array) get_option( LSTAB_Storage::RAGGED_OPT, array() );
		update_option( LSTAB_Storage::DISMISSED_OPT, array_values( $index ), true );

		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
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
	 * Warn when the scheduler has stopped running.
	 *
	 * @return void
	 */
	/**
	 * One line describing a sheet that came back with ragged rows.
	 *
	 * Google gives every row the same number of cells, so a row that disagrees
	 * means the payload did not survive intact — nearly always an unmatched
	 * quotation mark, which runs two rows together. The table still renders;
	 * this only says where to look, and only to someone who can fix it.
	 *
	 * @param array<string,mixed> $ragged Stored finding.
	 * @return string
	 */
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
				'Row %3$s came back with a different number of cells than the other rows (%2$d), so a value in it may be missing or sitting in the wrong column. Most often a lone quotation mark or a comma inside a value has run two cells into one.',
				'%1$d rows came back with a different number of cells than the rest (%2$d), so values in them may be missing or sitting in the wrong column. Most often a lone quotation mark or a comma inside a value has run two cells into one. Rows: %3$s.',
				(int) $ragged['total'],
				'live-sheets-table'
			),
			(int) $ragged['total'],
			(int) $ragged['expected'],
			$listed
		);
	}

	/**
	 * Say that hidden columns and rows are on borrowed time.
	 *
	 * The choices someone paid to make keep working for ten days after the
	 * add-on stops, so a licence ending on a Tuesday does not rearrange a
	 * public page on the Tuesday. Ten quiet days followed by a page silently
	 * changing would be worse than no grace at all, so the countdown is said
	 * out loud while it runs.
	 *
	 * @return void
	 */
	public static function print_grace_notice() {
		if ( LSTAB_Limits::is_pro() ) {
			return;
		}

		$left = LSTAB_Limits::grace_remaining();

		if ( $left <= 0 ) {
			return;
		}

		?>
		<div class="notice notice-warning lstab-grace-notice">
			<p>
				<strong>
					<?php
					printf(
						/* translators: %s: human readable time difference, e.g. "6 days". */
						esc_html__( 'Columns and rows you hid will start showing again in %s.', 'live-sheets-table' ),
						esc_html( human_time_diff( time(), time() + $left ) )
					);
					?>
				</strong>
			</p>
			<p>
				<?php esc_html_e( 'Choosing what to leave out of a table is part of Pro, and Pro is not active on this site. Your choices are still being honoured for now, so nothing on your pages has changed yet.', 'live-sheets-table' ); ?>
			</p>
		</div>
		<?php
	}

	public static function print_cron_notice() {
		$health = LSTAB_Cron::health();

		if ( 'ok' === $health['state'] ) {
			return;
		}

		?>
		<div class="notice notice-warning lstab-cron-notice">
			<p>
				<strong><?php echo esc_html( $health['message'] ); ?></strong>
			</p>
			<p><?php echo esc_html( $health['detail'] ); ?></p>
			<p>
				<?php esc_html_e( 'Meanwhile you can update any sheet by hand with “Refresh now”.', 'live-sheets-table' ); ?>
			</p>

			<p>
				<strong><?php esc_html_e( 'To check sheets even when nobody visits', 'live-sheets-table' ); ?></strong><br>
				<?php esc_html_e( 'WordPress has no clock of its own — its schedule only runs when a page is requested, so a quiet site checks nothing. Give your host a real clock instead. Most hosting panels have a “Cron jobs” screen; paste this line into it:', 'live-sheets-table' ); ?>
			</p>
			<p>
				<code class="lstab-cron-line"><?php echo esc_html( LSTAB_Cron::system_cron_line() ); ?></code>
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: %s: link to the WordPress cron documentation. */
					esc_html__( 'No shell access? A free uptime monitor pointed at your home page does the same job, because every visit it makes runs the schedule. %s', 'live-sheets-table' ),
					'<a href="https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/" target="_blank" rel="noopener noreferrer">'
						. esc_html__( 'How to run WordPress schedules from a system cron', 'live-sheets-table' )
						. '</a>'
				);
				?>
			</p>
		</div>
		<?php
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
