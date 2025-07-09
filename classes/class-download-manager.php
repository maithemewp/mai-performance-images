<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mai Performance Download Manager class.
 *
 * @since 0.4.0
 */
class DownloadManager {
	/**
	 * The static instance.
	 *
	 * @since 0.4.0
	 *
	 * @var DownloadManager|null
	 */
	private static $instance = null;

	/**
	 * The logger instance.
	 *
	 * @since 0.4.0
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Get the static instance.
	 *
	 * @since 0.4.0
	 *
	 * @return DownloadManager
	 */
	public static function get_instance(): DownloadManager {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	private function __construct() {
		$this->logger = Logger::get_instance();
	}

	/**
	 * Download an external image to a temporary location.
	 *
	 * @since 0.4.0
	 *
	 * @param string $url The URL of the external image.
	 *
	 * @return string|null Path to the downloaded file or null if download failed.
	 */
	public function download_image( string $url ): ?string {
		// URL-decode the URL first to handle URL-encoded URLs.
		$decoded_url = urldecode( $url );

		// Parse URL to validate it.
		$parsed_url = wp_parse_url( $decoded_url );
		if ( ! $parsed_url || ! isset( $parsed_url['scheme'] ) ) {
			$this->logger->error( 'Invalid URL provided for external image download', [ 'url' => $url ] );
			return null;
		}

		// Get uploads directory for temporary storage.
		$uploads  = wp_get_upload_dir();
		$temp_dir = $uploads['basedir'] . '/mai-performance-images/tmp';

		// Create temp directory if it doesn't exist.
		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
		}

		// Generate filename using shared method.
		$filename  = $this->generate_filename( $url );
		$temp_path = $temp_dir . '/' . $filename;

		// Check if file already exists and is valid.
		if ( file_exists( $temp_path ) && filesize( $temp_path ) > 0 ) {
			return $temp_path;
		}

		// Download with timeout and size limits.
		$response = wp_remote_get( $decoded_url, [
			'timeout'             => 30,
			'stream'              => true,
			'filename'            => $temp_path,
			'limit_response_size' => 10 * 1024 * 1024, // 10MB limit
		] );

		// Check for download errors.
		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Failed to download external image', [
				'url'         => $url,
				'decoded_url' => $decoded_url,
				'error'       => $response->get_error_message()
			] );
			return null;
		}

		// Check HTTP response code.
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			$this->logger->error( 'External image download failed with HTTP error', [
				'url'           => $url,
				'decoded_url'   => $decoded_url,
				'response_code' => $response_code
			] );
			@unlink( $temp_path );
			return null;
		}

		// Validate it's actually an image file.
		$file_info = wp_check_filetype( $temp_path );
		$file_type = $file_info['type'] ?? null;

		if ( $file_type && ! str_starts_with( $file_type, 'image/' ) ) {
			$this->logger->error( 'Downloaded file is not a valid image', [
				'url'         => $url,
				'decoded_url' => $decoded_url,
				'file_type'   => $file_type
			] );
			@unlink( $temp_path );
			return null;
		}

		// Check file size (additional safety check).
		$file_size = filesize( $temp_path );
		if ( 0 === $file_size ) {
			$this->logger->error( 'Downloaded file size is invalid', [
				'url'         => $url,
				'decoded_url' => $decoded_url,
				'file_size'   => $file_size
			] );
			@unlink( $temp_path );
			return null;
		}

		return $temp_path;
	}

	/**
	 * Generate filename for external image based on URL.
	 *
	 * @since 0.4.0
	 *
	 * @param string $url The external image URL.
	 *
	 * @return string The generated filename.
	 */
	public function generate_filename( string $url ): string {
		$url_hash = md5( $url );

		// Extract just the filename from the URL path.
		$url_path = wp_parse_url( $url, PHP_URL_PATH );
		$filename = basename( $url_path );

		// Clean the filename.
		$filename = sanitize_file_name( $filename );

		// If no filename, use hash.
		if ( empty( $filename ) ) {
			$filename = $url_hash;
		}

		// Limit filename length to 50 characters
		if ( strlen( $filename ) > 50 ) {
			$extension = pathinfo( $filename, PATHINFO_EXTENSION );
			$name_part = pathinfo( $filename, PATHINFO_FILENAME );
			$name_part = substr( $name_part, 0, 50 - strlen( $extension ) - 1 );  // -1 for the dot
			$filename  = $name_part . '.' . $extension;
		}

		return $filename;
	}
}