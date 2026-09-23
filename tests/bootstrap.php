<?php
/**
 * Bootstrap for the WordPress-loaded test suite.
 *
 * Run it with: composer test
 */

// Sets WP_PHPUNIT__DIR via wp-phpunit's files-autoload, so it must come first.
require_once __DIR__ . '/vendor/autoload.php';

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );

$wp_phpunit_dir = getenv( 'WP_PHPUNIT__DIR' );

if ( ! $wp_phpunit_dir ) {
	fwrite( STDERR, "WP_PHPUNIT__DIR is not set. Run: composer test-setup\n" );
	exit( 1 );
}

require_once $wp_phpunit_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require_once __DIR__ . '/plugin-loader.php';
	}
);

require $wp_phpunit_dir . '/includes/bootstrap.php';
