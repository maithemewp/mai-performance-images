<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mai Performance Logger class.
 *
 * @since 0.1.0
 */
class Logger {
	/**
	 * The singleton instance.
	 *
	 * @since 0.1.0
	 *
	 * @var Logger
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @since 0.1.0
	 *
	 * @return Logger
	 */
	public static function get_instance(): Logger {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Log a message.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function log( string $message ): void {
		// Bail if debugging is not enabled.
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		// Format the message.
		$formatted = sprintf( 'Mai Performance Images: %s', $message );

		// If logging.
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// Log the message.
			error_log( $formatted );
		}

		// If displaying.
		if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
			// If ray is available, use it for additional debugging.
			if ( function_exists( '\ray' ) ) {
				/** @disregard P1010 */
				\ray( $formatted )->label( 'Mai Performance Images' );
			}
		}
	}

	/**
	 * Log an error message.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function error( $message ) {
		$this->log( $message, 'error' );
	}

	/**
	 * Log a warning message.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function warning( $message ) {
		$this->log( $message, 'warning' );
	}

	/**
	 * Log a success message.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function success( $message ) {
		$this->log( $message, 'success' );
	}

	/**
	 * Log an info message.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function info( $message ) {
		$this->log( $message, 'info' );
	}
}