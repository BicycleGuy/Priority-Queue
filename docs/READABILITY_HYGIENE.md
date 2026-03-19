# Readability Hygiene

This note captures the targeted readability rules for the billing and AI import surfaces.

## Current hotspots

- `includes/class-wp-pq-admin.php`
- `includes/class-wp-pq-api.php`
- `assets/js/admin-queue.js`

These files still carry too much responsibility, but this pass only tightens the billing and AI import paths.

## Rules for touched billing and AI code

- No newly added or materially rewritten function should exceed 80 lines without extraction.
- No touched control flow should exceed 3 levels of nesting after refactor.
- Prefer guard clauses to deeply nested conditionals.
- New helpers should be reused at least twice, or serve as a clear subsystem boundary.
- Repeated admin rendering and preview/import orchestration should be extracted into named helpers.

## What this pass extracted

- Billing Rollup client/job management rendering
- AI parse panel rendering
- AI preview rendering and context revalidation
- AI preview enrichment and warning generation

## Next extraction targets

1. Split `render_rollups_page()` into top-level section renderers.
2. Split statement/work-statement detail rendering into shared document helpers.
3. Isolate `New Request` AI-launch context handoff from the rest of `assets/js/admin-queue.js`.
