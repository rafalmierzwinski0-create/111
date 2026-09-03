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
					// Which source is being edited, so anything configured per
					// source — an add-on's colour rules, say — shows here too.
					'sourceId'       => array(
						'type'    => 'integer',
						'default' => 0,
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

		/*
		 * Redrawing a saved table from the copy already stored: no request to
		 * Google, so renaming a column or hiding one shows immediately rather
		 * than after a save. It is also the only way the bundled example can
		 * have a live preview at all, having nothing to fetch.
		 */
		register_rest_route(
			self::NAMESPACE_V1,
			'/redraw',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'redraw' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'sourceId' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'style'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'layout'   => array(
						'type'    => 'string',
						'default' => 'table',
					),
					'columns'  => array(
						'type'    => 'array',
						'default' => array(),
					),
				),
			)
		);

		/*
		 * The editor shows custom CSS working as it is typed. Confining a rule
		 * to one table is done by rewriting its selectors, and that is written
		 * once, here in PHP — a second copy in JavaScript would eventually
		 * disagree with the first, and the preview would stop being a preview.
		 */
		register_rest_route(
			self::NAMESPACE_V1,
			'/scoped-css',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'scoped_css' ),
				'permission_callback' => array( $this, 'can_write_css' ),
				'args'                => array(
					'css'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'selector' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
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
	 * Redraw a saved source from its stored copy.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function redraw( $request ) {
		$source_id = absint( $request->get_param( 'sourceId' ) );
		$source    = $source_id ? LSTAB_Storage::get( $source_id ) : null;

		if ( ! $source ) {
			return new WP_Error(
				'lstab_unknown_source',
				__( 'That sheet source no longer exists.', 'live-sheets-table' ),
				array( 'status' => 404 )
			);
		}

		if ( empty( $source['data']['headers'] ) && empty( $source['data']['rows'] ) ) {
			return new WP_Error(
				'lstab_nothing_stored',
				__( 'This sheet has not been fetched yet, so there is nothing to redraw.', 'live-sheets-table' ),
				array( 'status' => 409 )
			);
		}

		$rows    = (array) $source['data']['rows'];
		$preview = array_slice( $rows, 0, 25 );

		return rest_ensure_response(
			array(
				'rowCount'  => count( $rows ),
				'colCount'  => count( (array) $source['data']['headers'] ),
				'truncated' => count( $rows ) > count( $preview ),
				'html'      => LSTAB_Renderer::render_preview(
					LSTAB_Columns::apply(
						array(
							'headers' => (array) $source['data']['headers'],
							'rows'    => $preview,
						),
						LSTAB_Columns::sanitize( (array) $request->get_param( 'columns' ) )
					),
					array(
						'style'      => LSTAB_Styles::sanitize( (string) $request->get_param( 'style' ) ),
						'layout'     => (string) $request->get_param( 'layout' ),
						'source_id'  => $source_id,
						// The stored appearance, so a redraw does not undo what
						// the swatches have already applied to the preview.
						'style_vars' => isset( $source['style_vars'] ) ? $source['style_vars'] : array(),
						'custom_css' => isset( $source['custom_css'] ) ? $source['custom_css'] : '',
					)
				),
			)
		);
	}

	/**
	 * Permission check for the CSS preview.
	 *
	 * @return bool|WP_Error
	 */
	public function can_write_css() {
		if ( current_user_can( LSTAB_Limits::capability() ) && LSTAB_Custom_Css::user_can_edit() ) {
			return true;
		}

		return new WP_Error(
			'lstab_forbidden',
			__( 'You are not allowed to write CSS on this site.', 'live-sheets-table' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Confine a stylesheet to one selector, so the editor can show it working.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function scoped_css( $request ) {
		$selector = trim( (string) $request->get_param( 'selector' ) );

		/*
		 * The caller says which element to confine the rules to, but only in
		 * the shape this plugin uses. Anything else and a preview could be
		 * asked to style the dashboard around it.
		 */
		if ( ! preg_match( '#^\[data-lstab-preview="[a-z0-9-]{1,40}"\]$#', $selector ) ) {
			$selector = '[data-lstab-preview="none"]';
		}

		return rest_ensure_response(
			array(
				'css' => LSTAB_Custom_Css::scope(
					LSTAB_Custom_Css::sanitize( (string) $request->get_param( 'css' ) ),
					$selector
				),
			)
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

		/*
		 * Fetched and parsed in two steps rather than through fetch_table(), so
		 * the raw payload can be handed back as well. When a table comes out
		 * wrong the first question is always whether the sheet or the plugin is
		 * at fault, and only the bytes Google actually sent answer it.
		 */
		$csv = LSTAB_Fetcher::fetch_csv(
			$reference['sheet_id'],
			$reference['gid'],
			$reference['sheet_kind']
		);

		if ( is_wp_error( $csv ) ) {
			return new WP_Error(
				$csv->get_error_code(),
				$csv->get_error_message(),
				array( 'status' => 502 )
			);
		}

		$table = LSTAB_CSV_Parser::parse( $csv, $first_row_header );

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
				// Enough to see the header row and the first few data rows,
				// which is where a malformed export shows itself.
				'raw'       => self::sample( $csv ),
				'rawBytes'  => strlen( $csv ),
				'ragged'    => isset( $table['ragged'] ) ? $table['ragged'] : null,
				'html'      => LSTAB_Renderer::render_preview(
					LSTAB_Columns::apply(
						array(
							'headers' => $table['headers'],
							'rows'    => $preview,
						),
						LSTAB_Columns::sanitize( (array) $request->get_param( 'columns' ) )
					),
					array(
						'style'     => LSTAB_Styles::sanitize( (string) $request->get_param( 'style' ) ),
						'layout'    => (string) $request->get_param( 'layout' ),
						'source_id' => absint( $request->get_param( 'sourceId' ) ),
					)
				),
			)
		);
	}

	/**
	 * The opening of a payload, cut on a line boundary.
	 *
	 * @param string $csv Raw payload.
	 * @return string
	 */
	protected static function sample( $csv ) {
		$limit = 4000;

		if ( strlen( $csv ) <= $limit ) {
			return $csv;
		}

		$cut  = substr( $csv, 0, $limit );
		$last = strrpos( $cut, "\n" );

		return ( false === $last ? $cut : substr( $cut, 0, $last ) ) . "\n…";
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
