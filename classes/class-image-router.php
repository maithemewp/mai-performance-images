<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mai Performance Image Router class.
 *
 * @since 0.1.0
 */
final class ImageRouter {
	/**
	 * The image processor instance.
	 *
	 * @since 0.1.0
	 *
	 * @var ImageProcessor
	 */
	private $processor;

	/**
	 * The logger instance.
	 *
	 * @since 0.1.0
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param ImageProcessor $processor The image processor instance.
	 *
	 * @return void
	 */
	public function __construct( ImageProcessor $processor ) {
		$this->processor = $processor;
		$this->logger = Logger::get_instance();
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
		add_action( 'init',               [ $this, 'register_rewrite_rules' ] );
		add_action( 'template_redirect',  [ $this, 'handle_image_request' ] );
		add_filter( 'redirect_canonical', [ $this, 'prevent_trailing_slash_redirect' ], 10, 2 );
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
			status_header( 404 );
			exit( 'Invalid image path' );
		}

		// Get the image path.
		$image_path = $path_parts[1];

		// Parse the path to get dimensions and format.
		if ( ! preg_match( '/^(.+?)-([0-9]+)x([0-9]+|auto)\.(jpg|jpeg|png|gif|webp)$/', $image_path, $matches ) ) {
			status_header( 404 );
			exit( 'Invalid image format' );
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
			status_header( 404 );
			exit( 'Image file not found' );
		}

		// Get the directory structure from the base_path
		$path_info = pathinfo( $base_path );
		$dirname   = $path_info['dirname'];
		$filename  = $path_info['filename'];

		// Handle root-level files (when dirname is '.' or empty)
		$dirname = ($dirname === '.' || empty($dirname)) ? '' : $dirname;

		// Generate cache paths preserving the original directory structure
		$cache_dir  = rtrim($upload_dir['basedir'] . '/mai-performance-images/' . $dirname, '/');
		$cache_path = $cache_dir . '/' . $filename . '-' . $width . 'x' . ( $height ?? 'auto' ) . '.webp';

		// Process the image
		$result = $this->processor->handle_image( $original_path, $cache_path, $width, $height );

		// Check for success
		if ( ! $result['success'] ) {
			status_header( 500 );
			exit( $result['error'] ?? 'Failed to process image' );
		}

		// Serve the image
		status_header( 200 );
		header( 'Content-Type: ' . $result['mime_type'] );
		readfile( $result['file_path'] );
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
