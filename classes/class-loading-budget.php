<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Decides which images load immediately and which one gets high priority.
 *
 * WordPress makes the same decision on its own, from document order and the file's
 * own width times height. That is all it can know. This plugin knows more: it can
 * tell a logo from an entry image from an avatar, and it knows the archive and
 * single settings for the template being rendered. So it answers instead.
 *
 * Two rules, both borrowed from core because both are sound:
 *
 * 1. The first few images on a page load immediately, the rest lazy load. Which
 *    images those are is a count in document order, not a guess about the fold.
 * 2. Exactly one image gets fetchpriority="high". The hint is a ranking, so marking
 *    ten images high says the same as marking none.
 *
 * What differs from core is which images are allowed to spend a slot. Core counts
 * anything over 50,000 square pixels, measured on the file rather than on the page,
 * so a logo uploaded large for retina takes the slot from the article image. This
 * class skips by kind instead: a logo is never an LCP candidate no matter what it
 * was uploaded at.
 *
 * @since 0.7.0
 */
final class LoadingBudget {
	/**
	 * Image contexts and classes that never spend a slot.
	 *
	 * A logo, a scroll logo and an avatar are never the largest painted element,
	 * whatever their file dimensions say. Skipping them is the whole reason this
	 * class exists rather than leaving the count to WordPress.
	 *
	 * @since 0.7.0
	 *
	 * @var array
	 */
	private const SKIP_CONTEXTS = [
		'mai_logo',
		'mai_scroll_logo',
		'get_avatar',
	];

	/**
	 * Class fragments that never spend a slot, for images that arrive without a
	 * context of their own.
	 *
	 * @since 0.7.0
	 *
	 * @var array
	 */
	private const SKIP_CLASSES = [
		'custom-logo',
		'custom-scroll-logo',
		'avatar',
	];

	/**
	 * How many counted images have been seen.
	 *
	 * @since 0.7.0
	 *
	 * @var int
	 */
	private int $counted = 0;

	/**
	 * Whether the single high-priority slot has been spent.
	 *
	 * @since 0.7.0
	 *
	 * @var bool
	 */
	private bool $high_used = false;

	/**
	 * How many images load immediately before the rest lazy load.
	 *
	 * @since 0.7.0
	 *
	 * @var int|null
	 */
	private ?int $eager_count = null;

	/**
	 * Whether this image is allowed to spend a slot.
	 *
	 * @since 0.7.0
	 *
	 * @param array  $attr    The attributes for the tag.
	 * @param string $context The context for the element.
	 *
	 * @return bool
	 */
	public function counts( array $attr, string $context ): bool {
		if ( in_array( $context, self::SKIP_CONTEXTS, true ) ) {
			return false;
		}

		$class = (string) ( $attr['class'] ?? '' );

		foreach ( self::SKIP_CLASSES as $fragment ) {
			if ( str_contains( $class, $fragment ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Takes the next slot and returns the attributes for it.
	 *
	 * Call once per image that counts. An image that does not count should be given
	 * decline() instead, so it neither consumes a slot nor claims priority.
	 *
	 * @since 0.7.0
	 *
	 * @return array
	 */
	public function next(): array {
		$this->counted++;

		if ( $this->counted > $this->get_eager_count() ) {
			return $this->lazy();
		}

		return $this->eager();
	}

	/**
	 * Spends a slot on a decision already made elsewhere.
	 *
	 * A block or a template setting that forces eager or lazy still consumes a slot,
	 * so the count keeps describing the page rather than only the images nobody
	 * configured.
	 *
	 * @since 0.7.0
	 *
	 * @param string $loading Either 'eager' or 'lazy'.
	 *
	 * @return array
	 */
	public function take( string $loading ): array {
		$this->counted++;

		return 'eager' === $loading ? $this->eager() : $this->lazy();
	}

	/**
	 * Sets how many images load immediately, overriding the default.
	 *
	 * Used by the archive setting, which carries its own count.
	 *
	 * @since 0.7.0
	 *
	 * @param int $count The number of images.
	 *
	 * @return void
	 */
	public function set_eager_count( int $count ): void {
		$this->eager_count = max( 1, $count );
	}

	/**
	 * Returns the attributes for an image that loads immediately.
	 *
	 * Only the first of them claims high priority. Every later one loads
	 * immediately without a priority hint, which leaves the browser's own
	 * ordering intact rather than flattening it.
	 *
	 * @since 0.7.0
	 *
	 * @return array
	 */
	public function eager(): array {
		$attrs = [
			'loading'  => 'eager',
			'decoding' => 'sync',
		];

		if ( ! $this->high_used ) {
			$this->high_used        = true;
			$attrs['fetchpriority'] = 'high';
		}

		return $attrs;
	}

	/**
	 * Returns the attributes for a lazy loaded image.
	 *
	 * fetchpriority is deliberately left off. WordPress adds sizes="auto" to lazy
	 * images, which lets the browser measure the space the image actually fills and
	 * download the matching file, and a priority hint here would only compete with
	 * the one image that should have it.
	 *
	 * @since 0.7.0
	 *
	 * @return array
	 */
	public function lazy(): array {
		return [
			'loading'  => 'lazy',
			'decoding' => 'async',
		];
	}

	/**
	 * Returns the attributes for an image that declines its slot.
	 *
	 * "auto" is WordPress's own way of saying an image may or may not be visible.
	 * It keeps the image out of the running for high priority without claiming the
	 * image is offscreen.
	 *
	 * @since 0.7.0
	 *
	 * @return array
	 */
	public function decline(): array {
		return [
			'loading'       => 'eager',
			'fetchpriority' => 'auto',
			'decoding'      => 'sync',
		];
	}

	/**
	 * How many images load immediately before the rest lazy load.
	 *
	 * Defaults to WordPress's own threshold so an unconfigured site behaves the way
	 * WordPress would, only with better judgement about which images qualify.
	 *
	 * @since 0.7.0
	 *
	 * @return int
	 */
	public function get_eager_count(): int {
		if ( ! is_null( $this->eager_count ) ) {
			return $this->eager_count;
		}

		$default = function_exists( 'wp_omit_loading_attr_threshold' ) ? (int) wp_omit_loading_attr_threshold() : 3;

		/**
		 * Filters how many images load immediately before the rest lazy load.
		 *
		 * @since 0.7.0
		 *
		 * @param int $count The number of images.
		 */
		$this->eager_count = max( 1, (int) apply_filters( 'mai_performance_images_eager_count', $default ) );

		return $this->eager_count;
	}

	/**
	 * Resets the budget.
	 *
	 * Needed wherever a page renders more than once in a request, such as the
	 * customizer preview or a REST render.
	 *
	 * @since 0.7.0
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->counted   = 0;
		$this->high_used = false;
	}
}
