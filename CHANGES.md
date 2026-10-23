# Changelog

## 0.9.0 (10/23/26)

### Removed

- **The Settings > Performance Images page.** Its one checkbox turned the whole plugin off, the same as deactivating it. The plugin now always runs while it's active. Its saved setting, along with the old WebP settings stored with it, is deleted the next time someone visits the admin.

## 0.8.0 (10/14/26)

### Changed

- **One image loads right away by default, not three.** It's the first image of at least 50,000 square pixels, and it gets high priority. On a Mai archive in October 2026, one loaded the main image 0.5 to 0.8 seconds sooner on phones than three, and made no difference on desktop. `mai_performance_images_eager_count` still changes the number.
- **Small images and logos load right away only above the main image.** An icon or a logo above it loads right away without taking its spot. One below it, such as a footer logo, lazy loads. Before, logos always loaded right away, and a small image above the hero could take one of its spots.
- **Requires WordPress 6.9.** The plugin uses the finished-page filter WordPress added in 6.9.
- **A page with an image set to Eager follows its settings.** When any image on the page is set to Eager, by a block, the Customizer or a Mai grid, only those images load right away, and the first gets high priority. Small images and logos above them load right away too. Every other image lazy loads. Before, images left on Automatic still took the first three spots, so an Eager image lower down could lose high priority. A page with no Eager image loads its main image right away.
- **A Mai Engine page header image counts as set to Eager.** It loads right away and gets high priority, unless an Eager image above it already has it. Every image left on Automatic lazy loads. Before, it counted toward the first three, and its small listed size often kept it from high priority. Mai lists it at a small size, such as 300 by 109, while it shows full width. Now its size doesn't matter. A page without a page header image is unchanged.
- **Loading is decided once, on the finished page.** The plugin now waits until the whole page is built, then goes through every image from top to bottom. Before, it decided each image as it was built and had to guess where it would end up. That guessing is gone, including the check of which function was running.
- **An image marked high priority keeps it, and no image above it gets high as well.** Before, the page could end up with two.
- **REST, feeds, Ajax and the admin are left to WordPress,** apart from a block's or a Mai grid's Lazy or Eager choice, which still applies.

### Fixed

- **A logo marked high priority by code gives it up to the main image.** Mai Engine before 2.41.0 marks its logo `fetchpriority="high"`, so a page could end up with two. The logo now gets `auto`.
- **Tracking pixels are left alone.** An image whose width and height are both 2 pixels or less gets no loading changes from the plugin. Before, a hidden pixel near the top of the page, such as MailerLite's, used up one of the first three spots. One set to Eager could even make every other image lazy load.
- **On a block theme, the featured image above the post content gets high priority.** Before, the first image inside the post content took it.
- **A hero above a content area holding a grid keeps high priority.** Before, the grid inside the content area took the first spots.
- **Images Mai Publisher adds after the page is decided, such as sidebar and footer content, lazy load.** The rest of the page is always decided first, so a change in Mai Publisher can only affect its own images.

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
