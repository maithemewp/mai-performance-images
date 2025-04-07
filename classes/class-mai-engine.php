<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mai Engine Images.
 *
 * @since 0.1.0
 */
class MaiEngine extends Images {
	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function __construct() {
		/**
		 * Get mai breakpoints.
		 * @disregard P1010
		 */
		$breakpoints = \mai_get_breakpoints();

		// Set custom breakpoints for media queries.
		$this->tablet_breakpoint  = $breakpoints['md'];
		$this->desktop_breakpoint = $breakpoints['lg'];

		// Set Mai v2 default layout sizes.
		$this->content_size = 800;
		$this->wide_size    = 1200;

		// Call parent constructor to initialize logger and hooks
		parent::__construct();
	}

	/**
	 * Add hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function hooks(): void {

		// TODO: Handle logo and scroll logo.


		add_filter( 'genesis_markup_entry-image-link_content', [ $this, 'add_image_id_attribute' ], 10, 3 );
		add_filter( 'render_block_acf/mai-post-grid',          [ $this, 'render_mai_grid_block' ], 99, 2 );
		add_filter( 'render_block_acf/mai-term-grid',          [ $this, 'render_mai_grid_block' ], 99, 2 );
	}

	/**
	 * Add image ID attribute to entry image link.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attr The attributes.
	 *
	 * @return array
	 */
	public function add_image_id_attribute( string $content, array $args ): string {
		// Bail if not showing the image.
		if ( ! ( isset( $args['params']['args']['show'] ) && in_array( 'image', (array) $args['params']['args']['show'] ) ) ) {
			return $content;
		}

		// Bail if no entry.
		if ( ! isset( $args['params']['entry'] ) ) {
			return $content;
		}

		// Get entry and set image ID variable.
		$entry    = $args['params']['entry'];
		$image_id = null;

		// If instanceof WP_Post, get image ID.
		if ( $entry instanceof \WP_Post ) {
			$image_id = get_post_thumbnail_id( $entry->ID );
		}

		// If instanceof WP_Term, get image ID.
		if ( $entry instanceof \WP_Term ) {
			$image_id = get_term_meta( $entry->term_id, 'thumbnail_id', true );
		}

		// Bail if no image ID.
		if ( ! $image_id ) {
			return $content;
		}

		// Set up tag processor.
		$tags = new \WP_HTML_Tag_Processor( $content );

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'img' ] ) ) {
			$tags->set_attribute( 'data-mai-image-id', $image_id );
		}

		// Get updated content.
		$content = $tags->get_updated_html();

		return $content;
	}

	/**
	 * Render the Mai Grid block.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block.
	 *
	 * @return string
	 */
	public function render_mai_grid_block( string $block_content, array $block ): string {
		// Get ACF block data.
		$data = $block['attrs']['data'] ?? [];

		/** @disregard P1010 */
		$columns = array_reverse( \mai_get_breakpoint_columns( $data ) );

		/** @disregard P1010 */
		$ratio = isset( $data['image_orientation'] ) && $data['image_orientation'] ? \mai_get_aspect_ratio_from_orientation( $data['image_orientation'] ) : null;

		$args = [
			'aspect_ratio' => $ratio,
			'max_width'  => (int) ( 2400 / (int) $columns['lg'] ),
			'max_images' => 0, // Process all images in the grid
			'sizes'      => [
				'mobile'  => (int) ( 100 / (int) $columns['sm'] ) . 'vw',
				'tablet'  => (int) ( 100 / (int) $columns['md'] ) . 'vw',
				'desktop' => (int) ( 100 / (int) $columns['lg'] ) . 'vw',
			],
		];

		return $this->handle_image( $block_content, $args );
	}
}
