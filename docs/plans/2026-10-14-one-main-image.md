# One Main Image Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** One image loads right away by default, and small images and logos load right away only above it.

**Architecture:** The finished-page walk in `LoadingAttributes` splits into a decide pass that writes nothing and a write pass. Between them, pending logos and small images are settled by their position against the main image. `LoadingBudget` keeps the counting rules and gets a default count of 1.

**Tech Stack:** PHP 8.2, WordPress 6.9 `WP_HTML_Tag_Processor`, PHPUnit via `composer test`.

**Spec:** `docs/specs/2026-10-14-one-main-image.md`

**As built:** Task 1's two passes measured 6.4 ms per page against 3.9 ms before, so the build went back to one pass with bookmarks (`walk_page()` and `settle_waiting()`). The spec's How section has the numbers.

## Global Constraints

- Requires PHP 8.2 and WordPress 6.9, as the plugin header says. Don't raise either.
- `@since 0.8.0` on anything new or changed. The version header stays 0.7.0 until release.
- No em-dashes in comments, docs, CHANGES.md or README.
- Comments and docblocks match the existing voice: plain sentences, say why.
- Work on `develop`. Don't commit until Mike says so.

## Review Focus

- A page where every image is set to Lazy, plus a header and footer logo: no spot is taken and there's no small image, so only the header logo loads right away. Test in Task 2.
- A small image set to Eager by an editor still takes a spot, as today. Test in Task 2.
- A logo with `fetchpriority="low"` in a navigation overlay above the main image keeps `low` and loads right away. Test in Task 2.
- Images in `noscript` still keep their markers removed in the write pass. Covered by the existing noscript tests in Task 1.
- An image marked `fetchpriority="high"` below an image the walk picked high still takes high from it, with no bookmarks left. Covered by `test_an_image_marked_high_keeps_it_and_no_image_above_gets_it` in Task 1.

---

### Task 1: Split the walk into decide and write passes, no behavior change

**Files:**
- Modify: `classes/class-loading-attributes.php` (`walk()`, `decide()`)
- Modify: `classes/class-loading-budget.php` (add `spent()`)

**Interfaces:**
- Produces: `LoadingBudget::spent(): int`, the number of spots taken so far.
- Produces: `LoadingAttributes::walk()` builds `$decisions`, one entry per `IMG` opener in page order: `null` to leave the tag alone apart from its marker, or `[ 'attrs' => array, 'spot' => bool, 'pending' => '' ]`. A second pass over the same HTML writes them by index.
- Produces: the late pass (`$late`) moves to its own private `walk_late( string $html ): string`, unchanged in behavior.

- [ ] **Step 1:** Run `composer test` and note the count (98 tests).
- [ ] **Step 2:** Refactor. `spot` is `$budget->spent()` after `decide()` greater than before. The explicit-high rule becomes: when a decided image was already marked high on the tag and an earlier entry holds an automatic high, unset `fetchpriority` from that earlier entry's `attrs`. Remove the `mpi-high` and `mpi-here` bookmarks.
- [ ] **Step 3:** Run `composer test`. Expected: same count, all pass.

### Task 2: Default of 1, small images and logos by position

**Files:**
- Modify: `classes/class-loading-budget.php` (`get_eager_count()`, `big_enough()` to public, `decline()` to public, docblocks)
- Modify: `classes/class-loading-attributes.php` (`decide()`, `walk()`, class docblock)
- Test: `tests/integration/LoadingBudgetTest.php`, `tests/integration/LoadingAttributesTest.php`, `tests/integration/BlockTemplateTest.php`, `tests/integration/MaiEntryLoadingTest.php`

**Interfaces:**
- Consumes: `spent()` and `$decisions` from Task 1.
- Produces: `LoadingBudget::big_enough( array $attr ): bool` and `LoadingBudget::decline(): array`, both public.
- Produces: `decide()` marks an entry `pending` `'logo'` or `'small'`, with `attrs` set to what it gets above the main image: `decline()`, minus `fetchpriority` when the tag already has one.

