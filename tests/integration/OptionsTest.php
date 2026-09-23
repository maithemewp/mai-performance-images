<?php

declare(strict_types=1);

namespace Mai\PerformanceImages\Tests\Integration;

use Mai\PerformanceImages\Settings;
use Mai\PerformanceImages\Tests\TestCase;
use function Mai\PerformanceImages\get_plugin_options;
use function Mai\PerformanceImages\is_attributes_enabled;
use function Mai\PerformanceImages\remove_conversion_leftovers;

/**
 * The saved setting, and the cleanup of what WebP conversion left behind.
 */
final class OptionsTest extends TestCase {

	public function test_loading_is_on_when_nothing_is_saved(): void {
		delete_option( 'mai_performance_images' );

		$this->assertTrue( is_attributes_enabled() );
	}

	public function test_a_broken_saved_value_falls_back_to_the_default(): void {
		update_option( 'mai_performance_images', 'junk' );

		$this->assertSame( [ 'attributes' => true ], get_plugin_options() );
	}

	public function test_an_old_saved_value_keeps_its_choice(): void {
		update_option( 'mai_performance_images', [ 'attributes' => false, 'conversion' => true, 'quality' => 80 ] );

		$this->assertFalse( is_attributes_enabled() );
	}

	public function test_saving_the_form_stores_only_the_checkbox(): void {
		$settings = new Settings();

		$this->assertSame( [ 'attributes' => true ], $settings->sanitize( [ 'attributes' => '1', 'conversion' => '1' ] ) );
		$this->assertSame( [ 'attributes' => false ], $settings->sanitize( [] ) );
		$this->assertSame( [ 'attributes' => false ], $settings->sanitize( 'junk' ) );
	}

	public function test_conversion_leftovers_are_removed_once(): void {
		update_option( 'mai_performance_images_processor_batch_abc', [ 'x' ] );
		update_option( 'mai_performance_images_processor_status', 1 );
		update_option( 'mai_performance_images', [ 'attributes' => true ] );
		wp_schedule_event( time(), 'daily', 'mai_performance_images_cleanup_cache' );
		wp_schedule_event( time(), 'hourly', 'mai_performance_images_processor_cron' );

		remove_conversion_leftovers();

		$this->assertFalse( get_option( 'mai_performance_images_processor_batch_abc' ) );
		$this->assertFalse( get_option( 'mai_performance_images_processor_status' ) );
		$this->assertFalse( wp_next_scheduled( 'mai_performance_images_cleanup_cache' ) );
		$this->assertFalse( wp_next_scheduled( 'mai_performance_images_processor_cron' ) );
		$this->assertSame( [ 'attributes' => true ], get_option( 'mai_performance_images' ) );

		// A second run does nothing, even if something new appears.
		update_option( 'mai_performance_images_processor_batch_def', [ 'y' ] );
		remove_conversion_leftovers();
		$this->assertSame( [ 'y' ], get_option( 'mai_performance_images_processor_batch_def' ) );
	}
}
