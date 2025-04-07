<?php

namespace Mai\Performance;

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
	public function handle_image( $original_path, $cache_path, $width, $height = null ) {
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

		// Schedule the processing if not already scheduled.
		$args = [
			'original_path' => $original_path,
			'cache_path'    => $cache_path,
			'width'         => $width,
			'height'        => $height,
		];

		// Check if already scheduled.
		$is_scheduled = wp_next_scheduled( 'mai_process_image', [ $args ] );

		// If not scheduled, schedule it.
		if ( ! $is_scheduled ) {
			// Try to schedule and capture the result.
			$scheduled = wp_schedule_single_event( time(), 'mai_process_image', [ $args ] );

			if ( ! $scheduled ) {
				$this->logger->error( sprintf(
					'Failed to schedule image processing for %s',
					$args['original_path']
				) );
			}

			// Verify it was actually scheduled.
			$next_run = wp_next_scheduled( 'mai_process_image', [ $args ] );

			if ( ! $next_run ) {
				$this->logger->error( sprintf(
					'Scheduling verification failed for %s',
					$args['original_path']
				) );
			}
		}

		// Get the original image extension.
		$extension = pathinfo( $original_path, PATHINFO_EXTENSION );

		// Return the original image path for now.
		return [
			'success'   => true,
			'file_path' => $original_path,
			'mime_type' => 'image/' . $extension,
		];
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
		// Validate required arguments.
		if ( ! isset( $args['original_path'], $args['cache_path'], $args['width'] ) ) {
			return;
		}

		// Check if the image is already processed
		if ( file_exists( $args['cache_path'] ) && filesize( $args['cache_path'] ) > 0 ) {
			return;
		}

		// Set lock duration and key.
		$lock_duration = 30; // seconds
		$lock_key      = 'mai_processing_' . md5( $args['cache_path'] );

		// First check if there's an existing lock.
		if ( get_transient( $lock_key ) ) {
			// Check if this is a retry attempt.
			$is_retry = isset( $args['retry_count'] ) && $args['retry_count'] > 0;

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
			if ( isset( $args['height'] ) && $args['height'] ) {
				$image->cover( $args['width'], $args['height'] );
			} else {
				$image->scaleDown( $args['width'] );
			}

			// Save.
			$image->save( $args['cache_path'], 'webp', 80 );

			// Verify.
			if ( ! file_exists( $args['cache_path'] ) || filesize( $args['cache_path'] ) === 0 ) {
				throw new \Exception( 'Failed to save processed image or file is empty' );
			}

			$this->logger->success( sprintf(
				'Successfully processed image: %s',
				$args['original_path']
			) );

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

			// Schedule retry if under retry limit.
			if ( ! isset( $args['retry_count'] ) || $args['retry_count'] < 3 ) {
				$args['retry_count'] = ( $args['retry_count'] ?? 0 ) + 1;

				// For retries, we'll still use the 5-minute interval.
				wp_schedule_single_event(
					time() + ( 5 * MINUTE_IN_SECONDS ),
					'mai_process_image',
					[ $args ]
				);

				// Keep the lock for retries.
				return;
			}

			// If we've exceeded retry attempts, clean up the lock.
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
