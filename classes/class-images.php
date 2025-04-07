<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mai Performance Images class.
 *
 * @since 0.1.0
 */
class Images extends AbstractImages {
	/**
	 * Add hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function hooks(): void {
		// Block image filters.
		add_filter( 'render_block_core/cover',               [ $this, 'render_image_block' ], 99, 2 );
		add_filter( 'render_block_core/image',               [ $this, 'render_image_block' ], 99, 2 );
		add_filter( 'render_block_core/post-featured-image', [ $this, 'render_image_block' ], 99, 2 );
		add_filter( 'render_block_core/media-text',          [ $this, 'render_media_text_block' ], 99, 2 );
		add_filter( 'render_block_core/site-logo',           [ $this, 'render_site_logo_block' ], 99, 2 );
	}

	/**
	 * Filters the content of an image block.
	 *
	 * @since 0.1.0
	 *
	 * @param string $block_content The block content about to be appended.
	 * @param array  $block         The full block, including name and attributes.
	 *
	 * @return string
	 */
	public function render_image_block( string $block_content, array $block ): string {
		// Bail if no content.
		if ( ! $block_content ) {
			return $block_content;
		}

		// Get alignment args.
		$args = $this->get_alignment_args( $block['attrs']['align'] ?? '' );

		// Add image ID.
		$args['image_id'] = $block['attrs']['id'] ?? null;

		// Process the image.
		return $this->process_image_tag( $block_content, $args );
	}

	/**
	 * Filters the content of a media-text block.
	 *
	 * @since 0.1.0
	 *
	 * @param string $block_content The block content about to be appended.
	 * @param array  $block         The full block, including name and attributes.
	 *
	 * @return string
	 */
	public function render_media_text_block( string $block_content, array $block ): string {
		// Bail if no content.
		if ( ! $block_content ) {
			return $block_content;
		}

		// Get alignment args.
		$args = $this->get_alignment_args( $block['attrs']['align'] ?? '' );

		// Add image ID.
		$args['image_id'] = $block['attrs']['mediaId'] ?? null;

		// Process the image.
		return $this->process_image_tag( $block_content, $args );
	}

	/**
	 * Filters the content of a site logo block.
	 *
	 * @since 0.1.0
	 *
	 * @param string $block_content The block content about to be appended.
	 * @param array  $block         The full block, including name and attributes.
	 *
	 * @return string
	 */
	public function render_site_logo_block( string $block_content, array $block ): string {
		// Bail if no content.
		if ( ! $block_content ) {
			return $block_content;
		}

		// Get alignment args.
		$args = $this->get_alignment_args( $block['attrs']['align'] ?? '' );

		// Add image ID.
		$args['image_id'] = get_theme_mod( 'custom_logo' );

		// Process the image.
		return $this->process_image_tag( $block_content, $args );
	}

	/**
	 * Filters the content of a post featured image block.
	 *
	 * @since 0.1.0
	 *
	 * @param string $block_content The block content about to be appended.
	 * @param array  $block         The full block, including name and attributes.
	 *
	 * @return string
	 */
	public function render_post_featured_image_block( string $block_content = '', array $block = [] ): string {
		// Bail if no content.
		if ( ! $block_content ) {
			return $block_content;
		}

		// Get alignment args.
		$args = $this->get_alignment_args( $block['attrs']['align'] ?? '' );

		// Add image ID.
		$args['image_id'] = get_post_thumbnail_id();

		// Process the image.
		return $this->process_image_tag( $block_content, $args );
	}
}