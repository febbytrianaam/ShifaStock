# Current State

- Capstone final testing (documentation finalized 2026-10-19; tests executed 2026-10-18 WIB): shared searchable batch dropdowns now preserve the option for the selected location, including prefill and submission. No FEFO, stock-service, or permission changes. Final results: 70 PHPUnit tests / 733 assertions, seven MySQL competing-process scenarios, 13 live HTTP Playwright cases, and frontend build passed. Manual clinic UAT remains unperformed. [Implementation report](../30_implemented/2026-10-19-capstone-final-testing/implementation_report.md), [actual final evidence](../Capstone_Testing_Final/Ringkasan_Pengujian.md). Initial 58-test screenshot evidence remains historical.

## What Exists Now

- README refresh (2026-10-18): public-facing setup, features, inventory safeguards, isolated tests, deployment steps, and documentation links now reflect the current implementation. Credential-pattern checks were limited to README content, not a repository-wide security certification. Report: `Docs/30_implemented/2026-10-18-readme-refresh/implementation_report.md`.

- Unit/history and concurrency protection (2026-10-18): stock writes, snapshots, medicine edits, and unit renames share a database transaction mutex; historical stock units cannot be changed. MySQL snapshot comparison tolerates JSON key reordering without ignoring value/type differences. Seven real MySQL parallel-worker scenarios and the isolated 58-test suite passed. Report: `Docs/30_implemented/2026-10-18-unit-and-concurrency-protection/implementation_report.md`.

- Audit continuation (2026-10-18): monthly opname snapshots detect net-zero transactions, new batches, and unit changes; audited recount resets stale input safely. Receipt drafts no longer silently change inactive destination locations. Stock-entry quantities are limited to four-decimal database precision. Report: `Docs/30_implemented/2026-10-18-opname-audit/implementation_report.md`.

- Inventory flow safeguards (2026-10-16): mandatory batches for special adjustments, protected self-role updates, locked reversal checks, and locked monthly opname save/finalization with batch snapshot validation. See `Docs/30_implemented/2026-10-16-inventory-flow-safeguards/implementation_report.md`.

- Daily batch reconciliation (2026-10-16): physical differences require explicit batch counts; approval updates totals, batches, and ledger atomically. Stale reports are rejected. Report: `Docs/30_implemented/2026-10-16-daily-batch-reconciliation/implementation_report.md`.
- Automated inventory testing (2026-10-16): isolated test runner, full stock-cycle regression coverage, read-only total/batch/ledger audit, and an exact-fraction FEFO fix. The daily-count gap discovered by these tests has now been fixed. Report: `Docs/30_implemented/2026-10-16-automated-inventory-testing/implementation_report.md`.

- Receipt workflow safety (2026-10-15): editable drafts, supplier invoice duplicate warnings, unsaved-form protection, row-level receipt validation, and monitoring shortcuts that prefill transfer/return/condition forms. Report: `Docs/30_implemented/2026-10-15-receipt-workflow-safety/implementation_report.md`.

- Inventory usability revision (2026-10-15): separate supplier invoice field and receipt history, operational medicine packaging, stock-unit/availability context, FEFO estimates, clearer stock-changing actions, daily approval comparisons, monitoring source labels, and quantity formatting without trailing decimal zeroes. Details: `docs/30_implemented/2026-10-15-inventory-usability/implementation_report.md`.

- Laravel 13 application with Livewire 3, Tailwind CSS 4, Vite, Chart.js, and SweetAlert2.
- Username/password authentication with active-account middleware.
- Roles: administrator, petugas farmasi, kepala apoteker, guest.
- Database-backed RBAC matrix through `role_permissions`, with locked administrator permissions for core admin access.
- Superadmin controls for system references, system settings, RBAC default reset, role access preview, and audit log export/filtering.
- Medicine master data module with Livewire UI and import/research fields.
- Inventory modules:
  - multi-item receipt documents with draft and post flow, destination location, and receipt unit conversion snapshot
  - stock transfer between locations
  - inventory issue/outgoing stock with FEFO allocation
  - traceable initial stock input per batch and location
  - supplier/PBF returns
  - special stock adjustments
  - stock card
  - linked reversal for final stock movements with dedicated RBAC permission
  - expiry monitoring with filters
  - monitoring parameters for expiry horizon and minimum stock per location
  - reports
  - low stock/shortage monitoring
  - stock opname from approved daily checks
  - monthly stock opname sessions with snapshot, physical count, and pharmacist finalization
- User-facing inventory language uses "stok" for clinic workflows instead of accounting-like "saldo".
- Batch stock balances per medicine, batch, and location.
- FEFO allocation for transfers when no explicit batch is selected.
- SweetAlert2 confirmations and validation prompts for major form workflows.
- Audit logs for user and inventory/admin actions.
- Feature tests for inventory, RBAC, guest role, nightly checks, and admin user management.
- Legacy reference material from the previous `Docs/` folder currently lives in this `docs/` tree alongside the new structured documentation.

## What Does Not Exist Yet

- Production deployment documentation has a checklist, but actual host, backup owner, SSL, queue, and cron details are not filled yet.
- Formal backup/restore procedure exists as a checklist; it still needs a real environment trial.
- Formal data retention policy.
- Automated browser CI pipeline.
- Purchase order workflow for supplier ordering.
- Full audit coverage for every possible read/write event.

## Current Limitations

- `php artisan test --filter=InventoryFlowTest` currently fails in this local environment because PHP lacks `pdo_sqlite`; this is an environment issue, not an assertion failure.
- Dashboard supports demo and real mode, but some dashboard cards may intentionally remain demo-oriented.
- Daily physical checks reconcile aggregate stock; patient/medical-service detail is intentionally outside ShifaStock.
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
3. Create initial stock, receipt draft, post it, issue stock, transfer stock, create return, create special adjustment, and inspect stock card.
4. Create a monthly stock opname session, save physical counts, and finalize as kepala apoteker.
5. Verify expiry filters, monitoring parameters, low-stock views, report CSV export, reversal, and audit log entries.

## Setup/Deployment State

Known local setup uses PHP 8.3+, MySQL, Composer, npm, Laravel migrations/seeders, and Vite build. Production deployment target is unknown.

