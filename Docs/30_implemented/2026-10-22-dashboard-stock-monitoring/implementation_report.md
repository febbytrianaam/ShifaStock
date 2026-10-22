# Dashboard Stock Monitoring Alignment

Date: 2026-10-22, Asia/Jakarta. Tested working tree based on `77ecbd2`, including daily-draft/portrait-report follow-ups. Publication authorized on 2026-10-22; these changes and their documentation are packaged together in the next commit. Use Git history for the publication hash; the recorded test runs predate that commit.

## Cause And Fix

The dashboard's Kosong / Menipis KPI and Perlu Ditindaklanjuti list queried approved physical counts through `LatestNightlyItems`. Monitoring's separate minimum-stock table queried current `stock_balances` against per-medicine/location `stock_parameters`. The two widgets represented different sources: the dashboard could show zero and Belum Ada Laporan while Monitoring already showed an actual minimum-stock alert.

`App\Queries\MinimumStockAlerts` now contains the existing Monitoring query, reused by both controllers. The dashboard counts all qualifying medicine/location pairs and previews eight, prioritizing zero then lower stock. It displays current quantity, unit, location, minimum, and a no-transactions notice where appropriate. Links open the minimum-stock section with medicine-code and location filters. The card action stays on one line on compact desktop layouts.

## Unchanged Rules

- A minimum must be explicitly configured for the medicine/location; an unconfigured master is not automatically an empty-stock alert.
- The existing rule remains current quantity less than or equal to minimum, including equality and an explicitly configured zero minimum.
- A configured pair without a balance is treated as zero, as before in Monitoring, with explicit wording on the dashboard.
- Totals count medicine/location pairs, not summed quantities or distinct medicines across locations; the eight-row preview does not cap the KPI.
- Monitoring's historical physical-count summary and first table still use the latest approved result per medicine/location. They remain separate from transaction-based minimum monitoring.
- Printing, FEFO, stock posting, access permissions, user-managed matrix settings, and database schema are unchanged. No migration, reset, seed, or operational data cleanup is required.
- Browser refresh/navigation reads updated posted data; this is not push/live polling or a claim that unrecorded clinical usage is known to the system.

## Verification

| Execution | Actual Result | Evidence |
| --- | --- | --- |
| Full isolated SQLite suite | 147 tests / 1533 assertions passed | Ignored `storage/app/qa/inventory/phpunit-results.xml` |
| New regressions | Six dashboard-specific tests plus one transaction workflow test | `DashboardStockMonitoringTest.php`, `CapstoneFunctionalTest.php` |
| Real HTTP browser regression | 16/16 passed, including dashboard desktop/mobile, filtered drill-down, and quantity refresh after posting | Ignored `storage/app/qa/inventory-browser/Browser_final.json` |
| Stock postconditions | Gudang remained 50; Apotek changed from 20 to 18 after the intended simulation posting; inventory/batch/ledger reconciliation passed | Same runner's actual PHP postcondition checks |
| Screenshot review | Desktop before/after posting and mobile rendered with backend quantities and minimums; images opened for visual review | `Browser_Dashboard_Monitoring_Desktop.png`, `Browser_Dashboard_Monitoring_Mobile.png`, `Browser_Dashboard_Monitoring_Setelah_Posting.png` in the ignored QA folder |
| Frontend and static checks | Vite build, focused Pint, Node syntax, and whitespace checks passed | Actual commands |
| Read-only persistent UAT check | At 13:54 WIB, dashboard and Monitoring each returned one minimum-stock alert despite zero approved daily reports | Actual controller/query comparison using the restricted UAT connection; no writes |

Backend cases cover no approved daily reports, current stock superseding stale historical shortages, threshold equality, separate location thresholds, zero and absent balances, missing/null minimums, full counts versus limited preview, and Guest read-only behavior. Real controller posting tests cover draft reception (no stock increase), posted reception, transfer, issue, and restocking; monitoring and dashboard agree after each stage.

Earlier daily-history tests still verify partial-count preservation in the historical Monitoring table; their dashboard expectations now explicitly reflect the new transaction/minimum source. Browser fixtures use a random temporary MySQL database removed afterward, not persistent UAT or clinic inventory. No manual clinic acceptance test was performed.

## Follow-Up Audit Of Other Widgets

On 2026-10-22 at 14:20 WIB, the actual dashboard controller output was compared with independent, read-only SQL queries on the restricted persistent UAT connection. This is a simulation-data snapshot, not clinical inventory or a guarantee that these values remain unchanged after tester activity.

| Widget | Backend Source And Meaning | Actual UAT Snapshot | Comparison |
| --- | --- | --- | --- |
| Master Terverifikasi | Active verified masters with a stock unit and nonempty dosage form, not every imported master | 1 medicine | Matched |
| Obat Memiliki Stok | Distinct medicines with a positive balance in any location | 2 medicines | Matched |
| Kosong / Menipis | Configured medicine/location pairs at or below minimum | 1 pair | Matched |
| Batch Mendekati ED | Distinct batches in the warning-date range with positive stock in at least one location | 0 batches | Matched |
| Penerimaan chart | Distinct receipt references per day over seven days | 1 transaction | Matched |
| Transfer chart | Distinct transfer references per day over seven days | 4 transactions | Matched |
| Total Aktivitas | Sum of the chart's receipt and transfer transactions only | 5 transactions | Matched |
| Stok per Lokasi | Positive balances ordered by last update, limited to 12 preview rows | 4 rows | Matched |
| Administrator | Actual account and active-account counts; number of supported enum roles | 4 accounts, 4 active, 4 roles | Matched |

No additional disconnected widget or dummy counter was found in this audit. The chart counts transaction references, not individual medicine/batch rows; opening balances, issues, returns, and adjustments are not included in its two series. A zero near-expiry count does not mean the widget is disconnected, and does not represent already-expired batches.

Two display limitations were identified without changing feature behavior: the dashboard table search filters only the at-most-12 displayed rows, and its Tersedia badge means a positive quantity, not that the quantity exceeds the configured minimum. Full current balances remain available in Laporan; the minimum alert is reported separately above.

The focused backend rerun `DashboardOnboardingTest|DashboardStockMonitoringTest|CapstoneFunctionalTest` passed **25 tests / 254 assertions**, using SQLite `:memory:`. Actual JUnit evidence: ignored `storage/app/qa/inventory/dashboard-widget-audit.xml`. This audit did not rerun Playwright, change application code, or write persistent UAT/clinic inventory. Earlier full-suite and browser counts above are separate, actual executions, not recounted as new audit runs.
