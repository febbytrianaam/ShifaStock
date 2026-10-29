# Current State

- Import publication package (2026-10-29): Excel importer, guided UI, source detail, readable confirmation warnings, preview deletion, manual-tab resumption and operator documentation are being committed together from base `2d2e8ef`. Fresh runs on 29 October passed 213 tests / 2347 assertions, 16 browser groups, MySQL competing workers, frontend build, Composer validation, formatting and whitespace checks. Source Excel, credentials, QA output, unrelated Capstone changes and local analysis notes are excluded from the package. Historical uncommitted notes below describe earlier stages; use Git history for the publication commit. [Verification](../30_implemented/2026-10-27-issue-import-guided-ui/implementation_report.md#publication-verification-29-october-2026).

- Resume import after manual-tab navigation (2026-10-27): opening a valid preview remembers its document/step for the current user session. The manual-page import tab returns to that same preview, and manual/upload pages offer **Lanjutkan pemeriksaan** rather than requiring a new file. Only valid unposted previews resume; expired/deleted/posted documents and another user's or Guest's pointer are excluded. Existing unsaved-form SweetAlert is verified, not bypassed; unsaved values are not autosaved. Fresh verification: 213 tests / 2347 assertions, 16 browser groups, MySQL workers, build, Pint and whitespace checks passed. [Details](../30_implemented/2026-10-27-issue-import-guided-ui/implementation_report.md#follow-up-resume-after-switching-to-manual-input).

- Explicit import confirmation guidance (2026-10-27): the ambiguous medicine/unit warning now says **Pilihan obat dan satuan perlu dikonfirmasi**, with select/check-unit/centang Benar/save instructions, a one-BUTIR/one-Tablet example, automatic-suggestion clarification and saved-master-change caution. Matching distinguishes an unconfirmed suggestion from a saved choice needing review; source statuses expose expandable **Cara memperbaiki**. No inventory, approval, or permission rules changed. Fresh verification: 210 tests / 2291 assertions, 15 browser groups, MySQL workers, build, Pint and whitespace checks passed. [Details](../30_implemented/2026-10-27-issue-import-guided-ui/implementation_report.md#follow-up-explicit-confirmation-guidance).

- Clinic-friendly import detail follow-up (2026-10-27, same working tree): matching/review lists include stable group numbers and actual sale dates/times in WIB, with first/last ranges for multi-date groups. Expandable full-width source tables show original Excel row numbers, sale times, medicine code/name, original quantity/unit, racikan yes/no and readable status; no patient/doctor/raw sale/racikan identifiers are added. Local medicine search preserves selections, filters matching detail rows together, and bulk approval only affects visible choices. Mobile source details use the main list scroller without an extra vertical scroll. Fresh verification: 209 tests / 2266 assertions, 15 browser groups, MySQL workers, build, Pint, JS syntax and whitespace checks passed. [Details](../30_implemented/2026-10-27-issue-import-guided-ui/implementation_report.md#follow-up-excel-details-and-clinic-friendly-search).

- Compact import top area (2026-10-27, same working tree): draft matching/review now begin directly below the issue tabs. Document identity, expandable period/file counts, compact step navigation, history/deletion, and status/validation messages live in the right information rail; matching instructions are expandable. Posted/expired document headers remain unchanged. The mapping save button uses native form association, separate from the deletion form. Fresh verification: 208 tests / 2214 assertions, 13 browser groups across eight widths, MySQL workers, build, Pint and whitespace checks passed. [Details](../30_implemented/2026-10-27-issue-import-guided-ui/implementation_report.md#follow-up-compact-top-area).

- Import side-panel follow-up (2026-10-27, same working tree): matching and final review now place the medicine list left and guidance/summary/actions right at desktop widths of 1280 px and above. Available list/panel height follows actual page position; scrolling a long list does not move the matching save command. Smaller screens stack without horizontal overflow. Repeated per-row guidance moved to the panel. Fresh verification: 207 tests / 2196 assertions, 13 browser groups including a 48-medicine fixture across eight widths (320-2560 px), MySQL competing workers, frontend build and Pint passed. No persistent inventory changes. [Details](../30_implemented/2026-10-27-issue-import-guided-ui/implementation_report.md#follow-up-list-left-information-right).

- Guided issue-import UI (2026-10-27, working tree on `2d2e8ef`): upload, explicit medicine/unit matching, and final review now occupy separate steps. Valid saved mappings are hidden by default, conversions use a concrete one-source-unit phrase, and optional bulk confirmation never approves empty selections or survives a medicine/factor edit. Grouped warnings explain the next action; review filters do not exclude transactions from posting. Mobile rows stack without horizontal table overflow. Cutoff, FEFO, atomic posting, duplicate protection, required attestations, and permissions remain unchanged. Actual fresh verification: 207 tests / 2196 assertions (including 28 import tests / 311 assertions), 12 browser groups, MySQL concurrency checks, build and Pint passed. Synthetic isolated databases only; no clinic workbook imported or persistent stock changed. [Report](../30_implemented/2026-10-27-issue-import-guided-ui/implementation_report.md).

- Import-preview deletion follow-up (2026-10-24, working tree): trash actions on issue-import history/detail use SweetAlert and existing operational permission. Only unposted draft/expired previews can be deleted; locked fresh-state checks protect posted/duplicate-only history, stock, source ledger, mappings, and recording policy. Deletion is audited; Guest is denied. Actual verification: 24 import tests / 266 assertions, full suite 203 tests / 2151 assertions, ten browser groups, MySQL workers, build and Pint passed. No UAT/clinic data deleted. [Details](../30_implemented/2026-10-24-neoklinik-issue-import/implementation_report.md#follow-up-hapus-pratinjau-impor).

- Excel issue import (2026-10-24, working tree on `2d2e8ef`): Pengeluaran Obat now includes XLSX upload, explicit medicine/unit mapping, per-drug preview with expandable rows, atomic FEFO posting, immutable recording boundary per location, duplicate/changed-source protection, encrypted inventory-only snapshots, history/audit/card links, and Guest read-only access. No live API integration or clinical-file posting. Additive tables migrated to persistent UAT only; the missing-table 500 was verified fixed with an authenticated read-only HTTP 200. Actual verification: 199 tests / 2087 assertions, 20 import tests / 202 assertions, eight Playwright groups across four viewports, MySQL competing workers, and build passed. Clinical export semantics/racikan/source identity and product mappings remain operator-confirmed, not clinically certified by these tests. [Report](../30_implemented/2026-10-24-neoklinik-issue-import/implementation_report.md).

- Operational guidance publication package (2026-10-23): daily start/detail explanations, accessible header tooltips, issue-page manual-recording guidance, README/operator guidance, and regression tests are being published together at the user's explicit request. This supersedes the earlier uncommitted status below; use Git history for the publication commit. Prior stage evidence is retained without relabeling browser executions as fresh. No backend stock rules or permission grants changed; unrelated Capstone/private artifacts are excluded.

- Medicine issue guidance (2026-10-23, uncommitted on `ba7045c`): Pengeluaran Obat explains standalone/manual recording (no Neoklinik sync), issued versus remaining quantity, location/unit, automatic/optional references, FEFO, immediate posting, duplicate risk, and transfer/return boundaries above its form. Two fields have accessible hints; stock/permission rules are unchanged. Actual runs: 179 tests / 1885 assertions, 17 operational browser cases with desktop/tablet/phone guidance, and build passed. [Report](../30_implemented/2026-10-23-issue-guidance/implementation_report.md).

- Daily column-help follow-up (2026-10-23, uncommitted): main and pharmacist-review headings provide native top-layer hover/focus/click descriptions; compact layouts expose equivalent buttons within the existing collapsed guide. Help controls are non-submitting, use shared descriptions and Lucide, support Escape/outside dismissal, and do not change count inputs or grid geometry. Actual latest verification: 177 tests / 1861 assertions, 20 workspace browser cases, and build passed. [Details](../30_implemented/2026-10-23-daily-count-guidance/implementation_report.md#follow-up-column-tooltips).

- Daily-count guidance (2026-10-23, working tree on `ba7045c`): the daily start page explains initial stock versus system stock versus physical counts before the form, with location/unit, zero/unchecked, approval timing, and a system-10/physical-2 example. The generic description is hidden to avoid duplication. The counting workspace has a native collapsed explanation using its actual location; existing inventory/permission/print rules are unchanged. [Report](../30_implemented/2026-10-23-daily-count-guidance/implementation_report.md).

- Shared daily drafts (2026-10-22, publication package based on `af37ff0`): petugas and kepala apoteker with existing operational permission can continue each other's drafts, add medicines, and submit. Creator identity remains unchanged; per-item last-editor snapshots and a paginated Activity tab distinguish actual workers from creator/sender/reviewer. Versioned locked writes reject stale save/add/submit/cancel without overwriting newer work. Cancel remains creator-only, review remains privileged, Guest remains read-only, and submitted/approved reports stay locked. Additive tracking migration applied to UAT only, without resetting tester data or permissions. Earlier owner-only edit instructions below are historical and superseded. [Report](../30_implemented/2026-10-22-shared-daily-check/implementation_report.md).

- Daily-workspace publication verification (2026-10-22, 16:22 WIB): fresh final-tree executions passed 157 tests / 1621 assertions, 12 workspace + seven partial + 16 operational browser cases, frontend build, and syntax/whitespace checks. This package includes compact input/Rincian, responsive full-width table behavior, adapted isolated runners, README, and UAT operator guidance. No inventory/permission changes; unrelated Capstone artifacts and private/ignored QA outputs are excluded. [Publication results](../30_implemented/2026-10-22-daily-check-workspace/implementation_report.md#publication-verification).

- Full-width table correction (2026-10-22): actual in-app UAT inspection found the previous 1360 px table cap left 912 px unused on a 2560 px viewport. That cap is now removed; page padding/other sections are unchanged. Live Full HD/QHD/tablet/phone measurements show table and workspace widths match, without horizontal overflow. Build and 12 workspace browser cases passed again, now enforcing full-width coverage through 3840 x 2160. No UAT form submitted; viewport override reset. Earlier capped-table entries below are historical. [Report](../30_implemented/2026-10-22-daily-check-workspace/implementation_report.md#follow-up-actual-panel-and-full-width-table).

- Responsive daily-table follow-up (2026-10-22): preserve full-width page/header/toolbar/footer; cap only the left-aligned table at 1360 px and fill it with proportional columns. Container-based stacked rows account for tablet sidebar width; phone count/unit fields get a full row and the add command becomes an accessible plus icon. Actual reruns: 157 tests / 1621 assertions, 12 workspace browser cases across 12 viewport sizes (320-2560 px), seven partial cases, and build passed. No persistent data changes. [Details](../30_implemented/2026-10-22-daily-check-workspace/implementation_report.md#follow-up-responsive-table-only).

- Daily-check UI overhaul (2026-10-22, working tree on `5e86898`): compact medicine/system/physical-count list, per-medicine Rincian, default ten-row pagination, live examined/unsaved indicators, stable editing under filters, and counting-view save/send controls outside the list scroller. Pharmacist review and saved summary use separate accessible tabs. All inputs remain submitted across pages; owner/RBAC, partial confirmation, batch/stock safeguards, and A4 print output remain unchanged. Actual verification: 157 tests / 1621 assertions, ten new UI + seven partial + 16 operational browser checks, build and desktop/mobile visual review. No persistent fixture changes or publication. [Report](../30_implemented/2026-10-22-daily-check-workspace/implementation_report.md).

- Partial daily submission (2026-10-22): complete and selected lists can be saved as incomplete drafts or explicitly submitted with only counted medicines. `confirm_partial` is required for mixed reports; all-blank submission is rejected. Null rows remain unexamined, never zero; only non-null counts receive snapshots, approval, stock/batch/ledger changes, or enter count history/periodic recaps. Original scope/list retained. Grouped escaped warnings limit examples and reveal the first bad row across pages. Read-only messages explain ownership/status/permission without relaxing them. Verification: 157 tests / 1621 assertions, seven partial + 15 daily + 16 operational browser checks, build, reconciliation, and A4 portrait PDF review. [Report](../30_implemented/2026-10-22-partial-daily-submission/implementation_report.md).

- Dashboard widget audit (2026-10-22): every widget's controller output matched independent read-only UAT queries; no additional dummy/disconnected counter found. Focused rerun: 25 tests / 254 assertions passed. Distinguish unique stocked medicines, medicine/location minimum alerts, and per-reference chart transactions. The stock table is a 12-row positive-balance preview; its client-side search is limited to those rows and Tersedia does not imply above minimum. No feature or persistent-data changes in this audit. [Details](../30_implemented/2026-10-22-dashboard-stock-monitoring/implementation_report.md#follow-up-audit-of-other-widgets).

- Dashboard minimum-stock alignment (2026-10-22): Kosong / Menipis and Perlu Ditindaklanjuti now share `MinimumStockAlerts` with Monitoring's current-stock table, not approved daily snapshots. Configured medicine/location pairs at or below minimum appear even before any approved daily check; unconfigured masters are not classified. Eight preview rows, uncapped total, explicit source/minimum/unit/location, and filtered drill-down are included. Historical physical-count monitoring stays separate; no inventory, FEFO, schema, or permission changes. Verification: 147 tests / 1533 assertions, 16 real browser cases, build, visual review, and a read-only UAT match (one alert, zero approved reports). [Report](../30_implemented/2026-10-22-dashboard-stock-monitoring/implementation_report.md).

- Dedicated daily-check print report (portrait follow-up, 2026-10-22): Cetak / Simpan PDF opens an authenticated, standalone A4-portrait report with a matching 210 mm preview, not the operational page. Saved items, units, results, requests, notes, and batch counts print without UI pagination/filters; draft/partial/UAT labels remain explicit. Dirty drafts receive a SweetAlert reminder to save first. Latest actual verification: 140 tests / 1450 assertions, 15 daily browser checks, and one-page/eight-page PDFs with all 70 long-report medicines present; all nine rendered pages reviewed. Earlier build/Blade and UAT-isolation results are historical. No persistent inventory or permissions changed. [Report](../30_implemented/2026-10-21-daily-check-print-report/implementation_report.md).

- Add medicines to daily drafts (2026-10-21): the draft owner can use Tambah Obat to search/select additional eligible masters without cancelling. Current unsaved quantity, unit, batch, flag, requested quantity, and note inputs save atomically with blank new rows; no stock write occurs. Duplicate rows and stale/ineligible requests are protected, and submitted/approved reports stay locked. No schema, FEFO, or RBAC changes; preserve the user's superadmin-managed Stok Awal access. Latest verification: 133 tests / 1392 assertions and 12 daily browser checks passed; build/Blade and UAT isolation passed. [Report](../30_implemented/2026-10-21-add-medicines-to-draft/implementation_report.md).

- Selected daily checks (2026-10-21): operators can start a complete report or select medicines through a searchable checkbox list. Only report medicines must be counted and can be adjusted on approval. Satuan Hitung is a master-linked dropdown with automatic stock-unit selection and warnings for incomplete masters or mismatched old drafts. Batch/snapshot/ownership/RBAC checks and one-report-per-date/location remain. Dashboard, monitoring, and comparison resolve the last approved count per medicine/location, preserving other results after partial checks. Additive scope migration applied to UAT only, preserving tester data. Latest combined suite: 124 tests / 1306 assertions passed; selected-check browser eight cases, operational browser 13 cases, nine MySQL races, build/Blade and isolation verified. [Report](../30_implemented/2026-10-21-selected-daily-check/implementation_report.md).

- Multi-item transfers (2026-10-21): spreadsheet-style transfer rows with searchable medicine/batch, per-medicine stock units and FEFO hints, one reference/source/destination, add/remove controls, SweetAlert validation/confirmation, and row-specific backend errors. All declared items post in one inventory transaction with reference recheck; a later-item failure rolls back earlier rows. Legacy single-item payloads and Guest read-only access remain supported. No schema or inventory-algorithm change. Actual results: 112 tests / 1189 assertions; nine MySQL races; nine transfer browser checks; 13 operational browser regressions; Vite and Blade compile passed. Persistent UAT tester data was not seeded or cleared. [Implementation report](../30_implemented/2026-10-21-multi-item-transfer/implementation_report.md).

- UAT domain routing (2026-10-21, user-requested): `https://shifastock.nexspace.my.id` now reaches the isolated UAT runner on loopback port 4002 instead of the normal clinic-configured artisan server. Dedicated UAT credentials/database, debug-off mode, secure HTTPS cookies, and local-proxy-only protocol handling are active. The main `.env` and clinic database remain unchanged. Latest full suite: 100 tests / 1001 assertions passed; eight actual public-domain browser checks passed without inventory writes. Restart with `php scripts/uat.php serve`, not plain artisan on port 4002. [UAT guide](uat_guide.md).

- Local UAT data cleanup (2026-10-21, user-requested): at cleanup time four accounts, 167 medicine masters, units, locations, permissions, and supporting configuration were preserved; simulated operational/audit rows were cleared after a verified private backup. Masters/accounts were fingerprint-compared unchanged; clinic inventory isolation passed. Testers have since entered new transactions: do not treat UAT as still empty or clear/reseed it for QA. Earlier seeded 25-case browser results remain historical and fixture-dependent. [Current UAT guide](uat_guide.md).

- Friendly error pages (2026-10-21): custom Indonesian 403, 404, and 500 views with clinic identity, safe GET navigation, administrator guidance, and duplicate-transaction caution for server errors. The views require no database, session, JavaScript, or Vite manifest. JSON errors and developer debug behavior are unchanged. Actual verification: 97 PHPUnit tests / 974 assertions, seven isolated browser checks across desktop/mobile, live UAT 404 verification, and frontend build passed. [Implementation report](../30_implemented/2026-10-21-friendly-error-pages/implementation_report.md).

- Isolated UAT and combined read-only Guest (2026-10-21): persistent `shifastock_uat` database with a schema-limited MySQL account, dedicated runtime/session/cache/log/files, 167 copied Excel masters, simulated inventory and accounts, and no clinical-account/transaction cloning. Guest can see all operational views, including opening balances and monitoring parameters, but cannot gain write or superadmin privileges through the matrix. [UAT guide](uat_guide.md), [implementation report](../30_implemented/2026-10-21-uat-guest/implementation_report.md). The local clinic inventory fingerprint remains unchanged; manual clinic UAT is still pending.

- Dashboard and first-login guide (2026-10-21): dashboard reads only persisted data, without demo mode. The role-aware spotlight guide has Lewati/Kembali/Selanjutnya/Selesai, account-level persistence, and a replay button in the topbar. A reproduced positioning bug was corrected: the welcome step is now centered without targeting the entire dashboard; later steps keep their menu spotlight. Latest verification after this fix: 97 PHPUnit tests / 982 assertions and nine isolated onboarding browser checks passed, including short screens and resize. Authorized local cleanup preserved 167 Excel-sourced medicines and removed six seed medicines plus three reviewed test movements, related balances/batches, and unfilled drafts after a private backup. This cleanup was local, not a production deployment. [Implementation report](../30_implemented/2026-10-21-dashboard-onboarding/implementation_report.md).

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
- Login password visibility uses an accessible Lucide eye toggle; the field is masked by default and before submission. The button never submits the form and is hidden when JavaScript is unavailable.
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

- Use `php scripts/test-inventory.php` for isolated SQLite testing, rather than relying on the configured clinic database.
- Dashboard has no simulated mode. Zero stock and empty activity after cleanup are expected until actual opening balances or transactions are recorded.
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

