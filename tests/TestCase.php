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
	 * Starts every test with an untouched page: no images counted, high priority free.
	 */
	public function set_up(): void {
		parent::set_up();

		LoadingAttributes::instance()->get_budget()->reset();
	}

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
