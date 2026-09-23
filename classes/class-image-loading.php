<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mai Performance Images Image Loading class.
 *
 * @since 0.1.0
 */
final class ImageLoading {
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
		// Add hooks used for attributes.
		add_action( 'enqueue_block_editor_assets',           [ $this, 'enqueue_block_editor_assets' ] );
		add_filter( 'render_block_core/cover',               [ $this, 'render_loading_attribute' ], 10, 2 );
		add_filter( 'render_block_core/image',               [ $this, 'render_loading_attribute' ], 10, 2 );
		add_filter( 'render_block_core/post-featured-image', [ $this, 'render_loading_attribute' ], 10, 2 );
		add_filter( 'render_block_core/media-text',          [ $this, 'render_loading_attribute' ], 10, 2 );
		add_filter( 'render_block_core/site-logo',           [ $this, 'render_loading_attribute' ], 10, 2 );
	}

	/**
	 * Enqueues the block editor assets.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		if ( ! is_attributes_enabled() ) {
			return;
		}

		$asset_file = include( dirname( __DIR__ ) . '/build/block-settings.asset.php' );

		wp_enqueue_script(
			'mai-performance-images-block-settings',
			plugins_url( 'build/block-settings.js', dirname( __FILE__ ) ),
			$asset_file['dependencies'],
			$asset_file['version']
		);
	}

	/**
	 * Writes an editor's Lazy or Eager choice onto a block's image.
	 *
	 * Inside post content, WordPress then reads the value in its pass over the
	 * content. Outside it, the tag was already answered, so apply_to_tag() also
	 * takes back what a lazy image should not keep.
	 *
	 * @since 0.1.0
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block.
	 *
	 * @return string The block content.
	 */
	public function render_loading_attribute( $block_content, $block ) {
		// Get the img loading attribute. An empty value is the editor's "Default",
		// which hands the decision to LoadingAttributes rather than forcing lazy.
		$loading = $block['attrs']['imgLoading'] ?? '';

		// Bail if the setting is off.
		if ( ! is_attributes_enabled() ) {
			return $block_content;
		}

		// Bail if the editor made no explicit choice.
		if ( ! in_array( $loading, [ 'lazy', 'eager' ], true ) ) {
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
			$args['class_name'] = 'wp-block-cover__image-background';
		}

		// Loop through tags.
		while ( $tags->next_tag( $args ) ) {
			LoadingAttributes::instance()->apply_to_tag( $tags, $loading );
		}

		// Get updated block content.
		$block_content = $tags->get_updated_html();

		return $block_content;
	}

}
