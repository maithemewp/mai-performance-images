<?php

namespace Mai\PerformanceImages;

use WP_HTML_Tag_Processor;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mai Engine Images.
 *
 * @since 0.1.0
 */
class MaiEngine extends Images {
	/**
	 * The attributes enabled.
	 *
	 * @since 0..0
	 *
	 * @var bool
	 */
	protected $attributes_enabled;

	/**
	 * The conversion enabled.
	 *
	 * @since 0.1.0
	 *
	 * @var bool
	 */
	protected $conversion_enabled;

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
		// Set props.
		$this->attributes_enabled = is_attributes_enabled();
		$this->conversion_enabled = is_conversion_enabled();

		// Bail if nothing is enabled.
		if ( ! $this->attributes_enabled && ! $this->conversion_enabled ) {
			return;
		}

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

		// Add hooks used for both attributes and conversion.
		add_action( 'genesis_site_title',                      [ $this, 'before_logo' ], 0 );
		add_filter( 'mai_page_header_img',                     [ $this, 'render_page_header_image' ], 999, 3 );
		add_filter( 'genesis_markup_entry-image-link_content', [ $this, 'render_entry_image' ], 10, 3 );
		add_filter( 'render_block_acf/mai-post-grid',          [ $this, 'render_block_entry_image' ], 99, 2 );
		add_filter( 'render_block_acf/mai-term-grid',          [ $this, 'render_block_entry_image' ], 99, 2 );

		// Bail if attributes are disabled.
		if ( ! $this->attributes_enabled ) {
			return;
		}

