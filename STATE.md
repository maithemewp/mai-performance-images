# State
Updated: October 2, 2026 by Claude

## Now
Unreleased rewrite on top of 0.7.0. Image loading is decided once, on the finished page, using WordPress 6.9's `wp_template_enhancement_output_buffer` filter. The minimum WordPress version is now 6.9. The version number is still 0.7.0 and the code is marked `@since 0.8.0`.

How it decides:
- If any image on the page is set to Eager (block Image Loading, Mai Customizer archive or single settings, Mai grid settings), only those load right away and the first gets high priority. Everything else lazy loads.
- A Mai page header image (`page-header-image` class) counts as Eager automatically, and skips the 50,000 square pixel floor for high priority.
- With nothing set to Eager, the first 3 images load right away.
- Logos load right away without taking high priority. Avatars lazy load. Images 2x2 or smaller are left alone.
- Mai Publisher inserts content after the page is decided. Those images lazy load, on its `mai_publisher_html` filter.

## Next
- Decide the version number and release. Ask Mike before tagging.
- Remove the `mai_publisher_html` late pass once https://github.com/bizbudding/mai-publisher/issues/62 lands.
- Check mai-content-areas on real block theme content.

## Blocked / waiting on
- Nothing.

## Verify
- `composer test` (98 tests).
- Symlinked and active on these local sites: beof, eurweb, larrybrownsports, sportsdataio, visitsleepyhollow. Check with `curl -sk` that only configured images are `loading="eager"`, one has `fetchpriority="high"`, and no `data-mpi-loading` is left.

## Gotchas
- `docs/ideas/2026-10-02-remove-call-stack-check.md` explains why the call stack check was removed. It describes options from before the finished-page design was chosen.
- Requests that never reach the page buffer (REST, feeds, Ajax) are left to WordPress, plus the editor's and Mai entry choices.
- Eight local sites run older copies of the plugin, not the symlink.
