# Removing the call stack check

**Recommendation in one line: have Mai Engine fire two actions around `mai_get_processed_content()` and give its final pass a name, then delete the call stack check.** It is about ten lines in Mai Engine, the plugin gets simpler, and it fixes a real ordering bug the check has today. The rest of this doc is the why.

## What the check does

**The plugin counts images in page order, but some images are built before the images above them.** The first three images on the page load right away and the first one gets `fetchpriority="high"`. That only works if the count follows the page.

Two moments matter, and the rest of this doc uses these two words for them:

- **Build.** A block or shortcode writes an image tag with `wp_get_attachment_image()`. A Mai Post Grid does this for every entry. WordPress asks the plugin about the image right then.
- **Final pass.** `wp_filter_content_tags()` walks the finished HTML from top to bottom and asks about every image again. This is the only moment that sees images in page order. It is also the only moment that sees static images, meaning images saved as plain HTML in an Image or Cover block.

**`mai_get_processed_content()` builds first and runs the final pass last.** It calls `do_blocks()` near the top (`mai-engine/lib/functions/utilities.php:672`) and `wp_filter_content_tags()` near the bottom (line 677).

**A tiny example of the bug.** An After Header template part holds a Cover block hero, then a Mai Post Grid with three posts.

1. `do_blocks()` runs. The grid builds its three images. If the plugin counted them now, they would take slots one to three, and the first grid image would take high priority.
2. The final pass runs. The hero is the first image in the HTML, but all three slots are gone, so the hero lazy loads. The image the visitor sees first now arrives last.

**So images built during a build wait, and get counted at the final pass.** Inside post content the plugin knows when it is in a build, because `the_content` is a filter and it hooks in before and after core's pass. Mai's function has no hook, so the plugin looks at the call stack instead. `debug_backtrace()` returns the list of functions that are running right now. The plugin walks up to 40 of them looking for the names `mai_get_processed_content`, `maicca_get_processed_content` or `get_the_block_template_html` (`classes/class-loading-attributes.php:375-391`).

## Why it is worth replacing

**It matches function names as strings.** A rename, or a copy of the function under a new name, is invisible to it. There are already four copies it cannot see: Mai Notices, Mai Publisher, Mai Archive Pages and Mai Product Embeds each carry their own version for sites without Mai Engine. Mai Custom Content Areas has one too, and that one is in the list. Springwire and Dappier have ports too, but they only render for REST and feeds, not pages.

**It gets nested content wrong today.** When one processed content renders another inside it, the inner call's final pass counts its images before the outer call has seen the images above them. A Mai CCA block inside a template part is the common case. I tested a template part with a hero image, then a CCA block holding a three-image grid. Expected: hero high, two eager, one lazy. Actual: hero lazy, first grid image high, two more eager.

**It is not slow.** I measured it on four local sites (numbers below). The cost is under 0.05% of a request. Speed is not the reason to remove it. Fragility is.

## Where the function runs

`mai_get_processed_content(` appears 36 times across 11 plugins. Grouped by where the output lands on the page:

**Above the post content, where a hero can sit. These matter most.**

- **Mai Engine template parts** before-header, header-left, header-right, mobile-menu and after-header. All go through `mai_render_template_part()` (`mai-engine/lib/functions/templates.php:378`), on Genesis header hooks.
- **The 404 page template part,** which is the main content on a 404 page.
- **The page header description** on `mai_page_header` (`lib/structure/page-header.php:213`).
- **Archive descriptions** for the blog page, terms and authors, on `genesis_before_loop` (`lib/structure/archive.php:236, 284, 336`). These sit above the archive grid.
- **Mai Custom Content Areas** on before_header, after_header, before_loop, before_entry and before_entry_content (`mai-custom-content-areas/includes/functions.php:77, 277, 448`).
- **Mai Archive Pages** content before the loop (`mai-archive-pages/classes/class-mai-archive-pages.php:702`).

**Inside the loop. Matters for the first entry or two.**

- **Full post content shown in archives and grids** (`lib/classes/class-mai-entry.php:1194`), entry custom content (line 1303), and manual excerpts on singles (line 1037).
- **Grid "no results" text** (`lib/classes/class-mai-grid.php:211`), testimonial grid content (`mai-testimonials/classes/class-grid-block.php:89`), and content areas placed between archive entries (`mai-custom-content-areas/includes/functions.php:433`).

