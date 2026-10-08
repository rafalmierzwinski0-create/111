<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Hidden_Alerts {
	const OPTION = 'lstab_hidden_alerts';

	const DISMISSED_OPT = 'lstab_hidden_alerts_dismissed';

	public function register() {
		add_action( 'lstab_after_sync', array( __CLASS__, 'check' ), 20, 2 );
		add_action( 'lstab_source_deleted', array( __CLASS__, 'forget' ) );
		add_action( 'admin_notices', array( __CLASS__, 'print_notice' ) );
		add_action( 'admin_post_lstab_dismiss_hidden', array( $this, 'handle_dismiss' ) );
	}

	public static function check( $source, $table ) {
		$id = isset( $source['id'] ) ? (int) $source['id'] : 0;

		if ( $id <= 0 ) {
			return;
		}

		$fresh = LSTAB_Storage::get( $id );

		if ( ! $fresh ) {
			return;
		}

		$rows    = isset( $fresh['data']['rows'] ) ? (array) $fresh['data']['rows'] : array();
		$lines   = array();

		foreach ( LSTAB_Hidden_Rows::unresolved( $fresh['hidden_rows'], $rows ) as $stalled ) {
			$stalled['line'] = LSTAB_Hidden_Rows::line_for( $fresh, $stalled['index'] );
			$lines[]         = $stalled;
		}
		$headers = isset( $table['headers'] ) ? (array) $table['headers'] : array();

		$found = array(
			'title'   => (string) $fresh['title'],
			'rows'    => $lines,
			'columns' => LSTAB_Columns::orphans( isset( $source['columns_config'] ) ? $source['columns_config'] : array(), $headers ),
		);

		self::record( $id, ( $found['rows'] || $found['columns'] ) ? $found : null );
	}

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

	public static function forget( $id ) {
		self::record( (int) $id, null );
	}

	public static function signature( $id, $found ) {
		return md5( (string) $id . '|' . wp_json_encode( array( $found['rows'], $found['columns'] ) ) );
	}

	protected static function prune_dismissals( $index ) {
		$live = array();

		foreach ( $index as $id => $found ) {
			$live[] = self::signature( (int) $id, $found );
		}

		$dismissed = array_values( array_intersect( (array) get_option( self::DISMISSED_OPT, array() ), $live ) );

		update_option( self::DISMISSED_OPT, $dismissed, true );
	}

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

	public static function is_exposure( $found ) {
		foreach ( $found['columns'] as $column ) {
			if ( $column['hidden'] ) {
				return true;
			}
		}

		foreach ( $found['rows'] as $row ) {
			if ( 'moved' === $row['reason'] ) {
				return true;
			}
		}

		return false;
	}

	public static function print_notice() {
		if ( ! current_user_can( LSTAB_Limits::capability() ) ) {
			return;
		}

		$pending = self::pending();

		if ( ! $pending ) {
			return;
		}

		$exposed = false;

		foreach ( $pending as $found ) {
			$exposed = $exposed || self::is_exposure( $found );
		}

		?>
		<div class="notice <?php echo $exposed ? 'notice-warning' : 'notice-info'; ?> lstab-hidden-alert">
			<p>
				<strong>
					<?php
					echo esc_html(
						$exposed
							? __( 'Something you had taken out of a table is on the page again.', 'live-sheets-table' )
							: __( 'Something you had taken out of a table is no longer in the sheet.', 'live-sheets-table' )
					);
					?>
				</strong>
			</p>

			<?php foreach ( $pending as $lstab_id => $lstab_found ) : ?>
				<p><strong><?php echo esc_html( $lstab_found['title'] ); ?></strong></p>
				<ul class="lstab-alert-list">
					<?php foreach ( $lstab_found['columns'] as $lstab_column ) : ?>
						<li>
							<?php
							echo esc_html(
								$lstab_column['hidden']
									? sprintf(
										/* translators: 1: heading that was taken out, 2: heading in that position now. */
										__( 'The column you hid was headed “%1$s”; that position now holds “%2$s”, so the column is back on the page. Select the column you want and save.', 'live-sheets-table' ),
										$lstab_column['was'],
										'' === $lstab_column['now'] ? __( 'nothing', 'live-sheets-table' ) : $lstab_column['now']
									)
									: sprintf(
										/* translators: 1: heading that was renamed, 2: the name it was shown under, 3: heading in that position now. */
										__( 'The column you renamed to “%2$s” was headed “%1$s”; that position now holds “%3$s”, so the sheet\'s own heading is shown again.', 'live-sheets-table' ),
										$lstab_column['was'],
										$lstab_column['label'],
										'' === $lstab_column['now'] ? __( 'nothing', 'live-sheets-table' ) : $lstab_column['now']
									)
							);
							?>
						</li>
					<?php endforeach; ?>

					<?php foreach ( $lstab_found['rows'] as $lstab_row ) : ?>
						<?php
						$lstab_said = '' !== $lstab_row['label']
							? $lstab_row['label']
							: __( 'the empty row', 'live-sheets-table' );
						?>
						<li>
							<?php
							echo esc_html(
								'moved' === $lstab_row['reason']
									? sprintf(
										/* translators: 1: the sheet line, 2: the first few things the row said. */
										__( 'Line %1$d is no longer “%2$s”, so that row is back on the page. Rows have been inserted, removed or reordered in Google. Select the row you want and save.', 'live-sheets-table' ),
										(int) $lstab_row['line'],
										$lstab_said
									)
									: sprintf(
										/* translators: 1: the sheet line, 2: the first few things the row said. */
										__( 'The sheet no longer reaches line %1$d, where “%2$s” was hidden. The setting is kept: if the sheet grows back to that line, whatever is there will be hidden.', 'live-sheets-table' ),
										(int) $lstab_row['line'],
										$lstab_said
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
					"><?php esc_html_e( 'Open this table', 'live-sheets-table' ); ?></a>
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

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=' . LSTAB_Admin::SOURCES_SLUG ) );
		exit;
	}
}
