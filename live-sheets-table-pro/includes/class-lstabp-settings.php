<?php
/**
 * Pro settings screen.
 *
 * @package LiveSheetsTablePro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings and the private-sheet toggle.
 */
class LSTABP_Settings {

	const PAGE_SLUG = 'live-sheets-table-pro';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_filter( 'lstab_admin_tabs', array( $this, 'add_tab' ) );
		add_action( 'lstab_settings_sections', array( $this, 'render_licence_section' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_lstabp_save_client', array( $this, 'handle_save_client' ) );
		add_action( 'admin_post_lstabp_save_sources', array( $this, 'handle_save_sources' ) );
	}

	/**
	 * Add the screen under the free plugin's menu.
	 *
	 * @return void
	 */
	public function add_menu() {
		/*
		 * Under the free plugin's parent for hidden screens: the page exists
		 * and can be opened, but belongs in the row of tabs above the other
		 * screens rather than as a fourth line in the sidebar.
		 */
		add_submenu_page(
			LSTAB_Admin::HIDDEN_PARENT,
			__( 'Pro settings', 'live-sheets-table-pro' ),
			__( 'Pro', 'live-sheets-table-pro' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Add this screen to the row of tabs the free plugin prints.
	 *
	 * @param array<string,string> $tabs Page slug mapped to its label.
	 * @return array<string,string>
	 */
	public function add_tab( $tabs ) {
		if ( current_user_can( 'manage_options' ) ) {
			$tabs[ self::PAGE_SLUG ] = __( 'Pro', 'live-sheets-table-pro' );
		}

		return $tabs;
	}

	/**
	 * The licence, and how to stop paying for it, on the settings screen.
	 *
	 * Cancelling is put where someone would look for it rather than made hard
	 * to find. A subscription that takes a support ticket to leave is one people
	 * leave angrily, and say so in public.
	 *
	 * @param array<string,mixed> $settings Stored settings.
	 * @return void
	 */
	public function render_licence_section( $settings ) {
		$grace = LSTAB_Limits::is_pro() ? 0 : LSTAB_Limits::grace_remaining();
		?>
		<div class="lstab-card lstabp-licence-card">
			<h2 class="lstab-card-title"><?php esc_html_e( 'Your subscription', 'live-sheets-table-pro' ); ?></h2>

			<?php if ( LSTAB_Limits::is_pro() ) : ?>
				<p class="lstabp-licence-state is-active">
					<?php esc_html_e( 'Pro is active on this site.', 'live-sheets-table-pro' ); ?>
				</p>
			<?php elseif ( $grace > 0 ) : ?>
				<p class="lstabp-licence-state is-grace">
					<?php
					printf(
						/* translators: %s: human readable time difference, e.g. "6 days". */
						esc_html__( 'Pro is not running here. Columns and rows you hid will start showing again in %s.', 'live-sheets-table-pro' ),
						esc_html( human_time_diff( time(), time() + $grace ) )
					);
					?>
				</p>
			<?php else : ?>
				<p class="lstabp-licence-state">
					<?php esc_html_e( 'Pro is not running here.', 'live-sheets-table-pro' ); ?>
				</p>
			<?php endif; ?>

			<p class="lstab-help">
				<?php esc_html_e( 'Billing lives in your account, not on your site, so cancelling is done there and takes effect at the end of the period you have paid for. Nothing on your pages changes on the day you cancel.', 'live-sheets-table-pro' ); ?>
			</p>

			<p>
				<a class="button" href="<?php echo esc_url( LSTABP_Settings::account_url() ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Manage or cancel subscription', 'live-sheets-table-pro' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Where billing is managed.
	 *
	 * @return string
	 */
	public static function account_url() {
		return (string) apply_filters( 'lstabp_account_url', 'https://example.com/live-sheets-table/account/' );
	}

	/**
	 * Reuse the free plugin's admin styling, so Pro does not look bolted on.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'lstab-table' );
		wp_enqueue_style(
			'lstab-admin',
			LSTAB_URL . 'assets/css/lstab-admin.css',
			array( 'lstab-table' ),
			LSTAB_Plugin::asset_version( 'assets/css/lstab-admin.css' )
		);
	}

	/**
	 * Save the OAuth client.
	 *
	 * @return void
	 */
	public function handle_save_client() {
		$this->guard( 'lstabp_save_client' );

		LSTABP_Google_Auth::save_client(
			isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '',
			isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : ''
		);

		$this->redirect_with( 'success', __( 'Google client saved.', 'live-sheets-table-pro' ) );
	}

	/**
	 * Save which sources need the connected account.
	 *
	 * @return void
	 */
	public function handle_save_sources() {
		$this->guard( 'lstabp_save_sources' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- keys and values cast below.
		$submitted = isset( $_POST['private'] ) ? (array) wp_unslash( $_POST['private'] ) : array();

		foreach ( LSTAB_Storage::get_all() as $source ) {
			LSTABP_Private_Sheets::set_private(
				$source['id'],
				! empty( $submitted[ $source['id'] ] )
			);
		}

		$this->redirect_with( 'success', __( 'Sheet settings saved.', 'live-sheets-table-pro' ) );
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'live-sheets-table-pro' ) );
		}

		$client    = LSTABP_Google_Auth::client();
		$connected = LSTABP_Google_Auth::is_connected();
		$sources   = LSTAB_Storage::get_all();

		require LSTABP_PATH . 'includes/views/settings-page.php';
	}

	/**
	 * Print and clear the queued notice.
	 *
	 * @return void
	 */
	public static function print_notice() {
		$key    = 'lstabp_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );

		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			'error' === $notice['type'] ? 'notice-error' : 'notice-success',
			esc_html( $notice['message'] )
		);
	}

	/**
	 * Capability and nonce check.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	protected function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'live-sheets-table-pro' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Redirect back with a message.
	 *
	 * @param string $type    success or error.
	 * @param string $message Message.
	 * @return void
	 */
	protected function redirect_with( $type, $message ) {
		set_transient(
			'lstabp_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}
}
