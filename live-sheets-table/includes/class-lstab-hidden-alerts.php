<?php
/**
 * Telling someone when a hidden thing has come back.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Watches the choices about what to leave out, and says when one stops working.
 *
 * Every other kind of fault here announces itself: a table that will not sync
 * turns red, a sheet that arrives damaged raises a warning. Something being
 * shown that was meant to be hidden announces nothing at all — the page simply
 * looks complete, which is exactly what it would look like if all were well.
 * That is the one failure in this plugin nobody would notice on their own, so
 * it is the one that has to come and find them.
 */
class LSTAB_Hidden_Alerts {

	/**
	 * Autoloaded index: source ID mapped to what has come undone.
	 */
	const OPTION = 'lstab_hidden_alerts';

	/**
	 * Signatures of alerts somebody has already seen and put away.
	 */
	const DISMISSED_OPT = 'lstab_hidden_alerts_dismissed';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'lstab_after_sync', array( __CLASS__, 'check' ), 20, 2 );
		add_action( 'lstab_source_deleted', array( __CLASS__, 'forget' ) );
		add_action( 'admin_notices', array( __CLASS__, 'print_notice' ) );
		add_action( 'admin_post_lstab_dismiss_hidden', array( $this, 'handle_dismiss' ) );
	}

	/**
	 * Look at one source after it has synced.
	 *
	 * @param array<string,mixed> $source Source row as it was before the sync.
	 * @param array<string,mixed> $table  The table just stored.
	 * @return void
	 */
	public static function check( $source, $table ) {
		$id = isset( $source['id'] ) ? (int) $source['id'] : 0;

		if ( $id <= 0 ) {
			return;
		}

		// Read the source again: the sync has just rewritten the choices about
		// hidden rows and the column settings against what actually arrived.
		$fresh = LSTAB_Storage::get( $id );

		if ( ! $fresh ) {
			return;
		}

		$rows    = isset( $fresh['data']['rows'] ) ? (array) $fresh['data']['rows'] : array();
		$headers = isset( $table['headers'] ) ? (array) $table['headers'] : array();

		/*
		 * Columns are read from the settings as they were *before* this sync.
		 * Reconciling them against the new headings is what drops a setting
		 * whose column has gone — which is the very thing worth reporting, so
		 * by the time the source is read again there is nothing left to see.
		 */
		$found = array(
			'title'   => (string) $fresh['title'],
			'rows'    => LSTAB_Hidden_Rows::unresolved( $fresh['hidden_rows'], $rows ),
			'columns' => LSTAB_Columns::orphans( isset( $source['columns_config'] ) ? $source['columns_config'] : array(), $headers ),
		);

		self::record( $id, ( $found['rows'] || $found['columns'] ) ? $found : null );
	}

	/**
	 * Put one source into the index, or take it out.
	 *
	 * @param int        $id    Source ID.
	 * @param array|null $found What has come undone, or null when nothing has.
	 * @return void
	 */
	protected static function record( $id, $found ) {
		$index = (array) get_option( self::OPTION, array() );
		$had   = isset( $index[ $id ] );

		if ( $found ) {
			$index[ $id ] = $found;
		} elseif ( $had ) {
			unset( $index[ $id ] );
		} else {
			return;
		}

		update_option( self::OPTION, $index, true );
		self::prune_dismissals( $index );
	}

	/**
	 * Forget a source that has been deleted.
	 *
	 * @param int $id Source ID.
	 * @return void
	 */
	public static function forget( $id ) {
		self::record( (int) $id, null );
	}

	/**
	 * What makes one alert different from another.
	 *
	 * Dismissing is of this particular thing having come undone, not of the
	 * subject in general: a second row coming back next week is news again.
	 *
	 * @param int                 $id    Source ID.
	 * @param array<string,mixed> $found Recorded finding.
	 * @return string
	 */
	public static function signature( $id, $found ) {
		return md5( (string) $id . '|' . wp_json_encode( array( $found['rows'], $found['columns'] ) ) );
	}

	/**
	 * Drop dismissals of alerts that are no longer raised.
	 *
	 * @param array<int,array<string,mixed>> $index Current index.
	 * @return void
	 */
	protected static function prune_dismissals( $index ) {
		$live = array();

		foreach ( $index as $id => $found ) {
			$live[] = self::signature( (int) $id, $found );
		}

		$dismissed = array_values( array_intersect( (array) get_option( self::DISMISSED_OPT, array() ), $live ) );

		update_option( self::DISMISSED_OPT, $dismissed, true );
	}

	/**
	 * Everything currently worth saying, minus what has been put away.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function pending() {
		$index     = (array) get_option( self::OPTION, array() );
		$dismissed = (array) get_option( self::DISMISSED_OPT, array() );
		$pending   = array();

		foreach ( $index as $id => $found ) {
			if ( in_array( self::signature( (int) $id, $found ), $dismissed, true ) ) {
				continue;
			}

			$pending[ (int) $id ] = $found;
		}

		return $pending;
	}

	/**
	 * Print the notice.
	 *
	 * @return void
	 */
	public static function print_notice() {
		if ( ! current_user_can( LSTAB_Limits::capability() ) ) {
			return;
		}

		$pending = self::pending();

		if ( ! $pending ) {
			return;
		}

		?>
		<div class="notice notice-warning lstab-hidden-alert">
			<p>
				<strong><?php esc_html_e( 'Something you had left out of a table is being shown again.', 'live-sheets-table' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'The sheet changed in a way that the choice could not be followed through. Nothing is broken, and the page looks perfectly normal — which is why this is being said out loud rather than left to be noticed.', 'live-sheets-table' ); ?>
			</p>

			<?php foreach ( $pending as $lstab_id => $lstab_found ) : ?>
				<p>
					<strong><?php echo esc_html( $lstab_found['title'] ); ?></strong>
				</p>
				<ul class="lstab-alert-list">
					<?php foreach ( $lstab_found['columns'] as $lstab_column ) : ?>
						<li>
							<?php
							echo esc_html(
								$lstab_column['hidden']
									? sprintf(
										/* translators: %s: heading of a column. */
										__( 'The column “%s” is no longer in the sheet under that heading, so it is no longer being left out of the table.', 'live-sheets-table' ),
										$lstab_column['was']
									)
									: sprintf(
										/* translators: 1: heading of a column, 2: the name it was shown under. */
										__( 'The column “%1$s” is no longer in the sheet under that heading, so it is no longer being shown as “%2$s”.', 'live-sheets-table' ),
										$lstab_column['was'],
										$lstab_column['label']
									)
							);
							?>
						</li>
					<?php endforeach; ?>

					<?php foreach ( $lstab_found['rows'] as $lstab_row ) : ?>
						<li>
							<?php
							echo esc_html(
								'ambiguous' === $lstab_row['reason']
									? sprintf(
										/* translators: %s: what the row is called. */
										__( 'The hidden row “%s” was edited, and several rows now say that, so there is no telling which one you meant. None of them is hidden.', 'live-sheets-table' ),
										$lstab_row['name']
									)
									: sprintf(
										/* translators: %s: what the row is called. */
										__( 'The hidden row “%s” is not in the sheet any more. Your choice is kept in case it comes back.', 'live-sheets-table' ),
										$lstab_row['name']
									)
							);
							?>
						</li>
					<?php endforeach; ?>
				</ul>
				<p>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'page'   => LSTAB_Admin::EDIT_SLUG,
								'source' => (int) $lstab_id,
							),
							admin_url( 'admin.php' )
						)
					);
					?>
					"><?php esc_html_e( 'Open this table and put it right', 'live-sheets-table' ); ?></a>
				</p>
			<?php endforeach; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lstab-inline-form">
				<input type="hidden" name="action" value="lstab_dismiss_hidden">
				<?php wp_nonce_field( 'lstab_dismiss_hidden' ); ?>
				<button type="submit" class="button button-small"><?php esc_html_e( 'I have read this', 'live-sheets-table' ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * Remember that this has been read.
	 *
	 * @return void
	 */
	public function handle_dismiss() {
		if ( ! current_user_can( LSTAB_Limits::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'live-sheets-table' ) );
		}

		check_admin_referer( 'lstab_dismiss_hidden' );

		$dismissed = (array) get_option( self::DISMISSED_OPT, array() );

		foreach ( self::pending() as $id => $found ) {
			$dismissed[] = self::signature( (int) $id, $found );
		}

		update_option( self::DISMISSED_OPT, array_values( array_unique( $dismissed ) ), true );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=' . LSTAB_Admin::MENU_SLUG ) );
		exit;
	}
}
