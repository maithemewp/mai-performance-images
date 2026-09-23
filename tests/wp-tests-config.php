<?php
/**
 * Config for the WordPress-loaded test suite.
 *
 * WARNING: the WordPress test bootstrap DROPS the core tables carrying $table_prefix in this
 * database on every run. Never point it at a real site's database. Keep the dedicated name.
 */

// roots/wordpress-no-content lands in vendor/<vendor>/<name> because it declares no installer plugin.
define( 'ABSPATH', __DIR__ . '/vendor/roots/wordpress-no-content/' );

define( 'DB_NAME', getenv( 'WP_TESTS_DB_NAME' ) ?: 'mai_performance_images_tests' );
define( 'DB_USER', getenv( 'WP_TESTS_DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'WP_TESTS_DB_PASS' ) ?: '' );
define( 'DB_HOST', getenv( 'WP_TESTS_DB_HOST' ) ?: '127.0.0.1' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

// Must be a plain local variable. The wp-phpunit shim reads it as one.
$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Mai Performance Images Tests' );

// Quoted, because Herd's PHP lives under "Application Support" and the bootstrap passes this to system() unquoted.
define( 'WP_PHP_BINARY', escapeshellarg( PHP_BINARY ) );

define( 'WP_DEBUG', true );
