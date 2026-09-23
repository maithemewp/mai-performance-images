<?php

declare(strict_types=1);

namespace Mai\PerformanceImages\Tests\Integration;

use Mai\PerformanceImages\LoadingAttributes;
use Mai\PerformanceImages\Tests\TestCase;

/**
 * Which images load right away, as WordPress actually asks about them.
 */
final class LoadingAttributesTest extends TestCase {

	/**
	 * Registers stand-ins for the things that build images mid-content.
	 */
	public function set_up(): void {
		parent::set_up();

		$image_ids = [ $this->create_image(), $this->create_image(), $this->create_image(), $this->create_image() ];

		// A grid block, which builds its images while the content renders.
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( 'mpi-test/grid' ) ) {
			register_block_type( 'mpi-test/grid', [ 'render_callback' => fn() => '' ] );
		}

		\WP_Block_Type_Registry::get_instance()->get_registered( 'mpi-test/grid' )->render_callback = static function ( $attributes ) use ( $image_ids ) {
			$html = '';

			foreach ( array_slice( $image_ids, 0, (int) ( $attributes['count'] ?? 2 ) ) as $id ) {
				$html .= wp_get_attachment_image( $id, 'full', false, [ 'class' => 'entry-image' ] );
			}

			return $html;
		};

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

	public function test_a_content_image_above_a_grid_gets_high_priority(): void {
		$html = $this->the_content( $this->static_image( 1 ) . '<!-- wp:mpi-test/grid {"count":4} /-->' );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy', 'lazy' ], $this->loading( $html ) );
	}

