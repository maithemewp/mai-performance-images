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
	 * The grid entry index.
	 *
	 * @since 0.1.0
	 *
	 * @var int
	 */
	protected $grid_entry_index = 0;

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

		// Add hooks.
		add_action( 'genesis_site_title',                      [ $this, 'before_logo' ], 0 );
		add_filter( 'mai_page_header_img',                     [ $this, 'render_page_header_image' ], 999, 3 );
		add_filter( 'genesis_markup_entry-image-link_content', [ $this, 'render_entry_image' ], 10, 3 );
		add_filter( 'render_block_acf/mai-post-grid',          [ $this, 'render_mai_grid_block' ], 99, 2 );
		add_filter( 'render_block_acf/mai-term-grid',          [ $this, 'render_mai_grid_block' ], 99, 2 );
		add_action( 'acf/init',                                [ $this, 'register_grid_block_field_group' ] );
		add_filter( 'mai_grid_args',                           [ $this, 'add_grid_args' ] );
		add_filter( 'genesis_markup_entry-image-link_content', [ $this, 'add_attributes' ], 10, 2 );
		add_action( 'mai_after_entry',                         [ $this, 'increment_index' ], 10, 2 );
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
	 * Filters the page header image.
	 *
	 * @since 0.1.0
	 *
	 * @param string $image The existing image.
	 * @param int    $image_id The image ID.
	 * @param string $image_size The image size.
	 *
	 * @return string
	 */
	public function render_page_header_image( string $image, int $image_id, string $image_size ): string {
		/**
		 * Set up tag processor.
		 * @disregard P1008
		 */
		$tags = new \WP_HTML_Tag_Processor( $image );

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'img', 'class_name' => 'custom-scroll-logo' ] ) ) {
			/** @disregard P1010 */
			// $tags->set_attribute( 'data-mai-image-id', $image_id );

			// Check for loading attribute.
			$loading = $tags->get_attribute( 'loading' );

			// If loading is eager, set to lazy.
			if ( ! $loading ) {
				$tags->set_attribute( 'loading', 'eager' );
				$tags->set_attribute( 'fetchpriority', 'high' );
			}
		}

		// Get updated content.
		$image = $tags->get_updated_html();

		// Set args.
		$args = [
			'image_id'  => $image_id,
			'max_width' => 2400,
			'sizes'     => [
				'mobile'  => '100vw',
				'tablet'  => '100vw',
				'desktop' => '100vw',
			],
		];

		// Add the attributes.
		$image = $this->handle_image( $image, $args );
		$image = $this->handle_attributes( $image );

		return $image;
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
		// Reset the grid entry index.
		$this->grid_entry_index = 1;

		// Get ACF block data.
		$data = $block['attrs']['data'] ?? [];

		/** @disregard P1010 */
		$columns     = array_reverse( \mai_get_breakpoint_columns( $data ) );
		$position    = $data['image_position'] ?? null;
		$side        = $position && ( str_contains( $position, 'left' ) || str_contains( $position, 'right' ) ) ? 2 : 1;
		$orientation = $data['image_orientation'] ?? null;
		$image_size  = $data['image_size'] ?? null;
		$image_width = 'custom' === $orientation ? $this->get_image_size( $image_size ) : null;
		$max_width   = (int) ( 2400 / (int) $columns['lg'] / $side );

		// If we have an image width, compare it to the max width by columns.
		if ( $image_width ) {
			$max_width = min( $max_width, $image_width * 2 );
		}

		// Get ratio.
		/** @disregard P1010 */
		$ratio = $orientation ? \mai_get_aspect_ratio_from_orientation( $orientation ) : null;

		// Set args.
		$args = [
			'aspect_ratio' => $ratio,
			'max_width'    => $max_width,
			'max_images'   => 0, // Process all images in the grid
			'sizes'        => [
				'mobile'  => (int) ( 100 / (int) $columns['sm'] ) . 'vw',
				'tablet'  => (int) ( 100 / (int) $columns['md'] / $side ) . 'vw',
				'desktop' => (int) ( 100 / (int) $columns['lg'] / $side ) . 'vw',
			],
		];

		/** @disregard P1008 */
		return $this->handle_image( $block_content, $args );
	}

	/**
	 * Register grid block field group.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_grid_block_field_group(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		/** @disregard P1010 */
		\acf_add_local_field_group(
			[
				'key'   => 'mai_performance_images_grid_block_field_group',
				'title' => esc_html__( 'Mai Performance Images', 'mai-engine' ),
				'fields' => [
					[
						'label'        => __( 'Mai Performance Images', 'mai-engine' ),
						'key'          => 'field_63f9a2b4c8d1e',
						'type'         => 'accordion',
						'open'         => 0,
						'multi_expand' => 1,
						'endpoint'     => 0,
					],
					[
						'key'     => 'field_63f9a2b4c8d2e',
						'name'    => 'image_loading',
						'label'   => esc_html__( 'Image Loading', 'mai-engine' ),
						'type'    => 'select',
						'choices' => [
							''      => esc_html__( 'Default', 'mai-engine' ),
							'lazy'  => esc_html__( 'Lazy (for offscreen images)', 'mai-engine' ),
							'eager' => esc_html__( 'Eager (loads immediately)', 'mai-engine' ),
						],
						'conditional_logic' => [
							[
								'field'    => 'mai_grid_block_show',
								'operator' => '==',
								'value'    => 'image',
							],
						],
					],
					[
						'key'               => 'field_63f9a2b4c8d3e',
						'name'              => 'image_loading_count',
						'label'             => esc_html__( 'Image Loading Count', 'mai-engine' ),
						'instructions'      => esc_html__( 'Enter the number of entries to eager load images for. The rest will be lazy loaded. Leave empty or use 0 to eagerload all images.', 'mai-engine' ),
						'type'              => 'number',
						'conditional_logic' => [
							[
								'field'    => 'mai_grid_block_show',
								'operator' => '==',
								'value'    => 'image',
							],
							[
								'field'    => 'field_63f9a2b4c8d2e',
								'operator' => '==',
								'value'    => 'eager',
							],
						],
					],
				],
				'location' => [
					[
						[
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/mai-post-grid',
						],
					],
					[
						[
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/mai-term-grid',
						],
					],
				],
				'menu_order' => 10,
				'active'     => true,
			]
		);
	}

	/**
	 * Add grid args.
	 *
	 * @since 0.1.0
	 *
	 * @param array $args The args.
	 *
	 * @return array
	 */
	public function add_grid_args( array $args ): array {
		/** @disregard P1010 */
		$args['image_loading'] = \get_field( 'image_loading' );
		/** @disregard P1010 */
		$args['image_loading_count'] = \get_field( 'image_loading_count' );

		return $args;
	}

	/**
	 * Add attributes to entry image link.
	 *
	 * @since 0.1.0
	 *
	 * @param string $content The existing content.
	 * @param array  $args    The layout args.
	 *
	 * @return string
	 */
	public function add_attributes( string $content, array $args ): string {
		$data = isset( $args['params']['args'] ) ? $args['params']['args'] : null;

		// Bail if no data.
		if ( ! $data ) {
			return $content;
		}

		// Get context.
		$context = $args['params']['args']['context'] ?? null;

		// Bail if not a block.
		if ( 'block' !== $context ) {
			return $content;
		}

		// Get loading and count.
		$default = 'lazy';
		$loading = $data['image_loading'] ?? $default;
		$count   = $data['image_loading_count'] ?? null;

		// Bail if no loading.
		if ( ! $loading ) {
			return $content;
		}

		// If count is over the index, force lazy loading.
		if ( $count && $count < $this->grid_entry_index ) {
			$loading = 'lazy';
		}

		// Set up tag processor.
		$tags = new \WP_HTML_Tag_Processor( $content );

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'img', 'class_name' => 'entry-image' ] ) ) {
			// Add loading attribute.
			$tags->set_attribute( 'loading', $loading );
		}

		// Get updated block content.
		$content = $tags->get_updated_html();

		return $content;
	}

	/**
	 * Increment the grid entry index.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function increment_index(): void {
		$this->grid_entry_index++;
	}
}
