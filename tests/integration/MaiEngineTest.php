<?php

declare(strict_types=1);

namespace Mai\PerformanceImages\Tests\Integration;

use Mai\PerformanceImages\MaiEngine;
use Mai\PerformanceImages\Tests\TestCase;
use function Mai\PerformanceImages\add_mai_engine_support;

/**
 * The wiring into Mai Engine, with a stand-in Mai_Engine class from tests/stubs.php.
 */
final class MaiEngineTest extends TestCase {

	public function test_mai_support_answers_for_entry_images(): void {
		add_mai_engine_support();

		do_action( 'mai_before_entry', null, [ 'image_loading' => 'lazy' ] );
		$loading = apply_filters( 'mai_performance_images_entry_loading', '', [] );
		do_action( 'mai_after_entry', null, [] );

		$this->assertSame( 'lazy', $loading );
	}

	public function test_mai_support_stays_off_when_the_setting_is_off(): void {
		// The bootstrap already ran this once, with the setting on.
		remove_all_filters( 'mai_performance_images_entry_loading' );
		remove_all_filters( 'mai_grid_args' );

		update_option( 'mai_performance_images', [ 'attributes' => false ] );
		add_mai_engine_support();

		$this->assertFalse( has_filter( 'mai_performance_images_entry_loading' ) );
		$this->assertFalse( has_filter( 'mai_grid_args' ) );
	}

	public function test_grid_blocks_pass_their_setting_to_their_entries(): void {
		$GLOBALS['mpi_test_fields'] = [
			'image_loading'       => 'eager',
			'image_loading_count' => '2',
		];

		$args = ( new MaiEngine() )->add_grid_args( [ 'context' => 'block' ] );

		unset( $GLOBALS['mpi_test_fields'] );

		$this->assertSame( 'eager', $args['image_loading'] );
		$this->assertSame( '2', $args['image_loading_count'] );
	}

	public function test_the_customizer_settings_go_after_the_image_settings(): void {
		$engine   = new MaiEngine();
		$archive  = $engine->add_archive_settings( [ [ 'settings' => 'show' ], [ 'settings' => 'image_width' ], [ 'settings' => 'title' ] ], 'post' );
		$single   = $engine->add_single_settings( [ [ 'settings' => 'image_size' ], [ 'settings' => 'title' ] ], 'post' );

		$this->assertSame( [ 'show', 'image_width', 'image_loading', 'image_loading_count', 'title' ], array_column( $archive, 'settings' ) );
		$this->assertSame( [ 'image_size', 'image_loading', 'title' ], array_column( $single, 'settings' ) );
	}
}
