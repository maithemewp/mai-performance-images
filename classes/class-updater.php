<?php

namespace Mai\PerformanceImages;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Updater.
 *
 * @since 0.2.0
 */
class Updater {

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'plugins_loaded', [ $this, 'init' ] );
	}

	/**
	 * Initialize the updater.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function init() {
		$updater = PucFactory::buildUpdateChecker( 'https://github.com/maithemewp/mai-performance-images/', plugin_dir_path(__DIR__) . 'mai-performance-images.php', 'mai-performance-images' );
		$updater->setBranch( 'main' );

		// Maybe set github api token.
		if ( defined( 'MAI_GITHUB_API_TOKEN' ) ) {
			/** @disregard P1011 */
			$updater->setAuthentication( MAI_GITHUB_API_TOKEN );
		}
	}
}