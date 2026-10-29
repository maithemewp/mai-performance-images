<?php

declare(strict_types=1);

namespace Mai\PerformanceImages\Tests\Integration;

use Mai\PerformanceImages\LoadingAttributes;
use Mai\PerformanceImages\Tests\TestCase;

/**
 * Iframes are left to WordPress, which only lazy loads one once enough images came
 * before it. The plugin answers for images first, so it has to let WordPress count
 * them, or a video below the main image loads right away.
 */
final class IframeLoadingTest extends TestCase {

	/**
	 * Where WordPress stops treating media as near the top, for the current render.
	 *
	 * @var int
	 */
	private int $threshold = 3;

	public function set_up(): void {
		parent::set_up();

		add_filter( 'wp_omit_loading_attr_threshold', fn() => $this->threshold );
	}

	public function tear_down(): void {
		parent::tear_down();

		wp_omit_loading_attr_threshold( true );
	}

	/**
	 * WordPress's media count lives in a static that never resets, and the threshold
	 * is cached, so each render moves the threshold to 3 above the count now.
	 *
	 * @return void
	 */
	private function fresh_threshold(): void {
		$this->threshold = wp_increase_content_media_count( 0 ) + 3;

		wp_omit_loading_attr_threshold( true );
	}

	/**
	 * Renders content inside the main query's loop, where WordPress counts media.
	 *
	 * @param string $content The post content.
	 *
	 * @return string
	 */
	private function in_the_loop( string $content ): string {
		$this->go_to( get_permalink( self::factory()->post->create() ) );
		$this->fresh_threshold();

		$html = '';

		while ( have_posts() ) {
			the_post();
			$html .= $this->the_content( $content );
		}

		return $html;
	}

	/**
	 * Returns the loading value of the first iframe, or "none".
	 *
	 * @param string $html The HTML.
	 *
	 * @return string
	 */
	private function iframe_loading( string $html ): string {
		$tags = new \WP_HTML_Tag_Processor( $html );

		return $tags->next_tag( [ 'tag_name' => 'iframe' ] ) ? (string) ( $tags->get_attribute( 'loading' ) ?? 'none' ) : 'missing';
	}

	/**
	 * Returns what WordPress alone gives the iframe, with the plugin's image hook off.
	 *
	 * @param string $content The post content.
	 *
	 * @return string
	 */
	private function without_plugin( string $content ): string {
		$plugin = LoadingAttributes::instance();

		remove_filter( 'pre_wp_get_loading_optimization_attributes', [ $plugin, 'mark_image' ], 10 );

		try {
			return $this->iframe_loading( $this->in_the_loop( $content ) );
		} finally {
			add_filter( 'pre_wp_get_loading_optimization_attributes', [ $plugin, 'mark_image' ], 10, 4 );
		}
	}

	private function video(): string {
		return '<iframe src="https://www.youtube.com/embed/x0pTQbS9mxA" width="560" height="315" title="Video"></iframe>';
	}

	/**
	 * A content image as it sits in post content: plain HTML, processed in the loop.
	 *
	 * @param int    $n      A number to tell the images apart.
	 * @param int    $width  The width.
	 * @param int    $height The height.
	 * @param string $extra  More attributes.
	 *
	 * @return string
	 */
	private function image( int $n, int $width = 1024, int $height = 683, string $extra = '' ): string {
		return sprintf( '<img src="https://example.org/content-%1$d.jpg" class="wp-image-%1$d" width="%2$d" height="%3$d" alt="" %4$s/>', $n, $width, $height, $extra );
	}

	public function test_a_video_after_three_images_lazy_loads(): void {
		$html = $this->page( fn() => $this->in_the_loop( $this->image( 1 ) . $this->image( 2 ) . $this->image( 3 ) . $this->video() ) );

		$this->assertSame( 'lazy', $this->iframe_loading( $html ) );
	}

	public function test_a_video_first_on_the_page_loads_right_away(): void {
		$html = $this->page( fn() => $this->in_the_loop( $this->video() . $this->image( 1 ) ) );

		$this->assertSame( 'none', $this->iframe_loading( $html ) );
	}

	public function test_a_video_after_two_images_loads_right_away(): void {
		$html = $this->page( fn() => $this->in_the_loop( $this->image( 1 ) . $this->image( 2 ) . $this->video() ) );

		$this->assertSame( 'none', $this->iframe_loading( $html ) );
	}

	public function test_the_plugin_still_decides_the_images(): void {
		$html = $this->page( fn() => $this->in_the_loop( $this->image( 1 ) . $this->image( 2 ) . $this->image( 3 ) . $this->video() ) );

		$this->assertSame( [ 'eager+high', 'lazy', 'lazy' ], $this->loading( $html ) );
		$this->assertNoMarkers( $html );
	}

	/**
	 * The iframe gets the same answer as on a site without the plugin, for each mix
	 * of images WordPress counts differently.
	 *
	 * @dataProvider mixes
	 *
	 * @param string[] $parts The content, as image, small, or video, in order.
	 *
	 * @return void
	 */
	public function test_the_video_matches_wordpress_alone( array $parts ): void {
		$build = function () use ( $parts ): string {
			$html = '';

			foreach ( $parts as $n => $part ) {
				$html .= match ( $part ) {
					'image' => $this->image( $n ),
					'small' => $this->image( $n, 100, 100 ),
					'auto'  => $this->image( $n, 1024, 683, 'fetchpriority="auto"' ),
					'lazy'  => $this->image( $n, 1024, 683, 'loading="lazy"' ),
					'video' => $this->video(),
				};
			}

			return $html;
		};

		$expected = $this->without_plugin( $build() );
		$actual   = $this->iframe_loading( $this->page( fn() => $this->in_the_loop( $build() ) ) );

		fwrite( STDERR, json_encode( $parts ) . " expected=$expected actual=$actual\n" );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @return array<string, array{0: string[]}>
	 */
	public static function mixes(): array {
		return [
			'video first'              => [ [ 'video', 'image' ] ],
			'after one image'          => [ [ 'image', 'video' ] ],
			'after three images'       => [ [ 'image', 'image', 'image', 'video' ] ],
			'after three small images' => [ [ 'small', 'small', 'small', 'video' ] ],
			'after auto images'        => [ [ 'auto', 'auto', 'auto', 'video' ] ],
			'after lazy images'        => [ [ 'lazy', 'lazy', 'lazy', 'video' ] ],
			'after a mix'              => [ [ 'image', 'small', 'auto', 'image', 'video' ] ],
		];
	}
}
