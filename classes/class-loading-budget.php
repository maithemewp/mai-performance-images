<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Decides which images load immediately and which one gets high priority.
 *
 * WordPress makes the same decision on its own. It counts images in the main loop
 * and, before the loop, anything over 50,000 square pixels measured on the file. So
 * a logo uploaded large for retina can take the slot from the article image. This
 * plugin knows more: it can tell a logo from an entry image from an avatar, and it
 * knows the archive, single and grid settings for the entry being rendered.
 *
 * Two rules, both borrowed from core because both are sound:
 *
 * 1. The first few images WordPress asks about load immediately, the rest lazy
 *    load. LoadingAttributes makes sure that order is page order.
 * 2. At most one image gets fetchpriority="high". The hint is a ranking, so marking
 *    ten images high says the same as marking none.
 *
 * @since 0.7.0
 */
final class LoadingBudget {
	/**
	 * Contexts that belong to a logo.
	 *
	 * @since 0.7.0
	 *
	 * @var array
	 */
	private const LOGO_CONTEXTS = [
		'mai_logo',
		'mai_scroll_logo',
	];

	/**
	 * Class names that belong to a logo.
	 *
	 * @since 0.7.0
	 *
	 * @var array
	 */
	private const LOGO_CLASSES = [
		'custom-logo',
		'custom-scroll-logo',
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
	 * Returns what kind of image this is, when the kind decides its loading.
	 *
	 * A logo is never the largest painted element, and neither is an avatar, so
	 * neither spends a slot. A logo sits in the header and loads right away. An
	 * avatar is usually in a comment list and lazy loads.
	 *
	 * Only images built by wp_get_attachment_image() or get_avatar() carry a class
	 * or a context to go on. WordPress's pass over finished HTML passes neither.
	 *
	 * @since 0.7.0
	 *
	 * @param array  $attr    The attributes for the tag.
	 * @param string $context The context for the element.
	 *
	 * @return string 'logo', 'avatar', or an empty string.
	 */
	public function kind( array $attr, string $context ): string {
		$classes = self::classes( $attr );

		if ( in_array( $context, self::LOGO_CONTEXTS, true ) || array_intersect( self::LOGO_CLASSES, $classes ) ) {
			return 'logo';
		}

		if ( 'get_avatar' === $context || in_array( 'avatar', $classes, true ) ) {
			return 'avatar';
		}

		return '';
	}

	/**
	 * Returns the attributes for an image of a kind that never spends a slot.
	 *
	 * @since 0.7.0
	 *
	 * @param string $kind 'logo' or 'avatar'.
	 *
	 * @return array
	 */
	public function for_kind( string $kind ): array {
		return 'logo' === $kind ? $this->decline() : $this->lazy();
	}

	/**
	 * Takes the next slot and returns the attributes for it.
	 *
	 * Call once per image that counts.
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
	 * Returns the attributes for a decision already made elsewhere.
	 *
	 * Eager spends a slot, so the images after it still count from the top of the
	 * page. Lazy does not, because an image somebody chose to lazy load is not near
	 * the top of the page.
	 *
	 * @since 0.7.0
	 *
	 * @param string $loading Either 'eager' or 'lazy'.
	 *
	 * @return array
	 */
	public function take( string $loading ): array {
		if ( 'eager' !== $loading ) {
			return $this->lazy();
		}

		$this->counted++;

		return $this->eager();
	}

	/**
	 * Returns the attributes for an image that already carries its loading value.
	 *
	 * This is WordPress asking again about an image this plugin answered while the
	 * tag was built, or an image a block wrote its choice onto. Neither spends a
	 * slot, which is also what core does. An eager image may still take high
	 * priority, when no image has it yet.
	 *
	 * @since 0.7.0
	 *
	 * @param string $loading       Either 'eager' or 'lazy'.
	 * @param string $fetchpriority The fetchpriority already on the image, if any.
	 *
	 * @return array
	 */
	public function keep( string $loading, string $fetchpriority ): array {
		if ( 'lazy' === $loading ) {
			return $this->lazy();
		}

		if ( 'high' === $fetchpriority ) {
			$this->high_used = true;
		}

		if ( $fetchpriority ) {
			return [ 'loading' => 'eager' ];
		}

		return $this->eager();
	}

	/**
	 * Gives back the high-priority slot.
	 *
	 * Used when a block forces lazy loading onto an image after it was given high
	 * priority, so the next eager image can have it instead.
	 *
	 * @since 0.7.0
	 *
	 * @return void
	 */
	public function release_high(): void {
		$this->high_used = false;
	}

	/**
	 * Returns the attributes for an image that loads immediately.
	 *
	 * The first one also gets high priority.
	 *
	 * @since 0.7.0
	 *
	 * @return array
	 */
	private function eager(): array {
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
	 * No fetchpriority, so a lazy image that turns out to be in view is not held
	 * back.
	 *
	 * @since 0.7.0
	 *
	 * @return array
	 */
	private function lazy(): array {
		return [
			'loading'  => 'lazy',
			'decoding' => 'async',
		];
	}

	/**
	 * Returns the attributes for an image that loads immediately without a slot.
	 *
	 * "auto" is WordPress's own way of saying an image may or may not be visible.
	 * It keeps the image out of the running for high priority, and WordPress does
	 * not count an image that carries it.
	 *
	 * @since 0.7.0
	 *
	 * @return array
	 */
	private function decline(): array {
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
	 * Starts the count over.
	 *
	 * For anything that renders a second page in the same request.
	 *
	 * @since 0.7.0
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->counted     = 0;
		$this->high_used   = false;
		$this->eager_count = null;
	}

	/**
	 * Returns the image's class names.
	 *
	 * @since 0.7.0
	 *
	 * @param array $attr The attributes for the tag.
	 *
	 * @return array
	 */
	public static function classes( array $attr ): array {
		return array_filter( explode( ' ', (string) ( $attr['class'] ?? '' ) ) );
	}
}
