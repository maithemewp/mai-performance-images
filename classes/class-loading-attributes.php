<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Answers WordPress when it asks how an image should load.
 *
 * WordPress asks in wp_get_loading_optimization_attributes(). It asks while
 * wp_get_attachment_image() builds a tag, and again for every image in finished
 * post content, in page order, in wp_filter_content_tags(). Answering there rather
 * than rewriting the finished HTML matters because WordPress adds sizes="auto" to
 * lazy images in the same pass, and only when it knows the image is lazy.
 *
 * Precedence, most specific first:
 *
 * 1. A logo or an avatar, which never spends a slot.
 * 2. A loading value already on the image. That is a block carrying an editor's
 *    explicit choice, or WordPress asking again about an image answered earlier.
 * 3. The Image Loading setting of the Mai entry being rendered, from its archive,
 *    single or grid settings.
 * 4. The position rule in LoadingBudget.
 *
 * @since 0.7.0
 */
final class LoadingAttributes {
	/**
	 * The one instance.
	 *
	 * The budget holds the page's image count and its one high-priority slot, so a
	 * second copy would count twice.
	 *
	 * @since 0.7.0
	 *
	 * @var LoadingAttributes|null
	 */
	private static ?LoadingAttributes $instance = null;

	/**
	 * The budget.
	 *
	 * @since 0.7.0
	 *
	 * @var LoadingBudget
	 */
	private LoadingBudget $budget;

	/**
	 * One flag per the_content call in progress, true once WordPress's own pass
	 * over the finished content has started.
	 *
	 * @since 0.7.0
	 *
	 * @var bool[]
	 */
	private array $content_passes = [];

