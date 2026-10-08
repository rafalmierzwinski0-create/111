<?php

defined( 'ABSPATH' ) || exit;

class LSTABP_Column_Looks {
	const OPTION = 'lstabp_column_looks';

	const MAX_LABEL = 40;

	const DEFAULT_TINT = '#c7e0f4';

	protected static $previewing = array();

	protected $bars = array();

	protected $columns = array();

	protected $settings = array();

	public function register() {
		add_filter( 'lstab_source_rows', array( $this, 'capture' ), 25, 4 );
		add_filter( 'lstab_cell_attributes', array( $this, 'attributes' ), 8, 5 );
		add_filter( 'lstab_heading_attributes', array( $this, 'heading_attributes' ), 8, 4 );

		add_filter( 'lstab_render_cell', array( $this, 'render_cell' ), 8, 5 );

		add_action( 'lstab_preview_request', array( $this, 'preview_request' ), 10, 2 );

		add_action( 'lstab_edit_pane_cards', array( $this, 'render_pane_card' ), 20, 3 );
		add_action( 'lstab_source_saved', array( $this, 'save' ) );
		add_action( 'lstab_source_deleted', array( $this, 'forget' ) );
		add_action( 'lstabp_forget_source', array( $this, 'forget' ) );
	}

	public static function all() {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	public static function for_source( $source_id ) {
		$key = (int) $source_id;

		if ( isset( self::$previewing[ $key ] ) ) {
			return self::$previewing[ $key ];
		}

		$all = self::all();

		return isset( $all[ $key ] ) ? self::sanitize( (array) $all[ $key ] ) : array();
	}

	public function preview_request( $request, $source_id ) {
		$looks = $request->get_param( 'looks' );

		if ( ! is_array( $looks ) ) {
			return;
		}

		self::$previewing[ (int) $source_id ] = self::sanitize( $looks );
	}

	public static function sanitize( $raw ) {
		$looks = self::looks();
		$clean = array();

		foreach ( (array) $raw as $heading => $setting ) {
			$heading = sanitize_text_field( (string) $heading );
			$setting = (array) $setting;
			$look    = isset( $setting['look'] ) ? (string) $setting['look'] : '';

			if ( '' === $heading || ! isset( $looks[ $look ] ) ) {
				continue;
			}

			$clean[ $heading ] = array(
				'look'  => $look,
				'tint'  => LSTABP_Rules::hex( isset( $setting['tint'] ) ? $setting['tint'] : '' ),
				'ink'   => LSTABP_Rules::hex( isset( $setting['ink'] ) ? $setting['ink'] : '' ),
				'label' => mb_substr(
					sanitize_text_field( isset( $setting['label'] ) ? (string) $setting['label'] : '' ),
					0,
					self::MAX_LABEL
				),
			);
		}

		return $clean;
	}

	public static function looks() {
		return array(
			'bar'    => __( 'A bar behind the number', 'live-sheets-table-pro' ),
			'pill'   => __( 'Every value as a pill', 'live-sheets-table-pro' ),
			'tint'   => __( 'The whole column in a colour', 'live-sheets-table-pro' ),
			'button' => __( 'A button, if the cell holds a link', 'live-sheets-table-pro' ),
		);
	}

	public static function fields() {
		return array(
			'bar'    => array( 'tint' ),
			'pill'   => array( 'tint' ),
			'tint'   => array( 'tint' ),
			'button' => array( 'tint', 'ink', 'label' ),
		);
	}

	public static function css_for( $look, $setting ) {
		$tint = isset( $setting['tint'] ) ? LSTABP_Rules::hex( $setting['tint'] ) : '';
		$ink  = isset( $setting['ink'] ) ? LSTABP_Rules::hex( $setting['ink'] ) : '';

		if ( 'bar' === $look ) {
			return '' !== $tint ? '--lstabp-bar-colour:' . $tint . ';' : '';
		}

		if ( 'pill' === $look ) {
			return LSTABP_Rules::css_for( '' !== $tint ? $tint : self::DEFAULT_TINT, 'pill' );
		}

		if ( 'tint' === $look ) {
			return LSTABP_Rules::css_for( '' !== $tint ? $tint : self::DEFAULT_TINT, 'row' );
		}

		if ( 'button' === $look ) {
			$css = '' !== $tint ? '--lstabp-cta-bg:' . $tint . ';' : '';

			return $css . ( '' !== $ink ? '--lstabp-cta-ink:' . $ink . ';' : '' );
		}

		return '';
	}

	public function capture( $rows, $headers, $source, $args ) {
		$this->bars     = array();
		$this->columns  = array();
		$this->settings = array();

		$chosen = self::for_source( isset( $source['id'] ) ? $source['id'] : 0 );

		if ( ! $chosen ) {
			return $rows;
		}

		$headers  = array_values( (array) $headers );
		$map      = LSTABP_Filters::column_map( $headers, $source );
		$rendered = LSTABP_Rules::rendered_positions( $headers, $source, $args );
		$rows     = array_values( (array) $rows );

		foreach ( $chosen as $heading => $setting ) {
			$key = LSTABP_Rules::key( (string) $heading );

			if ( ! isset( $map[ $key ] ) ) {
				continue;
			}

			$position = $map[ $key ];

			if ( ! isset( $rendered[ $position ] ) ) {
				continue;
			}

			$at                    = $rendered[ $position ];
			$this->columns[ $at ]  = $setting['look'];
			$this->settings[ $at ] = $setting;

			if ( 'bar' !== $setting['look'] ) {
				continue;
			}

			$this->measure( $rows, $position, $at );
		}

		return $rows;
	}

	protected function measure( $rows, $position, $at ) {
		$numbers = array();

		foreach ( $rows as $row_index => $row ) {
			$cell = isset( $row[ $position ] ) ? (string) $row[ $position ] : '';

			if ( '' === trim( $cell ) || ! LSTAB_Renderer::looks_numeric( $cell ) ) {
				continue;
			}

			$numbers[ $row_index ] = LSTAB_Renderer::to_number( $cell );
		}

		if ( ! $numbers ) {
			return;
		}

		$smallest = min( $numbers );
		$largest  = max( $numbers );

		if ( $smallest === $largest ) {
			return;
		}

		$floor = $smallest > 0 ? 0.0 : $smallest;
		$span  = $largest - $floor;

		if ( $span <= 0 ) {
			return;
		}

		foreach ( $numbers as $row_index => $number ) {
			$this->bars[ $row_index ][ $at ] = ( $number - $floor ) / $span;
		}
	}

	public function attributes( $attributes, $value, $col_index, $row_index, $source ) {
		if ( ! isset( $this->columns[ $col_index ] ) ) {
			return $attributes;
		}

		$look    = $this->columns[ $col_index ];
		$setting = $this->settings[ $col_index ];
		$css     = self::css_for( $look, $setting );

		if ( 'bar' === $look ) {
			if ( ! isset( $this->bars[ $row_index ][ $col_index ] ) ) {
				return $attributes;
			}

			$share = 2 + $this->bars[ $row_index ][ $col_index ] * 98;
			$css   = '--lstabp-bar:' . number_format( $share, 2, '.', '' ) . '%;' . $css;
		}

		if ( 'pill' === $look && '' === trim( (string) $value ) ) {
			return $attributes;
		}

		if ( '' === $css ) {
			return $attributes;
		}

		$classes = isset( $attributes['class'] ) ? $attributes['class'] . ' ' : '';

		$attributes['class'] = trim( $classes . ( 'tint' === $look ? 'lstab-ruled' : 'lstabp-' . $look ) );
		$attributes['style'] = isset( $attributes['style'] ) ? $attributes['style'] . $css : $css;

		return $attributes;
	}

	public function heading_attributes( $attributes, $heading, $col_index, $source ) {
		if ( ! isset( $this->columns[ $col_index ] ) || 'tint' !== $this->columns[ $col_index ] ) {
			return $attributes;
		}

		$tint = LSTABP_Rules::hex( $this->settings[ $col_index ]['tint'] );
		$tint = '' !== $tint ? $tint : self::DEFAULT_TINT;
		$ink  = LSTABP_Rules::ink( $tint );

		$css = 'background-color:' . $tint . ';color:' . $ink . ';'
			. '--lstab-head-bg:' . $tint . ';'
			. '--lstab-head-fg:' . $ink . ';'
			. '--lstab-fg:' . $ink . ';';

		$attributes['style'] = isset( $attributes['style'] ) ? $attributes['style'] . $css : $css;

		return $attributes;
	}

	public function render_cell( $custom, $value, $col_index, $row_index, $source ) {
		if ( ! isset( $this->columns[ $col_index ] ) || 'button' !== $this->columns[ $col_index ] ) {
			return $custom;
		}

		$address = trim( (string) $value );

		if ( ! preg_match( '#^https?://#i', $address ) ) {
			return $custom;
		}

		$safe = esc_url( $address );

		if ( '' === $safe ) {
			return $custom;
		}

		$label = $this->settings[ $col_index ]['label'];

		if ( '' === $label ) {
			$label = __( 'Open', 'live-sheets-table-pro' );
		}

		return '<a class="lstabp-cta-link" href="' . $safe . '" rel="nofollow noopener">' . esc_html( $label ) . '</a>';
	}

	public function render_pane_card( $pane, $source, $is_edit ) {
		if ( 'look' !== $pane ) {
			return;
		}

		$lstabp_chosen  = ( $is_edit && $source ) ? self::for_source( $source['id'] ) : array();
		$lstabp_looks   = self::looks();
		$lstabp_headers = ( $is_edit && $source && ! empty( $source['data']['headers'] ) )
			? array_values( (array) $source['data']['headers'] )
			: array();

		require LSTABP_PATH . 'includes/views/column-looks-card.php';
	}

	public function save( $source_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['_lstabp_looks_present'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Sanitised in sanitize().
		$raw   = isset( $_POST['lstabp_looks'] ) ? (array) wp_unslash( $_POST['lstabp_looks'] ) : array();
		$clean = self::sanitize( $raw );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['lstabp_looks_reset'] ) ) {
			$clean = array();
		}

		$all                     = self::all();
		$all[ (int) $source_id ] = $clean;

		if ( ! $clean ) {
			unset( $all[ (int) $source_id ] );
		}

		update_option( self::OPTION, $all, false );
	}

	public function forget( $source_id ) {
		$all = self::all();

		if ( ! isset( $all[ (int) $source_id ] ) ) {
			return;
		}

		unset( $all[ (int) $source_id ] );
		update_option( self::OPTION, $all, false );
	}
}
