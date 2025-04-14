<?php
/**
 * Plugin Name:       Mai Performance Images
 * Description:       Optimizes image delivery through automatic resizing and WebP conversion with static file caching.
 * Version:           0.1.0
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
