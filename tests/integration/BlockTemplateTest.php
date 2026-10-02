<?php

declare(strict_types=1);

namespace Mai\PerformanceImages\Tests\Integration;

use Mai\PerformanceImages\Tests\TestCase;

/**
 * Which images load right away on a block theme page, built from a block template.
 *
 * Each page goes through WordPress's own template_include filter and
 * get_the_block_template_html(), the way template-canvas.php renders it, inside
 * WordPress's page buffer.
 */
final class BlockTemplateTest extends TestCase {

	/**
	 * Image IDs for the test blocks.
	 *
	 * @var int[]
	 */
	private array $image_ids = [];

	/**
	 * The registered theme folders, to put back after each test.
	 *
	 * @var string[]
	 */
	private array $theme_directories = [];

	/**
	 * Switches to a block theme and registers stand-in blocks that build images.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->theme_directories = $GLOBALS['wp_theme_directories'];

		// With only one theme folder registered, WordPress looks for the theme in
		// wp-content/themes instead, which the suite does not have.
		register_theme_directory( DIR_TESTDATA . '/themedir2' );
		delete_site_transient( 'theme_roots' );
		switch_theme( 'block-theme' );

		// WordPress adds this on after_setup_theme, which already ran for the old theme.
		add_theme_support( 'block-templates' );

		$this->image_ids = [ $this->create_image(), $this->create_image(), $this->create_image() ];
		$image_ids       = $this->image_ids;

		// A grid block, which builds its images while the template renders.
		$this->register_render( 'mpi-test/grid', static function ( $attributes ) use ( $image_ids ) {
			$html = '';

			foreach ( array_slice( $image_ids, 0, (int) ( $attributes['count'] ?? 2 ) ) as $id ) {
				$html .= wp_get_attachment_image( $id, 'full', false, [ 'class' => 'entry-image' ] );
			}

			return $html;
		} );

		// Partner logos, built the way beof-plugin builds them: lazy on purpose.
		$partners = [];

		foreach ( array_slice( $image_ids, 0, 2 ) as $id ) {
			$partners[] = self::factory()->post->create();
			set_post_thumbnail( end( $partners ), $id );
		}

		$this->register_render( 'mpi-test/lazy-thumbnails', static function () use ( $partners ) {
			return implode( '', array_map( fn( $partner ) => get_the_post_thumbnail( $partner, 'full', [ 'loading' => 'lazy' ] ), $partners ) );
		} );

		add_shortcode(
			'mpi_logo',
			static fn() => wp_get_attachment_image( $image_ids[0], 'full', false, [ 'class' => 'custom-logo' ] )
		);
	}

	/**
	 * Goes back to the classic theme the rest of the suite uses.
	 */
	public function tear_down(): void {
		remove_theme_support( 'block-templates' );
		switch_theme( WP_DEFAULT_THEME );

		$GLOBALS['wp_theme_directories'] = $this->theme_directories;
		delete_site_transient( 'theme_roots' );

		parent::tear_down();
	}

