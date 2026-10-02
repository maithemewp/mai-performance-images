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
 * 1. The first few images on the page load immediately, the rest lazy load.
 *    LoadingAttributes asks once per image, top to bottom, on the finished page.
 * 2. At most one image gets fetchpriority="high". The hint is a ranking, so marking
 *    ten images high says the same as marking none.
 *
 * A page with an image configured Eager follows its configuration instead of rule
 * 1. Its Eager images load immediately and every image nobody chose for lazy
 * loads. Rule 2 still holds.
 *
 * One budget covers one page.
 *
 * @since 0.7.0
 * @since 0.8.0 Follows the configuration on a page with an image configured Eager.
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
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param bool $configured Whether the page has an image configured Eager. Then
	 *                         next() lazy loads instead of counting.
	 */
	public function __construct(
		public readonly bool $configured = false
	) {}

	/**
	 * Returns what kind of image this is, when the kind decides its loading.
	 *
	 * A logo is never the largest painted element, and neither is an avatar, so
	 * neither spends a slot. A logo sits in the header and loads right away. An
	 * avatar is usually in a comment list and lazy loads.
	 *
	 * The context is only known while a tag is built. The class is on the tag, so
	 * it is known on the finished page too.
	 *
	 * @since 0.7.0
	 * @since 0.8.0 Static, and the context is optional.
	 *
	 * @param array  $attr    The attributes for the tag.
	 * @param string $context The context for the element, if known.
	 *
	 * @return string 'logo', 'avatar', or an empty string.
	 */
	public static function kind( array $attr, string $context = '' ): string {
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
	 * Takes the next slot and returns the attributes for it.
	 *
	 * Call once per image that counts. On a page that follows its configuration,
	 * nothing is counted: an image nobody chose for lazy loads.
	 *
	 * @since 0.7.0
	 * @since 0.8.0 Lazy on a page that follows its configuration.
	 *
	 * @param array $attr The attributes for the tag, for its width and height.
	 *
	 * @return array
	 */
	public function next( array $attr = [] ): array {
		if ( $this->configured ) {
			return $this->lazy();
		}

		$this->counted++;

		if ( $this->counted > $this->get_eager_count() ) {
			return $this->lazy();
		}

		return $this->eager( $attr );
	}

	/**
	 * Returns the attributes for a decision already made elsewhere.
	 *
	 * Eager spends a slot, so the images after it still count from the top of the
	 * page. Lazy does not, because an image somebody chose to lazy load is not near
	 * the top of the page.
	 *
	 * @since 0.7.0
	 * @since 0.8.0 Can skip the size floor for high priority.
	 *
	 * @param string $loading Either 'eager' or 'lazy'.
	 * @param array  $attr    The attributes for the tag, for its width and height.
	 * @param bool   $floor   Whether the image must be big enough for high priority.
	 *
	 * @return array
	 */
	public function take( string $loading, array $attr = [], bool $floor = true ): array {
		if ( 'eager' !== $loading ) {
			return $this->lazy();
		}

		$this->counted++;

		return $this->eager( $attr, $floor );
	}

	/**
	 * Returns the attributes for an image already marked high priority.
	 *
	 * Somebody chose it as the page's main image, so it keeps high priority, loads
	 * right away, and spends a slot. WordPress honors the same choice.
	 *
	 * @since 0.7.0
	 *
	 * @return array
	 */
	public function take_high(): array {
		$this->counted++;
		$this->high_used = true;

		return [
			'loading'       => 'eager',
			'fetchpriority' => 'high',
			'decoding'      => 'sync',
		];
	}

	/**
	 * Returns the attributes for a logo or an avatar, which never spend a slot.
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
	 * Returns the attributes for a lazy loaded image.
	 *
	 * No fetchpriority, so a lazy image that turns out to be in view is not held
	 * back.
	 *
	 * @since 0.7.0
	 * @since 0.8.0 Public, for choices that spend nothing.
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
	 * Returns the attributes for an image that loads immediately.
	 *
	 * The first one big enough to be the main image also gets high priority. The
	 * floor is WordPress's own, 50,000 square pixels, so an icon never takes it.
	 * Without a width and height the image is given the benefit of the doubt.
	 *
	 * @since 0.7.0
	 * @since 0.8.0 Can skip the size floor.
	 *
	 * @param array $attr  The attributes for the tag, for its width and height.
	 * @param bool  $floor Whether the image must be big enough for high priority.
	 *
	 * @return array
	 */
	private function eager( array $attr = [], bool $floor = true ): array {
		$attrs = [
			'loading'  => 'eager',
			'decoding' => 'sync',
		];

		if ( ! $this->high_used && ( ! $floor || $this->big_enough( $attr ) ) ) {
			$this->high_used        = true;
			$attrs['fetchpriority'] = 'high';
		}

		return $attrs;
	}

	/**
	 * Whether an image is big enough to be the page's main image.
	 *
	 * @since 0.7.0
	 *
	 * @param array $attr The attributes for the tag.
	 *
	 * @return bool
	 */
	private function big_enough( array $attr ): bool {
		if ( ! isset( $attr['width'], $attr['height'] ) ) {
			return true;
		}

		/** This filter is documented in wp-includes/media.php */
		$min = (int) apply_filters( 'wp_min_priority_img_pixels', 50000 );

		return (int) $attr['width'] * (int) $attr['height'] >= $min;
	}

	/**
	 * Returns the attributes for an image that loads immediately without a slot.
	 *
	 * "auto" is WordPress's own way of saying an image may or may not be visible.
	 * It keeps the image out of the running for high priority.
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
