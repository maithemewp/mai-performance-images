# Changelog

## 0.7.0 (9/25/26)

### Changed

- **The plugin now decides loading for every image on the page.** The first three images load right away and the first gets high priority. Logos load right away without taking a spot, and avatars lazy load.
- **Grids, ads and other images built mid-content are counted where they sit on the page.** That covers post content, Mai template parts, content areas and descriptions, and block theme templates. Before, a grid could take the top spots from the cover image above it.
- **An image already marked high priority keeps it,** and an image under 50,000 square pixels, such as an icon, never takes it.
- **"Default" is now called "Automatic"** in the Image Loading choices, and the other choices say what they do.
- **A lazy grid or archive no longer uses up the top spots,** so the images after it still load right away.

### Fixed

- **The Image Loading Count on Content Archives works again.** Only the first that many entries load right away.
- **A grid's Image Loading setting ends with the grid.** Before, it could reach later images on the page.
- **Images in Mai template parts and content areas are counted once,** not twice.
- **A cover block's Lazy or Eager choice only reaches its background image,** not images inside the cover.
- **A block set to Lazy outside the post content no longer keeps high priority.**
- **Images with no width and height no longer use up a spot.**

### Removed

- **WebP conversion and on-the-fly resizing.** Cloudflare handles WebP. The Conversion, Image Quality, Cache Duration and Clear Cached Images settings are gone, along with the `wp mai-performance-images` commands. The plugin clears the old conversion's scheduled jobs and queue once after updating. Converted files are left in `uploads/mai-performance-images` for page caches that still point to them.

## 0.6.0 (3/5/26)

- Adds a Clear Cached Images button and fixes a cleanup crash when the cache folder is missing.

## 0.5.1 (12/18/25)

- Fixes the updater.

## 0.5.0 (12/17/25)

- Adds a settings page. WebP conversion is off by default.
