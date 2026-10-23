<?php
/**
 * Plugin Name:       Mai Performance Images
 * Description:       Loads each page's main image right away and lazy loads the rest, with Image Loading settings for blocks, Mai grids and the Customizer.
 * Version:           0.8.0
 * Requires at least: 6.9
 * Requires PHP:      8.1
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

// Initialize.
LoadingAttributes::instance();
new ImageLoading();
new MaiBlocks();
new Updater();

add_action( 'after_setup_theme', __NAMESPACE__ . '\add_mai_engine_support' );
/**
 * Add support for Mai Theme v2.
 *
 * @since 0.1.0
 *
 * @return void
 */
function add_mai_engine_support() {
	if ( ! class_exists( '\Mai_Engine' ) ) {
		return;
	}

	new MaiEngine();
	new MaiEntryLoading();
}

add_action( 'admin_init', __NAMESPACE__ . '\remove_conversion_leftovers' );
add_action( 'admin_init', __NAMESPACE__ . '\remove_settings_leftovers' );
/**
 * Removes what WebP conversion left behind, once.
 *
 * Conversion was removed in 0.7.0. Its scheduled jobs and queue rows are deleted.
 * The converted files in uploads/mai-performance-images are left alone, because
 * pages already cached by a page cache or CDN may still point to them. Delete that
 * folder once those caches have cleared.
 *
 * @since 0.7.0
 *
 * @return void
 */
function remove_conversion_leftovers(): void {
	if ( get_option( 'mai_performance_images_conversion_removed' ) ) {
		return;
	}

	global $wpdb;

	wp_clear_scheduled_hook( 'mai_performance_images_cleanup_cache' );
	wp_clear_scheduled_hook( 'mai_performance_images_processor_cron' );

	// The background queue stored its batches and status as site options. Each is
	// deleted through WordPress so the object cache forgets it too.
	$table = is_multisite() ? $wpdb->sitemeta : $wpdb->options;
	$key   = is_multisite() ? 'meta_key' : 'option_name';
	$names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT {$key} FROM {$table} WHERE {$key} LIKE %s",
			$wpdb->esc_like( 'mai_performance_images_processor_' ) . '%'
		)
	);

	foreach ( $names as $name ) {
		delete_site_option( $name );
	}

	delete_site_transient( 'mai_performance_images_processor_process_lock' );

	update_option( 'mai_performance_images_conversion_removed', MAI_PERFORMANCE_IMAGES_VERSION );
}

/**
 * The plugin version, for the one-time cleanup.
 *
 * @since 0.7.0
 */
const MAI_PERFORMANCE_IMAGES_VERSION = '0.8.0';

/**
 * Deletes the saved setting from the removed settings page.
 *
 * The page's one checkbox turned the whole plugin off, the same as deactivating
 * it, so it was removed. Its saved value, and the old WebP settings stored with
 * it, are deleted.
 *
 * @since 0.9.0
 *
 * @return void
 */
function remove_settings_leftovers(): void {
	if ( false === get_option( 'mai_performance_images' ) ) {
		return;
	}

	delete_option( 'mai_performance_images' );
}
