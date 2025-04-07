<?php
/**
 * Plugin Name:       Mai Performance Images
 * Description:       Adds dynamic image loading to the block editor.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.0
 * Author:            JiveDig
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mai-performance-images
 */

namespace Mai\Performance;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Include vendor files.
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Flush rewrite rules on activation.
 *
 * @since 0.1.0
 *
 * @return void
 */
register_activation_hook( __FILE__, function() {
	flush_rewrite_rules();
} );

// Initialize image handling with dependency injection.
$settings  = new BlockSettings();
$processor = new ImageProcessor();
$router    = new ImageRouter( $processor );
$images    = new Images();
