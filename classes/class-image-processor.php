<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;

/**
 * Mai Performance Image Processor class.
 *
 * @since 0.1.0
 */
final class ImageProcessor {
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
	 * @return void
	 */
	public function __construct() {
		$this->logger = Logger::get_instance();
	}

	/**
	 * Process an image.
	 *
	 * @since 0.1.0
	 *
	 * @param string $original_path Path to the original image.
	 * @param string $cache_path    Path where the processed image should be saved.
	 * @param int    $width         Target width for the processed image.
	 * @param int    $height        Optional. Target height for the processed image.
	 *
	 * @return array {
	 *     Response data
	 *
	 *     @type bool   $success     Whether the operation was successful.
	 *     @type string $file_path   Path to the file to serve.
	 *     @type string $mime_type   MIME type of the file to serve.
	 *     @type string $error       Error message if operation failed.
	 * }
	 */
	public function process_image( string $original_path, string $cache_path, int $width, ?int $height = null ): array {
		// Start performance monitoring.
		$start_time   = microtime( true );
		$start_memory = memory_get_usage();

		// Check if we already have a cached version.
		if ( file_exists( $cache_path ) && filesize( $cache_path ) > 0 ) {
			return [
				'success'   => true,
				'file_path' => $cache_path,
				'mime_type' => 'image/webp',
			];
		}

		// Create cache directory if it doesn't exist.
		$cache_dir = dirname( $cache_path );
		if ( ! file_exists( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
		}

		// Check if the original file exists
		if ( ! file_exists( $original_path ) ) {
			$this->logger->error( sprintf(
				'Original image file not found: %s',
				$original_path
			) );

			return [
				'success' => false,
				'error'   => 'Original image file not found',
			];
		}

		try {
			// Initialize ImageManager.
			// Check if the Driver class exists.
			if ( ! class_exists( Driver::class ) ) {
				throw new \Exception( 'ImageManager Driver class not found' );
			}

			// Initialize ImageManager.
			$manager = new ImageManager(
				Driver::class,
				[
					'autoOrientation' => false,
					'decodeAnimation' => true,
					'blendingColor'   => 'ffffff',
					'strip'           => true,
				]
			);

			// Read image with memory check.
			if ( memory_get_usage( true ) > ( 256 * 1024 * 1024 ) ) { // 256MB
				throw new \Exception( 'Memory limit exceeded before processing image' );
			}

			$image = $manager->read( $original_path );

			// Resize with memory check.
			if ( memory_get_usage( true ) > ( 256 * 1024 * 1024 ) ) { // 256MB
				throw new \Exception( 'Memory limit exceeded during image processing' );
			}

			// Resize.
			if ( isset( $height ) && $height ) {
				$image->cover( $width, $height );
			} else {
				$image->scaleDown( $width );
			}

			// Get image quality.
			$quality = apply_filters( 'mai_performance_images_image_quality', 80, [
				'original_path' => $original_path,
				'cache_path'    => $cache_path,
				'width'         => $width,
				'height'        => $height,
			] );

			// Save with memory check.
			if ( memory_get_usage( true ) > ( 256 * 1024 * 1024 ) ) { // 256MB
				throw new \Exception( 'Memory limit exceeded before saving image' );
			}

			$image->save( $cache_path, 'webp', $quality );

			// Clear image from memory.
			$image   = null;
			$manager = null;
			gc_collect_cycles();

			// Calculate performance metrics.
			$processing_time   = microtime( true ) - $start_time;
			$memory_used       = memory_get_usage() - $start_memory;
			$original_size     = filesize( $original_path );
			$processed_size    = filesize( $cache_path );
			$compression_ratio = $original_size ? round( ( $original_size - $processed_size ) / $original_size * 100, 2 ) : 0;

			// Log performance metrics.
			$this->logger->error( sprintf(
				'Image processed in %s seconds, using %s memory. Original: %s, Processed: %s, Compression: %s%%',
				round( $processing_time, 4 ),
				size_format( $memory_used ),
				size_format( $original_size ),
				size_format( $processed_size ),
				$compression_ratio
			) );

			// Verify.
			if ( ! file_exists( $cache_path ) || filesize( $cache_path ) === 0 ) {
				throw new \Exception( 'Failed to save processed image or file is empty' );
			}

			// Return the processed image.
			return [
				'success'   => true,
				'file_path' => $cache_path,
				'mime_type' => 'image/webp',
			];

		} catch ( \Exception $e ) {
			// Log error with performance metrics.
			$this->logger->error( sprintf(
				'Error processing image %s - %s (Time: %s, Memory: %s)',
				$original_path,
				$e->getMessage(),
				round( microtime( true ) - $start_time, 4 ),
				size_format( memory_get_usage() - $start_memory )
			) );

			// Clean up any partially processed files.
			if ( file_exists( $cache_path ) ) {
				@unlink( $cache_path );
			}

			// Clear any remaining resources.
			if ( isset( $image ) ) {
				$image = null;
			}
			if ( isset( $manager ) ) {
				$manager = null;
			}
			gc_collect_cycles();

			// Return the original image.
			$extension = pathinfo( $original_path, PATHINFO_EXTENSION );
			return [
				'success'   => true,
				'file_path' => $original_path,
				'mime_type' => 'image/' . $extension,
			];
		}
	}
}
