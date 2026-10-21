# AI Handoff

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