		// Add hooks used for attributes.
		add_filter( 'genesis_markup_entry-image-link_content', [ $this, 'add_grid_attributes' ], 10, 2 );
		add_filter( 'mai_content_archive_settings',            [ $this, 'add_archive_settings' ], 10, 2 );
		add_filter( 'mai_single_content_settings',             [ $this, 'add_single_settings' ], 10, 2 );
		add_action( 'acf/init',                                [ $this, 'add_grid_block_field_group' ] );
		add_filter( 'mai_grid_args',                           [ $this, 'add_grid_args' ] );
		add_filter( 'genesis_markup_entries_open',             [ $this, 'reset_index_filter' ], 10, 2 );
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
	 * Filters the custom logo.
	 * Default logo already has eager loading from Mai Engine.
	 *
	 * @since 0.1.0
	 *
	 * @param string $html    The existing logo HTML.
	 *
	 * @return string
	 */
	public function custom_logo( string $html ): string {
		// If conversion is enabled.
		if ( $this->conversion_enabled ) {
			/**
			 * Set up tag processor.
			 * @disregard P1008
			 */
			$tags  = new WP_HTML_Tag_Processor( $html );
			$sizes = [];

			// Loop through tags.
			while ( $tags->next_tag( [ 'tag_name' => 'img', 'class_name' => 'custom-logo' ] ) ) {
				/** @disregard P1010 */
				$tags->set_attribute( 'data-mai-image-id', \mai_get_logo_id() );

				// Set sizes.
				$sizes = $tags->get_attribute( 'sizes' );
			}

			// Get updated content.
			$html = $tags->get_updated_html();
		}

		/**
		 * Set up tag processor.
		 * @disregard P1008
		 */
		$tags = new WP_HTML_Tag_Processor( $html );

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'img', 'class_name' => 'custom-scroll-logo' ] ) ) {
			// Set loading attributes if attributes are enabled.
			if ( $this->attributes_enabled ) {
				$tags->set_attribute( 'data-mai-loading', 'eager' );
			}

			// Set data-mai-image-id if conversion is enabled.
			if ( $this->conversion_enabled ) {
				/** @disregard P1010 */
				$tags->set_attribute( 'data-mai-image-id', \mai_get_scroll_logo_id() );
			}
		}

		// Get updated content.
		$html = $tags->get_updated_html();

		// If conversion is enabled, handle the image.
		if ( $this->conversion_enabled ) {
			/**
			 * Get logo width.
			 * @disregard P1010
			 */
			$widths = \mai_get_option( 'logo-width', [] );
			$widths = array_map( 'absint', $widths );
			$width  = isset( $widths['desktop'] ) ? $widths['desktop'] : 0;
			$width  = max( $width, 1 );
			$sizes  = $sizes ?? $width . 'px';

			// Set args.
			$args = [
				'max_images' => 0,
				'max_width'  => $width * 2,
				'sizes'      => [
					'mobile'  => $sizes,
					'tablet'  => $sizes,
					'desktop' => $sizes,
				],
			];

			// Handle the image.
			$html = $this->handle_image( $html, $args );
		}

		return $html;
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
		// Set loading attributes if attributes are enabled.
		if ( $this->attributes_enabled ) {
			/**
			 * Set up tag processor.
			 * @disregard P1008
			 */
			$tags = new WP_HTML_Tag_Processor( $image );

			// Loop through tags.
			while ( $tags->next_tag( [ 'tag_name' => 'img', 'class_name' => 'page-header-image' ] ) ) {
				// Check for loading attribute.
				$loading = $tags->get_attribute( 'loading' );

				// If loading is not set, set to eager.
				if ( ! $loading ) {
					$tags->set_attribute( 'loading', 'eager' );
					$tags->set_attribute( 'fetchpriority', 'high' );
					$tags->set_attribute( 'decoding', 'sync' );
				}
			}

			// Get updated content.
			$image = $tags->get_updated_html();

			// Handle the attributes.
			$image = $this->handle_attributes( $image );
		}

		// If conversion is enabled, handle the image.
		if ( $this->conversion_enabled ) {
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

			// Handle the image.
			$image = $this->handle_image( $image, $args );
		}

		return $image;
	}

	/**
	 * Filters the archive and single entry image.
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

		// Set image ID.
		$image_id = $args['params']['args']['image_id'] ?? null;

		// Bail if no image ID.
		if ( ! $image_id ) {
			/** @disregard P1008 */
			return $content;
		}

		// Set data-mai-image-id if conversion is enabled.
		if ( $this->conversion_enabled ) {
			/**
			 * Set up tag processor.
			 * @disregard P1008
			 */
			$tags = new WP_HTML_Tag_Processor( $content );

			// Loop through tags.
			while ( $tags->next_tag( [ 'tag_name' => 'img' ] ) ) {
				$tags->set_attribute( 'data-mai-image-id', $image_id );
			}

			// Get updated content.
			$content = $tags->get_updated_html();
		}

		// Return the content based on the context.
		switch ( $args['params']['args']['context'] ) {
			case 'archive':
				$content = $this->render_archive_entry_image( $content, $args );
				break;
			case 'single':
				$content = $this->render_single_entry_image( $content, $args );
				break;
			// Skip block since we only need to set image ID here.
			// The `render_block_entry_image` method will handle the rest.
			default:
				break;
		}

		// If attributes are enabled, handle the attributes.
		if ( $this->attributes_enabled ) {
			$content = $this->handle_attributes( $content );
		}

		return $content;
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

		// Set loading attributes if attributes are enabled.
		if ( $this->attributes_enabled ) {
			// Set index.
			static $index = 0;
			$index++;

			// Get loading and count.
			$loading = $data['image_loading'] ?? 'lazy';
			$count   = $data['image_loading_count'] ?? null;

			// If not loading or index is greater than count, set to lazy.
			if ( ! $loading || ( $count && $index > $count ) ) {
				$loading = 'lazy';
			}

			// Set up tag processor.
			$tags = new WP_HTML_Tag_Processor( $content );

			// Loop through tags.
			while ( $tags->next_tag( [ 'tag_name' => 'img', 'class_name' => 'entry-image' ] ) ) {
				// Add loading attribute.
				$tags->set_attribute( 'loading', $loading );

				// Switch loading attribute.
				switch ( $loading ) {
					// If eager, set fetchpriority to high.
					case 'eager':
						$tags->set_attribute( 'fetchpriority', 'high' );
						$tags->set_attribute( 'decoding', 'sync' );
						break;
					// If lazy, set fetchpriority to low.
					// We were sometimes seeing loading as lazy, but fetchpriority as high.
					// This makes sure that doesn't happen.
					case 'lazy':
						$tags->set_attribute( 'fetchpriority', 'low' );
						$tags->set_attribute( 'decoding', 'async' );
						break;
				}
			}

			// Get updated block content.
			$content = $tags->get_updated_html();
		}

		// If conversion is enabled, handle the image.
		if ( $this->conversion_enabled ) {
			/** @disregard P1010 */
			$columns     = array_reverse( \mai_get_breakpoint_columns( $data ) );
			$position    = $data['image_position'] ?? null;
			$orientation = $data['image_orientation'] ?? null;
			$image_size  = $data['image_size'] ?? null;
			$side        = $position && ( str_contains( $position, 'left' ) || str_contains( $position, 'right' ) ) ? 2 : 1;

			/** @disregard P1010 */
			$ratio = $this->get_image_aspect_ratio( $orientation, $image_size );

			// Set args.
			$image_args = [
				'aspect_ratio' => $ratio,
				'max_width'  => (int) ( 2400 / (int) $columns['lg'] / $side ),
				'max_images' => 0, // Process all images in the archive.
				'sizes'      => [
					'mobile'  => (int) ( 100 / (int) $columns['sm'] ) . 'vw',
					'tablet'  => (int) ( 100 / (int) $columns['md'] / $side ) . 'vw',
					'desktop' => (int) ( 100 / (int) $columns['lg'] / $side ) . 'vw',
				],
			];

			// Handle the image.
			$content = $this->handle_image( $content, $image_args );
		}

		return $content;
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

		// Set loading attributes if attributes are enabled.
		if ( $this->attributes_enabled ) {
			// Get loading.
			$loading = $data['image_loading'] ?? 'lazy';

			// Setup tag processor.
			$tags = new WP_HTML_Tag_Processor( $content );

			// Loop through tags.
			while ( $tags->next_tag( [ 'tag_name' => 'img', 'class_name' => 'entry-image' ] ) ) {
				// Add loading attribute.
				$tags->set_attribute( 'loading', $loading );

				// Switch loading attribute.
				switch ( $loading ) {
					// If eager, set fetchpriority to high.
					case 'eager':
						$tags->set_attribute( 'fetchpriority', 'high' );
						$tags->set_attribute( 'decoding', 'sync' );
						break;
					// If lazy, set fetchpriority to low.
					// We were sometimes seeing loading as lazy, but fetchpriority as high.
					// This makes sure that doesn't happen.
					case 'lazy':
						$tags->set_attribute( 'fetchpriority', 'low' );
						$tags->set_attribute( 'decoding', 'async' );
						break;
				}
			}

			// Get updated content.
			$content = $tags->get_updated_html();
		}

		// If conversion is enabled, handle the image.
		if ( $this->conversion_enabled ) {
			// Get image aspect ratio.
			$orientation = $data['image_orientation'] ?? null;
			$image_size  = $data['image_size'] ?? null;
			$ratio       = $this->get_image_aspect_ratio( $orientation, $image_size );

			// Set args.
			$image_args = [
				'aspect_ratio' => $ratio,
				'max_width'  => 1600,
				'sizes'      => [
					'mobile'  => '90vw',
					'tablet'  => '80vw',
					'desktop' => '70vw',
				],
			];

			// Handle the image.
			$content = $this->handle_image( $content, $image_args );
		}

		return $content;
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
	public function render_block_entry_image( string $block_content, array $block ): string {
		// Bail if conversion is disabled.
		if ( ! $this->conversion_enabled ) {
			return $block_content;
		}

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

		// Get image aspect ratio.
		$image_id    = null;
		$orientation = $data['image_orientation'] ?? null;
		$image_size  = $data['image_size'] ?? null;
		$ratio       = $this->get_image_aspect_ratio( $orientation, $image_size );

		// Setup tag processor.
		$tags = new WP_HTML_Tag_Processor( $block_content );

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'img', 'class_name' => 'entry-image' ] ) ) {
			// Get image ID.
			$image_id = $tags->get_attribute( 'data-mai-image-id' );
			$image_id = $image_id ? (int) $image_id : null;
		}

		// Set args.
		$image_args = [
			'image_id'     => $image_id,
			'aspect_ratio' => $ratio,
			'max_width'    => $max_width,
			'max_images'   => 0, // Process all images in the grid
			'sizes'        => [
				'mobile'  => (int) ( 100 / (int) $columns['sm'] ) . 'vw',
				'tablet'  => (int) ( 100 / (int) $columns['md'] / $side ) . 'vw',
				'desktop' => (int) ( 100 / (int) $columns['lg'] / $side ) . 'vw',
			],
		];

		// Handle the image.
		$block_content = $this->handle_image( $block_content, $image_args );

		return $block_content;
	}

	/**
	 * Get image aspect ratio.
	 *
	 * @since 0.2.0
	 *
	 * @param string|null $orientation The image orientation.
	 * @param string|null $image_size  The image size.
	 *
	 * @return string|false
	 */
	public function get_image_aspect_ratio( ?string $orientation, ?string $image_size ): string {
		/** @disregard P1010 */
		$ratio = $orientation ? \mai_get_aspect_ratio_from_orientation( $orientation ) : false;
		/** @disregard P1010 */
		$ratio = ! $ratio && $image_size ? \mai_get_image_aspect_ratio( $image_size ) : $ratio;

		return $ratio;
	}

	/**
	 * Add attributes to entry image link.
	 * We can't add these attributes in the render_block filter
	 * because the new block args are not available yet.
	 *
	 * @since 0.1.0
	 *
	 * @param string $content The existing content.
	 * @param array  $args    The layout args.
	 *
	 * @return string
	 */
	public function add_grid_attributes( string $content, array $args ): string {
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
		$loading = $data['image_loading'] ?? 'lazy';
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
		$tags = new WP_HTML_Tag_Processor( $content );

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'img', 'class_name' => 'entry-image' ] ) ) {
			// Add loading attribute.
			$tags->set_attribute( 'loading', $loading );

			// Switch loading attribute.
			switch ( $loading ) {
				// If eager, set fetchpriority to high.
				case 'eager':
					$tags->set_attribute( 'fetchpriority', 'high' );
					$tags->set_attribute( 'decoding', 'sync' );
					break;
				// If lazy, set fetchpriority to low.
				// We were sometimes seeing loading as lazy, but fetchpriority as high.
				// This makes sure that doesn't happen.
				case 'lazy':
					$tags->set_attribute( 'fetchpriority', 'low' );
					$tags->set_attribute( 'decoding', 'async' );
					break;
			}
		}

		// Get updated block content.
		$content = $tags->get_updated_html();

		return $content;
	}

	/**
	 * Add archive settings.
	 *
	 * @since TBD
	 *
	 * @param array $settings The settings.
	 * @param string $name The name.
	 *
	 * @return array
	 */
	public function add_archive_settings( array $settings, string $name ): array {
		// Loop through settings.
		foreach ( $settings as $index => $setting ) {
			if ( ! isset( $setting['settings'] ) || 'image_width' !== $setting['settings'] ) {
				continue;
			}

			// Build new settings.
			$new = [
				[
					'settings'       => 'image_loading',
					'label'          => 'Image Loading',
					'type'           => 'select',
					'default'        => '',
					'choices'        => [
						''      => esc_html__( 'Default', 'mai-performance-images' ),
						'lazy'  => esc_html__( 'Lazy (for offscreen images)', 'mai-performance-images' ),
						'eager' => esc_html__( 'Eager (loads immediately)', 'mai-performance-images' ),
					],
					'active_callback' => [
						[
							'setting'  => 'show',
							'operator' => 'contains',
							'value'    => 'image',
						],
					],
				],
				[
					'settings'        => 'image_loading_count',
					'label'           => 'Image Loading Count',
					'description'     => esc_html__( 'Enter the number of entries to eager load images for. The rest will be lazy loaded. Leave empty or use 0 to eagerload all images.', 'mai-performance-images' ),
					'type'            => 'text',
					'sanitize'        => 'absint',
					'default'         => '',
					'active_callback' => [
						[
							'setting'  => 'show',
							'operator' => 'contains',
							'value'    => 'image',
						],
						[
							'setting'  => 'image_loading',
							'operator' => '==',
							'value'    => 'eager',
						],
					],
				],
			];

			// Insert the new setting after the current setting.
			array_splice( $settings, $index + 1, 0, $new );
			break;
		}

		// Reindex settings.
		$settings = array_values( $settings );

		return $settings;
	}

	/**
	 * Add single settings.
	 *
	 * @since TBD
	 *
	 * @param array $settings The settings.
	 * @param string $name The name.
	 *
	 * @return array
	 */
	public function add_single_settings( array $settings, string $name ): array {
		// Loop through settings.
		foreach ( $settings as $index => $setting ) {
			if ( ! isset( $setting['settings'] ) || 'image_size' !== $setting['settings'] ) {
				continue;
			}

			// Build new settings.
			$new = [
				[
					'settings'       => 'image_loading',
					'label'          => 'Image Loading',
					'type'           => 'select',
					'default'        => '',
					'choices'        => [
						''      => esc_html__( 'Default', 'mai-performance-images' ),
						'lazy'  => esc_html__( 'Lazy (for offscreen images)', 'mai-performance-images' ),
						'eager' => esc_html__( 'Eager (loads immediately)', 'mai-performance-images' ),
					],
					'active_callback' => [
						[
							'setting'  => 'show',
							'operator' => 'contains',
							'value'    => 'image',
						],
					],
				],
			];

			// Insert the new setting after the current setting.
			array_splice( $settings, $index + 1, 0, $new );
			break;
		}

		// Reindex settings.
		$settings = array_values( $settings );

		return $settings;
	}

	/**
	 * Register grid block field group.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function add_grid_block_field_group(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		/** @disregard P1010 */
		\acf_add_local_field_group(
			[
				'key'   => 'mai_performance_images_grid_block_field_group',
				'title' => esc_html__( 'Mai Performance Images', 'mai-performance-images' ),
				'fields' => [
					[
						'label'        => __( 'Mai Performance Images', 'mai-performance-images' ),
						'key'          => 'field_63f9a2b4c8d1e',
						'type'         => 'accordion',
						'open'         => 0,
						'multi_expand' => 1,
						'endpoint'     => 0,
					],
					[
						'key'     => 'field_63f9a2b4c8d2e',
						'name'    => 'image_loading',
						'label'   => esc_html__( 'Image Loading', 'mai-performance-images' ),
						'type'    => 'select',
						'choices' => [
							''      => esc_html__( 'Default', 'mai-performance-images' ),
							'lazy'  => esc_html__( 'Lazy (for offscreen images)', 'mai-performance-images' ),
							'eager' => esc_html__( 'Eager (loads immediately)', 'mai-performance-images' ),
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
						'label'             => esc_html__( 'Image Loading Count', 'mai-performance-images' ),
						'instructions'      => esc_html__( 'Enter the number of entries to eager load images for. The rest will be lazy loaded. Leave empty or use 0 to eagerload all images.', 'mai-performance-images' ),
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
	 * Reset the grid entry index.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	public function reset_index_filter( $content, $args ) {
		$this->reset_index();
		return $content;
	}

	/**
	 * Reset the grid entry index.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	public function reset_index(): void {
		$this->grid_entry_index = 1;
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
