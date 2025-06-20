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
	 * The queue instance.
	 *
	 * @since 0.1.0
	 *
	 * @var BackgroundProcess|null
	 */
	protected $queue = null;

	/**
	 * The logger instance.
	 *
	 * @since 0.1.0
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * The tablet breakpoint in pixels.
	 *
	 * @since 0.1.0
	 *
	 * @var int
	 */
	protected $tablet_breakpoint;

	/**
	 * The desktop breakpoint in pixels.
	 *
	 * @since 0.1.0
	 *
	 * @var int
	 */
	protected $desktop_breakpoint;

	/**
	 * The content size in pixels.
	 *
	 * @since 0.1.0
	 *
	 * @var int
	 */
	protected $content_size;

	/**
	 * The wide size in pixels.
	 *
	 * @since 0.1.0
	 *
	 * @var int
	 */
	protected $wide_size;

	/**
	 * Static array to store queue items.
	 *
	 * @since 0.1.0
	 *
	 * @var array
	 */
	protected static $queue_items = [];

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

		// Add shutdown hook to process queue.
		add_action( 'shutdown', [ $this, 'process_queue' ] );
	}

	/**
	 * Add hooks.
	 * Override this method in your child class to add your own hooks.
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
		add_action( 'init',               [ $this, 'set_queue' ] );
		add_filter( 'wp_content_img_tag', [ $this, 'filter_content_img_tag' ], 999, 3 );
		add_filter( 'get_custom_logo',    [ $this, 'filter_custom_logo' ], 999, 2 );
	}

	/**
	 * Set the queue.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function set_queue(): void {
		/** @disregard P1009 */
		$this->queue = new BackgroundProcess();
	}

	/**
	 * Filters an img tag within the content for a given context.
	 * WP filter callbacks must be public.
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
		return $this->handle_attributes( $filtered_image );
	}

	/**
	 * Filters the custom logo.
	 *
	 * @since 0.1.0
	 *
	 * @param string $html The HTML content.
	 *
	 * @return string
	 */
	public function filter_custom_logo( string $html ): string {
		return $this->handle_attributes( $html );
	}

	/**
	 * Handles the attributes of our dynamic images.
	 *
	 * @since 0.1.0
	 *
	 * @param string $html The HTML content.
	 *
	 * @return string
	 */
	public function handle_attributes( string $html ): string {
		// Tag processor.
		$tags = new \WP_HTML_Tag_Processor( $html );

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
					$tags->set_attribute( 'decoding', 'sync' );
				}
				// Otherwise set fetchpriority to low.
				// We were sometimes seeing loading as lazy, but fetchpriority as high.
				// This makes sure that doesn't happen.
				else {
					$tags->set_attribute( 'fetchpriority', 'low' );
					$tags->set_attribute( 'decoding', 'async' );
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
		$html = $tags->get_updated_html();

		return $html;
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
	 *     @type int         $max_width    Maximum width for the image.
	 *     @type int         $src_width    Width for the src attribute. Defaults to min(800, max_width).
	 *     @type int|null    $image_id     The image ID.
	 *     @type int         $max_images   Maximum number of images to process. 0 for all images, 1 for first image only. Default 1.
	 *     @type string|null $aspect_ratio Aspect ratio to use for cropping (e.g., "16:9" or "1.5").
	 *     @type array       $sizes       {
	 *         Responsive sizes configuration.
	 *         @type string $mobile  Mobile viewport size.
	 *         @type string $tablet  Tablet viewport size.
	 *         @type string $desktop Desktop viewport size.
	 *     }
	 *     @type array       $breakpoints {
	 *         Responsive breakpoints.
	 *         @type int $tablet  Tablet breakpoint.
	 *         @type int $desktop Desktop breakpoint.
	 *     }
	 * }
	 *
	 * @return string
	 */
	protected function handle_image( string $html, array $args = [] ): string {
		// Maybe set sizes and breakpoints.
		$this->set_properties();

		// Parse args with defaults, using class properties for default breakpoints
		$args = wp_parse_args( $args, [
			'aspect_ratio' => null,
			'max_width'    => 2400,
			'src_width'    => null,
			'image_id'     => null,
			'max_images'   => 1,
			'content_size' => $this->content_size,
			'wide_size'    => $this->wide_size,
			'breakpoints'  => [
				'tablet'  => $this->tablet_breakpoint,
				'desktop' => $this->desktop_breakpoint,
			],
			'sizes'        => [
				'mobile'  => '100vw',
				'tablet'  => '100vw',
				'desktop' => '100vw',
			],
		] );

		// Set src width to whichever is smaller, 400 or max_width if not explicitly set
		if ( ! $args['src_width'] ) {
			$args['src_width'] = min( 400, $args['max_width'] );
		}

		// Set up tag processor.
		$tags = new \WP_HTML_Tag_Processor( $html );

		// Track number of images processed
		$images_processed = 0;

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'img' ] ) ) {
			$src = $tags->get_attribute( 'src' );

			// Bail if no src.
			if ( ! $src ) {
				continue;
			}

			// Get uploads directory info.
			$uploads = wp_get_upload_dir();

			// Parse URL to get path.
			$url_path = wp_parse_url( $src, PHP_URL_PATH );

			// Skip if no path.
			if ( ! $url_path ) {
				continue;
			}

			// Convert URL to path relative to uploads directory.
			$path = str_replace( wp_parse_url( $uploads['baseurl'], PHP_URL_PATH ) . '/', '', $url_path );

			// Skip if path is unchanged (means URL wasn't in uploads directory).
			if ( $path === $url_path ) {
				continue;
			}

			// Get the original extension.
			$path_parts = pathinfo( $path );
			$extension  = $path_parts['extension'] ?? '';
			$filename   = $path_parts['filename'] ?? '';

			// Skip if no extension or it's an svg.
			if ( ! $extension || 'svg' === $extension ) {
				continue;
			}

			// Set image ID.
			$image_id = $tags->get_attribute( 'data-mai-image-id' );
			$image_id = $image_id ?: $args['image_id'];
			$image_id = (int) $image_id;

			// Remove data-mai-image-id attribute.
			$tags->remove_attribute( 'data-mai-image-id' );

			// If we have an image ID, get the full size URL.
			if ( $image_id ) {
				$full_url = wp_get_attachment_image_url( $image_id, 'full' );
				if ( $full_url ) {
					$path_parts = pathinfo( wp_parse_url( $full_url, PHP_URL_PATH ) );
					$path       = str_replace( wp_parse_url( $uploads['baseurl'], PHP_URL_PATH ) . '/', '', wp_parse_url( $full_url, PHP_URL_PATH ) );
					$extension  = $path_parts['extension'] ?? 'jpg';
					$filename   = $path_parts['filename'] ?? '';
				}
			}

			// Get the full path to the original image.
			$original_path = $uploads['basedir'] . '/' . $path;

			// Set base widths and start srcset.
			$base_widths = [ 400, 800, 1200, 1600, 2400 ];
			$srcset      = [];

			// Filter widths based on max_width.
			$widths = array_filter( $base_widths, function( $w ) use ( $args ) {
				// Don't exceed the max_width.
				return $w <= $args['max_width'];
			} );

			// Build srcset.
			$all_webp_available = true;
			foreach ( $widths as $w ) {
				$height = null;

				// If we have an aspect ratio, calculate the height based on the width and aspect ratio.
				if ( $args['aspect_ratio'] ) {
					// Parse aspect ratio (e.g., "16/9" or "1.5").
					$ratio = $args['aspect_ratio'];
					if ( str_contains( $ratio, '/' ) ) {
						// Format is "width/height".
						list( $ratio_width, $ratio_height ) = explode( '/', $ratio );
						$ratio = (float) $ratio_width / (float) $ratio_height;
					} else {
						// Format is decimal (e.g., "1.5").
						$ratio = (float) $ratio;
					}

					// Calculate height based on width and aspect ratio.
					$height = round( $w / $ratio );
				}

				// Check for cached file and queue for processing if needed.
				$result = $this->check_cached_file( $original_path, $filename, $w, $height );

				// If not successful, mark as not all available.
				if ( ! $result['success'] ) {
					$all_webp_available = false;
				}

				$srcset[] = "{$result['url']} {$w}w";
			}

			// Check if all size values are the same using array_unique.
			$unique_sizes = array_unique( array_values( $args['sizes'] ) );

			if ( 1 === count( $unique_sizes ) ) {
				// If all sizes are the same, just use that single value.
				$sizes = reset( $unique_sizes );
			} else {
				// Build sizes attribute using our responsive sizes.
				$sizes_parts = [];

				// Add mobile size as the default (no breakpoint).
				if ( isset( $args['sizes']['mobile'] ) ) {
					$sizes_parts[] = $args['sizes']['mobile'];
				}

				// Add tablet size with a breakpoint.
				if ( isset( $args['sizes']['tablet'] ) ) {
					$sizes_parts[] = '(min-width: ' . $args['breakpoints']['tablet'] . 'px) ' . $args['sizes']['tablet'];
				}

				// Add desktop size with a breakpoint.
				if ( isset( $args['sizes']['desktop'] ) ) {
					$sizes_parts[] = '(min-width: ' . $args['breakpoints']['desktop'] . 'px) ' . $args['sizes']['desktop'];
				}

				// Back to string.
				$sizes = implode( ', ', $sizes_parts );
			}

			// Calculate height for src URL if needed.
			$src_height = null;
			if ( $args['aspect_ratio'] ) {
				// Parse aspect ratio (e.g., "16/9" or "1.5").
				$ratio = $args['aspect_ratio'];
				if ( str_contains( $ratio, '/' ) ) {
					// Format is "width/height".
					list( $ratio_width, $ratio_height ) = explode( '/', $ratio );
					$ratio = (float) $ratio_width / (float) $ratio_height;
				} else {
					// Format is decimal (e.g., "1.5").
					$ratio = (float) $ratio;
				}

				// Calculate height based on width and aspect ratio.
				$src_height = round( $args['src_width'] / $ratio );
			}

			// Check for cached file and queue for processing if needed.
			$src_result = $this->check_cached_file( $original_path, $filename, $args['src_width'], $src_height );

			// If src is not successful, mark as not all available.
			if ( ! $src_result['success'] ) {
				$all_webp_available = false;
			}

			// Only modify attributes if all WebP versions are available.
			if ( $all_webp_available ) {
				// Set the attributes array.
				$attr = [
					'src'    => $src_result['url'],
					'srcset' => implode( ', ', $srcset ),
					'sizes'  => $sizes,
				];

				// Filter the attributes.
				$attr = apply_filters( 'mai_performance_images_image_attributes', $attr, $args );

				// Set the attributes.
				$tags->set_attribute( 'data-mai-image', esc_attr( wp_json_encode( $attr ) ) );
			}

			// Increment processed count.
			$images_processed++;

			// Break if we've hit our max (unless max_images is 0 which means process all).
			if ( $args['max_images'] > 0 && $images_processed >= $args['max_images'] ) {
				break;
			}
		}

		return $tags->get_updated_html();
	}

	/**
	 * Set properties.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function set_properties(): void {
		// If sizes are not set.
		if ( ! isset( $this->content_size, $this->wide_size ) ) {
			// Get theme.json layout sizes.
			$settings           = wp_get_global_settings();
			$this->content_size = isset( $settings['layout']['contentSize'] ) ? (int) str_replace( 'px', '', $settings['layout']['contentSize'] ) : 800;
			$this->wide_size    = isset( $settings['layout']['wideSize'] ) ? (int) str_replace( 'px', '', $settings['layout']['wideSize'] ) : 1200;
		}

		// If breakpoints are not set.
		if ( ! isset( $this->tablet_breakpoint, $this->desktop_breakpoint ) ) {
			// Set the breakpoints.
			$this->tablet_breakpoint  = 782; // WP's min-width where columns are no longer stacked.
			$this->desktop_breakpoint = 960; // I just picked this one.
		}

		// Typecast to int.
		$this->content_size       = (int) $this->content_size;
		$this->wide_size          = (int) $this->wide_size;
		$this->tablet_breakpoint  = (int) $this->tablet_breakpoint;
		$this->desktop_breakpoint = (int) $this->desktop_breakpoint;
	}

	/**
	 * Check if a cached file exists and queue it for processing if needed.
	 *
	 * @since 0.1.0
	 *
	 * @param string $original_path The original image path.
	 * @param string $filename      The filename without extension.
	 * @param int    $width         The target width.
	 * @param int    $height        The target height.
	 *
	 * @return array {
	 *     The result of the check.
	 *
	 *     @type bool   $success Whether the WebP version exists and is ready.
	 *     @type string $url     The URL to use for the image.
	 * }
	 */
	protected function check_cached_file( string $original_path, string $filename, int $width, ?int $height = null ): array {
		// Get uploads directory info.
		$uploads = wp_get_upload_dir();

		// Get the relative path from uploads directory.
		$relative_path = str_replace( $uploads['basedir'] . '/', '', $original_path );
		$dir_name      = dirname( $relative_path );

		// Generate cache path in the mai-performance-images directory, preserving the original directory structure.
		$cache_dir  = $uploads['basedir'] . '/mai-performance-images' . ( '.' === $dir_name ? '' : '/' . $dir_name );
		$cache_path = $cache_dir . '/' . $filename . '-' . $width . 'x' . ( $height ?? 'auto' ) . '.webp';

		// Check if cached file exists and is not empty.
		if ( file_exists( $cache_path ) && filesize( $cache_path ) > 0 ) {
			// Get file modification time.
			$cache_time = filemtime( $cache_path );

			// Check if original file exists before getting its modification time.
			if ( ! file_exists( $original_path ) ) {
				// If original file doesn't exist, return the cached version.
				return [
					'success' => true,
					'url'     => str_replace( $uploads['basedir'], $uploads['baseurl'], $cache_path ),
				];
			}

			$original_time = filemtime( $original_path );

			// If cache is newer than original, we're good.
			if ( $cache_time > $original_time ) {
				return [
					'success' => true,
					'url'     => str_replace( $uploads['basedir'], $uploads['baseurl'], $cache_path ),
				];
			}
		}

		// Check if original file exists before adding to queue.
		if ( ! file_exists( $original_path ) ) {
			// If original file doesn't exist, return the original URL.
			return [
				'success' => false,
				'url'     => str_replace( $uploads['basedir'], $uploads['baseurl'], $original_path ),
			];
		}

		// Add to static queue items array.
		$queue_item = [
			'original_path' => $original_path,
			'cache_path'    => $cache_path,
			'width'         => $width,
			'height'        => $height,
		];

		// Add to static queue items array.
		self::$queue_items[] = $queue_item;

		// Return the original image URL for now.
		return [
			'success' => false,
			'url'     => str_replace( $uploads['basedir'], $uploads['baseurl'], $original_path ),
		];
	}

	/**
	 * Process the queue at shutdown.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function process_queue(): void {
		// If no items to process, bail.
		if ( empty( self::$queue_items ) ) {
			return;
		}

		$has_items = false;

		// Get all batches once.
		$batches = $this->queue->get_batches();

		// Process each item.
		foreach ( self::$queue_items as $item ) {
			// Check if item is already in queue.
			if ( ! $this->is_item_in_queue( $item, $batches ) ) {
				$this->queue->push_to_queue( $item );
				$has_items = true;
			}
		}

		// If we added any items, save and dispatch the queue.
		if ( $has_items ) {
			$this->queue->save()->dispatch();
			$this->logger->info( 'New items added to queue and dispatched' );
		}

		// Clear the static array.
		self::$queue_items = [];
	}

	/**
	 * Check if an item is already in the queue.
	 *
	 * @since 0.1.0
	 *
	 * @param array $item    The item to check.
	 * @param array $batches The queue batches.
	 *
	 * @return bool
	 */
	protected function is_item_in_queue( array $item, array $batches ): bool {
		// If no batches, item is not in queue.
		if ( empty( $batches ) ) {
			return false;
		}

		// Check each batch for the item.
		foreach ( $batches as $batch ) {
			foreach ( $batch->data as $queued_item ) {
				if (
					$queued_item['original_path'] === $item['original_path'] &&
					$queued_item['cache_path'] === $item['cache_path'] &&
					$queued_item['width'] === $item['width'] &&
					$queued_item['height'] === $item['height']
				) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get the image size by name.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name The name of the image size.
	 *
	 * @return int|null
	 */
	public function get_image_size( string $name ): ?int {
		/** @disregard P1010 */
		$sizes = $this->get_available_image_sizes();
		return $sizes[ $name ]['width'] ?? null;
	}

	/**
	 * Get a combined list of default and custom registered image sizes.
	 * Originally taken from CMB2. Static variable added here.
	 *
	 * @since  0.1.0
	 *
	 * @link   http://core.trac.wordpress.org/ticket/18947
	 * @global array $_wp_additional_image_sizes All image sizes.
	 *
	 * @return array
	 */
	public function get_available_image_sizes(): array {
		static $image_sizes = null;

		if ( ! is_null( $image_sizes ) ) {
			return $image_sizes;
		}

		$image_sizes = [];

		// Get image sizes.
		global $_wp_additional_image_sizes;
		$default_image_sizes = [ 'thumbnail', 'medium', 'large' ];

		foreach ( $default_image_sizes as $size ) {
			$image_sizes[ $size ] = [
				'height' => intval( get_option( "{$size}_size_h" ) ),
				'width'  => intval( get_option( "{$size}_size_w" ) ),
				'crop'   => get_option( "{$size}_crop" ) ? get_option( "{$size}_crop" ) : false,
			];
		}

		if ( isset( $_wp_additional_image_sizes ) && count( $_wp_additional_image_sizes ) ) {
			$image_sizes = array_merge( $image_sizes, $_wp_additional_image_sizes );
		}

		return $image_sizes;
	}
}
