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
final class MaiBlocks {
	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'render_block_acf/mai-post-preview', [ $this, 'render_block_post_preview' ], 99, 2 );
	}

	/**
	 * Lazy loads the post preview block's images.
	 *
	 * The block shows a preview card for a linked post, which is rarely the main
	 * image on the page.
	 *
	 * @since 0.4.0
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block.
	 *
	 * @return string The block content.
	 */
	public function render_block_post_preview( string $block_content, array $block ): string {
		if ( ! is_attributes_enabled() ) {
			return $block_content;
		}

		$tags = new WP_HTML_Tag_Processor( $block_content );

		while ( $tags->next_tag( [ 'tag_name' => 'img' ] ) ) {
			LoadingAttributes::instance()->apply_to_tag( $tags, 'lazy' );
		}

		return $tags->get_updated_html();
	}
}
