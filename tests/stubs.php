<?php
/**
 * Stand-ins for Mai Engine and ACF, which the suite does not load.
 *
 * Each copies only what the plugin relies on.
 */

if ( ! function_exists( 'mai_get_processed_content' ) ) {
	/**
	 * Same order as Mai Engine's: blocks first, WordPress's pass over the content last.
	 *
	 * @param string $content The content.
	 *
	 * @return string
	 */
	function mai_get_processed_content( $content ) {
		$content = do_blocks( $content );
		$content = do_shortcode( $content );

		return wp_filter_content_tags( $content );
	}
}

if ( ! class_exists( 'Mai_Engine' ) ) {
	class Mai_Engine {}
}

if ( ! function_exists( 'get_field' ) ) {
	/**
	 * Returns values set in $GLOBALS['mpi_test_fields'].
	 *
	 * @param string $name The field name.
	 *
	 * @return mixed
	 */
	function get_field( $name ) {
		return $GLOBALS['mpi_test_fields'][ $name ] ?? null;
	}
}
