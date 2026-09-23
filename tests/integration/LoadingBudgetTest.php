<?php

declare(strict_types=1);

namespace Mai\PerformanceImages\Tests\Integration;

use Mai\PerformanceImages\LoadingBudget;
use Mai\PerformanceImages\Tests\TestCase;

/**
 * The counting rules on their own.
 */
final class LoadingBudgetTest extends TestCase {

	public function test_first_three_load_right_away_and_only_the_first_is_high(): void {
		$budget  = new LoadingBudget();
		$answers = array_map( fn() => $budget->next(), range( 1, 5 ) );

		$this->assertSame( [ 'eager', 'eager', 'eager', 'lazy', 'lazy' ], array_column( $answers, 'loading' ) );
		$this->assertSame( 'high', $answers[0]['fetchpriority'] );
		$this->assertArrayNotHasKey( 'fetchpriority', $answers[1] );
	}

	public function test_the_eager_count_filter_changes_the_count_and_never_drops_below_one(): void {
		add_filter( 'mai_performance_images_eager_count', fn() => 0 );

		$budget = new LoadingBudget();

		$this->assertSame( 1, $budget->get_eager_count() );
		$this->assertSame( 'eager', $budget->next()['loading'] );
		$this->assertSame( 'lazy', $budget->next()['loading'] );
	}

	public function test_a_chosen_lazy_image_does_not_spend_a_slot(): void {
		$budget = new LoadingBudget();

		$budget->take( 'lazy' );
		$budget->take( 'lazy' );

		$this->assertSame( [ 'eager', 'eager', 'eager', 'lazy' ], array_column( [ $budget->next(), $budget->next(), $budget->next(), $budget->next() ], 'loading' ) );
	}

	public function test_a_chosen_eager_image_spends_a_slot_and_takes_high(): void {
		$budget = new LoadingBudget();

		$this->assertSame( 'high', $budget->take( 'eager' )['fetchpriority'] );
		$budget->next();
		$budget->next();

		$this->assertSame( 'lazy', $budget->next()['loading'] );
	}

	public function test_keeping_an_answered_image_spends_nothing(): void {
		$budget = new LoadingBudget();

		$this->assertSame( [ 'loading' => 'eager' ], $budget->keep( 'eager', 'high' ) );

		// High was already on the kept image, so the next image does not get it.
		$next = $budget->next();
		$this->assertSame( 'eager', $next['loading'] );
		$this->assertArrayNotHasKey( 'fetchpriority', $next );

		$budget->next();
		$budget->next();
		$this->assertSame( 'lazy', $budget->next()['loading'] );
	}

	public function test_a_kept_eager_image_without_priority_takes_high_when_it_is_free(): void {
		$budget = new LoadingBudget();

		$this->assertSame( 'high', $budget->keep( 'eager', '' )['fetchpriority'] );
	}

	public function test_released_high_goes_to_the_next_eager_image(): void {
		$budget = new LoadingBudget();

		$budget->next();
		$budget->release_high();

		$this->assertSame( 'high', $budget->next()['fetchpriority'] );
	}

	public function test_logos_and_avatars_are_known_by_context_or_class(): void {
		$budget = new LoadingBudget();

		$this->assertSame( 'logo', $budget->kind( [], 'mai_logo' ) );
		$this->assertSame( 'logo', $budget->kind( [ 'class' => 'custom-logo' ], 'wp_get_attachment_image' ) );
		$this->assertSame( 'avatar', $budget->kind( [], 'get_avatar' ) );
		$this->assertSame( 'avatar', $budget->kind( [ 'class' => 'avatar avatar-48 photo' ], 'wp_get_attachment_image' ) );
		$this->assertSame( '', $budget->kind( [ 'class' => 'avatar-card-image' ], 'wp_get_attachment_image' ) );
	}

	public function test_a_logo_loads_right_away_without_priority_and_an_avatar_lazy_loads(): void {
		$budget = new LoadingBudget();

		$this->assertSame( [ 'loading' => 'eager', 'fetchpriority' => 'auto', 'decoding' => 'sync' ], $budget->for_kind( 'logo' ) );
		$this->assertSame( 'lazy', $budget->for_kind( 'avatar' )['loading'] );
		$this->assertSame( 'high', $budget->next()['fetchpriority'] );
	}

	public function test_reset_starts_the_page_over(): void {
		$budget = new LoadingBudget();

		array_map( fn() => $budget->next(), range( 1, 4 ) );
		$budget->reset();

		$this->assertSame( 'high', $budget->next()['fetchpriority'] );
	}
}
