# Add Medicines To Daily Drafts

Date: 2026-10-21, Asia/Jakarta. Tested working tree based on `77ecbd2`. Publication authorized on 2026-10-22 together with the portrait print report and dashboard monitoring fix; use Git history for the publication hash. The results below remain the actual stage-specific executions, not tests rerun at publication time.

## Operator Flow

Open your draft in Pemeriksaan Stok > Harian, choose **Tambah Obat**, search by medicine name/code, check one or more additional medicines, and choose **Tambahkan ke Pemeriksaan**. Existing form inputs are saved as a draft in the same request; added medicines start with blank quantities and their master stock units. Complete the new counts before submitting to the pharmacist.

Closing with X, Batal, or Escape does not save, alter existing fields, or add items. Search does not clear selected medicines. Empty selection uses SweetAlert and returns to the picker. Enter in the search field does not unexpectedly submit the underlying count form. The dialog is bounded and scrollable on desktop/mobile. When all eligible masters are already included, the add button is disabled.

## Backend And Safety

- The existing `nightly.save` endpoint accepts `action=add`; its route permissions are unchanged. The same form includes current `items` and the existing JSON batch-count payload, avoiding a separate navigation that would discard unsaved input.
- The existing inventory transaction/mutex and locked report row protect the save and append together. Only the draft owner with operational access can add; another operator, pharmacist who is not the owner, and Guest are denied. Submitted/approved reports cannot be extended. A submitted report returned through the existing correction workflow is a draft again and can be extended by its owner.
- Eligibility remains active medicines or masters with import-source metadata. Existing medicines are excluded from the picker; backend filtering and the report/medicine uniqueness constraint prevent duplicate rows.
- Existing IDs, quantities, units, batch counts, flags, requested quantities, and notes are preserved from the submitted form. Invalid selection/count data or a stale tab missing a current item causes rollback, not partial saving. Errors retain input through normal Laravel validation.
- New items have null counts, not inferred zero. All report items must be completed before submission. Adding saves a draft only: no stock totals, batch balances, movements, FEFO allocations, or approvals are changed.
- Complete/selected scope and one report per date/location remain unchanged. No removal of existing medicines, replacement of an approved report, schema migration, or RBAC default/reset was added. The user's superadmin-managed Stok Awal permission remains untouched.

## Changed Areas

- `NightlyCheckController`: available master query and atomic `add` branch in draft saving.
- Nightly show/index views: add dialog, feedback, and guidance that no longer tells operators to cancel merely to add a medicine.
- `resources/js/daily-counts.js`: dialog/search/cancel controls, icons, and SweetAlert handling that remains visible outside the native dialog's top layer.
- `AddNightlyMedicinesTest`: nine new cases; existing selected-check assertion now distinguishes actual report rows from masters in the hidden add picker.
- Isolated selected-check browser suite extended from eight to twelve checks; PHP postconditions now verify the approved two-item report.

## Actual Verification

| Check | Actual Result | Evidence |
| --- | --- | --- |
| Full isolated SQLite suite | 133 tests / 1392 assertions passed | Ignored `storage/app/qa/inventory/phpunit-results.xml` |
| New addition regression cases | Nine tests passed, included above | `tests/Feature/AddNightlyMedicinesTest.php` |
| Real HTTP Playwright daily checks | 12/12 passed at 1440 x 900 and 390 x 844 | Ignored `storage/app/qa/selected-nightly-browser/results.json` |
| Browser postconditions | Two approved items, selected medicine Apotek 20 to 0, Gudang remains 50, added medicine count 0, total/batch/ledger reconciliation consistent | Actual PHP fixture postconditions |
| Existing MySQL competing-process regressions | Nine scenarios passed, including daily approval and multi-transfer races | Actual `php scripts/test-inventory-mysql.php` execution and ignored worker fixtures |
| Frontend build and Blade compilation | Passed | Actual `npm run build` / `php artisan view:cache` executions |
| UAT safety | Clinic inventory fingerprint unchanged; UAT MySQL grants restricted and clinic reads denied | Actual `php scripts/uat.php verify`, 15:45 WIB; ignored isolation report |

The first full-suite execution exceeded the runner's 180-second timeout and is not counted as a pass. It was rerun with `INVENTORY_TEST_TIMEOUT=900`; that complete execution passed in about 33 seconds. The timeout setting was supplied only to the process; no application limits or stock rules were changed.

The browser really posts unsaved quantity/batch/unit/flag/request/note data with the selected addition, verifies stable original input IDs and retained values, rejects incomplete new items, then submits and approves through normal routes. Responses/writes are not mocked. Each fixture uses a random temporary MySQL database removed afterward; clinic and persistent UAT inventory are not used as test fixtures. Screenshots `Tambah_Obat_Draf_Desktop.png`, `Tambah_Obat_Draf_Mobile.png`, and `Tambah_Obat_Isian_Tersimpan.png` were opened and visually checked; they contain simulation data and stay in ignored local QA storage.

## Availability And Limits

Refresh the existing UAT application to load built assets; no migration/reseed or permission change is required. Manual staff acceptance is still pending. The feature adds items, not an independent purchase request or an automatic merge of concurrent draft edits. If another tab has already extended a report, a stale form missing those rows must be refreshed before it can save successfully.
