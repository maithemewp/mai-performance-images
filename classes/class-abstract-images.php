<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mai Performance Abstract Images class.
 *
 * @since 0.1.0
 */
abstract class AbstractImages {
	/**
	 * The logger instance.
	 *
	 * @since 0.1.0
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function __construct() {
		$this->logger = Logger::get_instance();
		$this->hooks();
		$this->core_hooks();
	}

	/**
	 * Add hooks.
	 * Override this method to add your own hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	abstract protected function hooks(): void;

	/**
	 * Setup core hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function core_hooks(): void {
		add_filter( 'wp_content_img_tag', [ $this, 'filter_content_img_tag' ], 999, 3 );
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
	protected function process_image_tag( string $html, array $args = [] ): string {
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
				$height    = null; // Initialize height variable

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
			$attr = apply_filters( 'mai_performance_images_image_attributes', $attr, $args );

			// Set the attributes.
			$tags->set_attribute( 'data-mai-image', esc_attr( wp_json_encode( $attr ) ) );

			// Break after first image.
			break;
		}

		return $tags->get_updated_html();
	}

	/**
	 * Get default args for a block based on its alignment.
	 *
	 * @since 0.1.0
	 *
	 * @param string $align The block alignment.
	 *
	 * @return array
	 */
	protected function get_alignment_args( string $align ): array {
		switch ( $align ) {
			case 'full':
				return [
					'max_width' => 2400,
					'sizes'     => [
						'mobile'  => '100vw',
						'tablet'  => '100vw',
						'desktop' => '100vw',
					],
				];
			case 'wide':
				return [
					'max_width' => 1200,
					'sizes'     => [
						'mobile'  => '90vw',
						'tablet'  => '90vw',
						'desktop' => '1200px',
					],
				];
			default:
				return [
					'max_width' => 800,
					'sizes'     => [
						'mobile'  => '90vw',
						'tablet'  => '90vw',
						'desktop' => '800px',
					],
				];
		}
	}

	/**
	 * Filters an img tag within the content for a given context.
	 *
	 * @since 0.1.0
	 *
	 * @param string $filtered_image Full img tag with attributes that will replace the source img tag.
	 * @param string $context        Additional context, like the current filter name or the function name from where this was called.
	 * @param int    $attachment_id  The image attachment ID. May be 0 in case the image is not an attachment.
	 *
	 * @return string
	 */
	public function filter_content_img_tag( string $filtered_image, string $context, int $attachment_id ): string {
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