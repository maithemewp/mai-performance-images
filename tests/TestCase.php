<?php

declare(strict_types=1);

namespace Mai\PerformanceImages\Tests;

use Mai\PerformanceImages\LoadingAttributes;
use WP_UnitTestCase;

/**
 * Base class for the suite.
 */
abstract class TestCase extends WP_UnitTestCase {

	/**
	 * Creates an image attachment with size data, without writing a file.
	 *
	 * @param int $width  The original width.
	 * @param int $height The original height.
	 *
	 * @return int The attachment ID.
	 */
	protected function create_image( int $width = 1200, int $height = 800 ): int {
		$file = 'test-' . wp_generate_password( 6, false ) . '.jpg';
		$id   = self::factory()->attachment->create_object(
			[
				'file'           => $file,
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			]
		);

		wp_update_attachment_metadata(
			$id,
			[
				'width'  => $width,
				'height' => $height,
				'file'   => $file,
				'sizes'  => [],
			]
		);

		return $id;
	}

	/**
	 * Renders a page the way a real request does, inside WordPress's own page buffer.
	 *
	 * The plugin adds its page filter on wp_before_include_template, and WordPress
	 * then starts the buffer. Whatever the callback prints or returns is the page.
	 * Closing the buffer runs wp_template_enhancement_output_buffer over it.
	 *
	 * @param callable $render Prints the page, or returns it.
	 *
	 * @return string The page as the visitor gets it.
	 */
	protected function page( callable $render ): string {
		LoadingAttributes::instance()->add_page_filter();

		ob_start();

		if ( ! wp_start_template_enhancement_output_buffer() ) {
			ob_end_clean();
			$this->fail( 'WordPress did not start the page buffer.' );
		}

		try {
			echo (string) $render();
		} finally {
			ob_end_flush();
		}

		return (string) ob_get_clean();
	}

	/**
	 * Runs finished HTML through the plugin's page filter.
	 *
	 * @param string $html The page.
	 *
	 * @return string
	 */
	protected function filter_page( string $html ): string {
		return LoadingAttributes::instance()->filter_page( $html );
	}

	/**
	 * Returns loading, fetchpriority and decoding for every img tag, in page order.
	 *
	 * @param string $html The HTML.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function images( string $html ): array {
		$images = [];
		$tags   = new \WP_HTML_Tag_Processor( $html );

		while ( $tags->next_tag( [ 'tag_name' => 'img' ] ) ) {
			$images[] = [
				'loading'       => (string) $tags->get_attribute( 'loading' ),
				'fetchpriority' => (string) $tags->get_attribute( 'fetchpriority' ),
				'decoding'      => (string) $tags->get_attribute( 'decoding' ),
				'sizes'         => (string) $tags->get_attribute( 'sizes' ),
				'class'         => (string) $tags->get_attribute( 'class' ),
			];
		}

		return $images;
	}

	/**
	 * Returns just the loading value of each image, with "+high" on the high one.
	 *
	 * @param string $html The HTML.
	 *
	 * @return string[]
	 */
	protected function loading( string $html ): array {
		return array_map(
			fn( $image ) => $image['loading'] . ( 'high' === $image['fetchpriority'] ? '+high' : '' ),
			$this->images( $html )
		);
	}

	/**
	 * Asserts the HTML carries no marker anywhere.
	 *
	 * @param string $html The HTML.
	 */
	protected function assertNoMarkers( string $html ): void {
		$this->assertStringNotContainsString( LoadingAttributes::MARKER, $html );
	}

	/**
	 * A static image tag, as a saved block would have it.
	 *
	 * @param int    $n     A number to make the src unique.
	 * @param string $extra Extra attributes.
	 *
	 * @return string
	 */
	protected function static_image( int $n, string $extra = '' ): string {
		return sprintf( '<img src="https://example.org/static-%d.jpg" width="1024" height="683" alt="" %s/>', $n, $extra );
	}

	/**
	 * A Mai Engine page header image, built the way Mai Engine builds it.
	 *
	 * Mai tags it with a small fallback size, here 300x109, while it shows full width.
	 *
	 * @param array $attr Extra or replacement attributes.
	 *
	 * @return string
	 */
	protected function page_header_image( array $attr = [] ): string {
		return wp_get_attachment_image( $this->create_image( 300, 109 ), 'full', false, $attr + [ 'class' => 'page-header-image', 'sizes' => '100vw' ] );
	}

	/**
	 * Runs content through the_content, the way a post renders.
	 *
	 * @param string $content The content.
	 *
	 * @return string
	 */
	protected function the_content( string $content ): string {
		return (string) apply_filters( 'the_content', $content );
	}
}
