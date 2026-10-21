# Multi-Item Stock Transfer Implementation

Date: 2026-10-21, Asia/Jakarta.
Tested source before publication: working tree based on commit `25835db`, including the UAT port/domain follow-up. These changes are included with the selected daily-check publication requested afterward; see Git history for the publication commit. Stage-specific verification below remains historical; the latest combined suite is recorded in the current testing strategy.

## Behavior

- Transfer input is now a spreadsheet-style list. Source, destination, time, reference, and note apply to all rows. Each row has its own searchable medicine, optional searchable batch, quantity, stock unit, available batch stock, and FEFO estimate.
- Add/remove controls retain existing row selections and use Lucide icons. The form starts with two rows; entirely blank spare rows are ignored. Partially entered rows must be completed; maximum 50 rows per request.
- Batch options follow the row's medicine and shared source location, including the same batch number/ID at multiple locations. Floating dropdowns use the existing receipt positioning logic to avoid table clipping.
- Shared stock units are read from medicine master data; quantities are not summed across different units. Batch selection remains optional; automatic FEFO can split one medicine across several batch movements.
- SweetAlert identifies invalid rows and confirms stock movement. Backend errors include the row number and retain submitted input. A double-click is blocked while awaiting confirmation/submitting; backend reference checks remain authoritative.
- Existing history remains movement-level, with one shared reference and explicit batch labels, plus source/destination stock-card links. Dashboard transfer counting already counts unique references and remains one document even when FEFO creates several movements.

## Atomicity And Compatibility

`InventoryController::storeTransfer` accepts the new `items[]` shape and retains legacy top-level medicine/batch/quantity requests for existing callers. It validates active distinct locations and row fields, then uses the existing `InventoryTransaction` mutex for the entire operation. Reference availability, medicine usability, active locations, explicit batch ownership/expiry/source availability, and FEFO allocations are checked under that transaction.

Repeated medicine or batch rows consume the remaining stock sequentially. An error on any later row rolls back total stocks, batch stocks, all earlier movements, and audit writes. One audit event records the declared item count and per-item allocations. No FEFO algorithm, precision rule, permission, schema, or clinical data migration was changed. Reversal remains per movement, as before; no document-level draft/cancel/reversal feature is implied.

## Changed Areas

- Backend: `InventoryController::storeTransfer` only; unrelated automatic formatter changes were removed.
- Blade: transfer view and new `components/transfer-row.blade.php`; contextual page description and onboarding text.
- JavaScript: new `transfer-rows.js`, scoped initialization in `app.js`, receipt-compatible floating dropdown positioning, shared/external stock-context data support.
- CSS: scoped transfer table styles. Positioned table cells keep screen-reader-only labels inside the table scroll container on mobile.
- QA: `MultiTransferTest`, nine-scenario MySQL runner, isolated transfer phase in `capstone-final-browser.php`, and `test-multi-transfer-browser.cjs`.

## Actual Executed Results

| Check | Result | Evidence |
| --- | --- | --- |
| Full SQLite PHPUnit suite | 112 tests, 1189 assertions passed | `storage/app/qa/inventory/phpunit-results.xml` |
| Concurrent MySQL workers | Nine scenarios passed, including whole-transfer rollback and duplicate-reference races | Actual runner output; private `storage/app/qa/inventory/mysql/` worker evidence |
| New transfer browser suite | Nine checks passed; desktop 1440 x 900 and mobile 390 x 844 | `storage/app/qa/multi-transfer-browser/results.json` |
| Existing operational browser suite | 13 cases passed | `storage/app/qa/inventory-browser/Browser_final.json` |
| Independent browser stock check | Medicine A: Gudang 8, Apotek 72; medicine B: Gudang 13, Apotek 7; zero reconciliation mismatches | Actual PHP fixture runner postconditions |
| Frontend build | Passed | `npm run build` execution |
| Blade compilation | Passed | `php artisan view:cache` execution |
| Clinic/UAT separation | Clinic fingerprint unchanged, UAT MySQL grant limited, clinic read denied | `php scripts/uat.php verify`, ignored `storage/app/qa/uat-isolation.json` |
| Public-domain form smoke check | Real staff login, UAT banner, initialized two-row form and add-row icon verified over HTTPS; zero transfer POSTs, then logout | Actual local Playwright execution against the UAT domain; credentials not printed |

The browser suite checks independent item selection, add/remove preservation, source-dependent batch labels, SweetAlert with no invalid POST, a successful two-medicine/three-allocation transfer, both stock cards, failed-second-row feedback/input retention, mobile overflow/dropdown geometry, and Guest read-only enforcement. It sends actual HTTP requests to the isolated application, without intercepting successful writes.

The fixture runner creates a random MySQL database, uses generated credentials through process environment, checks final total/batch/ledger consistency, and removes the temporary database afterward. The persistent UAT has tester-entered transactions; these were not cleared, seeded, or used as fixtures. No credentials or operational screenshots are added to Git.

## Issues Found During Verification

- The first PHP fixture incorrectly tried to create opening stock through a Petugas-only session; fixture setup now uses the authorized pharmacist role and asserts redirects before running staff transfers. Permissions were not loosened.
- One same-process PHP view assertion did not observe flashed error text; the test now checks the actual session row-error message and retained values. The real HTTP browser independently verifies both the row message and restored input.
- The first mobile browser run found 580px document width at a 390px viewport. Absolutely positioned screen-reader labels had an outer containing block. Scoping the cells as positioned elements fixed this; the final browser run confirms the page does not expand horizontally while the table scrolls internally.
- Initial failures are not counted as passing executions. The final totals above come from completed reruns. `Kendala_Transfer.png` is diagnostic history, not successful evidence.

Final screenshots (`Transfer_Multi_Desktop.png`, `Transfer_Multi_Berhasil.png`, `Transfer_Multi_Gagal_Atomic.png`, `Transfer_Multi_Mobile.png`) were opened and visually inspected for readable labels, uncropped dropdowns, retained rows/errors, and responsive layout. They are ignored local QA evidence only.

## Limitations

- Formal staff acceptance is still pending.
- Per-row availability and FEFO hints are estimates from page-load data, not reserved stock. The backend validates cumulative availability again at save time, including repeated medicines/batches.
- No transfer draft, document-level cancellation, new public endpoint, or automatic database reset was added. UI changes are available through the existing UAT server after refreshing built assets.
