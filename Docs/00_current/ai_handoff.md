# AI Handoff

Publication package (2026-10-23): the user has now authorized updating docs, committing, and pushing the daily explanations, column tooltips, issue-page guidance, and their tests to `origin/main`. This supersedes the no-publication/uncommitted notes in the earlier implementation entries below. Use Git history for the resulting commit. Exclude unrelated Capstone screenshots/ZIPs, their helpers, private UAT profiles, and ignored QA evidence. This publication does not authorize production deployment, clinic migration, session resets, or inventory/permission changes.

Latest follow-up (2026-10-23, still uncommitted on `ba7045c`): issue-page copy explicitly states no Neoklinik integration and distinguishes issued quantity from remaining stock. It includes FEFO, reference/privacy guidance, immediate stock-card posting, duplicate risk after physical adjustments, and correct transfer/return workflows. Preserve current validation/default locations/permission behavior; no backend rule was changed. `IssueGuidanceTest` adds two cases; the existing operational browser runner adds one guide/read-only case at desktop/tablet/phone sizes, not another inventory posting. Actual current results: 179 tests / 1885 assertions, 17 operational browser cases, build/syntax/whitespace passed. No new commit/push permission or persistent inventory edits. [Report](../30_implemented/2026-10-23-issue-guidance/implementation_report.md).

