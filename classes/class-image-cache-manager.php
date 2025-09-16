<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mai Performance Image Cache Manager class.
 *
 * @since 0.1.0
 */
final class ImageCacheManager {
	/**
	 * The logger instance.
	 *
	 * @since 0.1.0
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Maximum age of cache files in seconds.
	 *
	 * @since 0.1.0
	 *
	 * @var int
	 */
	private $max_age;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function __construct() {
		$this->logger  = Logger::get_instance();

		// Get cache duration from settings.
		$options        = get_plugin_options();
		$cache_duration = $options['cache_duration'] ?? 30;
		$this->max_age  = $cache_duration * DAY_IN_SECONDS;
	}

	/**
	 * Clean up old cache files.
	 *
	 * @since 0.1.0
	 *
	 * @param int|null $max_age Optional. Maximum age in days. Default null (uses setting from admin).
	 *
	 * @return array {
	 *     Cleanup results.
	 *
	 *     @type int $removed Number of files removed.
	 *     @type int $skipped Number of files skipped.
	 * }
	 */
	public function cleanup( ?int $max_age = null ): array {
		// Set max age if provided.
		if ( $max_age ) {
			$this->max_age = $max_age * DAY_IN_SECONDS;
		}

		// Get uploads directory.
		$uploads = wp_get_upload_dir();

		// Get cache directory.
		$cache_dir = $uploads['basedir'] . '/mai-performance-images';

		// Initialize counters.
		$removed = 0;
		$skipped = 0;

		// Create recursive iterator.
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $cache_dir, \RecursiveDirectoryIterator::SKIP_DOTS )
		);

		// Loop through files.
		foreach ( $iterator as $file ) {
			// Skip if not a WebP file.
			if ( ! $file->isFile() || 'webp' !== $file->getExtension() ) {
				continue;
			}

			// Get file path and modification time.
			$file_path = $file->getPathname();
			$file_time = filemtime( $file_path );

			// Skip if file is newer than max age.
			if ( $file_time > time() - $this->max_age ) {
				$skipped++;
				continue;
			}

			// Try to remove file.
			if ( @unlink( $file_path ) ) {
				$removed++;
				$this->logger->info( sprintf(
					'Removed old cache file: %s',
					$file_path
				) );
			} else {
				$this->logger->warning( sprintf(
					'Failed to remove cache file: %s',
					$file_path
				) );
			}
		}

		// Log summary.
		$this->logger->info( sprintf(
			'Cache cleanup complete. Removed %d files, skipped %d files.',
			$removed,
			$skipped
		) );

		return [
			'removed' => $removed,
			'skipped' => $skipped,
		];
	}
}