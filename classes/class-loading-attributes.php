<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Answers WordPress when it asks how an image should load.
 *
 * WordPress asks once per image, in wp_get_loading_optimization_attributes(), while
 * it is still building the tag. Answering there rather than rewriting the finished
 * HTML matters for one reason beyond tidiness: WordPress adds sizes="auto" to lazy
 * images in the same pass, so an image only gets it if WordPress knows it is lazy
 * at the moment it writes the tag. Telling it afterwards is too late, and the image
 * keeps a sizes value that assumes it fills the viewport.
 *
 * The same question covers every image on the page. Content images, entry images,
 * grids, page headers, logos and avatars all route through it, so one answer
 * replaces the six separate passes this plugin used to make over rendered HTML.
 *
 * Precedence, most specific first:
 *
 * 1. A loading attribute already on the image, which is how a block carries an
 *    editor's explicit Lazy or Eager choice.
 * 2. The archive or single Image Loading setting, for entry images.
 * 3. The position rule in LoadingBudget.
 *
 * @since 0.7.0
 */
final class LoadingAttributes {
	/**
	 * The budget.
	 *
	 * @since 0.7.0
	 *
	 * @var LoadingBudget
	 */
	private LoadingBudget $budget;

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @return void
	 */
	public function __construct() {
		$this->budget = new LoadingBudget();
		$this->hooks();
	}

	/**
	 * Adds the hooks.
	 *
	 * @since 0.7.0
	 *
	 * @return void
	 */
	private function hooks(): void {
		if ( ! is_attributes_enabled() ) {
			return;
		}

		add_filter( 'wp_get_loading_optimization_attributes', [ $this, 'filter_loading_attributes' ], 10, 4 );
	}

	/**
	 * Returns the budget, so anything rendering a page twice in one request can
	 * reset it.
	 *
	 * @since 0.7.0
	 *
	 * @return LoadingBudget
	 */
	public function get_budget(): LoadingBudget {
		return $this->budget;
	}

	/**
	 * Filters the loading optimization attributes.
	 * WP filter callbacks must be public.
	 *
	 * @since 0.7.0
	 *
	 * @param array  $attrs    The loading optimization attributes.
	 * @param string $tag_name The tag name.
	 * @param array  $attr     The attributes for the tag.
	 * @param string $context  The context for the element.
	 *
	 * @return array
	 */
	public function filter_loading_attributes( $attrs, $tag_name, $attr, $context ): array {
		// Iframes keep WordPress's own behavior for now.
		if ( 'img' !== $tag_name ) {
			return $attrs;
		}

		// A logo or an avatar is never the largest painted element, whatever its
		// file dimensions say, so it neither claims priority nor spends a slot.
		if ( ! $this->budget->counts( (array) $attr, (string) $context ) ) {
			return $this->budget->decline();
		}

		$this->maybe_set_eager_count( (array) $attr, (string) $context );

		$explicit = $this->get_explicit_loading( (array) $attr, (string) $context );

		if ( $explicit ) {
			return $this->budget->take( $explicit );
		}

		return $this->budget->next();
	}

	/**
	 * Returns an explicit choice for this image, if there is one.
	 *
	 * An empty string means nobody chose, so the position rule decides.
	 *
	 * @since 0.7.0
	 *
	 * @param array  $attr    The attributes for the tag.
	 * @param string $context The context for the element.
	 *
	 * @return string
	 */
	private function get_explicit_loading( array $attr, string $context ): string {
		// A block writes the real attribute when an editor picks Lazy or Eager, so
		// by the time WordPress asks, the choice is already on the image.
		$loading = (string) ( $attr['loading'] ?? '' );

		if ( in_array( $loading, [ 'lazy', 'eager' ], true ) ) {
			return $loading;
		}

		// A Mai Grid block knows which entry it is on, which nothing here can work
		// out. It answers this filter while the grid is rendering.
		if ( str_contains( (string) ( $attr['class'] ?? '' ), 'entry-image' ) ) {
			/**
			 * Filters the loading value for an entry image inside a grid block.
			 *
			 * @since 0.7.0
			 *
			 * @param string $loading Either 'lazy', 'eager', or an empty string when
			 *                        the image is not in a grid or the grid made no
			 *                        explicit choice.
			 */
			$grid = (string) apply_filters( 'mai_performance_images_grid_loading', '' );

			if ( in_array( $grid, [ 'lazy', 'eager' ], true ) ) {
				return $grid;
			}
		}

		$args = $this->get_entry_args( $attr, $context );

		if ( ! $args ) {
			return '';
		}

		$setting = (string) ( $args['image_loading'] ?? '' );

		return in_array( $setting, [ 'lazy', 'eager' ], true ) ? $setting : '';
	}

	/**
	 * Applies the archive setting's own eager count, when it has one.
	 *
	 * The Content Archives settings carry "eager load the first N entries", which is
	 * the same idea as the position rule with a number the site chose.
	 *
	 * @since 0.7.0
	 *
	 * @param array  $attr    The attributes for the tag.
	 * @param string $context The context for the element.
	 *
	 * @return void
	 */
	private function maybe_set_eager_count( array $attr, string $context ): void {
		static $done = false;

		if ( $done ) {
			return;
		}

		$args = $this->get_entry_args( $attr, $context );

		if ( ! $args ) {
			return;
		}

		$done  = true;
		$count = absint( $args['image_loading_count'] ?? 0 );

		if ( $count ) {
			$this->budget->set_eager_count( $count );
		}
	}

	/**
	 * Returns the template args when this image is a Mai entry image.
	 *
	 * Entry images are identified by class rather than by context, so this works
	 * without Mai Engine having to name every call site.
	 *
	 * @since 0.7.0
	 *
	 * @param array  $attr    The attributes for the tag.
	 * @param string $context The context for the element.
	 *
	 * @return array
	 */
	private function get_entry_args( array $attr, string $context ): array {
		if ( ! function_exists( 'mai_get_template_args' ) ) {
			return [];
		}

		if ( ! str_contains( (string) ( $attr['class'] ?? '' ), 'entry-image' ) ) {
			return [];
		}

		/** @disregard P1010 */
		return (array) \mai_get_template_args();
	}
}
