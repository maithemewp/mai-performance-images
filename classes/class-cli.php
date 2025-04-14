<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mai Performance Images CLI class.
 *
 * @since 0.1.0
 */
final class CLI {
	/**
	 * The cache manager instance.
	 *
	 * @since 0.1.0
	 *
	 * @var ImageCacheManager|null
	 */
	private $cache_manager = null;

	/**
	 * The image queue instance.
	 *
	 * @since 0.1.0
	 *
	 * @var BackgroundProcess|null
	 */
	private $queue = null;

	/**
	 * Get the cache manager instance.
	 *
	 * @since 0.1.0
	 *
	 * @return ImageCacheManager
	 */
	private function get_cache_manager() {
		if ( null === $this->cache_manager ) {
			$this->cache_manager = new ImageCacheManager();
		}

		return $this->cache_manager;
	}

	/**
	 * Get the queue instance.
	 *
	 * @since 0.1.0
	 *
	 * @return BackgroundProcess
	 */
	private function get_queue() {
		if ( null === $this->queue ) {
			$this->queue = new BackgroundProcess();
		}

		return $this->queue;
	}

	/**
	 * Clean up old cache files.
	 *
	 * ## OPTIONS
	 *
	 * [--max-age=<days>]
	 * : Maximum age of cache files in days. Default 30.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mai-performance-images queue_cleanup
	 *     wp mai-performance-images queue_cleanup --max-age=60
	 *
	 * @since 0.1.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function queue_cleanup( $args, $assoc_args ) {
		// Get max age from command line or use default.
		$max_age = isset( $assoc_args['max-age'] ) ? (int) $assoc_args['max-age'] : null;

		// @disregard P1009.
		\WP_CLI::log( 'Starting cache cleanup...' );

		// Clean up cache.
		$result = $this->get_cache_manager()->cleanup( $max_age );

		// @disregard P1009.
		\WP_CLI::success( sprintf(
			'Cache cleanup complete. Removed %d files, skipped %d files.',
			$result['removed'],
			$result['skipped']
		) );
	}

	/**
	 * Display the queue status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mai-performance-images queue_status
	 *
	 * @since 0.1.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function queue_status( $args, $assoc_args ) {
		$queue = $this->get_queue();

		\WP_CLI::log( 'Queue Status:' );
		\WP_CLI::log( sprintf( '• Is Active:     %s', $queue->is_active() ? 'Yes' : 'No' ) );
		\WP_CLI::log( sprintf( '• Is Queued:     %s', $queue->is_queued() ? 'Yes' : 'No' ) );
		\WP_CLI::log( sprintf( '• Is Processing: %s', $queue->is_processing() ? 'Yes' : 'No' ) );
		\WP_CLI::log( sprintf( '• Is Paused:     %s', $queue->is_paused() ? 'Yes' : 'No' ) );
		\WP_CLI::log( sprintf( '• Is Cancelled:  %s', $queue->is_cancelled() ? 'Yes' : 'No' ) );
		\WP_CLI::log( sprintf( '• Batch Count:   %d', count( $queue->get_batches() ) ) );

		// Show batch details
		$batches = $queue->get_batches();
		if ( ! empty( $batches ) ) {
			\WP_CLI::log( "\nBatch Details:" );
			foreach ( $batches as $index => $batch ) {
				\WP_CLI::log( sprintf( "Batch %d:", $index + 1 ) );
				foreach ( $batch->data as $item ) {
					\WP_CLI::log( sprintf(
						"  • %s -> %s (%dx%d)",
						$item['original_path'],
						$item['cache_path'],
						$item['width'],
						$item['height'] ?? 'auto'
					) );
				}
			}
		}

		// If queue is not processing but has items, try to force process
		if ( $queue->is_queued() && ! $queue->is_processing() && ! $queue->is_paused() && ! $queue->is_cancelled() ) {
			\WP_CLI::log( "\nAttempting to process queue..." );
			$queue->maybe_handle();
			\WP_CLI::log( 'Queue processing started. Check status again in a few moments.' );
		}
	}

	/**
	 * Display the queue batches.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mai-performance-images queue_batches
	 *
	 * @since 0.1.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function queue_batches( $args, $assoc_args ) {
		\WP_CLI::log( 'Queue Batches:' );
		\WP_CLI::log( sprintf( '• Batch Count:   %d', count( $batches ) ) );

		if ( ! empty( $batches ) ) {
			\WP_CLI::log( "\nBatches:" );
			foreach ( $batches as $index => $batch ) {
				\WP_CLI::log( sprintf( 'Batch %d:', $index + 1 ) );
				foreach ( $batch->data as $item ) {
					\WP_CLI::log( sprintf(
						'  • %s -> %s (%dx%d)',
						$item['original_path'],
						$item['cache_path'],
						$item['width'],
						$item['height'] ?? 'auto'
					) );
				}
			}
		}
	}

	/**
	 * Delete all queues and their associated data.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mai-performance-images queue_delete
	 *
	 * @since 0.1.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function queue_delete( $args, $assoc_args ) {
		$this->get_queue()->delete_all();

		\WP_CLI::success( 'Deleted all queue batches and cleared all queue data.' );
	}
}