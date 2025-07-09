<?php

namespace Mai\PerformanceImages;

use WP_HTML_Tag_Processor;

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
		add_filter( 'render_block_core/cover',               [ $this, 'render_image_block' ], 99, 2 );
		add_filter( 'render_block_core/image',               [ $this, 'render_image_block' ], 99, 2 );
		add_filter( 'render_block_core/post-featured-image', [ $this, 'render_image_block' ], 99, 2 );
		add_filter( 'render_block_core/media-text',          [ $this, 'render_media_text_block' ], 99, 2 );
		add_filter( 'render_block_core/site-logo',           [ $this, 'render_site_logo_block' ], 99, 2 );

		// This is so small that it's always blurry.
		// add_filter( 'get_avatar', [ $this, 'render_avatar' ], 99, 6 );
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
				break;
			case 'wide':
				$args = [
					'max_width' => $this->wide_size * 2,
					'sizes'     => [
						'mobile'  => '90vw',
						'tablet'  => '90vw',
						'desktop' => $this->wide_size . 'px',
					],
				];
				break;
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

		// If featured image, get image ID.
		if ( 'core/post-featured-image' === $block['blockName'] ) {
			$args['image_id'] = get_post_thumbnail_id();
		}
		// Standard block.
		else {
			$args['image_id'] = isset( $block['attrs']['id'] ) ? absint( $block['attrs']['id'] ) : null;

			// If image block, check for block bindings (pattern overrides).
			if ( 'core/image' === $block['blockName'] ) {
				// Get image ID from class.
				$image_id = $this->get_image_id( $block_content );

				// If image ID is found and it doesn't match the block's image ID, set it.
				if ( $image_id && $image_id !== $args['image_id'] ) {
					$args['image_id'] = $image_id;
				}
			}
		}

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
				break;
			case 'wide':
				$args = [
					'max_width' => $this->wide_size * 2,
					'sizes'     => [
						'mobile'  => '90vw',
						'tablet'  => '50vw',
						'desktop' => $this->wide_size / 2 . 'px',
					],
				];
				break;
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
	 * Filters the avatar.
	 *
	 * @since 0.4.0
	 *
	 * @param string|null $avatar      The avatar HTML.
	 * @param mixed       $id_or_email The user ID or email address.
	 * @param int         $size        The size of the avatar.
	 * @param string      $default     The default avatar.
	 * @param string      $alt         The alt text.
	 * @param array       $args        The arguments.
	 *
	 * @return string|null
	 */
	public function render_avatar( ?string $avatar, mixed $id_or_email, int $size, string $default, string $alt, array $args ): ?string {
		// Bail if no avatar.
		if ( ! $avatar ) {
			return $avatar;
		}

		// Get src.
		$src = $args['url'] ?? null;

		// If not external, bail.
		if ( ! $src || ! $this->is_external( $src ) ) {
			return $avatar;
		}

		// Set width.
		$width = $args['size'] ?? 100;

		// Set args.
		$args = [
			'aspect_ratio' => '1/1',
			'max_width'    => $width,
			'sizes'        => [
				'mobile'  => $width . 'px',
				'tablet'  => $width . 'px',
				'desktop' => $width . 'px',
			],
		];

		// Process the image.
		return $this->handle_image( $avatar, $args );
	}

	/**
	 * Get the image ID from the class.
	 *
	 * @since 0.1.0
	 *
	 * @param string $html The HTML content.
	 *
	 * @return int
	 */
	private function get_image_id( string $html ): int {
		// Set image ID.
		$image_id = 0;

		// Set up tag processor.
		$tags = new \WP_HTML_Tag_Processor( $html );

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'img' ] ) ) {
			$class   = (string) $tags->get_attribute( 'class' );
			$classes = explode( ' ', $class );

			// Get the class that starts with 'wp-image-'
			$image_class = array_filter( $classes, function( $class ) {
				return str_starts_with( $class, 'wp-image-' );
			} );

			// Bail if no image class.
			if ( empty( $image_class ) ) {
				continue;
			}

			// Get the image ID.
			$image_id = str_replace( 'wp-image-', '', reset( $image_class ) );
			$image_id = absint( $image_id );

			// Bail.
			break;

		}

		// Return the image ID.
		return $image_id;
	}
}
