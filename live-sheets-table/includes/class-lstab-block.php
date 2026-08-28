<?php
/**
 * Gutenberg block registration.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dynamic block.
 */
class LSTAB_Block {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the block type from its block.json.
	 *
	 * @return void
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$block = register_block_type(
			LSTAB_PATH . 'blocks/sheet-table',
			array( 'render_callback' => array( $this, 'render' ) )
		);

		if ( ! $block || empty( $block->editor_script_handles ) ) {
			return;
		}

		wp_localize_script(
			$block->editor_script_handles[0],
			'lstabBlock',
			array(
				'manageUrl' => admin_url( 'admin.php?page=' . LSTAB_Admin::MENU_SLUG ),
				'addUrl'    => admin_url( 'admin.php?page=' . LSTAB_Admin::EDIT_SLUG ),
			)
		);
	}

	/**
	 * Render callback — the same renderer the shortcode uses.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$attributes = wp_parse_args(
			(array) $attributes,
			array(
				'sourceId'    => 0,
				'showSearch'  => true,
				'showSort'    => true,
				'showUpdated' => true,
				'stylePreset' => '',
				'caption'     => '',
				'layout'      => 'auto',
			)
		);

		$html = LSTAB_Renderer::render(
			array(
				'source_id' => absint( $attributes['sourceId'] ),
				'search'    => ! empty( $attributes['showSearch'] ),
				'sort'      => ! empty( $attributes['showSort'] ),
				'show_meta' => ! empty( $attributes['showUpdated'] ),
				'style'     => sanitize_key( (string) $attributes['stylePreset'] ),
				'caption'   => sanitize_text_field( (string) $attributes['caption'] ),
				'layout'    => sanitize_key( (string) $attributes['layout'] ),
			)
		);

		if ( '' === $html ) {
			return '';
		}

		return '<div ' . $this->wrapper_attributes() . '>' . $html . '</div>';
	}

	/**
	 * Block wrapper attributes, safe to call outside the block render pipeline.
	 *
	 * get_block_wrapper_attributes() reads the block currently being rendered,
	 * which is unset when the callback is invoked directly (tests, or another
	 * plugin calling render_block_core_* style helpers).
	 *
	 * @return string
	 */
	protected function wrapper_attributes() {
		$in_render_pipeline = class_exists( 'WP_Block_Supports' )
			&& isset( WP_Block_Supports::$block_to_render['blockName'] );

		if ( $in_render_pipeline && function_exists( 'get_block_wrapper_attributes' ) ) {
			return get_block_wrapper_attributes( array( 'class' => 'lstab-block' ) );
		}

		return 'class="wp-block-live-sheets-table-sheet-table lstab-block"';
	}
}
