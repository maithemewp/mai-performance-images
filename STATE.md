# State
Updated: October 14, 2026 by Claude

## Now
0.8.0 is committed on `develop` and waits for Mike's go to merge into `main` and tag. Live sites run 0.7.0, apart from the develop test sites: sw.doyouremember.com, eurweb.com, allhiphop.com and totalprosports.com.

How it decides:
- If any image on the page is set to Eager (block Image Loading, Mai Customizer archive or single settings, Mai grid settings), only those load right away and the first gets high priority. Big images left on Automatic lazy load.
- A Mai page header image (`page-header-image` class) counts as Eager automatically, and skips the 50,000 square pixel floor for high priority.
- With nothing set to Eager, one image loads right away: the first of at least 50,000 square pixels, with high priority. `mai_performance_images_eager_count` changes the count.
- Logos and small images (under 50,000 square pixels) load right away with `fetchpriority="auto"` while a slot is free, so above the main image, and lazy load after. With no image big enough, the first small image takes the slot, else the first logo stands in.
- Avatars lazy load. Images 2x2 or smaller are left alone.
- Mai Publisher inserts content after the page is decided. Those images lazy load, on its `mai_publisher_html` filter.

Spec: `docs/specs/2026-10-14-one-main-image.md`. Plan: `docs/plans/2026-10-14-one-main-image.md`.

## Next
- Merge `develop` into `main` and tag 0.8.0, once Mike says yes.
- Push 0.8.0 from a clean checkout of the tag to the 23 active sites stuck on 0.2.0 to 0.6.0. Their updater isn't updating them, and nobody knows why yet. List them with `mai-sites plugins list mai-performance-images`.
- Remove the `mai_publisher_html` late pass once https://github.com/bizbudding/mai-publisher/issues/62 lands.
- Check mai-content-areas on real block theme content.
- Later, maybe: move this into Mai Engine once it proves out on real sites.

## Blocked / waiting on
- Mike's go to tag 0.8.0.

## Verify
- `composer test` (124 tests). Walk speed has no automated check; rerun the timing below after changing the walk.
- Symlinked on beof, eurweb, larrybrownsports, sportsdataio, visitsleepyhollow. List images with `curl -sk` plus a `?mpi=<random>` query, because eurweb runs WP Rocket and serves cached pages otherwise. Expect one `fetchpriority="high"`, logos above it eager, footer logos lazy, no `data-mpi-loading`.
- Walk time on the sw Stories archive (200 KB, 26 images) is 3.9 ms per page, the same as before the change. A two-pass version measured 6.4 ms and was dropped.
- Mobile Lighthouse on the local eurweb News archive, 5 alternating runs each, October 14: 0.7.0 median score 71 and LCP 4.9 s; 0.8.0 median 76 and 4.3 s. 0.8.0 won 3 of 5 pairs, and the spread between runs (3.3 to 8.4 s) is bigger than the gap, so it's no worse, but not a proven win on this page. The LCP image was the first entry in every run.

## Gotchas
- The walk keeps at most 8 logos or small images waiting for a slot below them, because the tag processor allows 10 bookmarks. Past 8, the rest above the main image lazy load.
- sw.doyouremember.com: Cloudflare blocks curl. Fetch with headless Chromium, a normal user agent, and basic auth doyouremember/doyouremember.
- Requests that never reach the page buffer (REST, feeds, Ajax) are left to WordPress, plus the editor's and Mai entry's choices. WordPress uses its own count of 3 there.
- Eight local sites run older copies of the plugin, not the symlink.
