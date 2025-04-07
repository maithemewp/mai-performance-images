<?php

namespace Mai\Performance;

/**
 * Mai Performance Logger class.
 *
 * @since 0.1.0
 */
final class Logger {
	/**
	 * The singleton instance.
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
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {}

	/**
	 * Log a message.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 * @param string $level   Optional. The log level. Default 'info'.
	 *
	 * @return void
	 */
	public function log( $message, $level = 'info' ) {
		// Bail if debugging is not enabled
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		// Format the message
		$formatted = sprintf( 'Mai Performance Images [%s]: %s', strtoupper( $level ), $message );

		// Log the message
		error_log( $formatted );

		// If ray is available, use it for additional debugging with appropriate color
		if ( function_exists( '\ray' ) ) {
			$ray = \ray( $formatted )->label( 'Mai Performance Images' );

			// Set color based on level
			switch ( $level ) {
				case 'error':
					$ray->red();
					break;
				case 'warning':
					$ray->orange();
					break;
				case 'success':
					$ray->green();
					break;
				default:
					$ray->blue();
					break;
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