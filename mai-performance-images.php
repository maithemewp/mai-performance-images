<?php
/**
 * Plugin Name:       Mai Performance Images
 * Description:       Optimizes image delivery through automatic resizing and WebP conversion with static file caching.
 * Version:           0.2.0
 * Requires at least: 6.7
 * Requires PHP:      8.0
 * Author:            JiveDig
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mai-performance-images
 */

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Include vendor files.
require_once __DIR__ . '/vendor/autoload.php';

// Initialize image handling with dependency injection.
$images    = new Images();
$loading   = new ImageLoading();
$processor = new ImageProcessor();
$scheduler = new Scheduler();

add_action( 'cli_init', __NAMESPACE__ . '\register_cli_command' );
/**
 * Register the CLI command.
 *
 * @since 0.1.0
 *
 * @return void
 */
function register_cli_command() {
	/** @disregard P1009 */
	\WP_CLI::add_command( 'mai-performance-images', 'Mai\PerformanceImages\CLI' );
}

add_action( 'after_setup_theme', __NAMESPACE__ . '\add_mai_engine_support' );
/**
 * Add support for Mai Theme v2.
 *
 * @since 0.1.0
 *
 * @return void
 */
function add_mai_engine_support() {
	if ( ! ( class_exists( '\Mai_Engine' ) ) ) {
		return;
	}

	// Initialize Mai Engine Images.
	new MaiEngine();
}

add_filter( 'http_request_args', __NAMESPACE__ . '\http_request_args', 10, 2 );
/**
 * Add authorization header to HTTP requests.
 *
 * @since 0.1.0
 *
 * @param array  $r   HTTP request arguments.
 * @param string $url HTTP request URL.
 *
 * @return array Modified HTTP request arguments.
 */
function http_request_args( $r, $url ) {
	// Bail if no url.
	if ( ! $url ) {
		return $r;
	}

	// Parse the URL to get query parameters.
	$query = wp_parse_url( $url, PHP_URL_QUERY );

	// Bail if no query.
	if ( ! $query ) {
		return $r;
	}

	// Parse the query string.
	wp_parse_str( $query, $result );

	// Bail if not our action.
	if ( ! isset( $result['action'] ) || ! str_starts_with( $result['action'], 'mai_performance_images_' ) ) {
		return $r;
	}

	/** @disregard P1011 */
	$un = defined( 'MAI_BASIC_AUTH_USERNAME' ) ? MAI_BASIC_AUTH_USERNAME : '';
	/** @disregard P1011 */
	$pw = defined( 'MAI_BASIC_AUTH_PASSWORD' ) ? MAI_BASIC_AUTH_PASSWORD : '';

	// Bail if no username or password.
	if ( ! ( $un && $pw ) ) {
		return $r;
	}

	// Add the authorization header.
	$r['headers']['Authorization'] = 'Basic ' . base64_encode( $un . ':' . $pw );

	return $r;
}

/**
 * Clear scheduled events on plugin deactivation.
 *
 * @since 0.1.0
 *
 * @return void
 */
register_deactivation_hook( __FILE__, function() {
	$scheduler = new Scheduler();
	$scheduler->clear_scheduled_events();
} );
