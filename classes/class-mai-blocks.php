<?php

namespace Mai\PerformanceImages;

use WP_HTML_Tag_Processor;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mai Blocks.
 *
 * @since 0.4.0
 */
class MaiBlocks extends Images {

	/**
	 * Add hooks.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	protected function hooks(): void {
		add_filter( 'render_block_acf/mai-post-preview', [ $this, 'render_block_post_preview' ], 99, 2 );
	}

	/**
	 * Render the post preview block.
	 *
	 * @since 0.4.0
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block.
	 *
	 * @return string The block content.
	 */
	public function render_block_post_preview( string $block_content, array $block ): string {
		/**
		 * Set up tag processor.
		 * @disregard P1008
		 */
		$tags = new WP_HTML_Tag_Processor( $block_content );

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'img' ] ) ) {
			$tags->set_attribute( 'data-mai-loading', 'lazy' );
		}

		// Get updated content.
		$block_content = $tags->get_updated_html();

		// Set args.
		$image_args = [
			'aspect_ratio' => '3/4',
			'max_width'    => 300,
			'sizes'        => [
				'mobile'  => '100vw',
				'tablet'  => '300px',
				'desktop' => '300px',
			],
		];

		/** @disregard P1008 */
		$block_content = $this->handle_image( $block_content, $image_args );
		$block_content = $this->handle_attributes( $block_content );

		// Return the block content.
		return $block_content;
	}
}