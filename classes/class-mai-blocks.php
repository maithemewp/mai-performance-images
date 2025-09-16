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
	 * The attributes enabled.
	 *
	 * @since 0.5.0
	 *
	 * @var bool
	 */
	protected $attributes_enabled;

	/**
	 * The conversion enabled.
	 *
	 * @since 0.5.0
	 *
	 * @var bool
	 */
	protected $conversion_enabled;

	/**
	 * Add hooks.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	protected function hooks(): void {
		// Set props.
		$this->attributes_enabled = is_attributes_enabled();
		$this->conversion_enabled = is_conversion_enabled();

		// Bail if nothing is enabled.
		if ( ! $this->attributes_enabled && ! $this->conversion_enabled ) {
			return;
		}

		// Bail if nothing is enabled.
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
		// If attributes are enabled.
		if ( $this->attributes_enabled ) {
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

			// Handle attributes.
			$block_content = $this->handle_attributes( $block_content );
		}

		// If conversion is enabled.
		if ( $this->conversion_enabled ) {
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
		}

		// Return the block content.
		return $block_content;
	}
}