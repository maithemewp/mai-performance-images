# One main image per page

Agreed with Mike on October 14, 2026. Builds on the unreleased 0.8.0 work on `develop`.

## Goal

By default, one image loads right away and gets high priority: the page's main image. Small images and logos never take that spot, and they only load right away when they sit above it. Most sites get this without changing any setting.

## Why

- **One beats three on phones.** On visitsleepyhollow in October 2026, a count of 1 beat 3 in 17 of 20 paired mobile Lighthouse runs on an archive, with the main image 0.5 to 0.8 seconds sooner. Desktop didn't change, because a lazy image already on screen still loads as soon as the browser sees it.
- **With one spot, the wrong image taking it costs more.** Today a 100x100 icon above the hero takes a spot. With three spots the hero usually still got one. With one, the hero would lazy load.
- **Logos below the main image waste an early download.** sw.doyouremember.com loads its footer logo right away on every category archive.

## Words

- **Spot.** One of the images that load right away by count. The default count is now 1.
- **Big enough.** At least `wp_min_priority_img_pixels` square pixels by the width and height on the tag. WordPress's default is 50,000.
- **Small image.** An image left on Automatic, with a width and height, that isn't big enough. Tiny images (2x2 or less) stay outside this, and are still left alone.
- **Main image.** The image that takes the last spot. With a count of 1, that is the first image that takes a spot.

## Rules

1. **The default count is 1.** `mai_performance_images_eager_count` still changes it, and still never goes below 1. WordPress's `wp_omit_loading_attr_threshold()` no longer sets the plugin's default.
2. **These take a spot, as today:** an image left on Automatic that is big enough, an image set to Eager (block, Mai setting, Mai page header), a `loading="eager"` already on a big enough tag on a page with nothing set to Eager, and an image marked `fetchpriority="high"`.
3. **A small image never takes a spot.** Above the main image it loads right away with `fetchpriority="auto"`. Below it, it lazy loads. A small image that code already gave `loading="eager"` loads right away with `fetchpriority="auto"` wherever it sits, without taking a spot (Mike, October 14).
4. **A logo follows the same position rule.** Above the main image it loads right away with `fetchpriority="auto"`, or keeps a `fetchpriority` it already has. Below it, it lazy loads. A logo set to Lazy stays lazy. A logo set to Eager by an editor always loads right away, as today.
5. **When no image takes a spot, the first small image takes it.** It loads right away, without high priority, since it isn't big enough. Logos above it load right away. Everything below it lazy loads.
6. **When no image takes a spot and there is no small image, the first logo loads right away.** Every other logo lazy loads. This keeps a header logo eager on a page with no other images, while a footer logo still lazy loads.
7. **A page with an image set to Eager follows the same position rule** for small images and logos. Images left on Automatic that are big enough still lazy load there.
8. **Unchanged:** tiny images, avatars, images without a width and height, images marked `fetchpriority="auto"` or `"low"`, images in `noscript` and `template`, the Mai Publisher late pass, and requests WordPress doesn't collect (REST, feeds, Ajax, the admin).

`fetchpriority="auto"` on a tag stays the way for a theme or a developer to keep an image out of the spot. The README says so.

## How

The walk stays one pass. A logo or small image seen while a spot is still free loads right away and gets a bookmark. When an image takes a spot below it, the bookmark is dropped and it stays eager. At the end of the page, the walk goes back to any still waiting: below the main image they lazy load, and with no main image the stand-in rule above applies.

The tag processor allows 10 bookmarks. The walk keeps 2 for moving high priority, so up to 8 can wait. A logo or small image past those, above the main image, lazy loads. A browser still loads a lazy image as soon as it sees it on screen.

A first build used two full passes, decide then write. On the sw Stories archive (200 KB, 26 images) that took the walk from 3.9 ms to 6.4 ms per page, because one pass of the tag processor alone costs 2.4 ms there. The one-pass build measures 3.9 ms, the same as before.

The scan for an image set to Eager (`has_configured_eager()`) stays as it is, before the walk.

## Tests

- The default count is 1, and the filter still changes it.
- A small image above the main image loads right away with `auto` and takes no spot. Below it, it lazy loads.
- A header logo loads right away and a footer logo lazy loads.
- A page with only small images: the first takes the spot without high priority, and the rest lazy load.
- A page with only a header and a footer logo: the first loads right away, the second lazy loads.
- A page set to Eager: a small image above the Eager image loads right away, and one below it lazy loads.
- A logo set to Eager by an editor stays eager below the main image.
- An image marked `fetchpriority="high"` below an image the walk picked still takes high priority from it.
- Every existing test, updated from the old count of 3 where it asserted three.

## Performance

Time the walk before and after on a real archive page (the sw Stories archive, about 200 KB). It must not be slower than before.

## Docs

README, CHANGES.md (Unreleased), STATE.md, and the mai-performance-images skill's rule list.

## Later

If this proves out on real sites, it may move into Mai Engine. Not part of this work.
