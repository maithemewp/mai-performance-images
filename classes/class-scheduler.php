<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mai Performance Images Scheduler class.
 *
 * @since 0.1.0
 */
final class Scheduler {
	/**
	 * The cache manager instance.
	 *
	 * @since 0.1.0
	 *
	 * @var ImageCacheManager
	 */
	private $cache_manager;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function __construct() {
		$this->cache_manager = new ImageCacheManager();
		$this->init();
	}

	/**
	 * Initialize the scheduler.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function init(): void {
		// Register the action hook.
		add_action( 'mai_performance_images_cleanup_cache', [ $this, 'cleanup_cache' ] );

		// Schedule the event if not already scheduled.
		if ( ! wp_next_scheduled( 'mai_performance_images_cleanup_cache' ) ) {
			wp_schedule_event( time(), 'daily', 'mai_performance_images_cleanup_cache' );
		}
	}

	/**
	 * Clean up cache files.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function cleanup_cache(): void {
		$this->cache_manager->cleanup();
	}

	/**
	 * Clear scheduled events.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function clear_scheduled_events(): void {
		wp_clear_scheduled_hook( 'mai_performance_images_cleanup_cache' );
	}
}