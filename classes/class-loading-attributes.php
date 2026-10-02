<?php

namespace Mai\PerformanceImages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Decides how every image on the page loads, once, on the finished page.
 *
 * WordPress 6.9 hands plugins the whole page before it is sent, through the
 * wp_template_enhancement_output_buffer filter. Adding that filter is also what
 * tells WordPress to collect the page. This class walks every image in it, top to
 * bottom, and sets loading, fetchpriority, decoding and sizes="auto". Because the
 * walk sees the page as the visitor gets it, the order is always right, whatever
 * built each image and whenever it was built.
 *
 * While the page renders, nothing is counted. Two things are done instead:
 *
 * 1. WordPress's own per-image guess is skipped, so every loading or fetchpriority
 *    value left on the finished page was put there on purpose, by code or by the
 *    saved HTML, and is respected.
 * 2. Choices only known while an image is built are written onto the tag as a
 *    temporary data-mpi-loading attribute: a logo found by its context, a Mai
 *    entry's Image Loading setting, and an editor's Lazy or Eager block choice.
 *    The walk reads each one and removes it.
 *
 * When WordPress is not collecting the page, such as for the REST API, feeds,
 * Ajax and the admin, or on a site that turns the collection off, WordPress
 * decides loading on its own. The editor's and the Mai entry's choices are still
 * applied, straight onto the tag, and no marker is written.
 *
 * Precedence on the finished page, most specific first:
 *
 * 0. A tiny image, 2 by 2 pixels or less by its width and height attributes,
 *    such as a tracking pixel. It is left exactly as it is, apart from its
 *    marker, and never makes the page follow its configuration.
 * 1. An avatar, or a logo, which never spends a slot.
 * 2. An image with no width and height, or with fetchpriority auto or low, which
 *    spends nothing either.
 * 3. An image already marked fetchpriority="high".
 * 4. A Lazy or Eager choice: the editor's, the Mai entry's, or a loading value
 *    already on the tag. Lazy spends nothing.
 * 5. The position rule in LoadingBudget.
 *
 * Configured Eager means the editor's or the Mai entry's choice, not a loading
 * value already on the tag. Mai Engine's page header image counts as configured
 * Eager too, unless it carries a marker or loading="lazy" already. When the page
 * has at least one image configured Eager, the page follows its configuration:
 * step 5 lazy loads instead of counting, and a loading="eager" already on the tag
 * stays eager without taking high priority.
 *
 * @since 0.7.0
 * @since 0.8.0 Decides on the finished page instead of while each image is built.
 * @since 0.8.0 Follows the configuration on a page with an image configured Eager.
 * @since 0.8.0 Leaves tiny images, such as tracking pixels, alone.
 * @since 0.8.0 Counts Mai Engine's page header image as configured Eager.
 */
final class LoadingAttributes {
	/**
	 * The temporary attribute that carries a choice from render time to the walk.
	 *
	 * @since 0.8.0
	 *
	 * @var string
	 */
	public const MARKER = 'data-mpi-loading';

	/**
	 * The largest width and height, in pixels, of an image left alone as tiny.
	 *
	 * @since 0.8.0
	 *
	 * @var int
	 */
	private const TINY = 2;

	/**
	 * The class Mai Engine puts on its page header image.
	 *
	 * @since 0.8.0
	 *
	 * @var string
	 */
	private const PAGE_HEADER_CLASS = 'page-header-image';

	/**
	 * Filters that run on the final HTML after WordPress's page filter.
	 *
	 * Mai Publisher opens its own buffer on template_redirect, outside WordPress's,
	 * and inserts ads and sidebar content after WordPress's filter has run. The
	 * page is always decided on WordPress's filter. These only give the images
	 * inserted afterwards an answer, so if Mai Publisher changes, only its own
	 * images are affected.
	 *
	 * Remove once Mai Publisher inserts on WordPress's filter:
	 * https://github.com/bizbudding/mai-publisher/issues/62
	 *
	 * @since 0.8.0
	 *
	 * @var string[]
	 */
	private const LATE_FILTERS = [
		'mai_publisher_html',
	];