**Inside other content.** These run nested, so ordering depends on what wraps them.

- **Inside post content:** content areas in the content location (`functions.php:257`, and again on the processed output at `includes/utilities.php:389`), accordions and notices used as shortcodes, testimonials, product embeds, the `[mai_content]` shortcode, and the Mai CCA block (`blocks/mai-cca/block.php:63`). Post content is already handled by the `the_content` hooks, so these do not need the call stack unless they are added after core's pass.
- **Inside a template part or another area:** the same blocks and shortcodes. This is where the nesting bug above lives.

**Below the content. Rarely affects the first three.** The after-entry template part, author boxes (`class-mai-entry.php:1591`, `archive.php:370`), before-footer, footer and footer-credits template parts, Mai Publisher ad content (`mai-publisher/includes/functions-ads.php:625`, `classes/class-ad-widget.php:49`, `blocks/ad/block.php:70`), and content areas after the entry, after the loop and in the footer.

**What actually triggered the check on real sites.** On the four sites I measured, every "wait" answer came from Mai Publisher ads (footer and after content), Advanced Ads placing a reusable block in the sidebar, and one content area showing a featured image above the entry content on larrybrownsports. Their template parts held only static images, so the check never had to step in for them. That is today's content, not a guarantee. A grid in an After Header part is a normal thing to build.

## What any replacement has to know

**Two facts, at the moment WordPress asks about an image:**

1. Are we inside processed content that will run a final pass later? If so, wait.
2. Is this ask that final pass? If so, count now, unless something around it will run its own pass later.

The `the_content` hooks give the plugin both facts for post content. Every option below is a different way to get them for Mai's function.

## Options that need no change to Mai Engine

### Option A: hook the actions Mai already fires around template parts

**Mai already fires an action right before and after each template part's processed content.** `mai_render_template_part()` fires `mai_before_{$slug}_template_part_content` and `mai_after_{$slug}_template_part_content` around the call (`templates.php:377-379`). Archive descriptions run inside the `mai_archives_description` action, and the page header description inside `mai_page_header`.

**The final pass can be recognised the same way core does it.** When `wp_filter_content_tags()` gets no context, it uses the name of the running hook (`wp-includes/media.php:1988-1990`). So inside a template part on `genesis_after_header`, the pass asks with context `genesis_after_header`, while built images ask with `wp_get_attachment_image`. Core uses this exact rule for `the_content`: "context is not the_content, and the_content is running, so wait" (`media.php:6189-6198`).

- **Pro:** no Mai Engine change, and no call stack.
- **Con:** it covers template parts and descriptions only. Content areas, Mai Archive Pages, archive entry content, grids and author boxes have no surrounding hook, so they would go back to being counted at build time. That is a step backwards from today.
- **Con:** it trades one list of names for another, a list of slugs and hook names.
- **Risk:** medium. Partial coverage that looks complete.

### Option B: treat any image built while blocks render as waiting

**Core gives no public way to know `do_blocks()` is running.** The plugin could count up on `pre_render_block` and down on `render_block`, but that guess breaks in two ways.

- **Shortcodes are not blocks.** An image from a shortcode in a term description is built in `do_shortcode()`, not `do_blocks()`.
- **Some code renders blocks with no final pass after it.** Mai Content Areas runs `do_blocks()` and `do_shortcode()` and nothing else (`mai-content-areas/src/Renderer.php:98-99`). The testimonial and accordion schema helpers render blocks only to strip them to text (`mai-testimonials/includes/functions.php:133`, `mai-accordion/includes/schema.php:94`). An image told to wait there is never asked again, so it ships with no `loading` attribute at all.

- **Risk:** high. Not recommended.

### Option C: spot the start of a final pass from inside it

**`wp_filter_content_tags()` calls `wp_lazy_loading_enabled( 'iframe', $context )` as its first step** (`media.php:1992`), so a filter on that could notice a pass starting. There is no matching signal for when it ends, and it reads a hook for a meaning it was never given. It is a cleverer version of the call stack check, not a fix. Not recommended.

### Option D: decide once, on the finished page

