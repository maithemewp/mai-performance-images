<?php

declare(strict_types=1);

namespace Mai\PerformanceImages\Tests\Integration;

use Mai\PerformanceImages\LoadingAttributes;
use Mai\PerformanceImages\Tests\TestCase;

/**
 * Which images load right away, decided once on the finished page.
 */
final class LoadingAttributesTest extends TestCase {

	/**
	 * Registers stand-ins for the things that build images mid-content.
	 */
	public function set_up(): void {
		parent::set_up();

		$image_ids = [ $this->create_image(), $this->create_image(), $this->create_image(), $this->create_image() ];

		// A grid block, which builds its images while the content renders.
		$this->register_render( 'mpi-test/grid', static function ( $attributes ) use ( $image_ids ) {
			$html = '';

			foreach ( array_slice( $image_ids, 0, (int) ( $attributes['count'] ?? 2 ) ) as $id ) {
				$html .= wp_get_attachment_image( $id, 'full', false, [ 'class' => 'entry-image' ] );
			}

			return $html;
		} );

		// A content area block, which runs Mai's processed content inside other content.
		$this->register_render( 'mpi-test/area', static fn() => mai_get_processed_content( '<!-- wp:mpi-test/grid {"count":3} /-->' ) );

		// An image ad, built the way Advanced Ads builds one.
		add_shortcode(
			'mpi_ad',
			static fn() => wp_img_tag_add_loading_optimization_attrs( '<img src="https://example.org/ad.jpg" width="728" height="90" alt="">', current_filter() )
		);

		add_shortcode( 'mpi_avatar', static fn() => get_avatar( 1 ) );

		add_shortcode(
			'mpi_logo',
			static fn() => wp_get_attachment_image( $image_ids[0], 'full', false, [ 'class' => 'custom-logo' ] )
		);
	}

	/**
	 * Registers a block, or replaces its render callback if it already exists.
	 *
	 * @param string   $name     The block name.
	 * @param callable $callback The render callback.
	 */
	private function register_render( string $name, callable $callback ): void {
		$registry = \WP_Block_Type_Registry::get_instance();

		if ( ! $registry->is_registered( $name ) ) {
			register_block_type( $name, [ 'render_callback' => fn() => '' ] );
		}

		$registry->get_registered( $name )->render_callback = $callback;
	}

	public function test_a_content_image_above_a_grid_gets_high_priority(): void {
		$html = $this->page( fn() => $this->the_content( $this->static_image( 1 ) . '<!-- wp:mpi-test/grid {"count":4} /-->' ) );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy', 'lazy' ], $this->loading( $html ) );
	}

