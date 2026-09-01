<?php
/**
 * The Elementor widget itself.
 *
 * Loaded only once Elementor has, because it extends a class Elementor owns.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Google Sheets table widget.
 */
class LSTAB_Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'lstab-sheet-table';
	}

	/**
	 * Name shown in the panel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Google Sheets Table', 'live-sheets-table' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-table';
	}

	/**
	 * Where it sits in the panel.
	 *
	 * @return array<int,string>
	 */
	public function get_categories() {
		return array( 'live-sheets-table' );
	}

	/**
	 * Words that find it in the panel's search.
	 *
	 * @return array<int,string>
	 */
	public function get_keywords() {
		return array( 'google', 'sheets', 'table', 'spreadsheet', 'csv' );
	}

	/**
	 * The stylesheet the rendered table needs.
	 *
	 * @return array<int,string>
	 */
	public function get_style_depends() {
		return array( 'lstab-table' );
	}

	/**
	 * The script the slider and search need.
	 *
	 * @return array<int,string>
	 */
	public function get_script_depends() {
		return array( 'lstab-table' );
	}

	/**
	 * The panel.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'lstab_source_section',
			array( 'label' => __( 'Sheet', 'live-sheets-table' ) )
		);

		$this->add_control(
			'source_id',
			array(
				'label'   => __( 'Saved source', 'live-sheets-table' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => LSTAB_Elementor::source_options(),
				'default' => 0,
			)
		);

		$this->add_control(
			'source_hint',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					/* translators: %s: link to the plugin's own screen. */
					__( 'Sheets are added and refreshed in %s.', 'live-sheets-table' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=' . LSTAB_Admin::MENU_SLUG ) ) . '" target="_blank">' . esc_html__( 'Sheets Tables', 'live-sheets-table' ) . '</a>'
				),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'lstab_display_section',
			array( 'label' => __( 'Display', 'live-sheets-table' ) )
		);

		$this->add_control(
			'show_search',
			array(
				'label'   => __( 'Search box', 'live-sheets-table' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_sort',
			array(
				'label'   => __( 'Sortable columns', 'live-sheets-table' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_updated',
			array(
				'label'   => __( 'Show “updated … ago”', 'live-sheets-table' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'live-sheets-table' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'inherit',
				'options' => array(
					'inherit' => __( 'Use the source default', 'live-sheets-table' ),
					'table'   => __( 'Table with a slider', 'live-sheets-table' ),
					'auto'    => __( 'Stack into cards when narrow', 'live-sheets-table' ),
					'cards'   => __( 'Always cards', 'live-sheets-table' ),
				),
			)
		);

		$this->add_control(
			'style_preset',
			array(
				'label'   => __( 'Style preset', 'live-sheets-table' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => self::preset_options(),
			)
		);

		$this->add_control(
			'caption',
			array(
				'label' => __( 'Caption', 'live-sheets-table' ),
				'type'  => \Elementor\Controls_Manager::TEXT,
			)
		);

		$this->end_controls_section();

		// Only where an add-on is listening for it; a field that accepts text
		// and changes nothing is worse than no field.
		if ( LSTAB_Limits::is_pro() ) {
			$this->start_controls_section(
				'lstab_rows_section',
				array( 'label' => __( 'Which rows', 'live-sheets-table' ) )
			);

			$this->add_control(
				'filter',
				array(
					'label'       => __( 'Filter', 'live-sheets-table' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'description' => __( 'Show only matching rows, for example: Kategoria is Rowery. Join conditions with “and”. Operators: is, is not, has, gt, gte, lt, lte.', 'live-sheets-table' ),
				)
			);

			$this->end_controls_section();
		}
	}

	/**
	 * Style presets, with the locked ones left out.
	 *
	 * @return array<string,string>
	 */
	protected static function preset_options() {
		$options = array( '' => __( 'Use the source default', 'live-sheets-table' ) );

		foreach ( LSTAB_Styles::available() as $key => $preset ) {
			$options[ $key ] = isset( $preset['label'] ) ? $preset['label'] : $key;
		}

		return $options;
	}

	/**
	 * Draw the table, in the editor and on the page alike.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		echo LSTAB_Renderer::render( // phpcs:ignore WordPress.Security.EscapeOutput -- The renderer escapes its own output.
			array(
				'source_id' => absint( isset( $settings['source_id'] ) ? $settings['source_id'] : 0 ),
				'search'    => 'yes' === ( isset( $settings['show_search'] ) ? $settings['show_search'] : 'yes' ),
				'sort'      => 'yes' === ( isset( $settings['show_sort'] ) ? $settings['show_sort'] : 'yes' ),
				'show_meta' => 'yes' === ( isset( $settings['show_updated'] ) ? $settings['show_updated'] : 'yes' ),
				'style'     => sanitize_key( isset( $settings['style_preset'] ) ? $settings['style_preset'] : '' ),
				'layout'    => sanitize_key( isset( $settings['layout'] ) ? $settings['layout'] : 'inherit' ),
				'caption'   => sanitize_text_field( isset( $settings['caption'] ) ? $settings['caption'] : '' ),
				'filter'    => sanitize_text_field( isset( $settings['filter'] ) ? $settings['filter'] : '' ),
			)
		);
	}
}