Latest follow-up (2026-10-23, still uncommitted): daily detail/review headings use `nightly-column-help` buttons and `nightly-column-help.js` with six shared native top-layer popovers. Keep `type=button`, valid described-by targets, pointer persistence, viewport clamping, Escape/outside/scroll dismissal, and the existing grid dimensions. Mobile help lives inside the collapsed guide rather than repeated per medicine or new always-visible headers. The old header `aria-hidden` was removed because it now contains interactive controls. Guide-body tests must select its direct div, not nested help-group divs. Actual latest runs: 177 tests / 1861 assertions and 20 real browser cases, with build/syntax/whitespace passing. No new publication authorization or inventory changes. [Report](../30_implemented/2026-10-23-daily-count-guidance/implementation_report.md#follow-up-column-tooltips).

Latest follow-up (2026-10-23, uncommitted on `ba7045c`): the daily start page defines initial/system/physical stock before the form and gives the 10-system/2-physical example, approval timing, and zero/unchecked semantics. The generic description is hidden there. Daily detail has a native collapsed, location-specific explanation above tabs; keep it collapsed by default and compact on small screens. No stock/permission/print logic changed. Existing 80 px minimum list-height assertions must not be weakened: an initial 320 px failure was fixed with shorter disclosure text and scoped spacing. Browser suite now has 17 cases, including two guidance checks. Do not reset persistent UAT or assume this UI request authorizes another commit/push. [Actual results](../30_implemented/2026-10-23-daily-count-guidance/implementation_report.md).

Publication package (2026-10-22): the user authorized updating docs, committing, and pushing the shared-draft feature and its verification/guidance to `origin/main`. Use Git history for the resulting commit. Exclude unrelated Capstone screenshots/ZIPs, their helper changes, private profiles, and ignored QA outputs. No production deployment or clinic migration is authorized by this publication.

Latest user-authorized behavior (2026-10-22, based on `af37ff0`): daily drafts are shared between operators with existing `operate-inventory` access, irrespective of creator. This explicitly supersedes owner-only save/add/submit instructions in older entries. Keep creator immutable, cancellation creator-only, existing review permission/state guards intact, and Guest read-only. New `draft_revision` is required for save/add/submit/cancel and compared inside the inventory transaction; stale web input retains its old revision and offers a fresh-version link in a new tab, never a blind retry or automatic merge. Track actual changed-row actors, not every row in the full-form submission; preserve names as snapshots and do not fabricate legacy history. Additive `2026_10_22_160000` migration has been applied only through `php scripts/uat.php migrate`; clinic `.env` database was not migrated. Do not reset UAT or seed/rewrite existing actor history. [Report](../30_implemented/2026-10-22-shared-daily-check/implementation_report.md).

Login 419 triage (2026-10-22): the user confirmed the public HTTPS domain. A fresh isolated Chrome session retrieved HTTP 200, the HTTPS form action, host-scoped Secure/Lax session cookies, and dynamic/private cache headers. One nonexistent-user submission returned HTTP 302 with credential validation, not 419. This verifies a fresh CSRF round-trip, not valid-account login or the cause of the user's expired session. Recovery guidance is in the UAT guide; no CSRF exemption, cookie weakening, key rotation, session purge, or login code change was made.

Publication package (2026-10-22): compact daily counting UI, final full-width table behavior, tablet/phone container queries, adapted browser runners, and updated operator/public documentation are included together. The user explicitly authorized committing and pushing this package to `origin/main`. This supersedes earlier uncommitted/do-not-publish notes below. Use Git history for the resulting publication commit. No persistent inventory or permissions are changed; unrelated Capstone screenshot/ZIP edits remain outside this package.

Final pre-publication reruns completed at 16:22 WIB: 157 tests / 1621 assertions, 12 workspace + seven partial + 16 operational browser cases, Vite build, syntax, and whitespace passed. Temporary fixture databases were removed and stock reconciliation passed. Selected-daily/PDF and MySQL worker races were not rerun; do not relabel their historical counts as current. [Details](../30_implemented/2026-10-22-daily-check-workspace/implementation_report.md#publication-verification).

Latest width fix (2026-10-22): user still saw a gap on a 27-inch display. Actual saved UAT report inspection confirmed `max-width:1360px` on `.nightly-list` left 912 px unused at 2560 px. Remove that cap, not page padding; both workspace and table now use full available width. Supersedes the earlier capped-table instruction below. Live Full HD/QHD/tablet/phone checks found zero right gap/no horizontal overflow; no inventory form submitted and viewport override reset. Build and 12 isolated workspace browser cases passed, with explicit full-width edge assertions through 4K. No new PHPUnit/PDF execution for this one-rule change. Still uncommitted. [Report](../30_implemented/2026-10-22-daily-check-workspace/implementation_report.md#follow-up-actual-panel-and-full-width-table).

Latest responsive correction (2026-10-22): the user explicitly wants the page content width/padding preserved. Only `.nightly-list` is capped at 1360 px, left-aligned; do not cap/center `.nightly-workspace`. Proportional tracks fill the table instead of placing all leftover width in an empty status column. Container queries at 700/460 px adapt to sidebar-constrained tablets and phones. Actual reruns: 157 tests / 1621 assertions, 12 workspace browser cases across 12 viewport sizes, seven partial browser cases, and build. Resize tests wait for actual height/sidebar settling; fixtures use a long name and Tablet/Kapsul. No backend, permissions, or persistent fixture changes. Still uncommitted. [Report](../30_implemented/2026-10-22-daily-check-workspace/implementation_report.md#follow-up-responsive-table-only).

Latest UI work (2026-10-22, uncommitted on `5e86898`): `nightly/show` is a compact workbench, with row/add-dialog partials, scoped CSS, and `nightly-workspace.js`. Main list defaults to ten rows; Rincian contains batch/request/note fields. All rows stay in the form even when hidden. Do not restore per-keystroke status filtering: it hides an input while the user is typing. Batch-derived totals, null/zero semantics, unit/ownership/RBAC checks, partial submission, and existing A4 printing are unchanged. Review and saved summary are separate tabs. Browser tests should click `[data-nightly-row-toggle]` and the Tinjauan Apoteker tab, not set `details.open` programmatically. Actual runs: 157 tests / 1621 assertions, 10 workspace + 7 partial + 16 operational browser cases. New phase: `php scripts/capstone-final-browser.php nightly-ui` uses temporary MySQL and 167 simulated masters; persistent UAT/clinic data remains untouched. Existing selected-daily/PDF script adapted but not rerun in this change. Do not commit/push without another request. [Report](../30_implemented/2026-10-22-daily-check-workspace/implementation_report.md).

Latest daily behavior (2026-10-22): a complete or selected list may be sent partially only with validated `confirm_partial`; at least one non-null count is required. Preserve unchecked rows/notes as null and original `scope` as initial-list intent. Snapshot/review only counted medicines; never coerce blank to zero or let it supersede prior approved history. History and period recaps exclude null counts. Grouped DOM-text warnings limit name examples, and `nightly:reveal-row` opens the correct page. Read-only drafts belonging to another user are intentional; do not change ownership/RBAC or reset UAT to make fields editable. Actual verification: 157 tests / 1621 assertions; seven partial, 15 daily, and 16 operational browser checks passed. [Report](../30_implemented/2026-10-22-partial-daily-submission/implementation_report.md).

Publication package (2026-10-22): draft additions, partial submission/read-only explanations, standalone A4-portrait daily reports, shared current-stock dashboard monitoring, and their documentation are included together. Use Git history for the final commit hash. Widget audit at 14:20 WIB matched all dashboard output to independent read-only UAT queries; focused rerun passed 25 tests / 254 assertions. Preserve the distinction between stock transaction alerts and historical physical results. The balance table search is only a 12-row preview; Tersedia means positive stock, not above minimum. Legacy Capstone screenshot/ZIP changes remain outside this publication; no secrets, databases, or ignored QA files are included. The uncommitted notes below describe their earlier verification stages, before this publication package.

Latest follow-up (2026-10-22): dashboard Kosong / Menipis and Perlu Ditindaklanjuti use `MinimumStockAlerts`, shared with Monitoring's `parameterRows`, rather than `LatestNightlyItems`. Keep approved physical results separate in Monitoring; do not mix stale counts with current transaction balances. Preserve explicit per-medicine/location minimums, inclusive threshold, and missing-balance-as-zero behavior. No automatic classification of all unconfigured masters. Dashboard links drill into `#stok-minimum` with code/location. Actual verification: 147 tests / 1533 assertions, 16 browser cases, build, screenshots, and read-only UAT consistency. Work remains uncommitted; do not reset tester data or permissions. [Report](../30_implemented/2026-10-22-dashboard-stock-monitoring/implementation_report.md).

Latest follow-up (2026-10-22): daily printing uses A4 portrait with a 210 mm preview, not landscape. The read-only `nightly.print` route and standalone `nightly/print.blade.php` share `reportData` with detail. Do not revert to printing the application page or apply UI pagination/filters to print data. Print recorded units and all report items, with draft/partial/UAT status explicit; never imply a draft is approved. `hasFormChanges` protects unsaved input through SweetAlert without autosave. Latest actual reruns: 140 tests / 1450 assertions and 15 daily browser cases; real PDFs one/eight portrait pages validated and all rendered pages visually inspected. Chrome may serialize A4 portrait CSS as `a4`; actual PDF geometry is approximately 595 x 842 pt. Work remains uncommitted alongside draft additions. No persistent UAT/clinic data or permission-matrix changes. [Report](../30_implemented/2026-10-21-daily-check-print-report/implementation_report.md).

Latest follow-up (2026-10-21): daily drafts support `nightly.save` with `action=add` and `medicine_ids[]`. This submits the full existing count form plus batch JSON, saves draft inputs, and appends only missing eligible medicines inside one locked inventory transaction. Do not implement additions by navigating away or replacing report items: unsaved counts must survive. Owner + operational access + draft status remain required; submitted/approved reports reject additions. New quantities stay null, duplicates are filtered, and stale forms missing current rows fail without partial saving. No migrations or permission reset: preserve the user's Stok Awal matrix edits. Full suite 133 tests / 1392 assertions; browser expanded to 12 actual checks with a two-item approved fixture. Work is uncommitted. [Report](../30_implemented/2026-10-21-add-medicines-to-draft/implementation_report.md).

## Earlier Published Feature

Latest feature (2026-10-21): daily checks accept `scope=complete|selected` and `medicine_ids[]` at creation. Selected reports persist only chosen eligible items; submission still requires all report items and existing batch/snapshot/ownership/approval checks. Never treat omitted items as zero. Satuan Hitung now follows the master stock unit through a dropdown; incomplete masters use active reference options with a warning, and mismatched counted drafts require explicit selection without silently reinterpreting quantities. One report per date/location remains; reopen does not replace scope. `LatestNightlyItems` must remain per medicine/location for dashboard/monitoring and date-bounded comparison. Scope migration was applied to UAT only with guarded `php scripts/uat.php migrate`; never reset persistent tester data. Latest combined suite: 124 tests / 1306 assertions; separate browser/MySQL results are recorded in the [report](../30_implemented/2026-10-21-selected-daily-check/implementation_report.md). Publication includes UAT routing, multi-transfer, and this daily-check follow-up; use Git history for the publication commit.

## Project Summary

ShifaStock is an internal medicine inventory monitoring app for Klinik Shifa Medika. It is a Laravel app with database-backed stock, batch, RBAC, audit, reporting, and daily stock check workflows.

## Current Stack

- Laravel 13 / PHP 8.3+
- Livewire 3
- Blade and Tailwind CSS 4
- Vite
- MySQL
- Chart.js and SweetAlert2
- PHPUnit feature tests

## Current Architecture

Most web routes live in `routes/web.php` and are protected by `auth`, `active`, and `can:*` middleware. Inventory operations are handled by `InventoryController` and `InventoryStockService`. Daily physical checks are handled by `NightlyCheckController`. Master medicine UI is Livewire via `MedicineIndex`.

## Current Constraints

- Do not expose demo passwords in documentation.
- Do not bypass RBAC middleware when adding routes.
- Do not manually mutate stock balances outside `InventoryStockService` unless there is a clear reason.
- Preserve existing user changes and dirty worktree rules.
- Current local PHPUnit run may require enabling `pdo_sqlite`.

## Key Files/Folders

- `routes/web.php`
- `app/Http/Controllers/InventoryController.php`
- `app/Http/Controllers/NightlyCheckController.php`
- `app/Http/Controllers/Admin/UserController.php`
- `app/Livewire/MedicineIndex.php`
- `app/Services/InventoryStockService.php`
- `app/Support/Permissions.php`
- `app/Support/AuditLogger.php`
- `resources/views/inventory/`
- `resources/views/nightly/`
- `resources/views/admin/`
- `resources/js/app.js`
- `database/migrations/`
- `tests/Feature/InventoryFlowTest.php`

## Current Risks

- Production deployment and backup strategy are unknown.
- Audit log retention policy is unknown.
- Stock accuracy depends on consistent staff process.
- RBAC matrix changes can create confusion if not smoke-tested by role.

## Next Recommended Task

Latest feature (2026-10-21): transfers now accept `items[]` with up to 50 entered rows under one reference. UI uses `transfer-rows.js` and `transfer-row.blade.php`; preserve the existing searchable-select dependency handling and shared stock-context behavior. Backend retains legacy single-item requests, serializes all rows together, rechecks reference/operational medicine/active locations under the inventory lock, and rolls back all rows on any failure. No migration or FEFO rewrite. Latest verification: 112 tests / 1189 assertions, nine MySQL races, nine transfer browser checks, 13 operational browser regressions, build and Blade compilation passed. Use the new isolated `php scripts/capstone-final-browser.php transfer` phase; do not fill/reset the user's persistent UAT to test this. See the implemented report.

Workspace runtime update (2026-10-21): the user requested the public domain to keep backend port 4002 but use UAT. The normal artisan process was replaced by `php scripts/uat.php serve`; the private manifest stores port 4002, HTTPS URL, and secure cookies. Keep the main `.env` unchanged and use the UAT runner, not plain artisan on port 4002. Initial fixtures were cleared after backup; four accounts and 167 masters were preserved. Testers have since entered new transactions; do not clear/reseed them for QA. Domain login/dashboard/Guest restrictions/logout were verified. Stage-specific results: 100 tests / 1001 assertions and eight public-domain browser checks. Source/configuration guidance is included in the current publication; secrets remain private and ignored.

Latest interface follow-ups (2026-10-21): welcome guidance is a centered targetless Driver.js step, not a spotlight of the entire dashboard. Later menu highlights remain permission-based. Friendly error pages are standalone Blade views under `resources/views/errors/` without database/session/Vite dependencies; normal JSON and local debug handling are unchanged. Login uses `resources/js/password-visibility.js` for an accessible Lucide eye toggle; no preset credentials are displayed. Latest full suite: 97 tests / 986 assertions passed; separate browser checks: onboarding 9, errors 7, login 3; Vite build passed. See [testing strategy](testing_strategy.md). Do not confuse these runs with historical Capstone or UAT results.

UAT separation and read-only Guest expansion are implemented (2026-10-21). Read [UAT guide](uat_guide.md) and [implementation report](../30_implemented/2026-10-21-uat-guest/implementation_report.md). Setup is already applied locally; do not reset/reseed the persistent UAT database or alter the main `.env`. Use the UAT URL and simulated accounts for clinic trials, not the clinic database. Credentials/configuration are private and ignored by Git. Actual staff UAT is still pending; record their feedback instead of treating automated checks as acceptance.

Dashboard-only real data and the first-login spotlight guide were completed on 2026-10-21. See [the implementation report](../30_implemented/2026-10-21-dashboard-onboarding/implementation_report.md). Local authorized cleanup is already applied: preserve the 167 Excel-derived medicines and do not re-import old SQL/demo stock. The cleanup command is an exact reviewed local manifest, not a generic database reset or production migration. The migration adds `users.onboarding_seen_at`; never mark clinic users as having seen the guide merely for QA. Browser fixtures use their own database.

Historical Capstone final testing and the cross-location batch-label fix are complete. Read [the implemented report](../30_implemented/2026-10-19-capstone-final-testing/implementation_report.md) and [actual results](../Capstone_Testing_Final/Ringkasan_Pengujian.md) before repeating work. For that historical package, functional executions were on 2026-10-18 WIB; documentation finalization on 2026-10-19 is not a new test execution.

Run a browser-based manual QA pass with seeded/local data and document results under `docs/30_implemented/YYYY-MM-DD-manual-qa-pass/` or a future `test_notes.md`.

## Things Not To Do Yet

- Do not introduce Spatie Permission unless the current simple RBAC matrix becomes insufficient.
- Do not add patient-service, prescription, POS, or supplier integrations; current PRD keeps ShifaStock focused on medicine inventory management.
- Do not remove legacy reference assets in `docs/` unless the user asks.
- Do not create speculative docs in `00_current/`.

## Useful Commands

```bash
composer install
npm install
php artisan migrate --seed
php artisan db:seed --class=LocalDemoSeeder
npm run build
php artisan serve
php artisan test
```

Use `php scripts/test-inventory.php` to force an isolated SQLite database. The runner can enable the SQLite PDO extension in its child process if necessary.

