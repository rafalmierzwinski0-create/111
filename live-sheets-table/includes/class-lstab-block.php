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

		/*
		 * A .mo file is invisible to JavaScript, so without this the block's
		 * own panel stays in English on a translated site while everything
		 * around it is not. The catalogue is the JSON tools/make-pot.php
		 * writes beside the .mo.
		 */
		wp_set_script_translations(
			$block->editor_script_handles[0],
			'live-sheets-table',
			LSTAB_PATH . 'languages'
		);

		wp_localize_script(
			$block->editor_script_handles[0],
			'lstabBlock',
			array(
				'manageUrl' => admin_url( 'admin.php?page=' . LSTAB_Admin::MENU_SLUG ),
				'addUrl'    => admin_url( 'admin.php?page=' . LSTAB_Admin::EDIT_SLUG ),
				// The filter field is only worth showing where something is
				// listening for it; without the add-on it would do nothing.
				'isPro'     => LSTAB_Limits::is_pro(),
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
				'layout'      => 'inherit',
				'filter'      => '',
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
				// Inert on its own: the add-on that reads it is what makes it
				// mean anything, exactly as with the shortcode attribute.
				'filter'    => sanitize_text_field( (string) $attributes['filter'] ),
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
