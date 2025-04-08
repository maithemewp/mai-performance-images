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
	 * Add hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function hooks(): void {
		/**
		 * Get mai breakpoints.
		 * @disregard P1010
		 */
		$breakpoints = \mai_get_breakpoints();

		// Set props.
		$this->tablet_breakpoint  = $breakpoints['md'];
		$this->desktop_breakpoint = $breakpoints['lg'];
		$this->content_size       = 800;
		$this->wide_size          = 1200;

		// TODO: Handle logo and scroll logo.

		// Add hooks.
		add_action( 'genesis_site_title',                      [ $this, 'before_logo' ], 0 );
		add_filter( 'genesis_markup_entry-image-link_content', [ $this, 'render_entry_image' ], 10, 3 );
		add_filter( 'render_block_acf/mai-post-grid',          [ $this, 'render_mai_grid_block' ], 99, 2 );
		add_filter( 'render_block_acf/mai-term-grid',          [ $this, 'render_mai_grid_block' ], 99, 2 );
	}

	/**
	 * Adds filter on custom logo before site title.
	 * Removes filter after the logo is added.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function before_logo() {
		add_filter( 'get_custom_logo', [ $this, 'custom_logo' ], 15, 1 );

		add_action( 'genesis_site_title', function() {
			remove_filter( 'get_custom_logo', [ $this, 'custom_logo' ], 15, 1 );
		}, 99 );
	}

	/**
	 * Adds image ID attribute to custom logo.
	 *
	 * @since 0.1.0
	 *
	 * @param string $html    The existing logo HTML.
	 *
	 * @return string
	 */
	public function custom_logo( string $html ): string {
		/**
		 * Set up tag processor.
		 * @disregard P1008
		 */
		$tags  = new \WP_HTML_Tag_Processor( $html );
		$sizes = [];

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'img', 'class_name' => 'custom-logo' ] ) ) {
			/** @disregard P1010 */
			$tags->set_attribute( 'data-mai-image-id', \mai_get_logo_id() );

			// Get logo sizes.
			$sizes = $tags->get_attribute( 'sizes' );
		}

		// Get updated content.
		$html = $tags->get_updated_html();

		/**
		 * Set up tag processor.
		 * @disregard P1008
		 */
		$tags = new \WP_HTML_Tag_Processor( $html );

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'img', 'class_name' => 'custom-scroll-logo' ] ) ) {
			/** @disregard P1010 */
			$tags->set_attribute( 'data-mai-image-id', \mai_get_scroll_logo_id() );
			$tags->set_attribute( 'data-mai-loading', 'eager' );
		}

		// Get updated content.
		$html = $tags->get_updated_html();

		/**
		 * Get logo width.
		 * @disregard P1010
		 */
		$widths = \mai_get_option( 'logo-width', [] );
		$widths = array_map( 'absint', $widths );
		$width  = isset( $widths['desktop'] ) ? $widths['desktop'] : 0;
		$width  = max( $width, 1 );
		$sizes  = $sizes ?? $width . 'px';

		$args = [
			'max_images' => 0,
			'max_width'  => $width * 2,
			'sizes'      => [
				'mobile'  => $sizes,
				'tablet'  => $sizes,
				'desktop' => $sizes,
			],
		];

		return $this->handle_image( $html, $args );
	}

	/**
	 * Add image ID attribute to entry image link.
	 *
	 * @since 0.1.0
	 *
	 * @param array $attr The attributes.
	 *
	 * @return array
	 */
	public function render_entry_image( string $content, array $args ): string {
		// Bail if not showing the image.
		if ( ! ( isset( $args['params']['args']['show'] ) && in_array( 'image', (array) $args['params']['args']['show'] ) ) ) {
			/** @disregard P1008 */
			return $content;
		}

		// Bail if no entry.
		if ( ! isset( $args['params']['entry'] ) ) {
			/** @disregard P1008 */
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
			/** @disregard P1008 */
			return $content;
		}

		/**
		 * Set up tag processor.
		 * @disregard P1008
		 */
		$tags = new \WP_HTML_Tag_Processor( $content );

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'img' ] ) ) {
			$tags->set_attribute( 'data-mai-image-id', $image_id );
		}

		// Get updated content.
		$content = $tags->get_updated_html();

		// Return the content based on the context.
		switch ( $args['params']['args']['context'] ) {
			case 'single':
				$content = $this->render_single_entry_image( $content, $args );
				break;
			case 'archive':
				static $count = 0;
				$content = $this->render_archive_entry_image( $content, $args );

				/**
				 * Set up tag processor.
				 * @disregard P1008
				 */
				$tags = new \WP_HTML_Tag_Processor( $content );

				// Loop through tags.
				while ( $tags->next_tag( [ 'tag_name' => 'img', 'class_name' => 'entry-image' ] ) ) {
					$count++;

					// Skip if 3 or under.
					if ( $count <= 3 ) {
						continue;
					}

					// Set loading to lazy.
					$tags->set_attribute( 'loading', 'lazy' );
				}

				// Get updated content.
				$content = $tags->get_updated_html();
				break;
			default:
				break;
		}

		// Return the content with the final attributes.
		return $this->handle_attributes( $content );
	}

	/**
	 * Render the single entry image.
	 *
	 * @since 0.1.0
	 *
	 * @param string $content The content.
	 * @param array  $args    The args.
	 * @return string
	 */
	public function render_single_entry_image( string $content, array $args ): string {
		/**
		 * Get template args.
		 * @disregard P1010
		 */
		$data = \mai_get_template_args();

		// Bail if no data.
		if ( ! $data ) {
			/** @disregard P1008 */
			return $content;
		}

		/** @disregard P1010 */
		$ratio = isset( $data['image_orientation'] ) && $data['image_orientation'] ? \mai_get_aspect_ratio_from_orientation( $data['image_orientation'] ) : null;

		// Set args.
		$args = [
			'aspect_ratio' => $ratio,
			'max_width'  => 1600,
			'sizes'      => [
				'mobile'  => '90vw',
				'tablet'  => '80vw',
				'desktop' => '70vw',
			],
		];

		/** @disregard P1008 */
		return $this->handle_image( $content, $args );
	}

	/**
	 * Render the archive entry image.
	 *
	 * @since 0.1.0
	 *
	 * @param string $content The content.
	 * @param array  $args    The args.
	 *
	 * @return string
	 */
	public function render_archive_entry_image( string $content, array $args ): string {
		/**
		 * Get template args.
		 * @disregard P1010
		 */
		$data = \mai_get_template_args();

		// Bail if no data.
		if ( ! $data ) {
			/** @disregard P1008 */
			return $content;
		}

		/** @disregard P1010 */
		$columns  = array_reverse( \mai_get_breakpoint_columns( $data ) );
		$position = $data['image_position'] ?? null;
		$side     = $position && ( str_contains( $position, 'left' ) || str_contains( $position, 'right' ) ) ? 2 : 1;

		/** @disregard P1010 */
		$ratio = isset( $data['image_orientation'] ) && $data['image_orientation'] ? \mai_get_aspect_ratio_from_orientation( $data['image_orientation'] ) : null;

		// Set args.
		$args = [
			'aspect_ratio' => $ratio,
			'max_width'  => (int) ( 2400 / (int) $columns['lg'] / $side ),
			'max_images' => 0, // Process all images in the archive.
			'sizes'      => [
				'mobile'  => (int) ( 100 / (int) $columns['sm'] ) . 'vw',
				'tablet'  => (int) ( 100 / (int) $columns['md'] / $side ) . 'vw',
				'desktop' => (int) ( 100 / (int) $columns['lg'] / $side ) . 'vw',
			],
		];

		/** @disregard P1008 */
		return $this->handle_image( $content, $args );
	}

	/**
	 * Render the Mai Grid block.
	 *
	 * @since 0.1.0
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
		$columns  = array_reverse( \mai_get_breakpoint_columns( $data ) );
		$position = $data['image_position'] ?? null;
		$side     = $position && ( str_contains( $position, 'left' ) || str_contains( $position, 'right' ) ) ? 2 : 1;

		/** @disregard P1010 */
		$ratio = isset( $data['image_orientation'] ) && $data['image_orientation'] ? \mai_get_aspect_ratio_from_orientation( $data['image_orientation'] ) : null;

		// Set args.
		$args = [
			'aspect_ratio' => $ratio,
			'max_width'  => (int) ( 2400 / (int) $columns['lg'] / $side ),
			'max_images' => 0, // Process all images in the grid
			'sizes'      => [
				'mobile'  => (int) ( 100 / (int) $columns['sm'] ) . 'vw',
				'tablet'  => (int) ( 100 / (int) $columns['md'] / $side ) . 'vw',
				'desktop' => (int) ( 100 / (int) $columns['lg'] / $side ) . 'vw',
			],
		];

		/** @disregard P1008 */
		return $this->handle_image( $block_content, $args );
	}
}
