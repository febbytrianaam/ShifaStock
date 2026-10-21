# Testing Strategy

## Latest Interface Regression Execution

2026-10-21 WIB: the latest full isolated PHPUnit run passed **97 tests / 986 assertions** after the centered onboarding welcome, friendly error pages, and login password toggle changes. Vite build passed. These totals replace earlier suite totals as the latest run; prior entries below remain stage-specific history.

Actual additional browser runs, executed separately:

| Runner | Result | Scope and Evidence |
| --- | --- | --- |
| `php scripts/capstone-final-browser.php onboarding` | 9/9 passed | Random isolated MySQL fixture, four-role welcome, menu steps, replay/skip/back/complete, mobile/short-screen/resize; ignored `storage/app/qa/onboarding/` |
| `node scripts/test-error-pages.cjs` | 7/7 passed | Six desktop/mobile HTML error responses and one POST-500 safety case; test-only loopback server with SQLite `:memory:`; ignored `storage/app/qa/error-pages/` |
| `node scripts/test-login-browser.cjs` | 3/3 passed | Local UAT login at port 8010, desktop/mobile toggle and no-JavaScript fallback; no credentials submitted; ignored `storage/app/qa/login-visibility/` |

Playwright must be available through Node module resolution. The runners currently use installed Windows Chrome. Reports/screenshots are local ignored QA evidence, not replacement Capstone screenshots. The latest UI work did not rerun the seven MySQL concurrency scenarios or the 25-check UAT suite; their executions below remain historical. Formal manual clinic UAT is still pending.

## Latest UAT Execution

2026-10-21 WIB: isolated-UAT/Guest implementation passed 90 PHPUnit tests / 931 assertions, seven competing-process MySQL scenarios, 25 direct UAT browser checks and frontend build. `php scripts/uat.php verify` confirms the clinic inventory fingerprint is unchanged and the schema-limited UAT MySQL account cannot read clinic medicines. See [UAT implementation and browser evidence](../30_implemented/2026-10-21-uat-guest/implementation_report.md), and [local UAT guide](uat_guide.md). Manual clinic staff acceptance is not completed.

`php scripts/uat.php browser-check` exercises the running persistent UAT app with temporary QA accounts, without overwriting the supplied simulation accounts or changing inventory. Evidence goes to ignored `storage/app/qa/uat-browser/`; bootstrap/grant verification goes to `storage/app/qa/uat-isolation.json`. All UAT commands must use the dedicated runner; do not repoint the main `.env` or use clinic transactions for testing.

## Earlier Dashboard Execution

2026-10-21 WIB: dashboard/onboarding/cleanup changes passed 81 PHPUnit tests / 795 assertions, seven competing-process MySQL scenarios, 13 operational browser cases, eight additional onboarding browser checks, and Vite build. npm's compatible development-dependency fixes reported zero vulnerabilities. These are actual working-tree runs based on commit `88835eb`, not new Capstone screenshots or manual staff UAT. [Report and private evidence locations](../30_implemented/2026-10-21-dashboard-onboarding/implementation_report.md).

`php scripts/capstone-final-browser.php onboarding` creates an isolated MySQL fixture for first-login, role-aware, replay, and mobile guide tests. `php scripts/capstone-final-browser.php regression` runs the existing 13 operational cases with output under ignored `storage/app/qa/inventory-browser/`, preserving historical Capstone evidence. Node must resolve Playwright; Windows Chrome is currently used by these runners. Neither runner logs fixture passwords or writes to the clinic database.

## Latest Recorded Capstone Execution

Executed 2026-10-18 WIB; documented 2026-10-19: 70 PHPUnit tests / 733 assertions, 7 competing-process MySQL scenarios, 13 live HTTP Playwright cases, and Vite build passed. See [actual evidence and limitations](../Capstone_Testing_Final/Ringkasan_Pengujian.md) and the [ten-group functional table](../Capstone_Testing_Final/Hasil_Pengujian_Fungsional.md). Manual staff UAT is not completed. The earlier 58-test screenshot package is historical, not the latest suite total.

`php scripts/capstone-final-tests.php` records fresh PHPUnit/MySQL/build output. With Playwright available through Node module resolution, `php scripts/capstone-final-browser.php final` creates a separate simulated MySQL database and exercises the actual local HTTP app without response interception. `php scripts/capstone-final-docs.php` formats that evidence; `node scripts/capstone-final-verify.cjs` checks it. Formatting/verifying evidence does not rerun functional tests. The MySQL account needs CREATE/DROP DATABASE privileges. Added browser runners target the local Windows environment; other hosts may require browser/PHP/npm executable adjustments.

## How To Run Tests

Preferred isolated runner (does not use the clinic database):

```bash
php scripts/test-inventory.php
```

The runner forces SQLite `:memory:` and bypasses cached application configuration. It enables `pdo_sqlite` only in the child process if available. JUnit output is written to `storage/app/qa/inventory/phpunit-results.xml`. Exit 0 means the suite passed; nonzero means a test or environment requirement failed.

On a slow host, set `INVENTORY_TEST_TIMEOUT=900` before running the same command. The default is 180 seconds, with an allowed range of 60 to 1800 seconds. A timed-out run is not a passing test result.

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
- Historical batchless adjustments and aggregate-only opening data can require reconciliation. New special adjustments require a batch. Do not automatically assign real physical differences to a batch using FEFO without verifying the physical batch counts.
- SQLite tests alone do not prove MySQL locking behavior. The separate MySQL runner below tests seven competing-process cases. Neither suite is a production-load benchmark or verification of physical shelf quantities.

## Daily Batch Browser Checks

Generate local read-only fixtures using `php scripts/verify-inventory-usability.php --fixtures`, then run `node tests/browser-daily-batches.cjs` with Playwright available through Node module resolution. This checks validation, automatic totals, unsaved changes, JSON submission, and desktop/mobile overflow. POST requests are intercepted; browser checks do not modify clinic stock. Backend persistence is tested separately in isolated SQLite.

`node tests/browser-opname-recount.cjs` uses the synthetic opname fixture to check SweetAlert cancel/confirm, the recount route, and desktop/mobile overflow. It also intercepts every request. `OpnameAuditTest` checks persistence, net-zero transactions, new batches, unit changes, stale form rejection, legacy snapshots, recount audit history, guest denial, inactive receipt destinations, and quantity precision.

## Isolated MySQL Concurrency Checks

```bash
php scripts/test-inventory-mysql.php
```

The configured MySQL account needs CREATE/DROP DATABASE permission. The runner creates its own random `shifastock_qa_` database, migrates only that database, and starts two independent PHP workers behind a readiness barrier for each case. Workers reject non-testing environments or database names outside the exact QA format. The runner stops its workers and drops only the generated database on completion/failure. It never runs migrate:fresh or destructive cleanup against the clinic database. Fixtures and barrier files in `storage/app/qa/inventory/mysql` are ignored by Git and contain no database credentials.

Cases: competing FEFO issues, repeated reversal, repeated monthly finalization, repeated daily approval, duplicate movement references, a unit edit against first receipt, and two initial receipts into a previously missing balance. Workers call the real controller/service paths; an SQL listener observes application mutex acquisition and delays it briefly to induce contention. Assertions check non-overlapping critical sections, expected accept/reject outcomes, and aggregate/batch/ledger agreement. This is not an HTTP authentication test or an exhaustive load test. Nonzero exit means failure; cleanup messages alone are not proof of success.

