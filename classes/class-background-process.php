<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mai Performance Image Queue class.
 *
 * @since 0.1.0
 */
class BackgroundProcess extends \MaiPerformanceImages_WP_Background_Process {
	/**
	 * The logger instance.
	 *
	 * @since 0.1.0
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * The image processor instance.
	 *
	 * @since 0.1.0
	 *
	 * @var ImageProcessor
	 */
	private $processor;

	/**
	 * The download manager instance.
	 *
	 * @since 0.4.0
	 *
	 * @var DownloadManager
	 */
	private $download_manager;

	/**
	 * @var string
	 */
	protected $prefix = 'mai_performance_images';

	/**
	 * Action identifier.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	protected $action = 'processor';

	/**
	 * Get the logger instance.
	 *
	 * @since 0.1.0
	 *
	 * @return Logger
	 */
	protected function get_logger(): Logger {
		if ( null === $this->logger ) {
			$this->logger = Logger::get_instance();
		}
		return $this->logger;
	}

	/**
	 * Get the processor instance.
	 *
	 * @since 0.1.0
	 *
	 * @return ImageProcessor
	 */
	protected function get_processor(): ImageProcessor {
		if ( null === $this->processor ) {
			$this->processor = new ImageProcessor();
		}
		return $this->processor;
	}

	/**
	 * Get the download manager instance.
	 *
	 * @since 0.4.0
	 *
	 * @return DownloadManager
	 */
	protected function get_download_manager(): DownloadManager {
		if ( null === $this->download_manager ) {
			$this->download_manager = DownloadManager::get_instance();
		}
		return $this->download_manager;
	}

	/**
	 * Task to perform on each item in the queue.
	 *
	 * @since 0.1.0
	 *
	 * @param array $item Queue item to iterate over.
	 *
	 * @return bool|array
	 */
	protected function task( $item ) {
		// Parse args with defaults.
		/** @disregard P1008 */
		$args = wp_parse_args( $item, [
			'original_path' => '',
			'original_url'  => '',
			'cache_path'    => '',
			'width'         => 0,
			'height'        => null,
			'is_external'   => false,
		] );

		// Handle external images.
		if ( $args['is_external'] && $args['original_url'] ) {
			return $this->process_external_image( $args );
		}

		// Validate required arguments for local images.
		if ( ! $args['original_path'] || ! $args['cache_path'] || ! $args['width'] ) {
			$this->get_logger()->error( sprintf(
				'Missing required arguments - original_path: %s, cache_path: %s, width: %s',
				$args['original_path'] ? 'set' : 'missing',
				$args['cache_path'] ? 'set' : 'missing',
				$args['width'] ? 'set' : 'missing'
			) );
			return false;
		}

		// Check if the image is already processed and valid.
		if ( file_exists( $args['cache_path'] ) ) {
			// Check if file is not empty.
			if ( filesize( $args['cache_path'] ) > 0 ) {
				// Get file modification time.
				$cache_time    = filemtime( $args['cache_path'] );
				$original_time = filemtime( $args['original_path'] );

				// If cache is newer than original, we're good.
				if ( $cache_time > $original_time ) {
					return false;
				}
			} else {
				// File exists but is empty, remove it.
				@unlink( $args['cache_path'] );
			}
		}

		// Process the image.
		try {
			$result = $this->get_processor()->process_image( $args['original_path'], $args['cache_path'], $args['width'], $args['height'] );

			// If processing failed, return false to retry.
			if ( ! $result['success'] ) {
				$this->get_logger()->error( 'Image processing failed', [
					'error' => $result['error'] ?? 'Unknown error',
					'path'  => $args['original_path']
				] );
				return false;
			}

			// Return false to indicate task is complete and shouldn't be requeued.
			return false;

		} catch ( \Exception $e ) {
			$this->get_logger()->error( 'Image processing failed', [
				'error' => $e->getMessage(),
				'path'  => $args['original_path']
			] );

			return false;
		}
	}

	/**
	 * Process an external image by downloading it first.
	 *
	 * @since 0.4.0
	 *
	 * @param array $args The processing arguments.
	 *
	 * @return bool|array
	 */
	protected function process_external_image( array $args ) {
		// Validate required arguments for external images.
		if ( ! $args['original_url'] || ! $args['cache_path'] || ! $args['width'] ) {
			$this->get_logger()->error( sprintf(
				'Missing required arguments for external image - original_url: %s, cache_path: %s, width: %s',
				$args['original_url'] ? 'set' : 'missing',
				$args['cache_path'] ? 'set' : 'missing',
				$args['width'] ? 'set' : 'missing'
			) );
			return false;
		}

		// Check if the cache already exists and is valid.
		if ( file_exists( $args['cache_path'] ) && filesize( $args['cache_path'] ) > 0 ) {
			// For external images, we can't check file modification times reliably,
			// so we assume the cache is valid if it exists and is not empty.
			return false;
		}

		// Use the already downloaded file path from the queue item.
		$temp_path = $args['original_path'];

		// If the original_path is "external-cached" or the file doesn't exist, download it.
		if ( $temp_path === 'external-cached' || ! file_exists( $temp_path ) ) {
			// Download the image to a temporary location.
			$temp_path = $this->get_download_manager()->download_image( $args['original_url'] );

			// Handle failed download.
			if ( ! $temp_path ) {
				$this->get_logger()->error( 'Failed to download external image', [
					'url' => $args['original_url']
				] );
				return false;
			}
		}

		// Process the downloaded image.
		try {
			$result = $this->get_processor()->process_image( $temp_path, $args['cache_path'], $args['width'], $args['height'] );

			// Clean up temp file immediately after processing.
			@unlink( $temp_path );

			// If processing failed, return false to retry.
			if ( ! $result['success'] ) {
				$this->get_logger()->error( 'External image processing failed', [
					'error' => $result['error'] ?? 'Unknown error',
					'url'   => $args['original_url']
				] );
				return false;
			}

			$this->get_logger()->info( 'Successfully processed external image', [
				'url'        => $args['original_url'],
				'cache_path' => $args['cache_path']
			] );

			// Return false to indicate task is complete and shouldn't be requeued.
			return false;

		} catch ( \Exception $e ) {
			// Clean up temp file on error.
			@unlink( $temp_path );

			$this->get_logger()->error( 'External image processing failed', [
				'error' => $e->getMessage(),
				'url'   => $args['original_url']
			] );

			return false;
		}
	}

	/**
	 * Complete the queue processing.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function complete() {
		/** @disregard P1013 */
		parent::complete();
		$this->get_logger()->info( 'Image queue processing completed' );
	}
}