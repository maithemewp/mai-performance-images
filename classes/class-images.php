<?php

namespace Mai\Performance;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mai Performance Images class.
 *
 * @since 0.1.0
 */
class Images {
	/**
	 * All registered image sizes.
	 *
	 * @since 0.1.0
	 *
	 * @var array
	 */
	public $all_sizes = null;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Adds the hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function hooks() {
		// Block image filters.
		add_filter( 'render_block_core/cover',               [ $this, 'render_image_block' ], 99, 2 );
		add_filter( 'render_block_core/image',               [ $this, 'render_image_block' ], 99, 2 );
		add_filter( 'render_block_core/post-featured-image', [ $this, 'render_image_block' ], 99, 2 );
		add_filter( 'render_block_core/media-text',          [ $this, 'render_media_text_block' ], 99, 2 );
		add_filter( 'render_block_core/site-logo',           [ $this, 'render_site_logo_block' ], 99, 2 );

		// Image attribute filter.
		add_filter( 'wp_content_img_tag', [ $this, 'filter_content_img_tag' ], 999, 3 );

		// Handle image requests.
		add_action( 'init', [ $this, 'set_image_sizes' ] );

	}

	/**
	 * Renders the image block and other similar blocks that have the same settings.
	 *
	 * @since 0.1.0
	 *
	 * @param string $block_content The block content.
	 * @param array  $block The block.
	 *
	 * @return string
	 */
	public function render_image_block( $block_content, $block ) {
		// Get alignment.
		$align = $block['attrs']['align'] ?? '';

		// Get max width and sizes from alignment.
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
					'max_width' => 1200,
					'sizes'     => [
						'mobile'  => '90vw',
						'tablet'  => '90vw',
						'desktop' => '1200px',
					],
				];
				break;
			default:
				$args = [
					'max_width' => 800,
					'sizes'     => [
						'mobile'  => '90vw',
						'tablet'  => '90vw',
						'desktop' => '800px',
					],
				];
				break;
		}

		// Force only the manually set width if it's set and has a px suffix.
		if ( isset( $block['attrs']['width'] )
			&& ! empty( $block['attrs']['width'] )
			&& str_ends_with( $block['attrs']['width'], 'px' )
		) {
			// Set width to 2x the actual value for max_width
			$width = absint( $block['attrs']['width'] );
			if ( $width > 0 ) {
				$args['max_width'] = $width * 2;
				$args['src_width'] = $width;
				$args['sizes']     = [
					'mobile'  => "{$width}px",
					'tablet'  => "{$width}px",
					'desktop' => "{$width}px",
				];
			}
		}

		// Maybe set the image ID.
		$args['image_id'] = $block['attrs']['id'] ?? null;

		return $this->process_image_tag( $block_content, $args );
	}

	/**
	 * Renders the media-text block.
	 * This is
	 *
	 * @since 0.1.0
	 *
	 * @param string $block_content The block content.
	 * @param array  $block The block.
	 *
	 * @return string
	 */
	public function render_media_text_block( $block_content, $block ) {
		// Get alignment.
		$align = $block['attrs']['align'] ?? '';

		// Get max width and sizes from alignment.
		switch ( $align ) {
			case 'full':
				$args = [
					'max_width' => 1200,
					'sizes'     => [
						'mobile'  => '100vw',
						'tablet'  => '50vw',
						'desktop' => '50vw',
					],
				];
				break;
			case 'wide':
				$args = [
					'max_width' => 600,
					'sizes'     => [
						'mobile'  => '100vw',
						'tablet'  => '50vw',
						'desktop' => '600px',
					],
				];
				break;
			default:
				$args = [
					'max_width' => 400,
					'sizes'     => [
						'mobile'  => '100vw',
						'tablet'  => '50vw',
						'desktop' => '400px',
					],
				];
				break;
		}

		// Get width from block attributes if available.
		if ( ! empty( $block['attrs']['width'] ) ) {
			$args['max_width'] = max( $args['max_width'], (int) $block['attrs']['width'] );
		}

		$args['image_id'] = $block['attrs']['id'] ?? null;

		return $this->process_image_tag( $block_content, $args );
	}

	/**
	 * Renders the site logo block.
	 * This block requires a width attribute so we handle it separately.
	 *
	 * @since 0.1.0
	 *
	 * @param string $block_content The block content.
	 * @param array  $block The block.
	 *
	 * @return string
	 */
	public function render_site_logo_block( $block_content, $block ) {
		$width = $block['attrs']['width'] ?? 800;

		$block_content = $this->process_image_tag( $block_content, [
			'max_width'  => $width * 2,
			'src_width'  => $width * 2,
			'sizes'      => [
				'mobile'  => "{$width}px",
				'tablet'  => "{$width}px",
				'desktop' => "{$width}px",
			],
		] );

		return $block_content;
	}

	/**
	 * Process an image tag to add dynamic image loading.
	 *
	 * @since 0.1.0
	 *
	 * @param string $html The HTML content.
	 * @param array  $args {
	 *     Optional. Arguments for processing the image.
	 *
	 *     @type int      $max_width  Maximum width for the image.
	 *     @type int      $src_width  Width for the src attribute. Defaults to min(800, max_width).
	 *     @type int|null $image_id   The image ID.
	 *     @type array    $sizes      {
	 *         Responsive sizes configuration.
	 *         @type string $mobile  Mobile viewport size.
	 *         @type string $tablet  Tablet viewport size.
	 *         @type string $desktop Desktop viewport size.
	 *     }
	 * }
	 *
	 * @return string
	 */
	public function process_image_tag( $html, $args = [] ) {
		// Parse args with defaults
		$args = wp_parse_args( $args, [
			'max_width'  => 2400,
			'src_width'  => null,
			'image_id'   => null,
			'sizes'      => [
				'mobile'  => '100vw',
				'tablet'  => '100vw',
				'desktop' => '100vw',
			],
		]);

		// Set src width to whichever is smaller, 400 or max_width if not explicitly set
		if ( ! $args['src_width'] ) {
			$args['src_width'] = min( 400, $args['max_width'] );
		}

		// Set up tag processor.
		$tags = new \WP_HTML_Tag_Processor( $html );

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'img' ] ) ) {
			// Get the original src.
			$src = $tags->get_attribute( 'src' );

			// Bail if no src.
			if ( ! $src ) {
				break;
			}

			// Get uploads directory info.
			$uploads = wp_get_upload_dir();

			// Parse URL to get path.
			$url_path = wp_parse_url( $src, PHP_URL_PATH );

			// Bail if no path.
			if ( ! $url_path ) {
				break;
			}

			// Convert URL to path relative to uploads directory.
			$path = str_replace( wp_parse_url( $uploads['baseurl'], PHP_URL_PATH ) . '/', '', $url_path );

			// Bail if path is unchanged (means URL wasn't in uploads directory).
			if ( $path === $url_path ) {
				break;
			}

			// Get the original extension.
			$path_parts = pathinfo( $path );
			$extension  = $path_parts['extension'] ?? 'jpg';

			// If we have an image ID, get the full size URL.
			if ( $args['image_id'] ) {
				$full_url = wp_get_attachment_image_url( $args['image_id'], 'full' );
				if ( $full_url ) {
					$path = str_replace( wp_parse_url( $uploads['baseurl'], PHP_URL_PATH ) . '/', '', wp_parse_url( $full_url, PHP_URL_PATH ) );
					$path_parts = pathinfo( $path );
					$extension  = $path_parts['extension'] ?? 'jpg';
				}
			}

			// Set base widths and start srcset.
			$base_widths = [ 400, 800, 1200, 1600, 2400 ];
			$srcset      = [];

			// Filter widths based on max_width.
			$widths = array_filter( $base_widths, function( $w ) use ( $args ) {
				// Don't exceed the max_width.
				return $w <= $args['max_width'];
			} );

			// Get the site URL for building absolute URLs
			$site_url = site_url();

			// Build srcset.
			foreach ( $widths as $w ) {
				// Create the path parts.
				$path_parts = pathinfo( $path );
				$dirname    = $path_parts['dirname'];
				$filename   = $path_parts['filename'];

				// Build the URL with the original extension.
				$image_url = $site_url . '/mai-performance-images/' . $path;
				$image_url = str_replace( '.' . $extension, '-' . $w . 'x' . ( $height ?? 'auto' ) . '.' . $extension, $image_url );
				$srcset[]  = "{$image_url} {$w}w";
			}

			// Build sizes attribute dynamically based on available widths add mobile size first (default).
			$sizes_parts   = [];
			$sizes_parts[] = $args['sizes']['mobile'];

			// Skip the smallest width as it's already covered by the default.
			$breakpoint_widths = array_slice( $widths, 1 );

			// Add breakpoints for each width, except the smallest.
			foreach ( $breakpoint_widths as $width ) {
				// Use the width as the breakpoint.
				$sizes_parts[] = "(min-width: {$width}px) {$width}px";
			}

			// Back to string.
			$sizes = implode( ', ', $sizes_parts );

			// Create the src URL with the original extension.
			$src_url = $site_url . '/mai-performance-images/' . $path;
			$src_url = str_replace( '.' . $extension, '-' . $args['src_width'] . 'x' . ( $height ?? 'auto' ) . '.' . $extension, $src_url );

			// Set the attributes array.
			$attr = [
				'src'    => $src_url,
				'srcset' => implode( ', ', $srcset ),
				'sizes'  => $sizes,
			];

			// Filter the attributes.
			$attr = apply_filters( 'mai_performance_imagess_image_attributes', $attr, $args );

			// Set the attributes.
			$tags->set_attribute( 'data-mai-image', esc_attr( wp_json_encode( $attr ) ) );

			// Break after first image.
			break;
		}

		return $tags->get_updated_html();
	}

	/**
	 * Get a combined list of default and custom registered image sizes.
	 *
	 * Originally taken from CMB2. Static variable added here.
	 *
	 * We can't use `genesis_get_image_sizes()` because we need it earlier than Genesis is loaded for Kirki.
	 *
	 * @since  0.1.0
	 *
	 * @link   http://core.trac.wordpress.org/ticket/18947
	 * @global array $_wp_additional_image_sizes All image sizes.
	 *
	 * @return array
	 */
	public function set_image_sizes() {
		$this->all_sizes = [];

		// Get image sizes.
		global $_wp_additional_image_sizes;
		$default_image_sizes = [ 'thumbnail', 'medium', 'large' ];

		foreach ( $default_image_sizes as $size ) {
			$this->all_sizes[ $size ] = [
				'height' => intval( get_option( "{$size}_size_h" ) ),
				'width'  => intval( get_option( "{$size}_size_w" ) ),
				'crop'   => get_option( "{$size}_crop" ) ? get_option( "{$size}_crop" ) : false,
			];
		}

		if ( isset( $_wp_additional_image_sizes ) && count( $_wp_additional_image_sizes ) ) {
			$this->all_sizes = array_merge( $this->all_sizes, $_wp_additional_image_sizes );
		}

		return $this->all_sizes;
	}

	/**
	 * Filters an img tag within the content for a given context.
	 *
	 * @since 0.1.0
	 *
	 * @param string $filtered_image Full img tag with attributes that will replace the source img tag.
	 * @param string $context        Additional context, like the current filter name or the function name from where this was called.
	 * @param int    $attachment_id  The image attachment ID. May be 0 in case the image is not an attachment.
	 * @return string
	 */
	public function filter_content_img_tag( $filtered_image, $context, $attachment_id ) {
		// Tag processor.
		$tags = new \WP_HTML_Tag_Processor( $filtered_image );

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'img' ] ) ) {
			$loading  = $tags->get_attribute( 'data-mai-loading' );
			$attr     = $tags->get_attribute( 'data-mai-image' );

			// If loading attribute.
			if ( $loading ) {
				// Remove the data-mai-loading attribute.
				$tags->remove_attribute( 'data-mai-loading' );

				// Set the loading attribute.
				$tags->set_attribute( 'loading', $loading );

				// If eager, set fetchpriority to high.
				if ( 'eager' === $loading ) {
					$tags->set_attribute( 'fetchpriority', 'high' );
				}
			}

			// If attributes.
			if ( $attr ) {
				// Unset the data-mai-image attribute.
				$tags->remove_attribute( 'data-mai-image' );

				// Decode the attributes.
				$attr = json_decode( $attr, true );

				// Bail if no attributes.
				if ( ! $attr ) {
					continue;
				}

				// Parse the attributes.
				$attr = wp_parse_args( $attr, [
					'src'    => '',
					'srcset' => '',
					'sizes'  => '',
				] );

				// Set the attributes.
				$tags->set_attribute( 'src', $attr['src'] );
				$tags->set_attribute( 'srcset', $attr['srcset'] );
				$tags->set_attribute( 'sizes', $attr['sizes'] );
			}
		}

		// Get the updated content.
		$filtered_image = $tags->get_updated_html();

		return $filtered_image;
	}
}