**WordPress 6.9 added a public filter that hands plugins the whole finished page:** `wp_template_enhancement_output_buffer` (`wp-includes/template.php:1085`). On classic themes like Mai, core turns this page buffer on by default (`wp-includes/script-loader.php:3701`). I confirmed it was running on every page of three Mai sites.

**The plugin could stop counting during the page and count once at the end.** While the page renders, it would only record choices that need build-time knowledge: logos, avatars, and a Mai entry's Image Loading setting, written as a temporary mark on the tag. At the end it walks every image in the finished page, top to bottom, and sets `loading`, `fetchpriority`, `decoding` and `sizes="auto"`.

- **Pro:** removes the call stack check, the `the_content` markers, the block template check and the widget checks. Nesting, copies of Mai's function and future content sources all just work, because the finished page is the truth.
- **Pro:** no Mai Engine change.
- **Con:** it is a rewrite of how the plugin decides, not a removal. Most of the 56 tests would need rewriting.
- **Con:** the plugin's floor would move from WordPress 6.7 to 6.9.
- **Con:** pages that never reach the buffer still need today's per-image answers. That covers REST, feeds, Ajax "load more", and any site that turns the buffer off. So the old path does not fully go away.
- **Con:** costs more. One walk over the finished page took 1.3 to 9 ms on the sites I measured, against under 0.7 ms for the call stack check.
- **Risk:** medium. Cleanest end state, biggest change.

## Options with a small Mai Engine change

### Option E: two actions and a named final pass (recommended)

**The change in Mai Engine,** inside `mai_get_processed_content()` in `lib/functions/utilities.php`:

```php
do_action( 'mai_before_processed_content' );                  // New, before line 669.
// ...existing lines 669 to 676 unchanged...
$content = wp_filter_content_tags( $content, 'mai_processed_content' ); // Line 677, now named.
// ...existing lines 678 and 679 unchanged...
do_action( 'mai_after_processed_content' );                   // New, before the return.
```

**What the plugin does with it.** It keeps one list of "areas currently open", with post content and Mai content in the same list. Mai's before action opens an area and the after action closes it. An image waits if any open area still has its final pass ahead. A Mai final pass is recognised by its name, `mai_processed_content`. The `the_content` hooks keep working as they do now. `inside_content_renderer()` and `debug_backtrace()` are deleted.

**I built this in a scratch copy (`/tmp/mpi-proto`) and ran the suite.** All 56 existing tests pass. Two new checks pass as well: the nested template part case (hero high, grid after it) and a classic theme using a block template.

**Is the output the same for every caller?** With this plugin active, yes: the plugin makes the loading decisions either way. Without it, one thing changes. The final pass's context changes from the running hook's name (for example `genesis_after_header`) to `mai_processed_content`. I searched every plugin in `~/Plugins` for filters that read that context (`wp_lazy_loading_enabled`, `wp_content_img_tag`, `wp_img_tag_add_loading_attr`, `wp_img_tag_add_decoding_attr`). None read it. Mai Engine's own two `wp_lazy_loading_enabled` filters ignore it. When Mai content runs inside post content, core currently treats the inner pass as post content and counts those images early. With the new name, core leaves them for post content's own pass, which is what core intends. I have not diffed real page output with and without the change.

**Nesting is handled by the list.** The inner area's final pass sees the outer area still open, so its images wait for the outer pass. This fixes the bug described above.

**Sites on an older Mai Engine.** The actions never fire, so nothing breaks. Mai content is counted at build time, which is how it worked before 0.7.0: a grid in a template part can take high priority from a hero above it. Two ways to handle the gap:

- **Release Mai Engine first, then the plugin.** Note the minimum Mai Engine version in the changelog. Simplest, since you control both.
- **Keep the call stack check only for older Mai Engine,** checked with `mai_get_version()`, and delete it in a later release. Safer during rollout, but the thing you want gone stays a while longer.

**Mai Custom Content Areas without Mai Engine** needs the same three changes in `maicca_get_processed_content()` (`includes/utilities.php:508-532`). While in there, its order differs from Mai Engine's: it runs the final pass before `do_shortcode()` (lines 527-528). I tested what that does today. An image from a shortcode gets no `loading`, `decoding` or `fetchpriority` at all. Moving `do_shortcode()` above the pass, as Mai Engine already does, fixes it. The other copies (Mai Notices, Mai Publisher, Mai Archive Pages, Mai Product Embeds) can fire the same two actions if their no-Mai-Engine path ever matters.

