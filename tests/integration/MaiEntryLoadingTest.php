<?php

declare(strict_types=1);

namespace Mai\PerformanceImages\Tests\Integration;

use Mai\PerformanceImages\MaiEntryLoading;
use Mai\PerformanceImages\Tests\TestCase;

/**
 * The Image Loading settings on Mai archives, singles and grids.
 *
 * Mai Engine is not loaded, so these fire the same hooks Mai_Entry and Genesis fire.
 */
final class MaiEntryLoadingTest extends TestCase {

	public function set_up(): void {
		parent::set_up();

		new MaiEntryLoading();
	}

	/**
	 * Renders a loop of entries the way Mai does, returning each entry's image.
	 *
	 * @param array $args  The loop's settings.
	 * @param int   $count How many entries.
	 *
	 * @return string
	 */
	private function loop( array $args, int $count ): string {
		$html = $this->open_loop();

		foreach ( range( 1, $count ) as $n ) {
			$html .= $this->entry( $args );
		}

		return $html . $this->close_loop();
	}

	private function open_loop(): string {
		// Genesis runs the open and close filters on every call. This is the opening call.
		apply_filters( 'genesis_markup_entries_open', '<div class="entries">', [ 'open' => '<div class="entries">', 'close' => '' ] );
		apply_filters( 'genesis_markup_entries_close', '', [ 'open' => '<div class="entries">', 'close' => '' ] );

		return '';
	}

	private function close_loop(): string {
		apply_filters( 'genesis_markup_entries_open', '', [ 'open' => '', 'close' => '</div>' ] );
		apply_filters( 'genesis_markup_entries_close', '</div>', [ 'open' => '', 'close' => '</div>' ] );

		return '';
	}

	private function entry( array $args, string $inner = '' ): string {
		do_action( 'mai_before_entry', null, $args );
		$html = wp_get_attachment_image( $this->create_image(), 'full', false, [ 'class' => 'entry-image' ] ) . $inner;
		do_action( 'mai_after_entry', null, $args );

		return $html;
	}

	public function test_eager_with_a_count_loads_only_that_many_right_away(): void {
		$html = $this->loop( [ 'image_loading' => 'eager', 'image_loading_count' => 2 ], 5 );

		$this->assertSame( [ 'eager+high', 'eager', 'lazy', 'lazy', 'lazy' ], $this->loading( $html ) );
	}

	public function test_eager_without_a_count_loads_them_all_right_away(): void {
		$html = $this->loop( [ 'image_loading' => 'eager', 'image_loading_count' => '' ], 5 );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'eager', 'eager' ], $this->loading( $html ) );
	}

	public function test_lazy_lazy_loads_them_all_and_leaves_the_slots_for_later_images(): void {
		$html  = $this->loop( [ 'image_loading' => 'lazy' ], 3 );
		$html .= wp_get_attachment_image( $this->create_image(), 'full' );

		$this->assertSame( [ 'lazy', 'lazy', 'lazy', 'eager+high' ], $this->loading( $html ) );
	}

	public function test_automatic_uses_the_first_three_rule(): void {
		$html = $this->loop( [ 'image_loading' => '' ], 5 );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_grid_setting_ends_with_the_grid(): void {
		$html  = $this->loop( [ 'image_loading' => 'lazy' ], 2 );
		$html .= wp_get_attachment_image( $this->create_image(), 'full', false, [ 'class' => 'entry-image' ] );

		$this->assertSame( [ 'lazy', 'lazy', 'eager+high' ], $this->loading( $html ) );
	}

	public function test_a_grid_inside_an_entry_answers_for_its_own_images(): void {
		$this->open_loop();
		$grid  = $this->loop( [ 'image_loading' => 'lazy' ], 2 );
		$html  = $this->entry( [ 'image_loading' => 'eager' ], $grid );
		$this->close_loop();

		$this->assertSame( [ 'eager+high', 'lazy', 'lazy' ], $this->loading( $html ) );
	}

	public function test_only_the_entry_image_follows_the_setting(): void {
		do_action( 'mai_before_entry', null, [ 'image_loading' => 'lazy' ] );
		$html = wp_get_attachment_image( $this->create_image(), 'full', false, [ 'class' => 'adjacent-entry-image' ] );
		do_action( 'mai_after_entry', null, [] );

		$this->assertSame( [ 'eager+high' ], $this->loading( $html ) );
	}
}
