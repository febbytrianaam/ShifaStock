# Implementation Report: PRD Gap Completion

## Date

2026-10-14

## Summary

Implemented the remaining operational scope from the PRD audit: outgoing stock, initial stock, monthly stock opname sessions, reversal, monitoring parameters, stricter batch expiry handling, period reports, deployment/backup documentation, and clinic-friendly stock terminology. Follow-up hardening also separated reversal permission and completed the receipt destination/conversion snapshot needed for PBF receiving.

## Shipped

- New inventory issue route and page at `/obat-keluar`.
- New initial stock route and page at `/stok-awal`, with `/saldo-awal` kept as a compatibility redirect.
- New monitoring parameter route and page at `/parameter-monitoring`.
- New monthly stock opname session tables and workflows.
- New reversal link on inventory movements, gated by `reverse-inventory-movements`.
- Receipt documents now store destination location and input-unit conversion snapshots per item.
- New system settings table for configurable expiry warning horizon.
- Minimum stock parameters are surfaced in shortage monitoring.
- Transfer and issue FEFO allocation skip expired batches and reject insufficient usable batch stock.
- Reports now support period reconciliation and CSV export.
- Production deployment, backup, and restore checklist added.
- User-facing "saldo" wording was replaced with "stok" across app screens and operational messages.

## Files Touched

- `app/Http/Controllers/InventoryController.php`
- `app/Http/Controllers/NightlyCheckController.php`
- `app/Http/Controllers/PrototypeController.php`
- `app/Services/InventoryStockService.php`
- `app/Support/Permissions.php`
- `routes/web.php`
- `resources/views/components/layouts/app.blade.php`
- `resources/views/inventory/*`
- `resources/views/livewire/medicine-index.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/nightly/shortages.blade.php`
- `database/migrations/2026_10_14_000001_add_prd_completion_tables.php`
- `Docs/00_current/deployment_backup.md`

## Verification

- `php -l app/Http/Controllers/InventoryController.php`
- `php -l app/Http/Controllers/NightlyCheckController.php`
- `php -l app/Services/InventoryStockService.php`
- `php artisan route:list --except-vendor`
- `php artisan migrate`
- `php artisan view:cache`
- `php artisan view:clear`
- `rg -n "\bsaldo\b|\bSaldo\b|\bSALDO\b" resources app -S`

## Known Follow-Up

- Automated feature tests still require the local PHP `pdo_sqlite` extension or a test database configuration that matches the local environment.
- ShifaStock is intentionally standalone for medicine inventory management; patient-service integrations are outside the current PRD scope.
- Manual browser QA should verify the new menu flow and role matrix after migrations are applied.