	public function test_an_ad_low_in_the_content_waits_its_turn(): void {
		$html = $this->the_content( $this->static_image( 1 ) . '[mpi_ad]' . $this->static_image( 2 ) . $this->static_image( 3 ) );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_an_avatar_in_the_content_lazy_loads_and_spends_nothing(): void {
		$html = $this->the_content( '[mpi_avatar]' . $this->static_image( 1 ) . $this->static_image( 2 ) . $this->static_image( 3 ) );

		$this->assertSame( [ 'lazy', 'eager+high', 'eager', 'eager' ], $this->loading( $html ) );
	}

	public function test_a_logo_in_the_content_loads_right_away_without_taking_high(): void {
		$html   = $this->the_content( '[mpi_logo]' . $this->static_image( 1 ) );
		$images = $this->images( $html );

		$this->assertSame( [ 'eager', 'auto' ], [ $images[0]['loading'], $images[0]['fetchpriority'] ] );
		$this->assertSame( 'high', $images[1]['fetchpriority'] );
	}

	public function test_an_image_asked_about_twice_spends_one_slot(): void {
		$ids = [ $this->create_image(), $this->create_image() ];

		// A Mai template part: built outside the_content, then run through WordPress's pass.
		$part = wp_filter_content_tags( implode( '', array_map( fn( $id ) => wp_get_attachment_image( $id, 'full' ), $ids ) ), 'genesis_after_header' );
		$next = wp_get_attachment_image( $this->create_image(), 'full' );
		$last = wp_get_attachment_image( $this->create_image(), 'full' );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $part . $next . $last ) );
	}

	public function test_an_image_without_dimensions_spends_nothing(): void {
		$html = $this->the_content( '<img src="https://example.org/no-size.jpg" alt="">' . $this->static_image( 1 ) );

		$this->assertSame( [ '', 'eager+high' ], $this->loading( $html ) );
	}

	public function test_an_image_marked_auto_or_low_spends_nothing(): void {
		$html = $this->the_content( $this->static_image( 1, 'fetchpriority="auto"' ) . $this->static_image( 2, 'fetchpriority="low"' ) . $this->static_image( 3 ) );

		$this->assertSame( 'high', $this->images( $html )[2]['fetchpriority'] );
	}

	public function test_an_editor_choice_on_an_image_block_wins(): void {
		$lazy  = '<!-- wp:image {"imgLoading":"lazy"} --><figure class="wp-block-image">' . $this->static_image( 1 ) . '</figure><!-- /wp:image -->';
		$eager = '<!-- wp:image {"imgLoading":"eager"} --><figure class="wp-block-image">' . $this->static_image( 2 ) . '</figure><!-- /wp:image -->';
		$html  = $this->the_content( $lazy . $this->static_image( 3 ) . $this->static_image( 4 ) . $this->static_image( 5 ) . $this->static_image( 6 ) . $eager );

		$this->assertSame( [ 'lazy', 'eager+high', 'eager', 'eager', 'lazy', 'eager' ], $this->loading( $html ) );
	}

	public function test_a_cover_choice_only_reaches_the_background_image(): void {
		$cover = '<!-- wp:cover {"imgLoading":"lazy"} --><div class="wp-block-cover"><img class="wp-block-cover__image-background" src="https://example.org/bg.jpg" width="1600" height="900" alt=""/><span class="wp-block-cover__background"></span><div class="wp-block-cover__inner-container">' . $this->static_image( 1 ) . '</div></div><!-- /wp:cover -->';
		$html  = $this->the_content( $cover );

		$this->assertSame( [ 'lazy', 'eager+high' ], $this->loading( $html ) );
	}

	public function test_forcing_lazy_after_the_fact_takes_back_high_priority(): void {
		$html = wp_get_attachment_image( $this->create_image(), 'full' );
		$this->assertStringContainsString( 'fetchpriority="high"', $html );

		$tags = new \WP_HTML_Tag_Processor( $html );
		$tags->next_tag( [ 'tag_name' => 'img' ] );
		LoadingAttributes::instance()->apply_to_tag( $tags, 'lazy' );
		$html = $tags->get_updated_html();

		$image = $this->images( $html )[0];
		$this->assertSame( [ 'lazy', '', 'async' ], [ $image['loading'], $image['fetchpriority'], $image['decoding'] ] );

		// The slot is free again for the next image.
		$this->assertStringContainsString( 'fetchpriority="high"', wp_get_attachment_image( $this->create_image(), 'full' ) );
	}

	public function test_forcing_lazy_adds_sizes_auto_when_there_is_a_srcset(): void {
		$tags = new \WP_HTML_Tag_Processor( '<img src="a.jpg" srcset="a.jpg 300w, b.jpg 600w" sizes="(max-width: 600px) 100vw, 600px">' );
		$tags->next_tag();
		LoadingAttributes::instance()->apply_to_tag( $tags, 'lazy' );

		$this->assertSame( 'auto, (max-width: 600px) 100vw, 600px', $tags->get_attribute( 'sizes' ) );
	}

	public function test_a_bad_value_from_another_filter_does_not_break_the_page(): void {
		add_filter( 'wp_get_loading_optimization_attributes', '__return_false', 5 );

		$this->assertIsArray( wp_get_loading_optimization_attributes( 'iframe', [ 'width' => 1, 'height' => 1 ], 'the_content' ) );
		$this->assertIsArray( wp_get_loading_optimization_attributes( 'img', [ 'width' => 1, 'height' => 1 ], 'the_content' ) );
	}


	public function test_a_hero_above_a_grid_in_a_template_part_gets_high_priority(): void {
		$html = mai_get_processed_content( $this->static_image( 1 ) . '<!-- wp:mpi-test/grid {"count":4} /-->' );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy', 'lazy' ], $this->loading( $html ) );
	}

	public function test_an_image_marked_high_keeps_it_and_loads_right_away(): void {
		$images = implode( '', array_map( fn() => wp_get_attachment_image( $this->create_image(), 'full', false, [ 'class' => 'x' ] ), range( 1, 3 ) ) );
		$hero   = wp_get_attachment_image( $this->create_image(), 'full', false, [ 'fetchpriority' => 'high' ] );
		$after  = wp_get_attachment_image( $this->create_image(), 'full' );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'eager+high', 'lazy' ], $this->loading( $images . $hero . $after ) );
	}

	public function test_an_image_marked_high_in_the_content_is_not_lazy(): void {
		$html = $this->the_content( $this->static_image( 1, 'fetchpriority="high"' ) . $this->static_image( 2 ) );

		$this->assertSame( [ 'eager+high', 'eager' ], $this->loading( $html ) );
	}

	public function test_a_small_icon_never_takes_high_priority(): void {
		$html = $this->the_content( '<img src="https://example.org/icon.png" width="32" height="32" alt="">' . $this->static_image( 1 ) );

		$this->assertSame( [ 'eager', 'eager+high' ], $this->loading( $html ) );
	}

	public function test_nothing_changes_when_the_setting_is_off(): void {
		update_option( 'mai_performance_images', [ 'attributes' => false ] );

		$core = [ 'decoding' => 'async', 'loading' => 'lazy' ];

		$this->assertSame( $core, LoadingAttributes::instance()->filter_loading_attributes( $core, 'img', [ 'width' => 100, 'height' => 100 ], 'wp_get_attachment_image' ) );

		$block = '<!-- wp:image {"imgLoading":"eager"} --><figure class="wp-block-image">' . $this->static_image( 1, 'loading="lazy"' ) . '</figure><!-- /wp:image -->';
		$this->assertSame( 'lazy', $this->images( do_blocks( $block ) )[0]['loading'] );
	}

	public function test_the_post_preview_block_lazy_loads_its_images(): void {
		$html = apply_filters( 'render_block_acf/mai-post-preview', wp_get_attachment_image( $this->create_image(), 'full' ), [ 'blockName' => 'acf/mai-post-preview' ] );

		$this->assertSame( [ 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_synced_pattern_is_counted_in_page_order(): void {
		$pattern = self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_content' => $this->static_image( 2 ) . '<!-- wp:mpi-test/grid {"count":3} /-->',
			]
		);

		$html = $this->the_content( $this->static_image( 1 ) . '<!-- wp:block {"ref":' . $pattern . '} /-->' );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_content_area_added_to_the_content_is_counted_in_page_order(): void {
		// Mai Custom Content Areas adds its area this way, before WordPress's pass.
		add_filter( 'the_content', fn( $content ) => mai_get_processed_content( '<!-- wp:mpi-test/grid {"count":2} /-->' ) . $content );

		$html = $this->the_content( $this->static_image( 1 ) . $this->static_image( 2 ) );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_content_area_added_after_the_pass_is_counted_in_page_order(): void {
		add_filter( 'the_content', fn( $content ) => $content . mai_get_processed_content( $this->static_image( 9 ) . '<!-- wp:mpi-test/grid {"count":2} /-->' ), 20 );

		$html = $this->the_content( $this->static_image( 1 ) );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_an_area_printed_on_a_hook_is_counted_in_page_order(): void {
		add_action( 'mpi_test_hook', fn() => print( mai_get_processed_content( $this->static_image( 1 ) . '<!-- wp:mpi-test/grid {"count":3} /-->' ) ) );

		ob_start();
		do_action( 'mpi_test_hook' );
		$html = ob_get_clean() . wp_get_attachment_image( $this->create_image(), 'full' );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy', 'lazy' ], $this->loading( $html ) );
	}
}
