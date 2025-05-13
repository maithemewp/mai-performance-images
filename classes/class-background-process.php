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
			'cache_path'    => '',
			'width'         => 0,
			'height'        => null,
		] );

		// Validate required arguments.
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

				// If cache is older than original, we should reprocess.
				$this->get_logger()->info( sprintf(
					'Reprocessing image as original is newer - cache: %s, original: %s',
					$args['cache_path'],
					$args['original_path']
				) );
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