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
	 * Gets the cache directory path.
	 *
	 * @since 0.6.0
	 *
	 * @return string
	 */
	public function get_cache_dir(): string {
		$uploads = wp_get_upload_dir();
		return $uploads['basedir'] . '/mai-performance-images';
	}

	/**
	 * Gets the number of cached WebP files.
	 *
	 * @since 0.6.0
	 *
	 * @return int
	 */
	public function get_cache_file_count(): int {
		$cache_dir = $this->get_cache_dir();

		if ( ! is_dir( $cache_dir ) ) {
			return 0;
		}

		$count    = 0;
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $cache_dir, \RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'webp' === $file->getExtension() ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Clears all cached files and removes the cache directory.
	 *
	 * @since 0.6.0
	 *
	 * @return int Number of files removed.
	 */
	public function clear_all(): int {
		$cache_dir = $this->get_cache_dir();

		if ( ! is_dir( $cache_dir ) ) {
			return 0;
		}

		$removed  = 0;
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $cache_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				@rmdir( $file->getPathname() );
			} elseif ( @unlink( $file->getPathname() ) ) {
				$removed++;
			}
		}

		@rmdir( $cache_dir );

		$this->logger->info( sprintf( 'Cleared all cache files. Removed %d files.', $removed ) );

		return $removed;
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

		// Get cache directory.
		$cache_dir = $this->get_cache_dir();

		// Bail if cache directory doesn't exist.
		if ( ! is_dir( $cache_dir ) ) {
			return [
				'removed' => 0,
				'skipped' => 0,
			];
		}

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