	public function test_an_ad_low_in_the_content_waits_its_turn(): void {
		$html = $this->page( fn() => $this->the_content( $this->static_image( 1 ) . '[mpi_ad]' . $this->static_image( 2 ) . $this->static_image( 3 ) ) );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_an_avatar_lazy_loads_and_spends_nothing(): void {
		$html = $this->page( fn() => $this->the_content( '[mpi_avatar]' . $this->static_image( 1 ) . $this->static_image( 2 ) . $this->static_image( 3 ) ) );

		$this->assertSame( [ 'lazy', 'eager+high', 'eager', 'eager' ], $this->loading( $html ) );
	}

	public function test_a_logo_loads_right_away_without_taking_high(): void {
		$html   = $this->page( fn() => $this->the_content( '[mpi_logo]' . $this->static_image( 1 ) ) );
		$images = $this->images( $html );

		$this->assertSame( [ 'eager', 'auto' ], [ $images[0]['loading'], $images[0]['fetchpriority'] ] );
		$this->assertSame( 'high', $images[1]['fetchpriority'] );
	}

	public function test_a_logo_built_with_a_mai_context_is_known_without_its_class(): void {
		add_filter( 'wp_get_attachment_image_context', fn() => 'mai_logo' );

		$html   = $this->page( fn() => wp_get_attachment_image( $this->create_image(), 'full', false, [ 'class' => 'x' ] ) . $this->static_image( 1 ) );
		$images = $this->images( $html );

		$this->assertSame( [ 'eager', 'auto' ], [ $images[0]['loading'], $images[0]['fetchpriority'] ] );
		$this->assertSame( 'high', $images[1]['fetchpriority'] );
	}

	public function test_a_hero_above_a_grid_in_a_template_part_gets_high_priority(): void {
		$html = $this->page( fn() => mai_get_processed_content( $this->static_image( 1 ) . '<!-- wp:mpi-test/grid {"count":4} /-->' ) );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_hero_above_a_content_area_with_a_grid_gets_high_priority(): void {
		// A template part holding a hero, then a content area that runs its own pass.
		$html = $this->page( fn() => mai_get_processed_content( $this->static_image( 1 ) . '<!-- wp:mpi-test/area /-->' ) );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_an_area_printed_on_a_hook_is_counted_in_page_order(): void {
		add_action( 'mpi_test_hook', fn() => print( mai_get_processed_content( $this->static_image( 1 ) . '<!-- wp:mpi-test/grid {"count":3} /-->' ) ) );

		$html = $this->page(
			function () {
				do_action( 'mpi_test_hook' );
				echo wp_get_attachment_image( $this->create_image(), 'full' );
			}
		);

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_content_area_added_to_the_content_is_counted_in_page_order(): void {
		add_filter( 'the_content', fn( $content ) => mai_get_processed_content( '<!-- wp:mpi-test/grid {"count":2} /-->' ) . $content );

		$html = $this->page( fn() => $this->the_content( $this->static_image( 1 ) . $this->static_image( 2 ) ) );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_content_area_added_after_the_pass_is_counted_in_page_order(): void {
		add_filter( 'the_content', fn( $content ) => $content . mai_get_processed_content( $this->static_image( 9 ) . '<!-- wp:mpi-test/grid {"count":2} /-->' ), 20 );

		$html = $this->page( fn() => $this->the_content( $this->static_image( 1 ) ) );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_synced_pattern_is_counted_in_page_order(): void {
		$pattern = self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_content' => $this->static_image( 2 ) . '<!-- wp:mpi-test/grid {"count":3} /-->',
			]
		);

		$html = $this->page( fn() => $this->the_content( $this->static_image( 1 ) . '<!-- wp:block {"ref":' . $pattern . '} /-->' ) );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy', 'lazy' ], $this->loading( $html ) );
	}

	public function test_an_image_without_dimensions_spends_nothing(): void {
		$html = $this->filter_page( '<img src="https://example.org/no-size.jpg" alt="">' . $this->static_image( 1 ) );

		$this->assertSame( [ '', 'eager+high' ], $this->loading( $html ) );
		$this->assertSame( 'async', $this->images( $html )[0]['decoding'] );
	}

	public function test_an_image_marked_auto_or_low_spends_nothing(): void {
		$html = $this->filter_page( $this->static_image( 1, 'fetchpriority="auto"' ) . $this->static_image( 2, 'fetchpriority="low"' ) . $this->static_image( 3 ) );

		$this->assertSame( [ '', '', 'eager+high' ], $this->loading( $html ) );
		$this->assertSame( [ 'auto', 'low' ], array_column( array_slice( $this->images( $html ), 0, 2 ), 'fetchpriority' ) );
	}

	public function test_an_image_in_noscript_spends_nothing_and_is_left_alone(): void {
		$pixel = '<noscript><img height="1" width="1" src="https://example.org/pixel.gif" alt=""></noscript>';
		$html  = $this->filter_page( $pixel . $this->static_image( 1 ) . $this->static_image( 2 ) . $this->static_image( 3 ) . $this->static_image( 4 ) );

		$this->assertSame( [ '', 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
		$this->assertSame( '', $this->images( $html )[0]['decoding'] );
	}

	/*
	 * Tiny images, such as tracking pixels, are left alone.
	 */

	public function test_a_tracking_pixel_first_on_the_page_is_left_alone(): void {
		$pixel = '<img src="https://example.org/pixel.gif" width="1" height="1" alt="" style="display:none">';
		$html  = $this->filter_page( $pixel . $this->static_image( 1 ) . $this->static_image( 2 ) . $this->static_image( 3 ) . $this->static_image( 4 ) );

		$this->assertStringStartsWith( $pixel, $html );
		$this->assertSame( [ '', 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_tracking_pixel_set_to_eager_does_not_switch_the_page_to_its_configuration(): void {
		$pixel = '<img data-mpi-loading="eager" src="https://example.org/pixel.gif" width="1" height="1" alt="">';
		$html  = $this->filter_page( $pixel . $this->static_image( 1 ) . $this->static_image( 2 ) . $this->static_image( 3 ) . $this->static_image( 4 ) );

		// Only the marker comes off the pixel.
		$this->assertSame( [ 'loading' => '', 'fetchpriority' => '', 'decoding' => '', 'sizes' => '', 'class' => '' ], $this->images( $html )[0] );
		$this->assertSame( [ '', 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
		$this->assertNoMarkers( $html );
	}

	public function test_a_2x2_image_is_left_alone(): void {
		$tiny = '<img src="https://example.org/tiny.gif" width="2" height="2" alt="" sizes="auto, 2px" srcset="https://example.org/tiny.gif 2w">';
		$html = $this->filter_page( $tiny . $this->static_image( 1 ) );

		$this->assertStringStartsWith( $tiny, $html );
		$this->assertSame( [ '', 'eager+high' ], $this->loading( $html ) );
	}

	public function test_a_3x3_image_is_treated_like_any_other(): void {
		$small = '<img src="https://example.org/small.gif" width="3" height="3" alt="">';
		$html  = $this->filter_page( $small . $this->static_image( 1 ) . $this->static_image( 2 ) . $this->static_image( 3 ) );

		$this->assertSame( [ 'eager', 'eager+high', 'eager', 'lazy' ], $this->loading( $html ) );
		$this->assertSame( 'sync', $this->images( $html )[0]['decoding'] );
	}

	public function test_a_pixel_without_both_dimensions_is_treated_as_before(): void {
		$no_size  = '<img src="https://example.org/pixel.gif" alt="">';
		$one_side = '<img src="https://example.org/pixel-2.gif" width="1" alt="">';
		$html     = $this->filter_page( $no_size . $one_side . $this->static_image( 1 ) );

		$this->assertSame( [ '', '', 'eager+high' ], $this->loading( $html ) );
		$this->assertSame( [ 'async', 'async' ], array_column( array_slice( $this->images( $html ), 0, 2 ), 'decoding' ) );
	}

	public function test_a_tracking_pixel_mai_publisher_inserts_later_is_left_alone(): void {
		$this->page( fn() => '<html><body>' . $this->static_image( 1 ) . '</body></html>' );

		$pixel = '<img src="https://example.org/pixel.gif" width="1" height="1" alt="">';

		$this->assertSame( $pixel, apply_filters( 'mai_publisher_html', $pixel ) );
	}

	public function test_an_editor_choice_on_an_image_block_wins(): void {
		$lazy  = '<!-- wp:image {"imgLoading":"lazy"} --><figure class="wp-block-image">' . $this->static_image( 1 ) . '</figure><!-- /wp:image -->';
		$eager = '<!-- wp:image {"imgLoading":"eager"} --><figure class="wp-block-image">' . $this->static_image( 2, 'loading="lazy"' ) . '</figure><!-- /wp:image -->';
		$html  = $this->page( fn() => $this->the_content( $lazy . $this->static_image( 3 ) . $this->static_image( 4 ) . $this->static_image( 5 ) . $this->static_image( 6 ) . $eager ) );

		// The Eager block makes the page follow its configuration, so the images
		// nobody chose lazy load and the Eager one gets high priority.
		$this->assertSame( [ 'lazy', 'lazy', 'lazy', 'lazy', 'lazy', 'eager+high' ], $this->loading( $html ) );
		$this->assertNoMarkers( $html );
	}

	public function test_a_cover_choice_only_reaches_the_background_image(): void {
		$cover = '<!-- wp:cover {"imgLoading":"lazy"} --><div class="wp-block-cover"><img class="wp-block-cover__image-background" src="https://example.org/bg.jpg" width="1600" height="900" alt=""/><span class="wp-block-cover__background"></span><div class="wp-block-cover__inner-container">' . $this->static_image( 1 ) . '</div></div><!-- /wp:cover -->';
		$html  = $this->page( fn() => $this->the_content( $cover ) );

		$this->assertSame( [ 'lazy', 'eager+high' ], $this->loading( $html ) );
	}

	public function test_an_image_marked_high_keeps_it_and_no_image_above_gets_it(): void {
		$html = $this->page(
			function () {
				foreach ( range( 1, 3 ) as $n ) {
					echo wp_get_attachment_image( $this->create_image(), 'full' );
				}

				echo wp_get_attachment_image( $this->create_image(), 'full', false, [ 'fetchpriority' => 'high' ] );
				echo wp_get_attachment_image( $this->create_image(), 'full' );
			}
		);

		$this->assertSame( [ 'eager', 'eager', 'eager', 'eager+high', 'lazy' ], $this->loading( $html ) );
	}

	public function test_an_image_marked_high_in_the_content_is_not_lazy(): void {
		$html = $this->page( fn() => $this->the_content( $this->static_image( 1, 'fetchpriority="high"' ) . $this->static_image( 2 ) ) );

		$this->assertSame( [ 'eager+high', 'eager' ], $this->loading( $html ) );
	}

	public function test_a_lazy_choice_takes_high_priority_away(): void {
		$block = '<!-- wp:image {"imgLoading":"lazy"} --><figure class="wp-block-image">' . $this->static_image( 1, 'fetchpriority="high"' ) . '</figure><!-- /wp:image -->';
		$html  = $this->page( fn() => do_blocks( $block ) . $this->static_image( 2 ) );

		$this->assertSame( [ 'lazy', 'eager+high' ], $this->loading( $html ) );
	}

	public function test_high_priority_needs_50000_square_pixels(): void {
		$under = '<img src="https://example.org/under.jpg" width="249" height="200" alt="">';
		$at    = '<img src="https://example.org/at.jpg" width="250" height="200" alt="">';
		$html  = $this->filter_page( '<img src="https://example.org/icon.png" width="32" height="32" alt="">' . $under . $at );

		$this->assertSame( [ 'eager', 'eager', 'eager+high' ], $this->loading( $html ) );
	}

	public function test_the_eager_count_filter_changes_how_many_load_right_away(): void {
		add_filter( 'mai_performance_images_eager_count', fn() => 1 );

		$html = $this->filter_page( $this->static_image( 1 ) . $this->static_image( 2 ) . $this->static_image( 3 ) );

		$this->assertSame( [ 'eager+high', 'lazy', 'lazy' ], $this->loading( $html ) );
	}

	public function test_lazy_images_get_sizes_auto_and_eager_images_do_not(): void {
		$responsive = 'srcset="https://example.org/a.jpg 300w, https://example.org/b.jpg 600w" sizes="(max-width: 600px) 100vw, 600px"';
		$images     = $this->images( $this->filter_page( str_repeat( $this->static_image( 1, $responsive ), 4 ) ) );

		$this->assertSame( '(max-width: 600px) 100vw, 600px', $images[0]['sizes'] );
		$this->assertSame( 'auto, (max-width: 600px) 100vw, 600px', $images[3]['sizes'] );
		$this->assertSame( [ 'sync', 'sync', 'sync', 'async' ], array_column( $images, 'decoding' ) );
	}

	public function test_sizes_auto_follows_the_core_switch(): void {
		add_filter( 'wp_img_tag_add_auto_sizes', '__return_false' );

		$responsive = 'srcset="https://example.org/a.jpg 300w" sizes="300px"';
		$images     = $this->images( $this->filter_page( str_repeat( $this->static_image( 1, $responsive ), 4 ) ) );

		$this->assertSame( '300px', $images[3]['sizes'] );
	}

	public function test_an_eager_image_loses_a_leftover_sizes_auto(): void {
		$images = $this->images( $this->filter_page( $this->static_image( 1, 'srcset="https://example.org/a.jpg 300w" sizes="auto, 300px"' ) ) );

		$this->assertSame( '300px', $images[0]['sizes'] );
	}

	public function test_a_marker_out_of_reach_of_the_walk_is_still_removed(): void {
		$json = '<script type="application/json">{"html":"<img data-mpi-loading=\"lazy\" src=\"x.jpg\">"}</script>';
		$html = $this->filter_page( $json . $this->static_image( 1, 'data-mpi-loading="lazy"' ) );

		$this->assertNoMarkers( $html );
		$this->assertSame( [ 'lazy' ], $this->loading( $html ) );
	}

	public function test_the_post_preview_block_lazy_loads_its_images(): void {
		$render = fn() => apply_filters( 'render_block_acf/mai-post-preview', $this->static_image( 1 ), [ 'blockName' => 'acf/mai-post-preview' ] );

		$this->assertSame( [ 'lazy' ], $this->loading( $this->page( $render ) ) );
		$this->assertSame( [ 'lazy' ], $this->loading( $render() ) );
	}

	public function test_content_mai_publisher_inserts_later_lazy_loads(): void {
		$publisher = new \Mai_Publisher_Output();

		ob_start();
		ob_start( [ $publisher, 'callback' ] );

		echo $this->page(
			function () use ( $publisher ) {
				// Rendered during the page, inserted by Mai Publisher after WordPress's filter.
				$publisher->insert = mai_get_processed_content( '<!-- wp:mpi-test/grid {"count":2} /-->' );

				return '<html><body>' . $this->static_image( 1 ) . $this->static_image( 2 ) . '</body></html>';
			}
		);

		ob_end_flush();
		$html = (string) ob_get_clean();

		// The page is decided on WordPress's filter. Images inserted after it lazy load.
		$this->assertSame( [ 'eager+high', 'eager', 'lazy', 'lazy' ], $this->loading( $html ) );
		$this->assertNoMarkers( $html );
	}

	public function test_the_page_is_decided_even_when_mai_publisher_skips_its_filter(): void {
		$publisher              = new \Mai_Publisher_Output();
		$publisher->skip_filter = true;

		ob_start();
		ob_start( [ $publisher, 'callback' ] );

		echo $this->page( fn() => '<html><body>' . $this->static_image( 1 ) . $this->static_image( 2 ) . '</body></html>' );

		ob_end_flush();
		$html = (string) ob_get_clean();

		// No mai_publisher_html filter ran, and the page is still fully decided.
		$this->assertSame( [ 'eager+high', 'eager' ], $this->loading( $html ) );
		$this->assertNoMarkers( $html );
	}

	public function test_the_late_pass_leaves_decided_images_alone(): void {
		$this->page( fn() => '<html><body>' . $this->static_image( 1 ) . '</body></html>' );

		$page = $this->static_image( 1, 'loading="eager" fetchpriority="high"' );

		$this->assertSame( $page, apply_filters( 'mai_publisher_html', $page ) );
	}

	public function test_without_wordpress_collecting_the_page_mai_publisher_leaves_it_alone(): void {
		$page = $this->static_image( 1, 'loading="lazy"' );

		$this->assertSame( $page, apply_filters( 'mai_publisher_html', $page ) );
	}

	/*
	 * Pages with at least one image configured Eager follow the configuration.
	 */

	/**
	 * An image block the editor set to Lazy or Eager.
	 *
	 * @param int    $n       A number to make the src unique.
	 * @param string $loading 'lazy' or 'eager'.
	 * @param string $image   The img tag, when not the default static image.
	 *
	 * @return string
	 */
	private function block_image( int $n, string $loading, string $image = '' ): string {
		return '<!-- wp:image {"imgLoading":"' . $loading . '"} --><figure class="wp-block-image">' . ( $image ?: $this->static_image( $n ) ) . '</figure><!-- /wp:image -->';
	}

	public function test_a_configured_eager_hero_is_the_only_image_that_loads_right_away(): void {
		$html = $this->page( fn() => $this->the_content( $this->block_image( 1, 'eager' ) . $this->static_image( 2 ) . $this->static_image( 3 ) . $this->static_image( 4 ) . $this->static_image( 5 ) ) );

		$this->assertSame( [ 'eager+high', 'lazy', 'lazy', 'lazy', 'lazy' ], $this->loading( $html ) );
		$this->assertNoMarkers( $html );
	}

	public function test_two_configured_eager_images_load_right_away_and_the_first_gets_high(): void {
		$html = $this->page( fn() => $this->the_content( $this->block_image( 1, 'eager' ) . $this->block_image( 2, 'eager' ) . $this->static_image( 3 ) . $this->static_image( 4 ) ) );

		$this->assertSame( [ 'eager+high', 'eager', 'lazy', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_configured_eager_image_lower_down_gets_high_and_the_images_above_it_lazy_load(): void {
		$html = $this->page( fn() => $this->the_content( $this->static_image( 1 ) . $this->static_image( 2 ) . $this->block_image( 3, 'eager' ) . $this->static_image( 4 ) ) );

		$this->assertSame( [ 'lazy', 'lazy', 'eager+high', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_logo_and_an_avatar_are_unaffected_by_a_configured_page(): void {
		$html   = $this->page( fn() => $this->the_content( '[mpi_logo][mpi_avatar]' . $this->block_image( 1, 'eager' ) . $this->static_image( 2 ) ) );
		$images = $this->images( $html );

		$this->assertSame( [ 'eager', 'lazy', 'eager+high', 'lazy' ], $this->loading( $html ) );
		$this->assertSame( 'auto', $images[0]['fetchpriority'] );
	}

	public function test_a_logo_set_to_eager_does_not_switch_the_page_to_its_configuration(): void {
		$logo = '<img class="custom-logo" src="https://example.org/logo.png" width="600" height="200" alt="">';
		$html = $this->page( fn() => apply_filters( 'render_block_core/site-logo', $logo, [ 'blockName' => 'core/site-logo', 'attrs' => [ 'imgLoading' => 'eager' ] ] ) . $this->static_image( 1 ) . $this->static_image( 2 ) . $this->static_image( 3 ) . $this->static_image( 4 ) );

		$this->assertSame( [ 'eager', 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_page_with_only_configured_lazy_images_keeps_the_first_three_rule(): void {
		$html = $this->page( fn() => $this->the_content( $this->block_image( 1, 'lazy' ) . $this->static_image( 2 ) . $this->static_image( 3 ) . $this->static_image( 4 ) . $this->static_image( 5 ) ) );

		$this->assertSame( [ 'lazy', 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_an_image_marked_high_keeps_it_on_a_configured_page(): void {
		$html = $this->page( fn() => $this->the_content( $this->block_image( 1, 'eager' ) . $this->static_image( 2, 'fetchpriority="high"' ) . $this->static_image( 3 ) ) );

		$this->assertSame( [ 'eager', 'eager+high', 'lazy' ], $this->loading( $html ) );
	}

	public function test_high_priority_on_a_configured_page_needs_50000_square_pixels(): void {
		$icon = '<img src="https://example.org/icon.png" width="32" height="32" alt="">';
		$html = $this->page( fn() => $this->the_content( $this->block_image( 1, 'eager', $icon ) . $this->block_image( 2, 'eager' ) . $this->static_image( 3 ) ) );

		$this->assertSame( [ 'eager', 'eager+high', 'lazy' ], $this->loading( $html ) );
	}

	public function test_loading_eager_in_the_markup_stays_eager_without_taking_high_on_a_configured_page(): void {
		$html = $this->page( fn() => $this->the_content( $this->static_image( 1, 'loading="eager"' ) . $this->block_image( 2, 'eager' ) . $this->static_image( 3 ) ) );

		$this->assertSame( [ 'eager', 'eager+high', 'lazy' ], $this->loading( $html ) );
	}

	public function test_an_eager_marker_inside_noscript_does_not_switch_the_page(): void {
		$hidden = '<noscript><img data-mpi-loading="eager" src="https://example.org/hidden.jpg" width="1024" height="683" alt=""></noscript>';
		$html   = $this->filter_page( $hidden . $this->static_image( 1 ) . $this->static_image( 2 ) . $this->static_image( 3 ) . $this->static_image( 4 ) );

		$this->assertSame( [ '', 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
		$this->assertNoMarkers( $html );
	}

	public function test_content_mai_publisher_inserts_later_lazy_loads_on_a_configured_page(): void {
		$publisher = new \Mai_Publisher_Output();

		ob_start();
		ob_start( [ $publisher, 'callback' ] );

		echo $this->page(
			function () use ( $publisher ) {
				$publisher->insert = mai_get_processed_content( '<!-- wp:mpi-test/grid {"count":2} /-->' );

				return '<html><body>' . do_blocks( $this->block_image( 1, 'eager' ) ) . $this->static_image( 2 ) . '</body></html>';
			}
		);

		ob_end_flush();
		$html = (string) ob_get_clean();

		$this->assertSame( [ 'eager+high', 'lazy', 'lazy', 'lazy' ], $this->loading( $html ) );
		$this->assertNoMarkers( $html );
	}

	/*
	 * A Mai Engine page header image counts as configured Eager.
	 */

	public function test_a_page_header_image_loads_right_away_with_high_and_the_rest_lazy_load(): void {
		$html = $this->page( fn() => $this->page_header_image() . $this->the_content( $this->static_image( 1 ) . $this->static_image( 2 ) . $this->static_image( 3 ) ) );

		// 300x109 is under 50,000 square pixels, but the page header still gets high.
		$this->assertSame( [ 'eager+high', 'lazy', 'lazy', 'lazy' ], $this->loading( $html ) );
		$this->assertNoMarkers( $html );
	}

	public function test_an_eager_image_above_the_page_header_keeps_high(): void {
		$html = $this->page( fn() => $this->the_content( $this->block_image( 1, 'eager' ) ) . $this->page_header_image() . $this->static_image( 2 ) );

		$this->assertSame( [ 'eager+high', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_page_without_a_page_header_image_is_unchanged(): void {
		$section = '<section class="page-header has-page-header-image">' . $this->static_image( 1 ) . '</section>';
		$html    = $this->page( fn() => $section . $this->static_image( 2 ) . $this->static_image( 3 ) . $this->static_image( 4 ) );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_page_header_image_is_known_with_extra_classes_and_inside_a_picture(): void {
		$header = '<picture><source srcset="https://example.org/header.webp" type="image/webp"><img class="lazyload page-header-image  extra" src="https://example.org/header.jpg" width="300" height="109" alt=""></picture>';
		$html   = $this->filter_page( $header . $this->static_image( 1 ) . $this->static_image( 2 ) );

		$this->assertSame( [ 'eager+high', 'lazy', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_page_header_image_given_lazy_by_code_stays_lazy_and_changes_nothing(): void {
		$html = $this->page( fn() => $this->page_header_image( [ 'loading' => 'lazy' ] ) . $this->static_image( 1 ) . $this->static_image( 2 ) . $this->static_image( 3 ) . $this->static_image( 4 ) );

		$this->assertSame( [ 'lazy', 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_page_header_image_marked_high_by_code_keeps_it(): void {
		$html = $this->page( fn() => $this->the_content( $this->block_image( 1, 'eager' ) ) . $this->page_header_image( [ 'fetchpriority' => 'high' ] ) . $this->static_image( 2 ) );

		$this->assertSame( [ 'eager', 'eager+high', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_tiny_page_header_image_is_left_alone_and_changes_nothing(): void {
		$tiny = '<img class="page-header-image" src="https://example.org/pixel.gif" width="2" height="2" alt="">';
		$html = $this->filter_page( $tiny . $this->static_image( 1 ) . $this->static_image( 2 ) . $this->static_image( 3 ) . $this->static_image( 4 ) );

		$this->assertStringStartsWith( $tiny, $html );
		$this->assertSame( [ '', 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_only_the_page_header_image_skips_the_50000_square_pixel_floor(): void {
		$small = '<img src="https://example.org/small.jpg" width="300" height="109" alt="">';
		$html  = $this->page( fn() => $this->the_content( $this->block_image( 1, 'eager', $small ) ) . $this->page_header_image() . $this->static_image( 2 ) );

		$this->assertSame( [ 'eager', 'eager+high', 'lazy' ], $this->loading( $html ) );
	}

	public function test_nothing_changes_when_the_setting_is_off(): void {
		update_option( 'mai_performance_images', [ 'attributes' => false ] );

		remove_all_filters( 'wp_template_enhancement_output_buffer' );
		LoadingAttributes::instance()->add_page_filter();
		$this->assertFalse( has_filter( 'wp_template_enhancement_output_buffer' ) );

		$page = $this->static_image( 1 ) . $this->static_image( 2 );
		$this->assertSame( $page, $this->filter_page( $page ) );

		$core = [ 'decoding' => 'async', 'loading' => 'lazy' ];
		$this->assertSame( $core, LoadingAttributes::instance()->filter_loading_attributes( $core, 'img', [ 'width' => 100, 'height' => 100, 'class' => 'entry-image' ], 'wp_get_attachment_image' ) );

		$block = '<!-- wp:image {"imgLoading":"eager"} --><figure class="wp-block-image">' . $this->static_image( 1, 'loading="lazy"' ) . '</figure><!-- /wp:image -->';
		$this->assertSame( 'lazy', $this->images( do_blocks( $block ) )[0]['loading'] );
	}

	public function test_a_bad_value_from_another_filter_does_not_break_the_page(): void {
		add_filter( 'wp_get_loading_optimization_attributes', '__return_false', 5 );

		$this->assertIsArray( wp_get_loading_optimization_attributes( 'iframe', [ 'width' => 1, 'height' => 1 ], 'the_content' ) );
		$this->assertIsArray( wp_get_loading_optimization_attributes( 'img', [ 'width' => 1, 'height' => 1 ], 'the_content' ) );
	}

	/*
	 * Requests WordPress does not collect: REST, feeds, Ajax, the admin.
	 */

	public function test_outside_a_collected_page_wordpress_decides_and_the_editor_choice_applies(): void {
		$lazy  = '<!-- wp:image {"imgLoading":"lazy"} --><figure class="wp-block-image">' . $this->static_image( 1, 'srcset="https://example.org/a.jpg 300w" sizes="300px"' ) . '</figure><!-- /wp:image -->';
		$eager = '<!-- wp:image {"imgLoading":"eager"} --><figure class="wp-block-image">' . $this->static_image( 2 ) . '</figure><!-- /wp:image -->';
		$html  = $this->the_content( $lazy . $eager . '<!-- wp:mpi-test/grid {"count":1} /-->' );

		$this->assertNoMarkers( $html );

		$images = $this->images( $html );
		$this->assertSame( [ 'lazy', 'eager' ], [ $images[0]['loading'], $images[1]['loading'] ] );
		$this->assertSame( 'auto, 300px', $images[0]['sizes'] );

		// The grid image has no choice, so WordPress's own answer stands. Outside the
		// loop that is lazy.
		$this->assertSame( 'lazy', $images[2]['loading'] );
	}

	public function test_a_rest_response_carries_no_markers(): void {
		$lazy = '<!-- wp:image {"imgLoading":"lazy"} --><figure class="wp-block-image">' . $this->static_image( 1 ) . '</figure><!-- /wp:image -->';
		$post = self::factory()->post->create( [ 'post_content' => $lazy . '<!-- wp:mpi-test/grid {"count":2} /-->' . '[mpi_logo]' ] );

		$response = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/posts/' . $post ) );
		$html     = $response->get_data()['content']['rendered'];

		$this->assertNoMarkers( $html );
		$this->assertSame( 'lazy', $this->images( $html )[0]['loading'] );
		$this->assertCount( 4, $this->images( $html ) );
	}

	public function test_a_feed_carries_no_markers(): void {
		$lazy = '<!-- wp:image {"imgLoading":"lazy"} --><figure class="wp-block-image">' . $this->static_image( 1 ) . '</figure><!-- /wp:image -->';
		$post = self::factory()->post->create( [ 'post_content' => $lazy . '<!-- wp:mpi-test/grid {"count":2} /-->' ] );

		$this->go_to( get_permalink( $post ) );
		the_post();
		$html = get_the_content_feed();

		$this->assertNoMarkers( $html );
		$this->assertSame( 'lazy', $this->images( $html )[0]['loading'] );
	}

	public function test_forcing_lazy_outside_a_collected_page_takes_back_high_priority(): void {
		$tags = new \WP_HTML_Tag_Processor( '<img src="a.jpg" width="600" height="400" fetchpriority="high" decoding="sync" srcset="a.jpg 300w, b.jpg 600w" sizes="(max-width: 600px) 100vw, 600px">' );
		$tags->next_tag();
		LoadingAttributes::instance()->apply_choice( $tags, 'lazy' );

		$image = $this->images( $tags->get_updated_html() )[0];

		$this->assertSame( [ 'lazy', '', 'async', 'auto, (max-width: 600px) 100vw, 600px' ], [ $image['loading'], $image['fetchpriority'], $image['decoding'], $image['sizes'] ] );
	}

	public function test_markers_are_only_written_while_the_page_is_collected(): void {
		$this->assertFalse( LoadingAttributes::instance()->is_collecting() );

		$inside = '';
		$this->page(
			function () use ( &$inside ) {
				$inside = (string) LoadingAttributes::instance()->is_collecting();
			}
		);

		$this->assertSame( '1', $inside );
		$this->assertFalse( LoadingAttributes::instance()->is_collecting() );
	}
}
