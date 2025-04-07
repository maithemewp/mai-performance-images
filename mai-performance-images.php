<?php
/**
 * Plugin Name:       Mai Performance Images
 * Description:       Adds dynamic image loading to the block editor.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.0
 * Author:            JiveDig
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mai-performance-images
 */

namespace Mai\Performance;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Include vendor files.
require_once __DIR__ . '/vendor/autoload.php';

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;

/**
 * Flush rewrite rules on activation.
 *
 * @since 0.1.0
 *
 * @return void
 */
register_activation_hook( __FILE__, function() {
	flush_rewrite_rules();
} );

/**
 * Mai Performance Images class.
 *
 * @since 0.1.0
 */
final class Images {
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
		// Admin editor assets.
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );

		// Block loading filters.
		add_filter( 'render_block_core/cover',               [ $this, 'render_loading_attribute' ], 10, 2 );
		add_filter( 'render_block_core/image',               [ $this, 'render_loading_attribute' ], 10, 2 );
		add_filter( 'render_block_core/site-logo',           [ $this, 'render_loading_attribute' ], 10, 2 );
		add_filter( 'render_block_core/post-featured-image', [ $this, 'render_loading_attribute' ], 10, 2 );

		// Block image filters.
		add_filter( 'render_block_core/cover',               [ $this, 'render_image_block' ], 99, 2 );
		add_filter( 'render_block_core/image',               [ $this, 'render_image_block' ], 99, 2 );
		add_filter( 'render_block_core/post-featured-image', [ $this, 'render_image_block' ], 99, 2 );
		add_filter( 'render_block_core/media-text',          [ $this, 'render_media_text_block' ], 99, 2 );
		add_filter( 'render_block_core/site-logo',           [ $this, 'render_site_logo_block' ], 99, 2 );

		// Image attribute filter.
		add_filter( 'wp_content_img_tag', [ $this, 'filter_content_img_tag' ], 999, 3 );

		// Handle image requests.
		add_action( 'init',               [ $this, 'set_image_sizes' ] );
		add_action( 'init',               [ $this, 'register_rewrite_rules' ] );
		add_action( 'template_redirect',  [ $this, 'handle_image_request' ] );

		// Prevent trailing slash redirect for image URLs.
		add_filter( 'redirect_canonical', [ $this, 'prevent_trailing_slash_redirect' ], 10, 2 );
	}

	/**
	 * Enqueues the block editor assets.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		$asset_file = include( __DIR__ . '/build/block-settings.asset.php' );

		wp_enqueue_script(
			'mai-performance-images-block-settings',
			plugins_url( 'build/block-settings.js', __FILE__ ),
			$asset_file['dependencies'],
			$asset_file['version']
		);
	}

	/**
	 * Render the core/site-logo block.
	 *
	 * @since 0.1.0
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block.
	 *
	 * @return string The block content.
	 */
	public function render_loading_attribute( $block_content, $block ) {
		// Get the img loading attribute.
		$loading = $block['attrs']['imgLoading'] ?? '';

		// Bail if no loading attribute is set.
		if ( ! $loading ) {
			return $block_content;
		}

		// Set up tag processor.
		$tags = new \WP_HTML_Tag_Processor( $block_content );

		// Set up args.
		$args = [
			'tag_name' => 'img',
		];

		// Add class check for cover block.
		// This insures only the background image is handled,
		// not any inner blocks.
		if ( 'core/cover' === $block['blockName'] ) {
			$args['class'] = 'wp-block-cover__background';
		}

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'img' ] ) ) {
			// Add loading attribute.
			$tags->set_attribute( 'data-mai-loading', $loading );
		}

		// Get updated block content.
		$block_content = $tags->get_updated_html();

		return $block_content;
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

		return $this->process_image( $block_content, $args );
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

		return $this->process_image( $block_content, $args );
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

		$block_content = $this->process_image( $block_content, [
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
	public function process_image( $html, $args = [] ) {
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

	/**
	 * Handle image request via template_redirect.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function handle_image_request() {
		// Check if this is an image request by looking at the URL path.
		$request_uri = $_SERVER['REQUEST_URI'];
		if ( ! str_contains( $request_uri, '/mai-performance-images/' ) ) {
			return;
		}

		// Extract the path from the URL.
		$path_parts = explode( '/mai-performance-images/', $request_uri );
		if ( 2 !== count( $path_parts ) ) {
			return;
		}

		// Get the image path.
		$image_path = $path_parts[1];

		// Parse the path to get dimensions and format.
		if ( ! preg_match( '/^(.+?)-([0-9]+)x([0-9]+|auto)\.(jpg|jpeg|png|gif|webp)$/', $image_path, $matches ) ) {
			return;
		}

		// Extract the parameters.
		$base_path = $matches[1];
		$width     = (int) $matches[2];
		$height    = 'auto' === $matches[3] ? null : (int) $matches[3];
		$extension = $matches[4];

		// Get the original image path from WordPress.
		$upload_dir = wp_get_upload_dir();

		// Try the original uploads location first.
		$original_path = $upload_dir['basedir'] . '/' . urldecode( $base_path ) . '.' . $extension;

		// If not found, try in mai-performance-images directory.
		if ( ! file_exists( $original_path ) ) {
			$original_path = $upload_dir['basedir'] . '/mai-performance-images/' . urldecode( $base_path ) . '.' . $extension;
		}

		// Check if file exists.
		if ( ! file_exists( $original_path ) ) {
			header( 'HTTP/1.1 404 Not Found' );
			exit( 'Image file not found' );
		}

		// Initialize the image manager.
		$manager = new ImageManager(
			Driver::class,
			autoOrientation: false,
			decodeAnimation: true,
			blendingColor: 'ffffff',
			strip: true,
		);

		// Generate a cache filename that preserves the original path structure.
		$cache_filename = $base_path . '-' . $width . 'x' . ( $height ?? 'auto' ) . '.webp';
		$cache_path     = $upload_dir['basedir'] . '/mai-performance-images/' . $cache_filename;

		// Create cache directory if it doesn't exist.
		$cache_dir = dirname( $cache_path );
		if ( ! file_exists( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
		}

		// Check if we already have a cached version.
		if ( file_exists( $cache_path ) ) {
			header( 'Content-Type: image/webp' );
			readfile( $cache_path );
			exit;
		}

		// Process the image.
		$image = $manager->read( $original_path );

		// Resize the image.
		if ( $height ) {
			// If both dimensions are specified, crop to exact size.
			$image->cover( $width, $height );
		} else {
			// If only width is specified, scale down maintaining aspect ratio.
			$image->scaleDown( $width, $height );
		}

		// Save the processed image.
		$image->save( $cache_path, 'webp', 80 );

		// Serve the processed image.
		header( 'Content-Type: image/webp' );
		readfile( $cache_path );
		exit;
	}

	/**
	 * Register rewrite rules for image URLs.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_rewrite_rules() {
		// Register the rewrite rule for image paths.
		add_rewrite_rule(
			'^mai-performance-images/(.+?)-([0-9]+)x([0-9]+|auto)\.(jpg|jpeg|png|gif|webp)$',
			'index.php?maipi_path=$1&maipi_width=$2&maipi_height=$3&maipi_ext=$4',
			'top'
		);

		// Register the query vars.
		add_filter('query_vars', function($vars) {
			$vars[] = 'maipi_path';
			$vars[] = 'maipi_width';
			$vars[] = 'maipi_height';
			$vars[] = 'maipi_ext';
			return $vars;
		});
	}

	/**
	 * Prevent trailing slash redirect for image URLs.
	 *
	 * @since 0.1.0
	 *
	 * @param string $redirect_url  The redirect URL.
	 * @param string $requested_url The requested URL.
	 *
	 * @return string|false
	 */
	public function prevent_trailing_slash_redirect( $redirect_url, $requested_url ) {
		// Check if this is an image request.
		if ( str_contains( $requested_url, '/mai-performance-images/' ) ) {
			return false;
		}

		return $redirect_url;
	}
}

// Initialize the class.
new Images;
