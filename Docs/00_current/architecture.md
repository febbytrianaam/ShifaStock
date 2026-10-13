# Architecture

## Current Stack

- Backend: Laravel 13, PHP 8.3+
- UI: Blade, Livewire 3 for medicine master UI, Tailwind CSS 4
- Frontend build: Vite
- JavaScript libraries: Chart.js, SweetAlert2, Axios bootstrap
- Database: MySQL for app runtime; PHPUnit is configured for SQLite memory tests when `pdo_sqlite` is available
- Auth: Laravel session authentication with username/password

## Major Modules

- Auth/session: `SessionController`, `EnsureActiveAccount`
- Dashboard: `PrototypeController`
- Master medicine: `MedicineIndex`, `Medicine`, `Unit`, `MedicinePackage`, `MedicineAudit`
- Inventory: `InventoryController`, `InventoryStockService`
- Daily checks: `NightlyCheckController`
- Admin users/RBAC: `Admin\UserController`, `Permissions`, `Role`
- Audit log: `AuditLogger`, `AuditLog`, `Admin\AuditLogController`
- UI shell: `resources/views/components/layouts/app.blade.php`
- Frontend behavior: `resources/js/app.js`, `resources/css/app.css`

## Runtime Flow

```mermaid
flowchart LR
    User[Authenticated user] --> Routes[routes/web.php]
    Routes --> Gates[can:* middleware]
    Gates --> Controllers[Controllers / Livewire]
    Controllers --> Services[InventoryStockService / AuditLogger]
    Services --> DB[(MySQL database)]
    Controllers --> Views[Blade / Livewire views]
    Views --> JS[app.js: SweetAlert, searchable selects, charts]
```

## Inventory Data Flow

```mermaid
flowchart TD
    Receipt[Medicine receipt] --> Batch[Batch]
    Receipt --> Movement[Inventory movement]
    Receipt --> StockBalance[Stock balance per medicine/location]
    Receipt --> BatchBalance[Batch balance per batch/location]
    Transfer[Transfer] --> FEFO{Explicit batch?}
    FEFO -- yes --> BatchBalance
    FEFO -- no --> Allocations[FEFO allocations]
    Allocations --> BatchBalance
    Transfer --> Movement
    Return[Supplier return] --> BatchBalance
    Return --> Movement
    Adjustment[Special adjustment] --> BatchBalance
    Adjustment --> Movement
    DailyCheck[Approved daily check] --> StockBalance
    DailyCheck --> Movement
```

## Important Interfaces And Services

- `InventoryStockService::add()` updates aggregate stock per medicine/location with negative-stock protection.
- `InventoryStockService::addBatch()` updates batch stock per batch/location with negative-stock protection.
- `InventoryStockService::fefoAllocations()` chooses batch allocations ordered by expiry.
- `Permissions::roleCan()` checks RBAC permissions from `role_permissions` with defaults and locked admin permissions.
- `AuditLogger::record()` writes audit trail entries.
- `resources/js/app.js` owns searchable select behavior, dependent dropdowns, SweetAlert confirmations, and Chart.js setup.

## External Services

No external SaaS integration is currently wired into the runtime application. Composer/npm package registries are used for dependency installation. GitHub is used as source control.

## Storage And Database Behavior

- `users` stores staff/admin/guest accounts.
- `role_permissions` stores RBAC matrix values.
- `medicines`, `units`, `medicine_packages`, and related tables store master medicine metadata.
- `stock_balances` stores current aggregate stock by medicine/location.
- `batch_stock_balances` stores current batch stock by medicine/batch/location.
- `inventory_movements` stores stock movement history.
- `inventory_receipts` and `inventory_receipt_items` store receipt documents and line items.
- `nightly_checks` and `nightly_check_items` store daily stock check workflow.
- `audit_logs` stores administrative/activity audit events.

## Security/Privacy-Relevant Behavior

- Passwords are hashed by Laravel model casting.
- Role permissions are enforced through route middleware.
- Inactive accounts are blocked by middleware.
- Audit logs include actor, role, IP address, user agent, event, description, and metadata.
- Demo password is intentionally not documented in README.

