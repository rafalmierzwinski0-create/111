<?php
/**
 * REST endpoints used by the admin preview screen and the block editor.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller.
 */
class LSTAB_Rest {

	const NAMESPACE_V1 = 'live-sheets-table/v1';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'url'            => array(
						'type'     => 'string',
						'required' => true,
					),
					'gid'            => array(
						'type'    => 'string',
						'default' => '',
					),
					'firstRowHeader' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'style'          => array(
						'type'    => 'string',
						'default' => '',
					),
					'layout'         => array(
						'type'    => 'string',
						'default' => 'table',
					),
					// Renames and hidden columns, so the preview shows what a
					// visitor would see rather than the raw sheet.
					'columns'        => array(
						'type'    => 'array',
						'default' => array(),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/sources',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_sources' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/sources/(?P<id>\d+)/refresh',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'refresh' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Permission check for management routes.
	 *
	 * @return bool|WP_Error
	 */
	public function can_manage() {
		if ( current_user_can( LSTAB_Limits::capability() ) ) {
			return true;
		}

		return new WP_Error(
			'lstab_forbidden',
			__( 'You are not allowed to manage sheet sources.', 'live-sheets-table' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Permission check for read-only routes used by the editor.
	 *
	 * @return bool|WP_Error
	 */
	public function can_edit_posts() {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		return new WP_Error(
			'lstab_forbidden',
			__( 'You are not allowed to list sheet sources.', 'live-sheets-table' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Fetch and parse a sheet without saving anything.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function preview( $request ) {
		$reference = LSTAB_Url::parse( (string) $request->get_param( 'url' ) );

		if ( is_wp_error( $reference ) ) {
			return new WP_Error(
				$reference->get_error_code(),
				$reference->get_error_message(),
				array( 'status' => 400 )
			);
		}

		$gid = (string) $request->get_param( 'gid' );
		if ( '' !== $gid ) {
			$reference['gid'] = LSTAB_Url::sanitize_gid( $gid );
		}

		$first_row_header = (bool) $request->get_param( 'firstRowHeader' );

		$table = LSTAB_Fetcher::fetch_table(
			$reference['sheet_id'],
			$reference['gid'],
			$reference['sheet_kind'],
			$first_row_header
		);

		if ( is_wp_error( $table ) ) {
			return new WP_Error(
				$table->get_error_code(),
				$table->get_error_message(),
				array( 'status' => 502 )
			);
		}

		$tabs = LSTAB_Fetcher::fetch_tabs( $reference['sheet_id'], $reference['sheet_kind'] );
		if ( is_wp_error( $tabs ) ) {
			// Tab discovery is best effort — a single-tab fallback is fine.
			$tabs = array();
		}

		$rows    = $table['rows'];
		$preview = array_slice( $rows, 0, 25 );

		return rest_ensure_response(
			array(
				'sheetId'   => $reference['sheet_id'],
				'sheetKind' => $reference['sheet_kind'],
				'gid'       => $reference['gid'],
				'tabs'      => $tabs,
				'headers'   => $table['headers'],
				'rows'      => $preview,
				'rowCount'  => count( $rows ),
				'colCount'  => count( $table['headers'] ),
				'truncated' => count( $rows ) > count( $preview ),
				'html'      => LSTAB_Renderer::render_preview(
					LSTAB_Columns::apply(
						array(
							'headers' => $table['headers'],
							'rows'    => $preview,
						),
						LSTAB_Columns::sanitize( (array) $request->get_param( 'columns' ) )
					),
					array(
						'style'  => LSTAB_Styles::sanitize( (string) $request->get_param( 'style' ) ),
						'layout' => (string) $request->get_param( 'layout' ),
					)
				),
			)
		);
	}

	/**
	 * List saved sources for the block picker.
	 *
	 * @return WP_REST_Response
	 */
	public function list_sources() {
		$sources = array();

		foreach ( LSTAB_Storage::get_all() as $source ) {
			$sources[] = array(
				'id'          => $source['id'],
				'title'       => $source['title'],
				'rowCount'    => $source['row_count'],
				'colCount'    => $source['col_count'],
				'lastStatus'  => $source['last_status'],
				'lastSuccess' => $source['last_success_gmt'],
			);
		}

		return rest_ensure_response( $sources );
	}

	/**
	 * Run a manual sync.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function refresh( $request ) {
		$id     = absint( $request->get_param( 'id' ) );
		$result = LSTAB_Sync::run( $id );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 502 )
			);
		}

		$source = LSTAB_Storage::get( $id );

		return rest_ensure_response(
			array(
				'success'     => true,
				'rowCount'    => $source ? $source['row_count'] : 0,
				'lastSuccess' => $source ? $source['last_success_gmt'] : null,
			)
		);
	}
}
