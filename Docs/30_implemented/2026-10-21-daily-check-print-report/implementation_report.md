# Daily Check Print Report

Date: 2026-10-21, Asia/Jakarta. Tested source: working tree based on `77ecbd2`, including the add-medicines-to-draft follow-up. Publication authorized on 2026-10-22 together with dashboard monitoring; use Git history for the publication hash. The dated results below are actual stage-specific executions, not a new execution at publication time.

## Portrait Follow-Up: 2026-10-22

At the user's request, the report now uses A4 portrait with 12 mm margins and a matching 210 mm screen preview. Cell padding is reduced to 1.5 mm without reducing text size: table text is 11 px, secondary text 10 px. The number column is widened so its header stays on one line. All eight result columns and the separate saved-batch table remain; no data, stock logic, or permission changes were made.

Actual reruns on 22 October, completed at approximately 08:45 WIB, against the working tree based on `77ecbd2`:

| Check | Actual Result | Evidence |
| --- | --- | --- |
| Full isolated SQLite suite | 140 tests / 1450 assertions passed, including portrait CSS assertions | Ignored `storage/app/qa/inventory/phpunit-results.xml` |
| Real HTTP daily browser suite | 15/15 passed; temporary MySQL database removed; approval and reconciliation postconditions passed | Ignored `storage/app/qa/selected-nightly-browser/results.json` |
| Actual Chrome PDFs | Approved report: one page; 70-item draft: eight pages; every page measures approximately 595 x 842 pt (A4 portrait) | `Laporan_Pemeriksaan.pdf` and `Laporan_Pemeriksaan_Panjang.pdf` in the same ignored QA folder |
| PDF content and visual review | All 70 codes present, headings repeated on every long-report page, no application controls or orphan closing page; all nine rendered pages opened and inspected | Poppler renders `Laporan_Pendek_Portrait-1.png` and `Laporan_Panjang_Portrait-1.png` through `-8.png`; pypdf checks |
| JavaScript syntax and whitespace | `node --check` and `git diff --check` passed | Actual command execution |

The first browser assertion expected the literal CSS serialization `a4 portrait`; Chrome returned `a4`, which has the same default orientation. The assertion now allows either and still rejects landscape. Actual PDF dimensions independently verify portrait; this initial failed run is not counted as passing. After widening the number column, the full backend/browser suites and PDF checks were rerun.

No Vite rebuild is required for this inline Blade CSS change. The previous operational browser, concurrency, build, and UAT-isolation results below are historical, not new executions for this format-only follow-up. No persistent UAT or clinic fixtures were written, and no physical-printer acceptance test was performed.

## Problem And Resolution

The old Cetak / Simpan PDF button invoked `window.print()` on the operational page. Its form, scroll containers, hidden/paginated rows, and layout CSS were not an appropriate report document.

The button opens `GET /pengecekan-malam/{id}/cetak` in a separate tab. This standalone report has its own print button, independent of the application layout and Vite assets. Its original 2026-10-21 landscape layout was changed to A4 portrait on 2026-10-22 at the user's request. It contains one clinic logo, configured report name/address, reference/date/location/scope/status, saved operator/reviewer and approval time, per-medicine results, prior dated counts, requested quantities/notes, and explicit saved batch counts. It never invents reviewer identity or approval for a draft. UAT reports retain the simulation banner.

`NightlyCheckController::reportData` supplies shared read data to detail and print views. Print uses every saved report item, not only currently visible/search-matched rows. Inactive masters remain printable through their saved report snapshots. Recorded units are printed without reinterpreting them as a different current master unit; null counts remain Belum diperiksa, not zero. Shortage totals count items rather than summing mixed medicine units.

The same authenticated `view-nightly-checks` permission guards the new GET route. Guest can print when permitted to view, but gains no write access. Responses are private/no-store; printing does not save, submit, approve, or mutate stock. No new migration, stock calculation, FEFO rule, or permission-matrix reset was added.

The shared form-safety module exposes its existing dirty-state comparison to the print-link handler. Unsaved daily-count changes produce a SweetAlert reminder to save the draft first; the current inputs remain intact and no print tab is opened. A clean saved report opens normally. The preview and footer explicitly identify the source as the last saved data.

## Historical Verification: 2026-10-21 Landscape Layout

| Check | Actual Result | Evidence |
| --- | --- | --- |
| Full isolated SQLite suite | 140 tests / 1447 assertions passed | Ignored `storage/app/qa/inventory/phpunit-results.xml` |
| New print feature cases | Seven tests passed, included above | `tests/Feature/NightlyPrintTest.php` |
| Real HTTP daily browser suite | 15/15 passed, including unsaved-print protection, standalone output, and long reports | Ignored `storage/app/qa/selected-nightly-browser/results.json` |
| Existing operational browser regression | 13/13 passed; actual stock postconditions/reconciliation verified | Ignored `storage/app/qa/inventory-browser/Browser_final.json` |
| Actual approved PDF | One A4-landscape page with saved results/batch, one loaded logo, no form controls or application sidebar | `Laporan_Pemeriksaan.pdf` in that ignored QA folder |
| Actual long draft PDF | Seven A4-landscape pages, all 70 medicine codes present, table headings repeated on every page, no orphan closing page | `Laporan_Pemeriksaan_Panjang.pdf`; actual pypdf validation |
| Visual inspection | Short report plus all seven long-report pages rendered and opened; no clipped rows, missing logo, or overlapping text observed | Poppler renders `Laporan_Pemeriksaan_Final.png` and `Laporan_Panjang_Final-1.png` through `-7.png` |
| Build and Blade compilation | Passed | Actual Vite build and `php artisan view:cache` |
| UAT separation | Clinic fingerprint unchanged; UAT grants restricted and clinic reads denied | `php scripts/uat.php verify`, final check 16:16 WIB |

PDFs were produced by headless Chrome's real print rendering against the authenticated application, with CSS paper size and no browser-added header/footer. Text extraction verifies complete data and page dimensions, not visual layout alone; the PNG renders were also reviewed. The 70-item draft fixture was saved through the actual controller, then its masters deactivated to exercise historical printing. All fixtures use random temporary MySQL databases removed afterward, not clinic or persistent UAT inventory. These local QA files are ignored and are not new Capstone deliverables.

## Findings During QA

- An initial unit fixture attempted to change a medicine's stock unit after draft history; the existing guard correctly rejected it. The fixture was corrected to a legacy draft with a different stored unit, without weakening the guard.
- The first long-report browser check attempted to create another page in a Playwright-owned context. The runner now reuses its authenticated page; no application change was required.
- The first long PDF placed the reviewer block alone on an eighth page. Moving reviewer/time into report metadata removed that orphan page. The final complete reruns passed; initial failures are not counted as successful runs.

## Limits

The operator opens the report preview and then chooses Cetak / Simpan PDF to use the browser's printer/Save as PDF destination. This is not a server-side PDF download. Saved drafts can be printed but are explicitly marked unapproved. Unsaved inputs are not silently written by printing. No physical-printer or manual clinic acceptance test was performed. This change does not replace the separate stock-card print feature or introduce supplier purchase orders.
