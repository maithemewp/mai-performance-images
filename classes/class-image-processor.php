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
		// Background processing.
		add_action( 'mai_process_image', [ $this, 'process_background_image' ] );
	}

	/**
	 * Handle image processing request.
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
	public function process_image( $original_path, $cache_path, $width, $height = null ) {
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

		// Set lock duration and key.
		$lock_duration = 30; // seconds
		$lock_key      = 'mai_processing_' . md5( $cache_path );

		// First check if there's an existing lock.
		if ( get_transient( $lock_key ) ) {
			// Get lock time.
			$lock_time = get_transient( $lock_key . '_time' );

			// If the lock is less than the duration old, bail.
			if ( $lock_time && ( time() - $lock_time < $lock_duration ) ) {
				// Return the original image for now
				$extension = pathinfo( $original_path, PATHINFO_EXTENSION );
				return [
					'success'   => true,
					'file_path' => $original_path,
					'mime_type' => 'image/' . $extension,
				];
			}
		}

		// Set processing lock with timestamp.
		set_transient( $lock_key, true, $lock_duration );
		set_transient( $lock_key . '_time', time(), $lock_duration );

		try {
			// Initialize ImageManager.
			// Check if the Driver class exists.
			if ( ! class_exists( Driver::class ) ) {
				throw new \Exception( 'ImageManager Driver class not found' );
			}

			$manager = new ImageManager(
				Driver::class,
				[
					'autoOrientation' => false,
					'decodeAnimation' => true,
					'blendingColor'   => 'ffffff',
					'strip'           => true,
				]
			);

			// Read image.
			$image = $manager->read( $original_path );

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

			// Save.
			$image->save( $cache_path, 'webp', $quality );

			// Verify.
			if ( ! file_exists( $cache_path ) || filesize( $cache_path ) === 0 ) {
				throw new \Exception( 'Failed to save processed image or file is empty' );
			}

			// Return the processed image
			return [
				'success'   => true,
				'file_path' => $cache_path,
				'mime_type' => 'image/webp',
			];

		} catch ( \Exception $e ) {
			// Log the error.
			$this->logger->error( sprintf(
				'Error processing image %s - %s',
				$original_path,
				$e->getMessage()
			) );

			// Clean up any partially processed files.
			if ( file_exists( $cache_path ) ) {
				@unlink( $cache_path );
			}

			// Return the original image
			$extension = pathinfo( $original_path, PATHINFO_EXTENSION );
			return [
				'success'   => true,
				'file_path' => $original_path,
				'mime_type' => 'image/' . $extension,
			];
		} finally {
			// Clean up the lock
			delete_transient( $lock_key );
			delete_transient( $lock_key . '_time' );
		}
	}

	/**
	 * Process image resize and conversion in the background.
	 *
	 * @since TBD
	 *
	 * @param array $args {
	 *     The processing arguments.
	 *
	 *     @type string $original_path Path to the original image file.
	 *     @type string $cache_path    Path where the processed image should be saved.
	 *     @type int    $width         Target width for the processed image.
	 *     @type int    $height        Optional. Target height for the processed image.
	 *     @type int    $retry_count   Optional. Number of times this processing has been retried.
	 * }
	 *
	 * @return void
	 */
	public function process_background_image( $args ) {
		// Parse args with defaults.
		$args = wp_parse_args( $args, [
			'original_path' => '',
			'cache_path'    => '',
			'width'         => 0,
			'height'        => null,
			'retry_count'   => 0,
		] );

		// Validate required arguments.
		if ( ! $args['original_path'] || ! $args['cache_path'] || ! $args['width'] ) {
			$this->logger->error( sprintf(
				'Missing required arguments - original_path: %s, cache_path: %s, width: %s',
				$args['original_path'] ? 'set' : 'missing',
				$args['cache_path'] ? 'set' : 'missing',
				$args['width'] ? 'set' : 'missing'
			) );
			return;
		}

		// Check if the image is already processed
		if ( file_exists( $args['cache_path'] ) && filesize( $args['cache_path'] ) > 0 ) {
			return;
		}

		// Set lock duration and key.
		$lock_duration = 10; // seconds - reduced from 30
		$lock_key      = 'mai_processing_' . md5( $args['cache_path'] );

		// First check if there's an existing lock.
		if ( get_transient( $lock_key ) ) {
			// Check if this is a retry attempt.
			$is_retry = $args['retry_count'] > 0;

			// Get lock time.
			$lock_time = get_transient( $lock_key . '_time' );

			// If this is not a retry and the lock is less than the duration old, bail.
			if ( ! $is_retry && $lock_time && ( time() - $lock_time < $lock_duration ) ) {
				return;
			}

			// Double check if image was created while we were locked.
			if ( file_exists( $args['cache_path'] ) && filesize( $args['cache_path'] ) > 0 ) {
				delete_transient( $lock_key );
				delete_transient( $lock_key . '_time' );
				return;
			}

			// Clear the old lock if it's expired
			if ( $lock_time && ( time() - $lock_time >= $lock_duration ) ) {
				delete_transient( $lock_key );
				delete_transient( $lock_key . '_time' );
			}
		}

		// Set processing lock with timestamp.
		set_transient( $lock_key, true, $lock_duration );
		set_transient( $lock_key . '_time', time(), $lock_duration );

		try {
			// Check if the original file exists.
			if ( ! file_exists( $args['original_path'] ) ) {
				throw new \Exception( 'Original image file not found: ' . $args['original_path'] );
			}

			// One final check before processing.
			if ( file_exists( $args['cache_path'] ) && filesize( $args['cache_path'] ) > 0 ) {
				return;
			}

			// Initialize ImageManager.
			// Check if the Driver class exists.
			if ( ! class_exists( Driver::class ) ) {
				throw new \Exception( 'ImageManager Driver class not found' );
			}

			$manager = new ImageManager(
				Driver::class,
				[
					'autoOrientation' => false,
					'decodeAnimation' => true,
					'blendingColor'   => 'ffffff',
					'strip'           => true,
				]
			);

			// Read image.
			$image = $manager->read( $args['original_path'] );

			// Resize.
			if ( $args['height'] ) {
				$image->cover( $args['width'], $args['height'] );
			} else {
				$image->scaleDown( $args['width'] );
			}

			// Get image quality.
			$quality = apply_filters( 'mai_performance_images_image_quality', 80, $args );

			// Save.
			$image->save( $args['cache_path'], 'webp', $quality );

			// Verify.
			if ( ! file_exists( $args['cache_path'] ) || filesize( $args['cache_path'] ) === 0 ) {
				throw new \Exception( 'Failed to save processed image or file is empty' );
			}

		} catch ( \Exception $e ) {
			// Log the error.
			$this->logger->error( sprintf(
				'Error processing image %s - %s',
				$args['original_path'],
				$e->getMessage()
			) );

			// Clean up any partially processed files.
			if ( file_exists( $args['cache_path'] ) ) {
				@unlink( $args['cache_path'] );
			}

			// Get the max retry count.
			$max_retries = (int) apply_filters( 'mai_performance_images_max_retries', 3 );

			// Schedule retry if under retry limit.
			if ( $args['retry_count'] < $max_retries ) {
				$args['retry_count']++;

				// For retries, we'll use a 3-minute interval.
				wp_schedule_single_event(
					time() + ( 3 * MINUTE_IN_SECONDS ),
					'mai_process_image',
					[ $args ]
				);

				// Keep the lock for retries.
				return;
			}

			// If we've exceeded retry attempts, clean up the lock.
			$this->logger->error( sprintf(
				'Exceeded max retries for image: %s',
				$args['original_path']
			) );
			delete_transient( $lock_key );
			delete_transient( $lock_key . '_time' );

		} finally {
			// Only clean up the lock if processing was successful.
			if ( ! isset( $e ) ) {
				delete_transient( $lock_key );
				delete_transient( $lock_key . '_time' );
			}
		}
	}
}
