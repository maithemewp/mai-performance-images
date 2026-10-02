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

if ( ! class_exists( 'Mai_Publisher_Output' ) ) {
	/**
	 * Same shape as Mai Publisher's page buffer: it wraps WordPress's buffer,
	 * inserts content rendered during the page, and filters the final HTML.
	 */
	class Mai_Publisher_Output {
		/**
		 * Content to insert before </body>, as Mai Publisher inserts a sidebar ad.
		 *
		 * @var string
		 */
		public $insert = '';

		/**
		 * Whether to return the page without running mai_publisher_html, as Mai
		 * Publisher does on its early exits.
		 *
		 * @var bool
		 */
		public $skip_filter = false;

		/**
		 * Buffer callback.
		 *
		 * @param string $buffer The page.
		 *
		 * @return string
		 */
		public function callback( $buffer ) {
			$buffer = str_replace( '</body>', $this->insert . '</body>', $buffer );

			if ( $this->skip_filter ) {
				return $buffer;
			}

			return apply_filters( 'mai_publisher_html', $buffer );
		}
	}
}
