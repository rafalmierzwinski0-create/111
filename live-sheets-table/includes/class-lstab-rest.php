<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Rest {
	const NAMESPACE_V1 = 'live-sheets-table/v1';

	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

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
					'sticky'         => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'stickyHead'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'columns'        => array(
						'type'    => 'array',
						'default' => array(),
					),
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
					'sticky'   => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'stickyHead' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'columns'  => array(
						'type'    => 'array',
						'default' => array(),
					),
				),
			)
		);

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

		do_action( 'lstab_preview_request', $request, $source_id );

		return rest_ensure_response(
			array(
				'rowCount'  => count( $rows ),
				'colCount'  => count( (array) $source['data']['headers'] ),
				'truncated' => count( $rows ) > count( $preview ),
				'html'      => LSTAB_Renderer::render_preview(
					array(
						'headers' => (array) $source['data']['headers'],
						'rows'    => $preview,
					),
					array(
						'style'       => LSTAB_Styles::sanitize( (string) $request->get_param( 'style' ) ),
						'layout'      => (string) $request->get_param( 'layout' ),
						'sticky'      => (bool) $request->get_param( 'sticky' ),
						'sticky_head' => (bool) $request->get_param( 'stickyHead' ),
						'source_id'   => $source_id,
						'columns'    => LSTAB_Columns::sanitize( (array) $request->get_param( 'columns' ) ),
						'style_vars' => isset( $source['style_vars'] ) ? $source['style_vars'] : array(),
						'custom_css' => isset( $source['custom_css'] ) ? $source['custom_css'] : '',
					)
				),
			)
		);
	}

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

	public function scoped_css( $request ) {
		$selector = trim( (string) $request->get_param( 'selector' ) );

		if ( ! preg_match( '#^\[data-lstab-preview="[a-z0-9-]{1,40}"\]$#', $selector ) ) {
			$selector = '[data-lstab-preview="none"]';
		}

		return rest_ensure_response(
			array(
				'css' => LSTAB_Custom_Css::scope(
					LSTAB_Custom_Css::sanitize( (string) $request->get_param( 'css' ) ),
					$selector . ' .lstab'
				),
			)
		);
	}

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
			$tabs = array();
		}

		$rows    = $table['rows'];
		$preview = array_slice( $rows, 0, 25 );

		do_action( 'lstab_preview_request', $request, absint( $request->get_param( 'sourceId' ) ) );

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
				'raw'       => self::sample( $csv ),
				'rawBytes'  => strlen( $csv ),
				'ragged'    => isset( $table['ragged'] ) ? $table['ragged'] : null,
				'html'      => LSTAB_Renderer::render_preview(
					array(
						'headers' => $table['headers'],
						'rows'    => $preview,
					),
					array(
						'style'       => LSTAB_Styles::sanitize( (string) $request->get_param( 'style' ) ),
						'layout'      => (string) $request->get_param( 'layout' ),
						'sticky'      => (bool) $request->get_param( 'sticky' ),
						'sticky_head' => (bool) $request->get_param( 'stickyHead' ),
						'source_id'   => absint( $request->get_param( 'sourceId' ) ),
						'columns'   => LSTAB_Columns::sanitize( (array) $request->get_param( 'columns' ) ),
					)
				),
			)
		);
	}

	protected static function sample( $csv ) {
		$limit = 4000;

		if ( strlen( $csv ) <= $limit ) {
			return $csv;
		}

		$cut  = substr( $csv, 0, $limit );
		$last = strrpos( $cut, "\n" );

		return ( false === $last ? $cut : substr( $cut, 0, $last ) ) . "\n…";
	}

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
