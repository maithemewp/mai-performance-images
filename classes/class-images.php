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

		// Set properties.
		$this->set_properties();

		// Get alignment args.
		$align = $block['attrs']['align'] ?? null;

		// Build args.
		switch ( $align ) {
			case 'full':
				$args = [
					'max_width' => 2400,
					'sizes'     => [
						'mobile'  => '100vw',
						'tablet'  => '100vw',
						'desktop' => '100vw',
					],
				];
			case 'wide':
				$args = [
					'max_width' => $this->wide_size * 2,
					'sizes'     => [
						'mobile'  => '90vw',
						'tablet'  => '90vw',
						'desktop' => $this->wide_size . 'px',
					],
				];
			default:
				$args = [
					'max_width' => $this->tablet_breakpoint * 2,
					'sizes'     => [
						'mobile'  => '90vw',
						'tablet'  => '90vw',
						'desktop' => $this->tablet_breakpoint . 'px',
					],
				];
		}

		// Add image ID.
		$args['image_id'] = $block['attrs']['id'] ?? null;

		// Process the image.
		return $this->handle_image( $block_content, $args );
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

		// Set properties.
		$this->set_properties();

		// Get alignment.
		$align = $block['attrs']['align'] ?? null;

		// Build args.
		switch ( $align ) {
			case 'full':
				$args = [
					'max_width' => 2400,
					'sizes'     => [
						'mobile'  => '100vw',
						'tablet'  => '50vw',
						'desktop' => '50vw',
					],
				];
			case 'wide':
				$args = [
					'max_width' => $this->wide_size * 2,
					'sizes'     => [
						'mobile'  => '90vw',
						'tablet'  => '50vw',
						'desktop' => $this->wide_size / 2 . 'px',
					],
				];
			default:
				$args = [
					'max_width' => $this->tablet_breakpoint * 2,
					'sizes'     => [
						'mobile'  => '90vw',
						'tablet'  => '50vw',
						'desktop' => $this->tablet_breakpoint / 2 . 'px',
					],
				];
		}

		// Add image ID.
		$args['image_id'] = $block['attrs']['mediaId'] ?? null;

		// Process the image.
		return $this->handle_image( $block_content, $args );
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

		// Get width. This should always be set, but fallback anyway.
		$width = $block['attrs']['width'] ?? 200;

		// Build args.
		$args = [
			'image_id'  => get_theme_mod( 'custom_logo' ),
			'max_width' => $width * 2,
			'sizes'     => [
				'mobile'  => $width . 'px',
				'tablet'  => $width . 'px',
				'desktop' => $width . 'px',
			],
		];

		// Process the image.
		return $this->handle_image( $block_content, $args );
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

		// Set properties.
		$this->set_properties();

		// Get alignment and width.
		$align = $block['attrs']['align'] ?? null;
		$width = $block['attrs']['width'] ?? null;

		// Build args.
		switch ( $align ) {
			case 'full':
				$args = [
					'max_width' => 2400,
					'sizes'     => [
						'mobile'  => '100vw',
						'tablet'  => $width ? $width . 'px' : '100vw',
						'desktop' => $width ? $width . 'px' : '100vw',
					],
				];
			case 'wide':
				$args = [
					'max_width' => $this->wide_size * 2,
					'sizes'     => [
						'mobile'  => '90vw',
						'tablet'  => $width ? $width . 'px' : '90vw',
						'desktop' => $width ? $width . 'px' : '90vw',
					],
				];
			default:
				$args = [
					'max_width' => $this->desktop_breakpoint * 2,
					'sizes'     => [
						'mobile'  => '90vw',
						'tablet'  => $width ? $width . 'px' : $this->tablet_breakpoint . 'px',
						'desktop' => $width ? $width . 'px' : $this->desktop_breakpoint . 'px',
					],
				];
		}

		// Add image ID.
		$args['image_id'] = get_post_thumbnail_id();

		// Process the image.
		return $this->handle_image( $block_content, $args );
	}
}