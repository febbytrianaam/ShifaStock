# AI Handoff

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

Capstone final testing and the cross-location batch-label fix are complete. Read [the implemented report](../30_implemented/2026-10-19-capstone-final-testing/implementation_report.md) and [actual results](../Capstone_Testing_Final/Ringkasan_Pengujian.md) before repeating work. Last functional executions were on 2026-10-18 WIB; documentation finalization on 2026-10-19 is not a new test execution.

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

Known local issue: `php artisan test --filter=InventoryFlowTest` can fail with `could not find driver` if `pdo_sqlite` is not enabled.