	public function test_a_featured_image_above_the_content_gets_high_priority(): void {
		$post = $this->create_post( $this->static_image( 1 ) . $this->static_image( 2 ) . $this->static_image( 3 ) );
		$html = $this->render( $post, '<!-- wp:post-featured-image /--><!-- wp:post-content /-->' );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_featured_image_set_to_eager_is_the_only_image_that_loads_right_away(): void {
		$post = $this->create_post( $this->static_image( 1 ) . $this->static_image( 2 ) . $this->static_image( 3 ) );
		$html = $this->render( $post, '[mpi_logo]<!-- wp:post-featured-image {"imgLoading":"eager"} /--><!-- wp:post-content /-->' );

		$this->assertSame( [ 'eager', 'eager+high', 'lazy', 'lazy', 'lazy' ], $this->loading( $html ) );
		$this->assertNoMarkers( $html );
	}

	public function test_a_logo_in_the_template_loads_right_away_without_a_slot(): void {
		$post   = $this->create_post( $this->static_image( 1 ) . $this->static_image( 2 ) . $this->static_image( 3 ) );
		$html   = $this->render( $post, '[mpi_logo]<!-- wp:post-featured-image /--><!-- wp:post-content /-->' );
		$images = $this->images( $html );

		$this->assertSame( [ 'eager', 'auto' ], [ $images[0]['loading'], $images[0]['fetchpriority'] ] );
		$this->assertSame( [ 'eager', 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_hero_above_a_query_in_a_template_part_keeps_high_priority(): void {
		$this->create_template( 'wp_template_part', 'mpi-query', '<!-- wp:mpi-test/grid {"count":3} /-->' );

		$hero = '<!-- wp:cover --><div class="wp-block-cover"><img class="wp-block-cover__image-background" src="https://example.org/hero.jpg" width="1600" height="900" alt=""/><span class="wp-block-cover__background"></span><div class="wp-block-cover__inner-container"></div></div><!-- /wp:cover -->';
		$html = $this->render( $this->create_post( '' ), $hero . '<!-- wp:template-part {"slug":"mpi-query","theme":"block-theme"} /-->' );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_lazy_gallery_images_stay_lazy_and_spend_nothing(): void {
		$lazy    = fn( $n ) => '<!-- wp:image {"imgLoading":"lazy"} --><figure class="wp-block-image">' . $this->static_image( $n ) . '</figure><!-- /wp:image -->';
		$gallery = '<!-- wp:gallery --><figure class="wp-block-gallery has-nested-images">' . $lazy( 1 ) . $lazy( 2 ) . '</figure><!-- /wp:gallery -->';
		$post    = $this->create_post( $gallery . $this->static_image( 3 ) . $this->static_image( 4 ) . $this->static_image( 5 ) );
		$html    = $this->render( $post, '<!-- wp:post-featured-image /--><!-- wp:post-content /-->' );

		$this->assertSame( [ 'eager+high', 'lazy', 'lazy', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_thumbnail_built_lazy_stays_lazy_and_spends_nothing(): void {
		$post = $this->create_post( '<!-- wp:mpi-test/lazy-thumbnails /-->' . $this->static_image( 1 ) . $this->static_image( 2 ) );
		$html = $this->render( $post, '<!-- wp:post-featured-image /--><!-- wp:post-content /-->' );

		$this->assertSame( [ 'eager+high', 'lazy', 'lazy', 'eager', 'eager' ], $this->loading( $html ) );
	}

	public function test_a_footer_image_after_the_template_is_counted_in_page_order(): void {
		$post = $this->create_post( $this->static_image( 1 ) . $this->static_image( 2 ) );

		$this->create_template( 'wp_template', 'single', '<!-- wp:post-featured-image /--><!-- wp:post-content /-->' );
		add_action( 'wp_footer', fn() => print( wp_get_attachment_image( $this->image_ids[1], 'full', false, [ 'class' => 'mpi-footer' ] ) ) );

		$template = $this->load_template( $post );
		$html     = $this->page( fn() => include $template );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
		$this->assertSame( 'async', $this->images( $html )[3]['decoding'] );
		$this->assertNoMarkers( $html );
	}

	public function test_a_classic_theme_page_counts_in_page_order(): void {
		switch_theme( WP_DEFAULT_THEME );

		$post = $this->create_post( $this->static_image( 1 ) . $this->static_image( 2 ) . $this->static_image( 3 ) );
		$this->go_to( get_permalink( $post ) );

		$this->assertNotSame( ABSPATH . WPINC . '/template-canvas.php', apply_filters( 'template_include', get_single_template() ) );

		// A classic single template: the featured image, then the content.
		$html = $this->page( fn() => get_the_post_thumbnail( $post ) . $this->the_content( get_post_field( 'post_content', $post ) ) );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	public function test_a_block_theme_page_on_a_php_template_counts_in_page_order(): void {
		$post = $this->create_post( $this->static_image( 1 ) . $this->static_image( 2 ) . $this->static_image( 3 ) );
		$this->go_to( get_permalink( $post ) );

		// A plugin swaps in its own PHP template, so no block template renders.
		add_filter( 'template_include', fn() => __FILE__ );
		apply_filters( 'template_include', get_single_template() );

		$html = $this->page( fn() => get_the_post_thumbnail( $post ) . $this->the_content( get_post_field( 'post_content', $post ) ) );

		$this->assertSame( [ 'eager+high', 'eager', 'eager', 'lazy' ], $this->loading( $html ) );
	}

	/**
	 * Registers a block, or replaces its render callback if it already exists.
	 *
	 * @param string   $name     The block name.
	 * @param callable $callback The render callback.
	 */
	private function register_render( string $name, callable $callback ): void {
		$registry = \WP_Block_Type_Registry::get_instance();

		if ( ! $registry->is_registered( $name ) ) {
			register_block_type( $name, [ 'render_callback' => fn() => '' ] );
		}

		$registry->get_registered( $name )->render_callback = $callback;
	}

	/**
	 * Creates a post with a featured image.
	 *
	 * @param string $content The post content.
	 *
	 * @return int The post ID.
	 */
	private function create_post( string $content ): int {
		$post = self::factory()->post->create( [ 'post_content' => $content ] );
		set_post_thumbnail( $post, $this->create_image() );

		return $post;
	}

	/**
	 * Saves a template or template part for the block theme, the way the Site Editor does.
	 *
	 * @param string $post_type 'wp_template' or 'wp_template_part'.
	 * @param string $slug      The slug.
	 * @param string $content   The block markup.
	 */
	private function create_template( string $post_type, string $slug, string $content ): void {
		$id = self::factory()->post->create(
			[
				'post_type'    => $post_type,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_content' => $content,
			]
		);

		wp_set_post_terms( $id, 'block-theme', 'wp_theme' );
	}

	/**
	 * Visits the post and returns the template WordPress would include for it.
	 *
	 * @param int $post The post ID.
	 *
	 * @return string
	 */
	private function load_template( int $post ): string {
		$this->go_to( get_permalink( $post ) );

		$template = apply_filters( 'template_include', get_single_template() );

		$this->assertSame( ABSPATH . WPINC . '/template-canvas.php', $template );

		return $template;
	}

	/**
	 * Renders the post with a single template, the way template-canvas.php does.
	 *
	 * @param int    $post     The post ID.
	 * @param string $template The template's block markup.
	 *
	 * @return string
	 */
	private function render( int $post, string $template ): string {
		$this->create_template( 'wp_template', 'single', $template );
		$this->load_template( $post );

		return $this->page( fn() => get_the_block_template_html() );
	}
}