- [ ] **Step 1: Write the failing tests** in `LoadingAttributesTest`, with `[mpi_logo]` as the header logo and a `site-logo` footer logo built with the `mai_logo` context:
  - `test_the_default_count_is_one`: four static images give `[ 'eager+high', 'lazy', 'lazy', 'lazy' ]`.
  - `test_a_small_image_above_the_main_image_loads_right_away_without_a_spot`: a 100x100 image, then two static images give `[ 'eager', 'eager+high', 'lazy' ]`, and the small one has `fetchpriority="auto"`.
  - `test_a_small_image_below_the_main_image_lazy_loads`: static, then 100x100 give `[ 'eager+high', 'lazy' ]`.
  - `test_a_footer_logo_lazy_loads_and_a_header_logo_does_not`: header logo, static, footer logo give `[ 'eager', 'eager+high', 'lazy' ]`.
  - `test_with_no_image_big_enough_the_first_small_image_takes_the_spot`: header logo, 100x100, 100x100, footer logo give `[ 'eager', 'eager', 'lazy', 'lazy' ]`, and the first small image has no `fetchpriority="high"`.
  - `test_with_only_logos_the_first_one_loads_right_away`: header logo, footer logo give `[ 'eager', 'lazy' ]`.
  - `test_with_every_image_lazy_only_the_header_logo_loads_right_away`: header logo, two block images set to Lazy, footer logo give `[ 'eager', 'lazy', 'lazy', 'lazy' ]`.
  - `test_a_small_image_set_to_eager_still_takes_a_spot`: block image set to Eager holding a 100x100 image, then a static image give `[ 'eager', 'lazy' ]`.
  - `test_a_small_image_above_a_configured_eager_image_loads_right_away`: 100x100, block image set to Eager, 100x100 give `[ 'eager', 'eager+high', 'lazy' ]`.
  - `test_a_logo_set_to_eager_stays_eager_below_the_main_image`: static, then the Site Logo block filter with `imgLoading` eager on a `custom-logo` give `[ 'eager+high', 'eager' ]`.
  - `test_a_logo_marked_low_above_the_main_image_keeps_low`: a `custom-logo` with `fetchpriority="low"`, then static give loading `eager` and `fetchpriority` `low` on the logo.
- [ ] **Step 2:** Run `composer test -- --filter 'default_count_is_one|small_image|footer_logo|only_logos|every_image_lazy|logo_set_to_eager_stays|logo_marked_low'`. Expected: FAIL.
- [ ] **Step 3: Implement.** `get_eager_count()` defaults to 1. In `decide()`, a logo not set to Lazy and not chosen Eager is pending `'logo'`. An image that would reach `$budget->next()` and isn't `big_enough()` is pending `'small'`. After the decide pass, settle pending entries:

```
main = index of the last entry with spot = true
if no main:
    first pending 'small' -> attrs = budget->take( 'eager', size ), spot = true, main = it
if still no main:
    first pending 'logo' -> keeps its attrs, main = it
every other pending entry: index < main keeps its attrs, otherwise attrs = budget->lazy()
```

  Store each entry's size on it, so the first small image can be taken. Update the class docblocks to the new rules.
- [ ] **Step 4:** Run the filter from Step 2. Expected: PASS.
- [ ] **Step 5:** Run `composer test`. Update every existing assertion that relied on three spots to the new default, keeping each test's point. Rename `test_automatic_uses_the_first_three_rule`, `test_first_three_load_right_away_and_only_the_first_is_high` and `test_a_page_with_only_configured_lazy_images_keeps_the_first_three_rule` to say one. Expected: all pass.

### Task 3: Measure, verify on real pages, document

**Files:**
- Modify: `README.md`, `CHANGES.md`, `STATE.md`, `~/.claude2/skills/mai-performance-images/SKILL.md` (rule list)

- [ ] **Step 1:** Time `filter_page()` on `/tmp/sw-cat.html` (the sw Stories archive) 200 times, on the old walk (`git stash`) and the new one. Expected: both around a millisecond or less per page.
- [ ] **Step 2:** On a local symlinked site from STATE.md, fetch an archive and a single with `curl -sk` and list images. Expected: one `fetchpriority="high"`, logos above it eager, footer logo lazy, no `data-mpi-loading`.
- [ ] **Step 3:** Update README (rules and the `fetchpriority="auto"` note), CHANGES.md Unreleased, STATE.md, and the skill's rule list.
- [ ] **Step 4:** Whole-change review by a fresh reviewer, fix what holds up, run `composer test`.
