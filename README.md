# Mai Performance Images

Decides which images on a page load right away and which ones wait until the visitor scrolls near them.

WordPress already does this, but it guesses from the file size. A logo uploaded large for retina screens can take the top spot from the article's main image. This plugin knows a logo from an entry image from an avatar, and it knows the Image Loading settings of the archive, post or grid being shown.

## What it does

- **The first three images load right away.** The first of them also gets `fetchpriority="high"`, so the browser fetches it first. Every image after that lazy loads.
- **Logos load right away but never take the top spot.** Avatars always lazy load. Neither counts toward the three.
- **The count follows the page, not the code.** A grid or an ad shortcode builds its images before the rest of the post content, but it's counted where it sits on the page.
- **Lazy images get `sizes="auto"`,** so the browser picks the right file for the space the image actually fills.

## Settings

**Settings > Performance Images** has one checkbox, Image loading. It's on by default. Turn it off and WordPress decides loading on its own again.

**Image Loading** is a choice of Automatic, Lazy or Eager. It appears in four places:

- Image, cover, featured image, media and text, and site logo blocks, in the block sidebar.
- Mai Post Grid and Mai Term Grid blocks, in the block sidebar.
- Customizer > Theme Settings > Content Archives, for each post type and taxonomy.
- Customizer > Theme Settings > Single Content, for each post type.

With Eager, the grid and archive settings also take an **Image Loading Count**. The first that many entries load right away and the rest lazy load. Leave it empty to load them all right away.

Automatic uses the first-three rule. Lazy never counts toward the three, so the images after a lazy grid still get their turn.

## Filters

`mai_performance_images_eager_count` sets how many images load right away. The default is WordPress's own number, which is 3.

```php
add_filter( 'mai_performance_images_eager_count', function( $count ) {
	return 2;
} );
```

`mai_performance_images_entry_loading` answers for a Mai entry's image. Return `'lazy'` or `'eager'` to choose, or leave it empty.

```php
add_filter( 'mai_performance_images_entry_loading', function( $loading, $attr ) {
	return is_front_page() ? 'eager' : $loading;
}, 10, 2 );
```

## WebP conversion was removed in 0.7.0

Earlier versions could resize images and convert them to WebP on the fly. Cloudflare does that job now, so it's gone. After updating, the plugin deletes the old conversion's scheduled jobs and queue once.

The converted files in `wp-content/uploads/mai-performance-images` stay put, because pages already held in a page cache or on a CDN may still point to them. Delete that folder once those caches have cleared.

## Requirements

- WordPress 6.7 or later
- PHP 8.1 or later

## Development

```bash
composer test-setup
composer test
```

The tests load WordPress and need MySQL with an empty `mai_performance_images_tests` database. Set `WP_TESTS_DB_NAME`, `WP_TESTS_DB_USER`, `WP_TESTS_DB_PASS` or `WP_TESTS_DB_HOST` to use a different one.

Edit block editor code in `src/` and build it with `npm run build`. The built files in `build/` are committed.