	/**
	 * Whether the page was decided on WordPress's filter in this request.
	 *
	 * @since 0.8.0
	 *
	 * @var bool
	 */
	private bool $walked = false;

	/**
	 * Whether WordPress is collecting the page right now, from the moment its page
	 * buffer starts until the page is decided.
	 *
	 * @since 0.8.0
	 *
	 * @var bool
	 */
	private bool $collecting = false;

	/**
	 * The one instance.
	 *
	 * @since 0.7.0
	 *
	 * @var LoadingAttributes|null
	 */
	private static ?LoadingAttributes $instance = null;

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
		add_filter( 'pre_wp_get_loading_optimization_attributes', [ $this, 'mark_image' ], 10, 4 );
		add_filter( 'wp_get_loading_optimization_attributes',     [ $this, 'filter_loading_attributes' ], 10, 4 );

		// WordPress starts collecting the page at priority 1000 when the page filter
		// exists by then. This runs earlier on the same action, after the theme has
		// loaded, so a theme can still turn the setting off.
		add_action( 'wp_before_include_template', [ $this, 'add_page_filter' ], 10, 0 );
	}

	/**
	 * Adds the finished-page filter, when the setting is on.
	 *
	 * With the setting off the filter is never added, so this plugin does not make
	 * WordPress collect the page.
	 *
	 * @since 0.8.0
	 *
	 * @return void
	 */
	public function add_page_filter(): void {
		if ( ! is_attributes_enabled() ) {
			return;
		}

		add_filter( 'wp_template_enhancement_output_buffer', [ $this, 'filter_page' ] );
		add_action( 'wp_template_enhancement_output_buffer_started', [ $this, 'start_collecting' ] );

		foreach ( self::LATE_FILTERS as $filter ) {
			add_filter( $filter, [ $this, 'finish_page' ], 99 );
		}
	}

	/**
	 * Answers images inserted after WordPress's page filter, in a late filter.
	 *
	 * Only runs when the page was already decided on WordPress's filter. Images
	 * that already have a loading attribute are left alone. The rest were inserted
	 * afterwards, so they lazy load unless their marker says eager.
	 *
	 * WP filter callbacks must be public.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $html The final page.
	 *
	 * @return mixed
	 */
	public function finish_page( $html ) {
		if ( ! $this->walked || ! is_string( $html ) ) {
			return $html;
		}

		return $this->walk( $html, true );
	}

	/**
	 * Whether WordPress is collecting this page and will hand it to filter_page().
	 *
	 * Checked for every image rather than once, because it is only true from the
	 * moment the template is included. Anything built before that, or in a request
	 * that never includes a template, is answered the old way and gets no marker.
	 *
	 * @since 0.8.0
	 *
	 * @return bool
	 */
	public function is_collecting(): bool {
		return $this->collecting && is_attributes_enabled();
	}

	/**
	 * Notes that WordPress has started collecting the page.
	 *
	 * WP action callbacks must be public.
	 *
	 * @since 0.8.0
	 *
	 * @return void
	 */
	public function start_collecting(): void {
		$this->collecting = true;
		$this->walked     = false;
	}

	/**
	 * Skips WordPress's per-image guess while the page is collected.
	 *
	 * The walk over the finished page decides instead. Only a choice that cannot be
	 * seen on the finished page is returned, as a marker. wp_get_attachment_image()
	 * writes every returned key onto the tag.
	 *
	 * Iframes are left to WordPress.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed  $pre      False, or attributes another filter already chose.
	 * @param string $tag_name The tag name.
	 * @param mixed  $attr     The attributes for the tag.
	 * @param string $context  The context for the element.
	 *
	 * @return mixed
	 */
	public function mark_image( $pre, $tag_name, $attr, $context ) {
		if ( is_array( $pre ) || 'img' !== $tag_name || ! $this->is_collecting() ) {
			return $pre;
		}

		$attr = is_array( $attr ) ? $attr : [];
		$kind = LoadingBudget::kind( $attr, (string) $context );

		// A logo built with a Mai context has no other sign of being one. An avatar
		// is known by its class on the page, so its marker is only a backup.
		if ( $kind ) {
			return [ self::MARKER => $kind ];
		}

		$loading = $this->get_entry_loading( $attr );

		return $loading ? [ self::MARKER => $loading ] : [];
	}

	/**
	 * Applies a Mai entry's choice when the page is not collected.
	 *
	 * WordPress's own answer is kept otherwise. While the page is collected this
	 * never runs for images, because mark_image() answers first.
	 * WP filter callbacks must be public.
	 *
	 * @since 0.7.0
	 * @since 0.8.0 Only applies the entry's choice.
	 *
	 * @param mixed  $attrs    The loading optimization attributes.
	 * @param string $tag_name The tag name.
	 * @param mixed  $attr     The attributes for the tag.
	 * @param string $context  The context for the element.
	 *
	 * @return array
	 */
	public function filter_loading_attributes( $attrs, $tag_name, $attr, $context ): array {
		$attrs = is_array( $attrs ) ? $attrs : [];

		if ( 'img' !== $tag_name || ! is_attributes_enabled() ) {
			return $attrs;
		}

		$loading = $this->get_entry_loading( is_array( $attr ) ? $attr : [] );

		if ( 'lazy' === $loading ) {
			if ( 'high' === ( $attrs['fetchpriority'] ?? '' ) ) {
				unset( $attrs['fetchpriority'] );
			}

			$attrs['loading']  = 'lazy';
			$attrs['decoding'] = 'async';
		} elseif ( 'eager' === $loading ) {
			$attrs['loading'] = 'eager';
		}

		return $attrs;
	}

	/**
	 * Writes a Lazy or Eager choice onto an image tag.
	 *
	 * Used for an editor's block choice and for blocks the plugin always lazy loads.
	 * While the page is collected the choice goes on as a marker for the walk.
	 * Otherwise it is applied to the tag straight away.
	 *
	 * @since 0.8.0
	 *
	 * @param \WP_HTML_Tag_Processor $tags    The tag processor, on an img tag.
	 * @param string                 $loading Either 'lazy' or 'eager'.
	 *
	 * @return void
	 */
	public function apply_choice( \WP_HTML_Tag_Processor $tags, string $loading ): void {
		if ( $this->is_collecting() ) {
			$tags->set_attribute( self::MARKER, $loading );
			return;
		}

		$this->apply( $tags, 'lazy' === $loading ? [ 'loading' => 'lazy', 'decoding' => 'async' ] : [ 'loading' => 'eager' ] );
	}

	/**
	 * Decides loading for the page WordPress collected.
	 *
	 * WP filter callbacks must be public.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $html The page.
	 *
	 * @return string
	 */
	public function filter_page( $html ): string {
		$html = (string) $html;

		$this->collecting = false;
		$this->walked     = true;

		return $this->walk( $html );
	}

	/**
	 * Decides loading for every image on the finished page, top to bottom.
	 *
	 * Images inside noscript and template elements are not shown to a visitor
	 * with JavaScript, so they spend nothing and are left alone. So are tiny
	 * images, such as tracking pixels. Markers are removed from every image.
	 *
	 * @since 0.8.0
	 *
	 * @param string $html The finished page.
	 * @param bool   $late Whether to answer only images still without a loading
	 *                     attribute, inserted after the page was decided.
	 *
	 * @return string
	 */
	private function walk( string $html, bool $late = false ): string {
		if ( false === stripos( $html, '<img' ) ) {
			return $html;
		}

		$enabled   = is_attributes_enabled();
		$tags      = new \WP_HTML_Tag_Processor( $html );
		$budget    = new LoadingBudget( $enabled && ! $late && $this->has_configured_eager( $html ) );
		$hidden    = 0;
		$auto_high = false;

		while ( $tags->next_tag( [ 'tag_closers' => 'visit' ] ) ) {
			$name = $tags->get_tag();

			if ( 'NOSCRIPT' === $name || 'TEMPLATE' === $name ) {
				$hidden = max( 0, $hidden + ( $tags->is_tag_closer() ? -1 : 1 ) );
				continue;
			}

			if ( 'IMG' !== $name || $tags->is_tag_closer() ) {
				continue;
			}

			$marker = $tags->get_attribute( self::MARKER );

			if ( null !== $marker ) {
				$tags->remove_attribute( self::MARKER );
			}

			if ( $hidden || ! $enabled ) {
				continue;
			}

			$size = self::size( $tags );

			if ( self::is_tiny( $size ) ) {
				continue;
			}

			if ( $late ) {
				if ( null === $tags->get_attribute( 'loading' ) ) {
					$this->apply( $tags, [ 'loading' => 'eager' === $marker ? 'eager' : 'lazy' ] );
				}

				continue;
			}

			$page_header   = self::is_page_header( $tags, $marker );
			$marker        = $page_header ? 'eager' : $marker;
			$explicit_high = 'high' === strtolower( (string) $tags->get_attribute( 'fetchpriority' ) );
			$attrs         = $this->decide( $tags, $budget, is_string( $marker ) ? $marker : '', $size, $page_header );

			// An image somebody marked high outranks one this walk picked above it.
			if ( $explicit_high && $auto_high && 'high' === ( $attrs['fetchpriority'] ?? '' ) ) {
				$tags->set_bookmark( 'mpi-here' );
				$tags->seek( 'mpi-high' );
				$tags->remove_attribute( 'fetchpriority' );
				$tags->seek( 'mpi-here' );
				$tags->release_bookmark( 'mpi-here' );
				$tags->release_bookmark( 'mpi-high' );
				$auto_high = false;
			} elseif ( ! $explicit_high && 'high' === ( $attrs['fetchpriority'] ?? '' ) ) {
				$tags->set_bookmark( 'mpi-high' );
				$auto_high = true;
			}

			$this->apply( $tags, $attrs );
		}

		$html = $tags->get_updated_html();

		// A marker inside a script or an attribute value, such as block markup kept in
		// JSON, is out of the walk's reach. It is only text there, so it goes as text.
		if ( str_contains( $html, self::MARKER ) ) {
			$html = (string) preg_replace( '/\s' . self::MARKER . '=(\\\\?["\']|&quot;|&#0?39;)[a-z]*\1/', '', $html );
		}

		return $html;
	}

	/**
	 * Whether the finished page has an image configured Eager.
	 *
	 * Configured Eager is the eager marker, from the editor's block choice or the
	 * Mai entry's setting, or Mai Engine's page header image. It must be on an
	 * image the visitor sees, so not inside
	 * noscript or template. A logo does not count, because a logo loads right
	 * away whatever it is set to and says nothing about the rest of the page. A
	 * tiny image does not count either, because the walk leaves it alone.
	 *
	 * Runs before the walk, because the walk's first image may already depend on
	 * the answer. A page without the marker text or the page header class is never
	 * scanned. Otherwise the scan stops at the first match, which is usually near
	 * the top.
	 *
	 * @since 0.8.0
	 *
	 * @param string $html The finished page.
	 *
	 * @return bool
	 */
	private function has_configured_eager( string $html ): bool {
		if ( ! str_contains( $html, self::MARKER . '="eager"' ) && ! str_contains( $html, self::PAGE_HEADER_CLASS ) ) {
			return false;
		}

		$tags   = new \WP_HTML_Tag_Processor( $html );
		$hidden = 0;

		while ( $tags->next_tag( [ 'tag_closers' => 'visit' ] ) ) {
			$name = $tags->get_tag();

			if ( 'NOSCRIPT' === $name || 'TEMPLATE' === $name ) {
				$hidden = max( 0, $hidden + ( $tags->is_tag_closer() ? -1 : 1 ) );
				continue;
			}

			if ( 'IMG' !== $name || $tags->is_tag_closer() || $hidden ) {
				continue;
			}

			$marker = $tags->get_attribute( self::MARKER );

			if ( 'eager' !== $marker && ! self::is_page_header( $tags, $marker ) ) {
				continue;
			}

			if ( ! LoadingBudget::kind( [ 'class' => (string) $tags->get_attribute( 'class' ) ] ) && ! self::is_tiny( self::size( $tags ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the attributes for one image on the finished page.
	 *
	 * @since 0.8.0
	 *
	 * @param \WP_HTML_Tag_Processor $tags   The tag processor, on an img tag.
	 * @param LoadingBudget          $budget The page's budget.
	 * @param string                 $marker      The marker the image carried, if any.
	 * @param array|null             $size        The width and height from size().
	 * @param bool                   $page_header Whether this is Mai Engine's page
	 *                                            header image, which takes high
	 *                                            priority at any size.
	 *
	 * @return array
	 */
	private function decide( \WP_HTML_Tag_Processor $tags, LoadingBudget $budget, string $marker, ?array $size, bool $page_header = false ): array {
		$chosen        = in_array( $marker, [ 'lazy', 'eager' ], true ) ? $marker : '';
		$loading       = $chosen ?: strtolower( (string) $tags->get_attribute( 'loading' ) );
		$loading       = in_array( $loading, [ 'lazy', 'eager' ], true ) ? $loading : '';
		$fetchpriority = strtolower( (string) $tags->get_attribute( 'fetchpriority' ) );
		$kind          = in_array( $marker, [ 'logo', 'avatar' ], true ) ? $marker : LoadingBudget::kind( [ 'class' => (string) $tags->get_attribute( 'class' ) ] );

		// An editor can still lazy load a logo, from the Site Logo block.
		if ( 'avatar' === $kind || ( 'logo' === $kind && 'lazy' === $loading ) ) {
			return $budget->lazy();
		}

		if ( 'logo' === $kind ) {
			$attrs = $budget->for_kind( 'logo' );

			// Keep low on a logo hidden in a navigation overlay.
			if ( $fetchpriority ) {
				unset( $attrs['fetchpriority'] );
			}

			return $attrs;
		}

		// WordPress never adds loading or fetchpriority without both dimensions, to
		// avoid layout shift. WordPress uses auto and low for images that may not be
		// shown, such as hidden blocks and navigation overlays. None of these spend a
		// slot. Only a choice made for them is applied.
		if ( ! $size || in_array( $fetchpriority, [ 'auto', 'low' ], true ) ) {
			return match ( $loading ) {
				'lazy'  => $budget->lazy(),
				'eager' => [ 'loading' => 'eager' ],
				default => [],
			};
		}

		// Somebody marked this as the page's main image. WordPress honors that, so
		// this does too, unless an editor chose Lazy for it.
		if ( 'high' === $fetchpriority ) {
			return 'lazy' === $chosen ? $budget->lazy() : $budget->take_high();
		}

		// On a page that follows its configuration, only configured Eager images
		// compete for high priority. A loading="eager" already on the tag is kept.
		if ( 'eager' === $loading && ! $chosen && $budget->configured ) {
			return [ 'loading' => 'eager' ];
		}

		// Mai tags its page header image with a small fallback size, such as 300x109,
		// while it shows full width, so the size floor would wrongly rule it out.
		if ( $loading ) {
			return $budget->take( $loading, $size, ! $page_header );
		}

		return $budget->next( $size );
	}

	/**
	 * Returns the image's width and height, from the attributes on the tag.
	 *
	 * Only the tag is read, never the image file or its metadata, so this costs
	 * two attribute reads. LoadingBudget uses the answer for its 50,000 square
	 * pixel floor, and is_tiny() for tracking pixels.
	 *
	 * @since 0.8.0
	 *
	 * @param \WP_HTML_Tag_Processor $tags The tag processor, on an img tag.
	 *
	 * @return array|null The width and height, or null without both as numbers.
	 */
	private static function size( \WP_HTML_Tag_Processor $tags ): ?array {
		$width  = $tags->get_attribute( 'width' );
		$height = $tags->get_attribute( 'height' );

		if ( ! is_numeric( $width ) || ! is_numeric( $height ) ) {
			return null;
		}

		return [
			'width'  => (int) $width,
			'height' => (int) $height,
		];
	}

	/**
	 * Whether an image is tiny, such as a 1 by 1 tracking pixel.
	 *
	 * A tiny image is never the page's main image and never worth holding back,
	 * so the walk leaves it exactly as it is. An image without both dimensions is
	 * not tiny, because its size is unknown.
	 *
	 * @since 0.8.0
	 *
	 * @param array|null $size The width and height from size().
	 *
	 * @return bool
	 */
	private static function is_tiny( ?array $size ): bool {
		return $size && $size['width'] <= self::TINY && $size['height'] <= self::TINY;
	}

	/**
	 * Whether an image is Mai Engine's page header image, counted as configured Eager.
	 *
	 * Known by its class on the tag alone, whatever other classes it has or what
	 * wraps it. A page header image that already carries a marker, or that code
	 * gave loading="lazy", keeps that choice instead.
	 *
	 * @since 0.8.0
	 *
	 * @param \WP_HTML_Tag_Processor $tags   The tag processor, on an img tag.
	 * @param string|true|null       $marker The image's marker, or null without one.
	 *
	 * @return bool
	 */
	private static function is_page_header( \WP_HTML_Tag_Processor $tags, string|bool|null $marker ): bool {
		return null === $marker
			&& $tags->has_class( self::PAGE_HEADER_CLASS )
			&& 'lazy' !== strtolower( (string) $tags->get_attribute( 'loading' ) );
	}

	/**
	 * Writes loading attributes onto an image tag.
	 *
	 * A lazy image loses high priority and gets sizes="auto" by WordPress's own
	 * rule. Any other image loses a leftover "auto" in sizes, which only works on
	 * lazy images. Every image gets decoding="async" if it has no decoding yet.
	 *
	 * @since 0.8.0
	 *
	 * @param \WP_HTML_Tag_Processor $tags  The tag processor, on an img tag.
	 * @param array                  $attrs The attributes to write.
	 *
	 * @return void
	 */
	private function apply( \WP_HTML_Tag_Processor $tags, array $attrs ): void {
		foreach ( $attrs as $name => $value ) {
			$tags->set_attribute( $name, $value );
		}

		if ( ! isset( $attrs['decoding'] ) && null === $tags->get_attribute( 'decoding' ) ) {
			$tags->set_attribute( 'decoding', 'async' );
		}

		if ( ! isset( $attrs['loading'] ) ) {
			return;
		}

		$sizes = $tags->get_attribute( 'sizes' );

		if ( 'lazy' !== $attrs['loading'] ) {
			if ( is_string( $sizes ) && wp_sizes_attribute_includes_valid_auto( $sizes ) ) {
				$rest = trim( explode( ',', $sizes, 2 )[1] ?? '' );

				if ( $rest ) {
					$tags->set_attribute( 'sizes', $rest );
				}
			}

			return;
		}

		if ( 'high' === $tags->get_attribute( 'fetchpriority' ) ) {
			$tags->remove_attribute( 'fetchpriority' );
		}

		/** This filter is documented in wp-includes/media.php */
		if ( ! apply_filters( 'wp_img_tag_add_auto_sizes', true ) ) {
			return;
		}

		$width = $tags->get_attribute( 'width' );

		if ( is_string( $sizes ) && is_string( $width ) && '' !== $width && ! wp_sizes_attribute_includes_valid_auto( $sizes ) ) {
			$tags->set_attribute( 'sizes', 'auto, ' . $sizes );
		}
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
