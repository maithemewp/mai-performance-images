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
$loading   = new ImageLoading();
$processor = new ImageProcessor();
$images    = new Images();

/**
 * Add support for Mai Theme v2.
 *
 * @since 0.1.0
 *
 * @return void
 */
add_action( 'after_setup_theme', function() {
	if ( ! ( class_exists( '\Mai_Engine' ) ) ) {
		return;
	}

	// Initialize Mai Engine Images.
	if ( class_exists( '\Mai\PerformanceImages\MaiEngine' ) ) {
		new MaiEngine();
	}
} );