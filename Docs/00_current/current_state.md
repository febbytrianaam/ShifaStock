# Current State

## What Exists Now

- Laravel 13 application with Livewire 3, Tailwind CSS 4, Vite, Chart.js, and SweetAlert2.
- Username/password authentication with active-account middleware.
- Roles: administrator, petugas farmasi, kepala apoteker, guest.
- Database-backed RBAC matrix through `role_permissions`, with locked administrator permissions for core admin access.
- Medicine master data module with Livewire UI and import/research fields.
- Inventory modules:
  - multi-item receipt documents with draft and post flow
  - stock transfer between locations
  - supplier/PBF returns
  - special stock adjustments
  - stock card
  - expiry monitoring with filters
  - reports
  - low stock/shortage monitoring
  - stock opname from approved daily checks
- Batch stock balances per medicine, batch, and location.
- FEFO allocation for transfers when no explicit batch is selected.
- SweetAlert2 confirmations and validation prompts for major form workflows.
- Audit logs for user and inventory/admin actions.
- Feature tests for inventory, RBAC, guest role, nightly checks, and admin user management.
- Legacy reference material from the previous `Docs/` folder currently lives in this `docs/` tree alongside the new structured documentation.

## What Does Not Exist Yet

- Production deployment documentation with actual host, backup, SSL, queue, and cron details.
- Formal backup/restore procedure.
- Formal data retention policy.
- Integration with prescription, point-of-sale, or patient service systems.
- Automated browser CI pipeline.
- Purchase order workflow for supplier ordering.
- Full audit coverage for every possible read/write event.

## Current Limitations

- `php artisan test --filter=InventoryFlowTest` currently fails in this local environment because PHP lacks `pdo_sqlite`; this is an environment issue, not an assertion failure.
- Dashboard supports demo and real mode, but some dashboard cards may intentionally remain demo-oriented.
- Daily physical checks reconcile aggregate stock; exact per-patient consumption is not tracked.
- Expiry monitoring depends on batch data from receipts.
- Retention and deletion policies are assumptions until confirmed by the clinic.

## Current Risks

- Local/demo credentials must not be reused in production.
- RBAC can hide routes, but permissions must be reviewed after matrix changes.
- Audit logs may contain IP address, user agent, actor data, and metadata; retention needs policy.
- Stock accuracy depends on disciplined receiving, transfer, and daily check workflows.
- Production security posture is unknown until deployment environment is defined.

## Next Best Step

Do a manual end-to-end smoke test in the browser with all roles:

1. Login as administrator, petugas, kepala apoteker, and guest.
2. Verify menu visibility and write permissions.
3. Create receipt draft, post it, transfer stock, create return, create special adjustment, and inspect stock card.
4. Verify expiry filters and low-stock views.
5. Verify audit log entries for administrative actions.

## Setup/Deployment State

Known local setup uses PHP 8.3+, MySQL, Composer, npm, Laravel migrations/seeders, and Vite build. Production deployment target is unknown.

