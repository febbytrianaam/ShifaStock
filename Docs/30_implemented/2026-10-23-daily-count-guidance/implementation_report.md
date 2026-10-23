# Daily Count Guidance Implementation

Date: 2026-10-23 WIB. Tested base: `ba7045c7e23e9924297ab957b687b34c8f9b8003` plus the working-tree changes described here. The user subsequently authorized publishing this guidance, tooltip follow-up, issue guidance, tests, and documentation to `origin/main`; use Git history for the publication commit. No production deployment is part of this package.

## Changes

- The daily start page now shows an unframed explanation before the form rather than the short generic description from the shared layout. It defines initial stock, system stock, and physical count, including location, stock unit, zero versus unchecked, and recorded transactions.
- The example explicitly says system 10 / physical 2 means entering 2; approval sets the system quantity to 2, not subtracting 2 to get 8. Saving/submitting is not approval. Only examined medicines are adjusted, and initial stock is not re-entered for each inspection.
- The counting workspace provides a native, keyboard-accessible collapsed explanation titled **Stok sistem dan jumlah fisik**. Its text uses the actual report location. Default collapse and bounded explanatory text preserve the counting area.
- Existing inventory calculations, master data, role/ownership rules, partial submission, batch checks, report printing, and transaction behavior were not changed.

## Files

- `resources/views/nightly/index.blade.php`: start-page definitions/example before the form and suppression of the duplicate generic description.
- `resources/views/nightly/show.blade.php`: location-specific collapsed explanation above the counting tabs.
- `resources/css/nightly-workspace.css`: scoped disclosure text, focus treatment, bounded body, and compact tablet/phone spacing.
- `tests/Feature/SelectedNightlyCheckTest.php`: three rendered-page regressions for explanatory content/no writes, default collapse/location, and Guest read-only access.
- `scripts/test-nightly-workspace-browser.cjs`: two added real-browser cases for start-page layout and keyboard/mobile disclosure; screenshots support full-page capture for the start guide.
- Current operator guide and implementation indexes updated.

## Actual Verification

Environment: Windows, PHP 8.5.9, PHPUnit 12.5.38, Chrome/Playwright, local MySQL fixtures, Vite 7.3.7. All transaction fixtures used SQLite `:memory:` or a separately generated MySQL database, not clinic or persistent UAT inventory.

| Execution | Result | Evidence |
| --- | --- | --- |
| `php scripts/test-inventory.php`, process timeout 900 s | 175 tests / 1805 assertions passed | `storage/app/qa/inventory/phpunit-results.xml` |
| `php scripts/capstone-final-browser.php nightly-ui` | 17/17 passed; two guidance cases plus existing workspace, data persistence, zero/null, batch, Guest, shared operator, stale version, and responsive regressions | `storage/app/qa/nightly-workspace-browser/results.json` |
| Independent browser postconditions | Gudang 50 / Apotek 20 unchanged; stock/batch/ledger reconciliation passed; temporary database removed | Actual fixture runner output |
| `npm.cmd run build` | Passed after compact-spacing correction | Actual Vite output |
| CJS syntax and `git diff --check` | Passed | Actual command output |

New screenshots in ignored local QA storage: `Penjelasan_Pemeriksaan_1440.png`, `_768.png`, `_390.png`, `Penjelasan_Hitung_Desktop.png`, and `Penjelasan_Hitung_Mobile.png`. Start desktop/tablet/phone, expanded phone explanation, and final 320 x 740 counting screenshots were opened for visual review. The unchanged responsive matrix also verifies through 4K, with count fields, detail fields, and footer reachable and no intersecting row regions or horizontal page overflow.

## QA Finding And Limits

Two initial browser attempts found a 320 x 740 list viewport of about 78.6 px, below the existing 80 px minimum, after adding the disclosure. The final version shortens its heading and reduces only the disclosure's tablet/phone spacing. The original minimum-area assertions were retained; the full 17-case rerun passed. Initial failures are not included as passing runs, and an old ignored `Kendala_UI.png` is not current success evidence.

No new MySQL competing-worker, full operational-browser, PDF pagination, or physical-printer execution is claimed for this UI-only change. The counting rules and printable report are untouched. This is automated/visual verification with simulated data, not clinic user acceptance. No credentials were added to documentation or screenshots.

See [plan](plan.md) and [operator guide](../../00_current/uat_guide.md).

## Follow-Up: Column Tooltips

The user additionally requested hover descriptions on detail-table headings. Main headings Obat, Stok sistem, Jumlah fisik / satuan, and Status now have text-plus-Lucide-help buttons. The pharmacist review headings also explain the saved count and signed adjustment, explicitly distinguishing it from patient usage. Removing the header's old `aria-hidden` makes its focusable controls available to assistive technology.

`resources/views/components/nightly-column-help.blade.php` renders consistent `type="button"`, popover target, label, and description relationships. Six static descriptions are shared between main/review headings and the mobile guide, rather than duplicated for 167 rows. `resources/js/nightly-column-help.js` opens native top-layer popovers on hover/focus/click, clamps their position to the viewport, permits pointer movement over the explanation, and closes them with Escape, outside interaction, or page/table scrolling. A native-title fallback is provided when the Popover API is absent; full touch behavior was verified in the current Chrome runtime, not every legacy browser. No new dependency was installed.

On compact layouts the existing collapsed explanation contains the four help buttons, leaving the main counting list unchanged when closed. Tooltip bodies stay in the browser's top layer instead of being clipped by table/guide overflow. The print route and inventory/permissions remain unchanged.

Actual reruns for this follow-up: **177 PHPUnit tests / 1861 assertions**, **20/20 real HTTP workspace browser cases**, Vite build, JavaScript syntax, and whitespace checks passed. Two new rendered-page tests verify safe/accessible controls and pharmacist review descriptions. Three new browser cases verify hover and pointer persistence without layout movement, focus/Escape/click-outside without posting, and mobile tapping/clamping at 320 and 390 px without changing quantities. Existing responsive, zero/null, batch, Guest, shared-draft, and stale-version cases remain included. Gudang 50 / Apotek 20 and reconciliation were unchanged; the temporary database was removed.

An initial browser run found a test selector that matched both the guide body and its new nested help group. It was scoped to the direct body; the 120 px body-height assertion was retained. This incomplete run is not counted as a pass. Current evidence is `storage/app/qa/nightly-workspace-browser/results.json`, `Tooltip_Header_Desktop.png`, `Tooltip_Header_320.png`, and `Tooltip_Header_390.png`; desktop and 320 px screenshots were opened for visual inspection. Prior 175/17 counts above remain the earlier guidance stage, not the latest tooltip run. No clinic-data edits, manual assistive-technology acceptance, new PDF pagination, or MySQL worker-race executions are claimed.

A separate Node smoke check exercised the unsupported-Popover branch with mocked platform objects: native title text is populated and custom tooltip content is hidden rather than appearing as fixed overlays. It passed, followed by a successful final build. This is a fallback unit smoke, not a legacy-browser test or an additional Playwright case; the 20-case browser count remains unchanged.

Publication follow-up: the user later authorized committing/pushing the combined daily/issue guidance package. The full pre-publication backend rerun passed **179 tests / 1885 assertions**; the 20-case workspace browser run above remains its earlier same-day execution. See [publication verification](../2026-10-23-issue-guidance/implementation_report.md#publication-verification) for scope and exclusions. No production deployment is included.
