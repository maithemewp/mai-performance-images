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
		add_action( 'enqueue_block_editor_assets',           [ $this, 'enqueue_block_editor_assets' ] );
		add_filter( 'render_block_core/cover',               [ $this, 'render_loading_attribute' ], 10, 2 );
		add_filter( 'render_block_core/image',               [ $this, 'render_loading_attribute' ], 10, 2 );
		add_filter( 'render_block_core/post-featured-image', [ $this, 'render_loading_attribute' ], 10, 2 );
		add_filter( 'render_block_core/media-text',          [ $this, 'render_loading_attribute' ], 10, 2 );
		add_filter( 'render_block_core/site-logo',           [ $this, 'render_loading_attribute' ], 10, 2 );
		add_filter( 'get_avatar',                            [ $this, 'render_avatar_loading_attribute' ], 10, 2 );
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
		$default = 'lazy';
		$loading = $block['attrs']['imgLoading'] ?? $default;

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
		while ( $tags->next_tag( $args ) ) {
			// Add loading attribute.
			$tags->set_attribute( 'data-mai-loading', $loading );
		}

		// Get updated block content.
		$block_content = $tags->get_updated_html();

		return $block_content;
	}

	/**
	 * Render the avatar loading attribute.
	 *
	 * @since 0.4.0
	 *
	 * @param string $avatar The avatar HTML.
	 * @param mixed  $id_or_email The user ID or email address.
	 *
	 * @return string The avatar HTML.
	 */
	public function render_avatar_loading_attribute( $avatar, $id_or_email ) {
		// Set up tag processor.
		$tags = new \WP_HTML_Tag_Processor( $avatar );

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'img' ] ) ) {
			$loading = $tags->get_attribute( 'loading' );
			$tags->set_attribute( 'data-mai-loading', $loading ?: 'lazy' );
		}

		// Get updated HTML.
		$avatar = $tags->get_updated_html();

		return $avatar;
	}
}
