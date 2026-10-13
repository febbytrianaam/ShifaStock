# Testing Strategy

## How To Run Tests

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
- Basic unit test placeholder in `tests/Unit/`
- Browser smoke script: `tests/browser-smoke.cjs`
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

In the current local environment, `InventoryFlowTest` has failed because PHP does not have `pdo_sqlite` enabled for SQLite `:memory:`. Enable `pdo_sqlite` or configure a suitable test database before treating the suite result as product failure.

