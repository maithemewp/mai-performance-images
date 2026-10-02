# Mai Performance Images

Decides which images on a page load right away and which ones wait until the visitor scrolls near them.

WordPress already does this, but it guesses from the file size. A logo uploaded large for retina screens can take the top spot from the article's main image. This plugin knows a logo from an entry image from an avatar, and it knows the Image Loading settings of the archive, post or grid being shown.

## What it does

- **Images set to Eager decide the page, when there are any.** If any image on the page is set to Eager, by a block, the Customizer or a Mai grid, those images load right away and every other image lazy loads. The first of them that is at least 50,000 square pixels also gets `fetchpriority="high"`, so the browser fetches it first.
- **A Mai Engine page header image counts as set to Eager.** It loads right away, and images left on Automatic lazy load. If it's the first Eager image on the page, it gets `fetchpriority="high"` whatever its size. Mai tags it with a small size, such as 300 by 109, while it shows full width, so the 50,000 square pixel floor would leave it out. The plugin knows it by the `page-header-image` class on the image. If code already gave it `loading="lazy"`, it stays lazy and the page is decided as if it weren't there.
- **Otherwise the first three images load right away.** The first of them that is at least 50,000 square pixels gets `fetchpriority="high"`. Every image after that lazy loads.
- **Tiny images are left alone.** An image whose width and height are both 2 pixels or less, such as a tracking pixel, gets nothing from the plugin. It doesn't count toward the three, never gets high priority, and doesn't count as an Eager image. The plugin reads only the width and height already on the tag, so this adds no measurable time.
- **Logos load right away but never take the top spot.** Avatars always lazy load. Neither counts toward the three, and a logo set to Eager doesn't count as an Eager image.
- **The count follows the finished page.** The plugin decides once, after the whole page is built, going from the top of the page to the bottom. It doesn't matter which block, template part or ad built an image, or when.
- **Choices already on an image are kept.** An image marked `fetchpriority="high"` keeps it, and no image above it gets high too. An image given `loading="lazy"` by code stays lazy and doesn't use up one of the three. Images marked `fetchpriority="auto"` or `"low"`, images without a width and height, and images inside `<noscript>` don't count.
- **Lazy images get `sizes="auto"`,** so the browser picks the right file for the space the image actually fills.

## How it works

WordPress 6.9 can hand plugins the whole finished page before it's sent. The plugin uses that to walk every image on the page once. While the page is being built, it only notes choices it can't see later, such as a block's Image Loading setting, as a temporary `data-mpi-loading` attribute. The walk reads that note and removes it.

Before the walk, the plugin checks the page once for an image set to Eager, including a Mai Engine page header image. That answer picks the rule for the whole page. So an Eager image lower down still gets high priority, and the images above it lazy load.

Some responses never reach that point: the REST API, feeds, Ajax, the admin, and sites that turn the finished-page step off. There, WordPress decides loading on its own, and the plugin only applies a block's or a Mai grid's Lazy or Eager choice. No `data-mpi-loading` attribute is written.

On sites running Mai Publisher, ads and sidebar content are added to the page after WordPress's step. The page is still decided at WordPress's step. Images Mai Publisher adds afterwards lazy load, at its `mai_publisher_html` filter.

## Settings

**Settings > Performance Images** has one checkbox, Image loading. It's on by default. Turn it off and WordPress decides loading on its own again.

**Image Loading** is a choice of Automatic, Lazy or Eager. It appears in four places:

- Image, cover, featured image, media and text, and site logo blocks, in the block sidebar.
- Mai Post Grid and Mai Term Grid blocks, in the block sidebar.
- Customizer > Theme Settings > Content Archives, for each post type and taxonomy.
- Customizer > Theme Settings > Single Content, for each post type.

With Eager, the grid and archive settings also take an **Image Loading Count**. The first that many entries load right away and the rest lazy load. Leave it empty to load them all right away.

Once any image on a page is set to Eager, images left on Automatic lazy load. A Mai Engine page header image counts as set to Eager. On a page with no Eager image, Automatic uses the first-three rule. Lazy never counts toward the three, so the images after a lazy grid still get their turn.

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

- WordPress 6.9 or later
- PHP 8.1 or later

## Development

```bash
composer test-setup
composer test
```

The tests load WordPress and need MySQL with an empty `mai_performance_images_tests` database. Set `WP_TESTS_DB_NAME`, `WP_TESTS_DB_USER`, `WP_TESTS_DB_PASS` or `WP_TESTS_DB_HOST` to use a different one.

Edit block editor code in `src/` and build it with `npm run build`. The built files in `build/` are committed.