	/**
	 * Returns the one instance, creating it on first use.
	 *
	 * @since 0.7.0
	 *
	 * @return LoadingAttributes
	 */
	public static function instance(): LoadingAttributes {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @return void
	 */
	private function __construct() {
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

		// Shortcodes run at 11 and WordPress's pass over the content at 12. This runs
		// between them because it is added after core's do_shortcode at 11.
		add_filter( 'the_content', [ $this, 'content_start' ], PHP_INT_MIN );
		add_filter( 'the_content', [ $this, 'content_tags_start' ], 11 );
		add_filter( 'the_content', [ $this, 'content_end' ], PHP_INT_MAX );
	}

	/**
	 * Returns the budget, so anything rendering a second page in one request can
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
	 * Marks the start of a the_content call.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $content The content.
	 *
	 * @return mixed
	 */
	public function content_start( $content ) {
		$this->content_passes[] = false;

		return $content;
	}

	/**
	 * Marks the point where WordPress's own pass over the content is next.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $content The content.
	 *
	 * @return mixed
	 */
	public function content_tags_start( $content ) {
		if ( $this->content_passes ) {
			$this->content_passes[ array_key_last( $this->content_passes ) ] = true;
		}

		return $content;
	}

	/**
	 * Marks the end of a the_content call.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $content The content.
	 *
	 * @return mixed
	 */
	public function content_end( $content ) {
		array_pop( $this->content_passes );

		return $content;
	}

	/**
	 * Filters the loading optimization attributes.
	 * WP filter callbacks must be public.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed  $attrs    The loading optimization attributes.
	 * @param string $tag_name The tag name.
	 * @param mixed  $attr     The attributes for the tag.
	 * @param string $context  The context for the element.
	 *
	 * @return array
	 */
	public function filter_loading_attributes( $attrs, $tag_name, $attr, $context ): array {
		$attrs   = is_array( $attrs ) ? $attrs : [];
		$attr    = is_array( $attr ) ? $attr : [];
		$context = (string) $context;

		// Iframes keep WordPress's own behavior.
		if ( 'img' !== $tag_name ) {
			return $attrs;
		}

		$kind = $this->budget->kind( $attr, $context );

		// A logo or an avatar never spends a slot. Its answer is written even when
		// WordPress will ask again, so the second ask sees it and skips the image too.
		if ( $kind ) {
			return $this->budget->for_kind( $kind );
		}

		// An image built while post content renders comes before the content's own
		// images in time, but not always on the page. WordPress asks again about
		// every image in the content, in page order, so the slot is spent then. Only
		// the entry's own choice is written now, because only now is it known.
		if ( $this->is_deferred( $context ) ) {
			$loading = $this->get_entry_loading( $attr );

			return $loading ? [ 'loading' => $loading ] : [];
		}

		// Without both dimensions WordPress writes neither loading nor fetchpriority,
		// so the image must not spend a slot it will never use.
		if ( ! isset( $attr['width'], $attr['height'] ) ) {
			return $attrs;
		}

		$fetchpriority = (string) ( $attr['fetchpriority'] ?? '' );

		// WordPress uses auto and low for images it keeps out of the count, such as
		// hidden blocks and navigation overlays.
		if ( in_array( $fetchpriority, [ 'auto', 'low' ], true ) ) {
			return $attrs;
		}

		$loading = (string) ( $attr['loading'] ?? '' );

		// Every answer this class gives while a tag is built sets decoding too. So a
		// loading value without decoding was chosen elsewhere, by a block or by an
		// entry inside post content, and has not spent its slot yet.
		if ( in_array( $loading, [ 'lazy', 'eager' ], true ) ) {
			return empty( $attr['decoding'] ) ? $this->budget->take( $loading ) : $this->budget->keep( $loading, $fetchpriority );
		}

		$loading = $this->get_entry_loading( $attr );

		if ( $loading ) {
			return $this->budget->take( $loading );
		}

		return $this->budget->next();
	}

	/**
	 * Writes a block's explicit loading choice onto an image tag.
	 *
	 * A block's render filter runs after the tag was built and answered, so a lazy
	 * choice also has to take back what the answer gave: the high-priority slot,
	 * sync decoding, and the missing sizes="auto".
	 *
	 * @since 0.7.0
	 *
	 * @param \WP_HTML_Tag_Processor $tags    The tag processor, on an img tag.
	 * @param string                 $loading Either 'lazy' or 'eager'.
	 *
	 * @return void
	 */
	public function apply_to_tag( \WP_HTML_Tag_Processor $tags, string $loading ): void {
		$tags->set_attribute( 'loading', $loading );

		if ( 'lazy' !== $loading ) {
			return;
		}

		if ( 'high' === $tags->get_attribute( 'fetchpriority' ) ) {
			$tags->remove_attribute( 'fetchpriority' );
			$this->budget->release_high();
		}

		$tags->set_attribute( 'decoding', 'async' );

		$sizes = $tags->get_attribute( 'sizes' );

		if ( is_string( $sizes ) && $tags->get_attribute( 'srcset' ) && ! str_starts_with( strtolower( trim( $sizes ) ), 'auto' ) ) {
			$tags->set_attribute( 'sizes', 'auto, ' . $sizes );
		}
	}

	/**
	 * Whether WordPress will ask about this image again in a pass over the content.
	 *
	 * Mirrors the check in wp_get_loading_optimization_attributes(), with one
	 * addition: an image built by a shortcode reports the_content as its context,
	 * but it is built before WordPress's pass at priority 12, so it is asked again
	 * too.
	 *
	 * @since 0.7.0
	 *
	 * @param string $context The context for the element.
	 *
	 * @return bool
	 */
	private function is_deferred( string $context ): bool {
		if ( doing_filter( 'the_content' ) ) {
			// The markers are missing if something removed them, so fall back to core's rule.
			if ( ! $this->content_passes ) {
				return 'the_content' !== $context;
			}

			return ! end( $this->content_passes );
		}

		foreach ( [ 'widget_text_content', 'widget_block_content' ] as $filter ) {
			if ( $filter !== $context && doing_filter( $filter ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the Mai entry's choice for its own entry image, if it made one.
	 *
	 * Only an image with the entry-image class, built while its entry renders,
	 * gets an answer. The entry knows its archive, single or grid setting and its
	 * position in the loop, which nothing at the image level can work out.
	 *
	 * @since 0.7.0
	 *
	 * @param array $attr The attributes for the tag.
	 *
	 * @return string 'lazy', 'eager', or an empty string.
	 */
	private function get_entry_loading( array $attr ): string {
		if ( ! in_array( 'entry-image', LoadingBudget::classes( $attr ), true ) ) {
			return '';
		}

		/**
		 * Filters the loading value for a Mai entry image.
		 *
		 * @since 0.7.0
		 *
		 * @param string $loading Empty by default. Return 'lazy' or 'eager' to choose.
		 * @param array  $attr    The attributes for the tag.
		 */
		$loading = (string) apply_filters( 'mai_performance_images_entry_loading', '', $attr );

		return in_array( $loading, [ 'lazy', 'eager' ], true ) ? $loading : '';
	}
}
