<?php

namespace Mai\Performance;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mai Performance Images class.
 *
 * @since 0.1.0
 */
final class BlockSettings {
	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Adds the hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function hooks() {
		// Admin editor assets.
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );

		// Block loading filters.
		add_filter( 'render_block_core/cover',               [ $this, 'render_loading_attribute' ], 10, 2 );
		add_filter( 'render_block_core/image',               [ $this, 'render_loading_attribute' ], 10, 2 );
		add_filter( 'render_block_core/site-logo',           [ $this, 'render_loading_attribute' ], 10, 2 );
		add_filter( 'render_block_core/post-featured-image', [ $this, 'render_loading_attribute' ], 10, 2 );
	}

	/**
	 * Enqueues the block editor assets.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		$asset_file = include( dirname( __DIR__ ) . '/build/block-settings.asset.php' );

		wp_enqueue_script(
			'mai-performance-images-block-settings',
			plugins_url( 'build/block-settings.js', dirname( __FILE__ ) ),
			$asset_file['dependencies'],
			$asset_file['version']
		);
	}

	/**
	 * Render the core/site-logo block.
	 *
	 * @since 0.1.0
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block.
	 *
	 * @return string The block content.
	 */
	public function render_loading_attribute( $block_content, $block ) {
		// Get the img loading attribute.
		$loading = $block['attrs']['imgLoading'] ?? '';

		// Bail if no loading attribute is set.
		if ( ! $loading ) {
			return $block_content;
		}

		// Set up tag processor.
		$tags = new \WP_HTML_Tag_Processor( $block_content );

		// Set up args.
		$args = [
			'tag_name' => 'img',
		];

		// Add class check for cover block.
		// This insures only the background image is handled,
		// not any inner blocks.
		if ( 'core/cover' === $block['blockName'] ) {
			$args['class'] = 'wp-block-cover__background';
		}

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'img' ] ) ) {
			// Add loading attribute.
			$tags->set_attribute( 'data-mai-loading', $loading );
		}

		// Get updated block content.
		$block_content = $tags->get_updated_html();

		return $block_content;
	}
}
