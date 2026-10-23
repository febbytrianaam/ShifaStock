# Testing Strategy

## Guidance Publication Verification

2026-10-23, approximately 15:45 WIB: the final combined guidance/tooltip feature tree again passed **179 tests / 1885 assertions**, about 50 seconds, on SQLite `:memory:`. Browser counts below are retained from their actual earlier same-day executions, not newly run at commit time. Publication includes the explanations, help controls, tests, README/current/operator guidance, and implementation reports; unrelated Capstone/private/ignored artifacts are excluded. [Publication record](../30_implemented/2026-10-23-issue-guidance/implementation_report.md#publication-verification).

## Medicine Issue Guidance

2026-10-23 WIB, working tree on `ba7045c`: the full isolated SQLite suite passed **179 tests / 1885 assertions**, and temporary-MySQL operational HTTP browser suite **17/17**. Two new rendered-page tests cover content/order/hints, Guest restrictions, and no viewing writes. One new browser case checks manual/no-Neoklinik copy, issued-not-remaining quantity, FEFO/posting explanation, desktop/tablet/phone geometry, and unchanged form defaults. Build, runner syntax, whitespace, reconciliation, and temporary-database cleanup passed; desktop/mobile full-page screenshots were opened. This does not rerun the separate 20-case daily-workspace suite, MySQL competing workers, or add a dedicated browser issue posting. [Evidence](../30_implemented/2026-10-23-issue-guidance/implementation_report.md).

## Daily Count Guidance

Tooltip follow-up on the same date: latest isolated SQLite run passed **177 tests / 1861 assertions** and actual HTTP workspace suite **20/20**. Added coverage verifies accessible non-submitting header controls, shared description targets, review meaning, hover/pointer persistence, keyboard/Escape/click-outside, mobile 320/390 px tapping/clamping, and unchanged quantities/POST count. Existing workspace geometry tests were retained, not relaxed. Build/syntax/whitespace, stock postconditions, cleanup, and desktop/phone visual review passed. The earlier 175/17 results below are the initial explanation stage. [Tooltip evidence](../30_implemented/2026-10-23-daily-count-guidance/implementation_report.md#follow-up-column-tooltips).

2026-10-23 WIB, working tree on `ba7045c`: isolated SQLite suite passed **175 tests / 1805 assertions**; actual HTTP workspace suite on temporary MySQL passed **17/17**. Three new rendered-page tests cover the explanation, default collapse/actual location, no inventory writes from viewing, and Guest restrictions. Two new browser cases cover guidance above the start form at desktop/tablet/phone sizes and native keyboard/mobile expansion without changing counts. Initial 320 px list-area failures were corrected through disclosure spacing, not weaker assertions. Build, CJS syntax, whitespace, stock postconditions, and temporary-database cleanup passed; responsive screenshots were opened. No new operational/MySQL-race/PDF/physical-printer test is claimed. [Details](../30_implemented/2026-10-23-daily-count-guidance/implementation_report.md).

## Shared Daily Draft Verification

Pre-publication rerun, approximately 23:30 WIB: the final feature tree again passed **172 tests / 1777 assertions**, about 51 seconds, on isolated SQLite. This is a fresh backend execution only; the 38 browser cases, ten MySQL races, and build below remain the earlier same-feature executions. Public login 419 triage used a fresh HTTPS context and one nonexistent account: CSRF round-trip passed, but no valid-user login or exact cause of the original report was established. No auth code was changed.

2026-10-22 WIB, completed 23:01, working tree on `af37ff08a23b84a710eaafddd3d1d28dec234878`: **172 PHPUnit tests / 1777 assertions**, **15 workspace + seven partial + 16 operational real HTTP browser cases**, and **ten MySQL competing-worker scenarios** passed. The new race starts petugas and apoteker on revision zero: exactly one succeeds, one is rejected, creator/actual winner remain correctly attributed, and no inventory movement occurs. Coverage includes both sharing directions, item attribution/no-op saves, stale save/add/submit/cancel, returned/locked reports, rollback, renamed actors, legacy unknown history, list cancellation version, and Guest/view-only restrictions. Build, changed-script syntax, whitespace, and read-only UAT isolation checks passed. Temporary databases were removed; clinic inventory fingerprint is unchanged. Activity/stale/reverse-direction screenshots and phone layout were opened for visual review. This supersedes earlier owner-only draft expectations; it does not grant new permissions. No new physical print/UAT operator acceptance or regenerated full PDF-pagination run is claimed. [Actual evidence and limitations](../30_implemented/2026-10-22-shared-daily-check/implementation_report.md).

## Final Workspace Publication Rerun

2026-10-22, completed 16:22 WIB: the final tree passed **157 tests / 1621 assertions** on isolated SQLite and **35 real HTTP browser cases** on temporary MySQL databases: **12 workspace**, **seven partial**, **16 operational**. Vite build, changed runner syntax checks, and whitespace checks passed. Reconciliation/stock postconditions passed and temporary databases were removed. No clinic or persistent UAT inventory fixtures were changed. Publication excludes private/ignored test evidence and unrelated Capstone artifacts. Selected-daily/PDF and MySQL competing-worker suites were not rerun. [Final verification](../30_implemented/2026-10-22-daily-check-workspace/implementation_report.md#publication-verification).

## Latest Full-Width Panel Verification

2026-10-22 WIB: inspected the user's saved UAT report in the actual in-app browser. The previous 1360 px cap caused 912 px of unused right space at 2560 px. After its removal, actual measurements showed zero right gap and no horizontal overflow at Full HD, QHD, tablet, and phone sizes. No UAT data submission; viewport reset afterward. Build and **12/12 temporary-MySQL workspace browser cases** passed again, now asserting table/workspace edge equality at desktop sizes through 3840 x 2160, plus the existing tablet/phone matrix. Stock postconditions/reconciliation passed and the temporary database was removed. Backend/partial/PDF results below are previous executions, not rerun for this CSS-only correction. [Evidence](../30_implemented/2026-10-22-daily-check-workspace/implementation_report.md#follow-up-actual-panel-and-full-width-table).

## Latest Responsive Daily Table Execution

2026-10-22 WIB: full isolated SQLite rerun passed **157 tests / 1621 assertions**, actual temporary-MySQL HTTP workspace suite **12/12**, and partial suite **7/7**. Workspace geometry is checked at twelve viewport sizes from 320 to 2560 px, with closed/expanded rows, long medicine/unit text, visible footer, no horizontal overflow or intersecting row regions, reachable detail inputs, and tablet/phone dialogs. The full page must retain its original width while only the table is capped and left-aligned. Responsive screenshots were opened for review; build and syntax/whitespace checks passed. Reconciliation passed and temporary databases were removed; no persistent UAT/clinic changes. Earlier operational/PDF totals remain historical for this follow-up. [Detailed evidence](../30_implemented/2026-10-22-daily-check-workspace/implementation_report.md#follow-up-responsive-table-only).

## Latest Daily Workspace Execution

2026-10-22 WIB, working tree on `5e86898`: actual isolated SQLite run passed **157 tests / 1621 assertions**. Temporary-MySQL HTTP browser runs passed **10/10 workspace**, **7/7 partial**, and **16/16 operational** cases. Workspace coverage includes 167 medicines, hidden-page input persistence through a real save/reload, zero/null progress, stable active input under filtering, Rincian and batch totals, tabs/Enter, unsaved-print feedback, validation focus, and owner/Guest restrictions. Desktop 1440 x 900, laptop 1366 x 768, and mobile 390 x 844 frame checks passed; list/detail screenshots were opened for review. Independent stock reconciliation passed, temporary databases were removed, and no persistent UAT/clinic fixtures were changed. Build and syntax/whitespace checks passed. Existing selected-daily/PDF and MySQL-race counts below are historical, not rerun for this UI change. [Detailed report](../30_implemented/2026-10-22-daily-check-workspace/implementation_report.md).

## Latest Partial Daily Submission Execution

2026-10-22 WIB: full isolated SQLite rerun passed **157 tests / 1621 assertions**, including ten partial-submission tests and read-only explanations. Actual temporary-MySQL HTTP browser runs passed **seven partial**, **15 existing daily**, and **16 existing operational** checks, with independent stock/batch/ledger postconditions and cleanup. The new partial suite covers cancellation, draft save from confirmation, mobile sending, pharmacist coverage confirmation, all-blank rejection, safe bounded warnings, and focus across pagination. A4 portrait PDFs were regenerated and parsed: one and eight pages, all 70 long-report codes, repeated headers, no orphan closing page; all nine rendered pages reviewed. Build/syntax/format checks passed. No operational UAT data or permissions changed. [Actual evidence and initial QA findings](../30_implemented/2026-10-22-partial-daily-submission/implementation_report.md). Earlier results below are stage-specific history.

## Latest Dashboard Widget Audit

2026-10-22, 14:20 WIB: a read-only restricted-UAT check compared actual dashboard controller output with independent database queries. All matched: ready verified masters 1, distinct stocked medicines 2, minimum alerts 1, stocked near-expiry batches 0, seven-day receipt references 1 and transfer references 4, balance preview 4 rows, administrator accounts 4/active 4/supported roles 4. These are simulation snapshots, not fixed expected inventory values.

Focused isolated backend rerun: **25 tests / 254 assertions passed** across `DashboardOnboardingTest`, `DashboardStockMonitoringTest`, and `CapstoneFunctionalTest`. JUnit evidence stays ignored at `storage/app/qa/inventory/dashboard-widget-audit.xml`. No application code or persistent data changed during this audit; Playwright was not rerun for the audit itself. The stock-table search covers only its 12-row preview, and Tersedia means positive stock rather than above minimum. [Audit details](../30_implemented/2026-10-22-dashboard-stock-monitoring/implementation_report.md#follow-up-audit-of-other-widgets).

## Latest Dashboard Minimum Monitoring Execution

2026-10-22 WIB: isolated SQLite suite passed **147 tests / 1533 assertions**. Six new dashboard regressions cover actual balances without daily approval, stale history, minimum equality/location separation, absent balance/zero minimum, missing configuration, and Guest read-only access. One actual posting workflow verifies dashboard and Monitoring after draft/post reception, transfer, issue, and restock. Existing 12-row preview coverage now uses minimum rules instead of historical checks.

Real HTTP Playwright operational regression passed **16/16**, including desktop/mobile dashboard, filtered medicine/location links, and updated Apotek quantity after a simulation posting. Independent stock and batch/ledger reconciliation postconditions passed, and the temporary MySQL database was removed. Desktop/mobile screenshots were opened for visual review. Build, focused Pint, Node syntax, and whitespace checks passed. The read-only persistent UAT comparison at 13:54 WIB returned **one alert from both dashboard and Monitoring, with zero approved daily reports**. No persistent fixtures or permission changes. [Details and evidence](../30_implemented/2026-10-22-dashboard-stock-monitoring/implementation_report.md). Older results below remain stage-specific history.

## Latest Portrait Print Execution

2026-10-22, approximately 08:45 WIB: actual full isolated SQLite rerun passed **140 tests / 1450 assertions**; actual HTTP daily browser rerun passed **15/15**, including portrait CSS, standalone printing, unsaved-input protection, and independent stock/reconciliation postconditions. Temporary browser MySQL data was removed; persistent UAT/clinic data was not used for fixtures.

Actual Chrome PDFs measured approximately **595 x 842 pt**: one approved page and eight pages for the 70-item draft. pypdf verified all medicine codes, repeated headers, no application controls, and no orphan closing page. All nine Poppler-rendered pages were opened and visually inspected. Evidence: ignored `storage/app/qa/selected-nightly-browser/`, with `Laporan_Pendek_Portrait-1.png` and `Laporan_Panjang_Portrait-1.png` through `-8.png`. Full details and the initial CSS-serialization assertion finding are in [the print report](../30_implemented/2026-10-21-daily-check-print-report/implementation_report.md). No physical-printer test performed; the earlier operational/browser concurrency and build results below are historical.

## Historical Landscape Print Execution

2026-10-21 WIB: complete isolated SQLite suite passed **140 tests / 1447 assertions**, including seven new print cases (standalone structure, saved batch/zero data, escaping, true approval/reviewer state, RBAC/login/404, recorded units, and unpaginated report items). The actual HTTP daily browser suite passed **15/15** checks, now including dirty-print SweetAlert, real separate-tab report navigation and PDF generation, and a 70-item saved draft whose masters were later deactivated.

Headless Chrome generated actual A4-landscape PDFs: approved report **one page**, long draft **seven pages**. pypdf verified page dimensions, all 70 medicine codes, repeated table headings, absence of application controls, and no orphan closing page. Poppler rendered the short document and all seven long pages; each was opened and visually inspected. Evidence stays ignored under `storage/app/qa/selected-nightly-browser/`. Build/Blade and UAT isolation passed. See [the print report](../30_implemented/2026-10-21-daily-check-print-report/implementation_report.md) for actual initial QA findings and limitations. Earlier suite totals below are historical.

The existing `regression` browser phase was also rerun for this print follow-up: **13/13 operational cases passed**, including its independent Gudang/Apotek stock and reconciliation postconditions. The final UAT isolation check at 16:16 WIB still passed; no persistent stock or user-managed access setting was changed.

## Latest Draft Addition Execution

2026-10-21 WIB: complete isolated SQLite execution passed **133 tests / 1392 assertions**, including nine new add-to-draft cases. The first execution hit the runner's 180-second limit; a process-only timeout increase to 900 seconds allowed a complete passing rerun in about 33 seconds. The incomplete run is not a passing result.

The isolated `selected-nightly` Playwright phase passed **12/12** checks: original eight flows plus searchable addition, X/Escape/Enter behavior, visible empty-selection SweetAlert, unsaved input preservation, stable item identity, blank new rows, and mandatory counting before submission. Desktop/mobile screenshots were opened and inspected. Independent PHP postconditions verify two approved items and consistent stock/ledger quantities. Build, Blade compilation, and UAT isolation passed. Ignored evidence: `storage/app/qa/selected-nightly-browser/` and `storage/app/qa/inventory/phpunit-results.xml`. No real inventory, persistent UAT data, or user-modified permission matrix was reset. [Report](../30_implemented/2026-10-21-add-medicines-to-draft/implementation_report.md). Earlier stage results below remain historical.

`php scripts/test-inventory-mysql.php` was rerun for this follow-up: all **nine existing competing-process scenarios** passed, including daily approval, inventory references, FEFO, and multi-item transfer rollback. These are existing concurrency regressions, not a newly added two-operator draft-edit race scenario.

## Latest Selected Daily Check Execution

2026-10-21 WIB: after the count-unit dropdown follow-up, the full isolated SQLite suite passed **124 tests / 1306 assertions**, including twelve selected-check tests. The added unit cases cover master-only selection, active references/no guessing for an incomplete master, the existing positive-count guard, and mismatched old drafts. `php scripts/capstone-final-browser.php selected-nightly` verifies **8 real HTTP Playwright cases** with a temporary MySQL database: selection/search, master unit dropdown/draft persistence, SweetAlert, draft/submit, explicit batch zero/requested quantity, pharmacist approval, mobile layout, and Guest restrictions. Independent postconditions verify Apotek 20 to 0, Gudang unchanged at 50, one approved item, and consistent total/batch/ledger. Ignored evidence: `storage/app/qa/selected-nightly-browser/`.

The existing `regression` browser phase passed **13/13** cases and `php scripts/test-inventory-mysql.php` passed **nine concurrent-worker scenarios** in this feature verification. Vite build/Blade compilation passed. UAT verification at 15:07 WIB confirmed clinic inventory unchanged and UAT grants isolated. Scope migration applied through the UAT runner; no persistent fixtures were inserted/seeded/cleared. [Details](../30_implemented/2026-10-21-selected-daily-check/implementation_report.md). Earlier totals below remain stage-specific history.

## Latest Multi-Item Transfer Execution

2026-10-21 WIB: full SQLite suite passed **112 tests / 1189 assertions**. `MultiTransferTest` adds coverage for multi-medicine FEFO/explicit batches, atomic rollback, repeated medicines/batches, invalid medicine/location/expiry, blank/partial/malformed/oversized rows, quantity precision, duplicate references, stock cards/dashboard counting, legacy payloads, and Guest restrictions.

`php scripts/test-inventory-mysql.php` passed **nine concurrent-process scenarios**, including two new races: a second-item shortage must roll back the losing multi-transfer, and a repeated multi-transfer reference may post only one complete transaction. The earlier seven scenarios also passed in this run.

With Playwright available through Node resolution, `php scripts/capstone-final-browser.php transfer` creates a random isolated MySQL database and exercises the actual new form. **Nine browser checks passed** at desktop 1440 x 900 and mobile 390 x 844; final aggregate/batch/ledger quantities were independently checked afterward. Evidence: ignored `storage/app/qa/multi-transfer-browser/`. `php scripts/capstone-final-browser.php regression` also passed all **13 operational browser cases**, stored under ignored `storage/app/qa/inventory-browser/`. Both temporary databases were removed; no fixture data was sent to persistent UAT or clinic databases. Build and Blade cache compilation passed. [Report](../30_implemented/2026-10-21-multi-item-transfer/implementation_report.md).

## Latest UAT Domain Execution

2026-10-21 WIB: after changing the port-4002 service to isolated UAT and enabling HTTPS proxy handling, the full SQLite suite passed **100 tests / 1001 assertions**. New regression cases cover validated listen ports, mandatory secure cookies for HTTPS UAT, trusted loopback versus external proxies, and ignored forwarded host/port values. HTTP checks confirmed the actual public domain serves the UAT login with secure cookies and HTTPS links.

An actual Playwright run against the public HTTPS domain passed eight checks: UAT login page, Guest login, empty reception/transfer charts, secure HttpOnly UAT session, denied admin route, accessible medicines, logout, and no browser errors. No inventory POSTs were issued; normal login/logout audit activity was recorded. Private evidence: `storage/app/qa/uat-public/results.json`. Clinic isolation verification and zero UAT movements were checked afterward. This is not a production deployment or manual staff acceptance. Previous test totals below remain historical.

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