- **Risk:** low. About ten lines in Mai Engine, fewer lines in the plugin, uses the same pattern as `the_content`.

### Option F: wrap the steps in a `mai_processed_content` filter

**This was the first idea: make `doing_filter( 'mai_processed_content' )` work like `doing_filter( 'the_content' )`.** It works, but has side effects Option E does not.

- **Every block and shortcode inside sees a different running hook.** `current_filter()` becomes `mai_processed_content` instead of, say, `genesis_after_header`. Ad plugins such as Advanced Ads pass `current_filter()` as the context when they add loading attributes to their own image. That image would then look like the final pass and be counted early. The plugin's own test for ads covers exactly this pattern.
- **It opens a new filter on all of Mai's content** that other code can hook and change, which Mai Engine then has to support.
- **It still needs start and end markers for nesting,** because `doing_filter()` says "somewhere inside", not "how deep".

Not recommended over Option E.

### Option G: name the final pass only

**Passing the `mai_processed_content` name without the two actions tells the plugin which ask is the final pass, but not when a build is happening.** On its own it is not enough. It is half of Option E.

## Block templates and `get_the_block_template_html`

**After the uncommitted block theme change, the name can come out of the list.** Only core's `template-canvas.php` calls `get_the_block_template_html()` (`wp-includes/template-canvas.php:12`). Core picks that canvas whenever the theme supports block templates (`wp-includes/block-template.php:65-138`). Classic themes with a `theme.json` get that support automatically (`wp-includes/theme-templates.php:139-140`).

**So the template check only needs to drop `wp_is_block_theme()`.** Checking that `template_include` returned the canvas, on any theme, covers both block themes and classic themes using a block template. I tested a classic theme with a saved block template. The canvas was used, `wp_is_block_theme()` was false, and the featured image got high priority with no call stack check. None of the four Mai sites have a `theme.json`, so on Mai sites this path never runs.

## Recommendation

**Do Option E, plus drop `wp_is_block_theme()` from the template check.** It is the only option that removes the call stack check, keeps full coverage, and fixes the nesting bug, for about ten lines in Mai Engine. It uses the same idea the plugin already trusts for post content. Option D is the cleaner end state if you ever want to delete every special case at once, but it is a rewrite, not a removal.

**The order of work:** add the actions and the pass name to Mai Engine and Mai Custom Content Areas, release Mai Engine, then release the plugin without the check. Decide whether the plugin keeps the check as a fallback for older Mai Engine for one release.

## What I measured

**Test suite.** The current suite passes, 56 tests. The Option E prototype passes all 56, plus the nested template part and the classic theme block template checks. Run against the old Mai Engine (no actions), the prototype fails only the three tests that put Mai content in page order, which is the expected fallback.

**The nesting bug, today.** A template part with a hero, then a CCA block holding three grid images, comes out as hero lazy, first grid image high, then two eager.

**The call stack check on real pages.** I used a temporary mu-plugin on eurweb, larrybrownsports, sportsdataio and visitsleepyhollow, five pages each (home, a post, a category, a page, a search), three runs each. The mu-plugin was deleted from all four sites afterwards.

- **Calls per page:** 1 to 81. The busiest was a single post on eurweb, with 81 images reaching the check and 36 told to wait.
- **Time per page:** 4 to 614 microseconds in total, under 0.05% of the request on every page.
- **Time per walk:** usually 3 to 30 microseconds. One walk took 341.
- **Depth:** it never hit the 40-frame limit. The deepest match was 25 frames down, in a stack 47 frames deep. It never matched `maicca_get_processed_content` or `get_the_block_template_html`, because Mai Engine's function is always nearer.
- **Wasted walks:** the most common case was a Mai entry image in an archive loop. The walk went all the way up (20 to 25 frames), found nothing, and answered "count now". Those are 6 to 8 microseconds each.

**Option D's cost.** A second temporary mu-plugin walked every image in the finished page with WordPress's HTML tag processor on eurweb, larrybrownsports and visitsleepyhollow. It took 1.3 to 9 ms per page, on pages of 120 to 340 KB with 5 to 61 images. The page buffer was already running on every page. That mu-plugin was deleted too, and the mu-plugins folders are back to how they were.
