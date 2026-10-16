# Testing Strategy

## How To Run Tests

Preferred isolated runner (does not use the clinic database):

```bash
php scripts/test-inventory.php
```

The runner forces SQLite `:memory:` and bypasses cached application configuration. It enables `pdo_sqlite` only in the child process if available. JUnit output is written to `storage/app/qa/inventory/phpunit-results.xml`. Exit 0 means the suite passed; nonzero means a test or environment requirement failed.

```bash
php artisan test
```

For frontend assets:

```bash
npm run build
```

For Blade compile sanity:

```bash
php artisan view:cache
php artisan view:clear
```

## Test Types That Exist

- PHPUnit feature tests in `tests/Feature/`
- Quantity-format unit tests in `tests/Unit/`
- Browser smoke script: `tests/browser-smoke.cjs`
- Receipt interaction checks: `tests/browser-receipt-workflow.cjs` with local fixtures.
- Full stock-cycle and reconciliation checks: `InventoryIntegrationTest` and `InventoryReconciliationTest`.
- Manual browser testing is still important for role menus, SweetAlert interactions, and searchable dropdown behavior.

## Important Existing Feature Coverage

- Inventory receipt/transfer/check reconciliation
- FEFO transfer split
- Supplier return and special adjustment stock updates
- Multi-item receipt draft/post
- Guest role view-only behavior
- Admin user management
- Nightly check/draft cancel behavior
- Prototype/dashboard UI checks

## Before Release, Test

- Login/logout for administrator, petugas, kepala apoteker, and guest.
- RBAC menu visibility and route access for each role.
- Receipt draft, receipt post, and receipt cancel.
- Transfer with explicit batch and automatic FEFO.
- Supplier/PBF return.
- Special adjustment increase/decrease.
- Daily check create/save/submit/review/approve/return/cancel.
- Stock card with required filters and date range validation.
- Expiry monitoring filters.
- Audit log entries for admin and inventory actions.
- `npm run build`.

## Dangerous Commands To Avoid

- Do not run `git reset --hard` unless explicitly requested.
- Do not wipe production-like databases.
- Do not run destructive migration reset commands on shared data.
- Do not commit `.env` or local secrets.

## Current Test Environment Note

Local PHP provides the SQLite extension DLL but does not load it by default. The isolated runner handles this without editing php.ini. The direct equivalent is `php -d extension=pdo_sqlite vendor/bin/phpunit`.

## Read-Only Stock Audit

```bash
php artisan inventory:reconcile
php artisan inventory:reconcile --json
```

These commands inspect the application's configured database without changing stock. They compare total stock, sum of all batch balances (including expired batches), and net movement ledger for every medicine/location present in any source. They also detect batch balances assigned to a different medicine from the batch record. Exit 1 means discrepancies were found; exit 0 means none were found in the inspected records. An empty database reports zero checked pairs and is not evidence of a validated operational dataset.

## Confirmed Limitations

- Daily physical differences now require verified batch counts and update aggregate stock, batches, and ledger together. `DailyBatchCountTest` covers stale submissions/approvals, batch validation, zero stock, redistribution, repeated approval, and atomic rollback. Historical inconsistencies are not rewritten automatically.
- Batchless adjustments and aggregate-only opening data can similarly require reconciliation. Do not automatically assign real physical differences to a batch using FEFO without verifying the physical batch counts.
- SQLite tests do not prove MySQL concurrent locking behavior. A live read-only audit checks the current local state, not future transactions or physical shelf quantities.

## Daily Batch Browser Checks

Generate local read-only fixtures using `php scripts/verify-inventory-usability.php --fixtures`, then run `node tests/browser-daily-batches.cjs` with Playwright available through Node module resolution. This checks validation, automatic totals, unsaved changes, JSON submission, and desktop/mobile overflow. POST requests are intercepted; browser checks do not modify clinic stock. Backend persistence is tested separately in isolated SQLite.

