<?php

declare(strict_types=1);

namespace Mai\PerformanceImages\Tests\Integration;

use Mai\PerformanceImages\Tests\TestCase;
use function Mai\PerformanceImages\remove_conversion_leftovers;
use function Mai\PerformanceImages\remove_settings_leftovers;

/**
 * The cleanup of what WebP conversion and the settings page left behind.
 */
final class OptionsTest extends TestCase {

	public function test_conversion_leftovers_are_removed_once(): void {
		update_option( 'mai_performance_images_processor_batch_abc', [ 'x' ] );
		update_option( 'mai_performance_images_processor_status', 1 );
		wp_schedule_event( time(), 'daily', 'mai_performance_images_cleanup_cache' );
		wp_schedule_event( time(), 'hourly', 'mai_performance_images_processor_cron' );

		remove_conversion_leftovers();

		$this->assertFalse( get_option( 'mai_performance_images_processor_batch_abc' ) );
		$this->assertFalse( get_option( 'mai_performance_images_processor_status' ) );
		$this->assertFalse( wp_next_scheduled( 'mai_performance_images_cleanup_cache' ) );
		$this->assertFalse( wp_next_scheduled( 'mai_performance_images_processor_cron' ) );

		// A second run does nothing, even if something new appears.
		update_option( 'mai_performance_images_processor_batch_def', [ 'y' ] );
		remove_conversion_leftovers();
		$this->assertSame( [ 'y' ], get_option( 'mai_performance_images_processor_batch_def' ) );
	}

	public function test_the_old_saved_setting_is_deleted(): void {
		update_option( 'mai_performance_images', [ 'attributes' => false, 'conversion' => false, 'quality' => 80 ] );

		remove_settings_leftovers();

		$this->assertFalse( get_option( 'mai_performance_images' ) );
	}
}
