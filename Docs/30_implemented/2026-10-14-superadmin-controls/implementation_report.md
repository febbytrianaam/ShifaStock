# Implementation Report: Superadmin Controls

## Date

2026-10-14

## Summary

Implemented the planned superadmin hardening work for ShifaStock administration: system references, system settings, RBAC reset/preview, and stronger audit log filtering/export.

## Shipped

- Added Referensi Sistem menu for stock locations and medicine units.
- Added active/inactive state to units.
- Added Pengaturan Sistem menu for:
  - default receipt destination location
  - clinic report name
  - report footer text
- Penerimaan Obat now uses the configured default receipt location when opening the form.
- Added new permissions:
  - `manage-system-references`
  - `manage-system-settings`
- Administrator permissions for reference/settings access are locked by default.
- Added reset-to-default action for the RBAC matrix.
- Added role access preview on the Hak Akses tab.
- Improved audit log filters:
  - search
  - event
  - actor
  - subject type
  - date range
- Added audit log CSV export.
- Added audit records for reference updates, permission reset, and system setting changes.

## Files Touched

- `app/Http/Controllers/Admin/UserController.php`
- `app/Http/Controllers/Admin/AuditLogController.php`
- `app/Http/Controllers/Admin/SystemReferenceController.php`
- `app/Http/Controllers/Admin/SystemSettingController.php`
- `app/Http/Controllers/InventoryController.php`
- `app/Models/Unit.php`
- `app/Support/Permissions.php`
- `routes/web.php`
- `resources/js/app.js`
- `resources/views/admin/references/index.blade.php`
- `resources/views/admin/settings/index.blade.php`
- `resources/views/admin/users/index.blade.php`
- `resources/views/admin/audit-logs/index.blade.php`
- `resources/views/components/layouts/app.blade.php`
- `resources/views/inventory/reports.blade.php`
- `resources/views/inventory/receipts.blade.php`
- `database/migrations/2026_10_14_000003_add_superadmin_controls.php`

## Verification

- `php -l` for changed PHP controllers, model, migration, routes, and permission helper.
- `php artisan migrate --force`
- `php artisan route:list --except-vendor`
- `php artisan view:cache`
- `php artisan view:clear`
- `npm run build`
- Permission sanity check:
  - administrator can manage system references
  - petugas cannot manage system references
  - petugas cannot reverse inventory movements by default

## Known Limitation

`php artisan test --filter=InventoryFlowTest` still cannot run in this local PHP environment because the SQLite PDO driver is missing (`could not find driver`). No application assertion was reached.
