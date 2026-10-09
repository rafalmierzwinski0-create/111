<?php

defined( 'ABSPATH' ) || exit;

class LSTABP_Settings {
	const PAGE_SLUG = 'live-sheets-table-pro';

	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_filter( 'lstab_admin_tabs', array( $this, 'add_tab' ) );
		add_action( 'lstab_settings_sections', array( $this, 'render_licence_section' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_lstabp_save_client', array( $this, 'handle_save_client' ) );
		add_action( 'admin_post_lstabp_save_sources', array( $this, 'handle_save_sources' ) );
	}

	public function add_menu() {
		add_submenu_page(
			LSTAB_Admin::MENU_SLUG,
			__( 'Pro settings', 'live-sheets-table-pro' ),
			__( 'Pro', 'live-sheets-table-pro' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function add_tab( $tabs ) {
		if ( current_user_can( 'manage_options' ) ) {
			$tabs[ self::PAGE_SLUG ] = __( 'Pro', 'live-sheets-table-pro' );
		}

		return $tabs;
	}

	public function render_licence_section( $settings ) {
		$grace  = LSTAB_Limits::is_pro() ? 0 : LSTAB_Limits::grace_remaining();
		$active = LSTAB_Limits::is_pro();
		?>
		<div class="lstab-panel lstabp-licence-card">
			<div class="lstab-panel-head">
				<?php echo LSTAB_Icons::badge( 'spark', 'amber' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
				<span>
					<h2><?php esc_html_e( 'Your subscription', 'live-sheets-table-pro' ); ?></h2>
					<span class="lstab-panel-sub"><?php esc_html_e( 'What Pro is doing on this site, and how to stop paying for it', 'live-sheets-table-pro' ); ?></span>
				</span>
			</div>

			<div class="lstab-state-strip">
				<?php if ( $active ) : ?>
					<span class="lstab-state lstab-state--calm lstabp-licence-state is-active">
						<?php echo LSTAB_Icons::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
						<?php esc_html_e( 'Pro is active on this site.', 'live-sheets-table-pro' ); ?>
					</span>
				<?php elseif ( $grace > 0 ) : ?>
					<span class="lstab-state lstab-state--warn lstabp-licence-state is-grace">
						<?php echo LSTAB_Icons::icon( 'alert' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
						<?php
						printf(
							/* translators: %s: human readable time difference, e.g. "6 days". */
							esc_html__( 'Pro is not running here. Columns and rows you hid will start showing again in %s.', 'live-sheets-table-pro' ),
							esc_html( LSTAB_Locale::span( time(), time() + $grace ) )
						);
						?>
					</span>
				<?php else : ?>
					<span class="lstab-state lstab-state--idle lstabp-licence-state">
						<?php echo LSTAB_Icons::icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
						<?php esc_html_e( 'Pro is not running here.', 'live-sheets-table-pro' ); ?>
					</span>
				<?php endif; ?>
			</div>

			<div class="lstab-row">
				<div class="lstab-row-say">
					<p class="lstab-row-title"><?php esc_html_e( 'Billing and cancellation', 'live-sheets-table-pro' ); ?></p>
					<p class="lstab-row-help">
						<?php esc_html_e( 'Billing is handled in your account, not on this site. Cancelling takes effect at the end of the period you have paid for.', 'live-sheets-table-pro' ); ?>
					</p>
				</div>
				<div class="lstab-row-do">
					<a class="lstab-mini lstab-mini--strong" href="<?php echo esc_url( LSTABP_Settings::account_url() ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo LSTAB_Icons::icon( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
						<?php esc_html_e( 'Manage or cancel subscription', 'live-sheets-table-pro' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	public static function account_url() {
		return (string) apply_filters( 'lstabp_account_url', 'https://example.com/live-sheets-table/account/' );
	}

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

	public function handle_save_client() {
		$this->guard();
		check_admin_referer( 'lstabp_save_client' );

		LSTABP_Google_Auth::save_client(
			isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '',
			isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : ''
		);

		$this->redirect_with( 'success', __( 'Google client saved.', 'live-sheets-table-pro' ) );
	}

	public function handle_save_sources() {
		$this->guard();
		check_admin_referer( 'lstabp_save_sources' );

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

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'live-sheets-table-pro' ) );
		}

		$client    = LSTABP_Google_Auth::client();
		$connected = LSTABP_Google_Auth::is_connected();
		$sources   = LSTAB_Storage::get_all();

		require LSTABP_PATH . 'includes/views/settings-page.php';
	}

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

	protected function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'live-sheets-table-pro' ), '', array( 'response' => 403 ) );
		}
	}

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
