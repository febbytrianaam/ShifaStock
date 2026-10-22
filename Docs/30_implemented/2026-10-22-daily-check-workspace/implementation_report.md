# Compact Daily Check Workspace

Implemented and tested: 2026-10-22, Asia/Jakarta. Base commit: `5e86898`; tests exercised the working tree on that base before publication. The publication commit is recorded in Git history.

## Final State

The table fills the available page width with proportional columns, without a 1360 px cap or narrower page margins. Available-space container queries adapt the same fields for sidebar-constrained tablets and phones. Rincian, live progress, input-preserving search/pagination, separate review/summary tabs, and accessible save/send controls are included. The intermediate capped-width attempts below are historical, not the final implementation.

## Publication Verification

Pre-publication verification completed on **2026-10-22 at 16:22 WIB** against the final working tree on `5e86898`. Actual fresh executions passed **157 tests / 1621 assertions**, **12/12 workspace**, **7/7 partial**, and **16/16 operational** browser cases (35 browser cases in total). Vite build, changed runner syntax checks, and whitespace checks passed. Temporary MySQL databases were removed and stock/batch/ledger postconditions passed. PHPUnit used SQLite `:memory:`; clinic and persistent UAT inventory were not changed.

These are the final publication reruns, not the initial counts in the historical stages below. The selected-daily/PDF suite and MySQL competing-worker suite were not rerun for this publication. Local runtime credentials, QA JSON/XML/PNGs, database files, and unrelated Capstone screenshot/ZIP edits are excluded from the commit. The final commit identifier is available in Git history; this document does not attempt to embed its own commit hash.

## Changes

- The daily report detail now uses a compact four-part list: medicine, current system stock, physical quantity/unit, and status/Rincian. The start screen and monthly stock-opname workflow are unchanged.
- Ten medicines appear per page by default; 25/50/100 remain available. Search and status filters are retained. An explicit empty state replaces an apparently blank list.
- Batch counts, prior approved results, saved comparison, low-stock flag, requested quantity, and note are inside Rincian. All rows and named fields remain in the same form, including rows on other pages. Collapsing, filtering, or switching tabs does not discard them.
- Live progress counts an explicit zero as examined and a blank as unexamined. The unsaved indicator uses the existing form-safety baseline, not automatic saving. Typing no longer reapplies a status filter on each keystroke, which previously could hide the active row immediately.
- Counting-view save/send controls stay outside the scrolling list. Available height accounts for the actual topbar/banner/padding and responds to resize. Very short viewports can still need normal page scrolling to keep controls usable.
- A next-unchecked control, guarded Enter behavior, Lucide command icons, expandable controls with ARIA state, and keyboard-accessible tabs are included. The mobile layout does not require horizontal page scrolling.
- Pharmacist review and the saved request summary have separate tabs instead of adding long sections below the input list. The summary is displayed once. Printing still opens the existing authenticated standalone A4-portrait report, not this workbench. If printing is attempted with unsaved inputs from the summary tab, the SweetAlert returns to the counting view.
- Validation reveals the affected page and expands Rincian before focusing a hidden batch input. Batch-derived totals remain read-only with a field explanation; editing the underlying batch counts is unchanged.
- The shared layout accepts an optional description-hiding prop for this screen only; other screens retain their descriptions and titles.

## Unchanged Safeguards

No controller, service, route, migration, FEFO, stock calculation, or role-permission rule changed. Only the owner of an operational draft can edit it. Guest and another user's draft remain read-only. Submitted/approved reports stay locked. Partial confirmation, null quantities, explicit zeroes, unit validation, snapshots, and stock/batch/ledger approval safeguards remain enforced by the backend.

No clinic or persistent UAT data was seeded, cleared, reassigned, or used as a transaction fixture. No production deployment was performed. The user subsequently authorized documentation updates, a local commit, and a normal push to `origin/main`; the publication excludes unrelated Capstone screenshots/archives and ignored local QA evidence.

## Actual Verification

Environment: Windows, PHP 8.5.9, PHPUnit 12.5.38, isolated SQLite `:memory:`; separate randomly named temporary MySQL databases for real HTTP Playwright tests, headless installed Chrome, Node, and Vite 7.3.7. Test secrets are generated at runtime and excluded from documentation/evidence.

| Execution | Actual result | Evidence (local, ignored) |
| --- | --- | --- |
| `php scripts/test-inventory.php` | 157 tests, 1621 assertions passed | `storage/app/qa/inventory/phpunit-results.xml` |
| `php scripts/capstone-final-browser.php nightly-ui` | 10/10 browser checks passed | `storage/app/qa/nightly-workspace-browser/results.json` and PNGs |
| `php scripts/capstone-final-browser.php partial-nightly` | 7/7 browser checks passed | `storage/app/qa/partial-nightly-browser/results.json` and PNGs |
| `php scripts/capstone-final-browser.php regression` | 16/16 browser checks passed | `storage/app/qa/inventory-browser/Browser_final.json` |
| `npm.cmd run build` | Passed | Actual Vite output |
| Node/PHP syntax and `git diff --check` | Passed; Git emitted line-ending notices only | Actual command output |

The new UI suite saves actual values on pages 1, 2, and 17 of a 167-medicine draft, reloads them, verifies live progress/zero handling, search/pagination, tab and Enter behavior, dirty printing, expandable batch totals, validation focus, and read-only accounts. Viewport checks cover 1440 x 900, 1366 x 768, and 390 x 844, including footer bounds, list height, and page width. Desktop/mobile list and expanded-detail screenshots were opened for visual inspection.

Independent PHP postconditions confirmed the UI-only fixture left Gudang at 50 and Apotek at 20, without reconciliation mismatches. The partial suite approved only the counted Apotek result (20 to 18), left Gudang at 50, and did not create stock for the unexamined medicine. The operational suite also passed its independent stock/ledger checks. Each temporary database was removed by its runner.

The first backend run found one missing informational sentence expected by an existing partial-report assertion. The partial-status wording was restored, and the complete suite passed on rerun. The first UI run passed nine cases; an additional hidden-batch validation case was then added and the full ten-case suite rerun successfully.

The existing selected-daily runner was adapted to click Rincian and the review tab, but its 15-case/PDF run was not rerun for this UI change. Previously generated PDF results and MySQL race counts remain historical, not new results. No manual clinic UAT or physical-printer acceptance is claimed.

## Follow-up: Wide-Screen Column Spacing

2026-10-22: the medicine column no longer absorbs all remaining desktop width. It is capped at 320 px (280 px in the compact desktop rule), while system stock, physical count, and status stay together. The mobile two-column row layout is unchanged.

The frontend build passed again. The actual isolated workspace browser rerun passed **11/11** cases, including a new geometry check at 1440, 1920, and 2560 px: medicine width stays within its cap, adjacent-column gaps remain 16 px, and the system-stock header aligns with its data. Existing desktop/laptop/mobile, persistence, validation, and read-only cases still passed. Wide-screen and normal desktop screenshots were opened for visual review. Independent stock reconciliation passed, and the temporary MySQL database was removed. No backend or persistent data changed; the backend/other-suite counts above are from the earlier overhaul execution, not rerun for this CSS-only follow-up.

## Follow-up: Responsive Table Only

2026-10-22: the preceding capped-column fix still left unused space inside the table's status track. It is superseded by proportional columns that occupy the complete table width. Following the user's clarification, only the list/table has a 1360 px maximum width and remains left-aligned. The page workspace, header, search/filter toolbar, pagination, and save/send area retain the original full content width and padding; the entire page is not centered or narrowed.

Container queries respond to actual workspace width, including space occupied by the desktop sidebar. At 700 px of available space or less, medicine rows switch to a stacked layout. At 460 px or less, the physical count/unit fields occupy a complete row with a visible label, and Tambah Obat uses its accessible plus icon. Long medicine names wrap; units and controls remain inside their cells. No business fields or permissions were removed.

Actual reruns: **157 PHPUnit tests / 1621 assertions**, **12/12 workspace browser cases**, **7/7 partial browser cases**, frontend build, Node/PHP syntax, and whitespace checks passed. The existing 16-case operational suite and PDF results earlier in this report were not rerun for this follow-up. Temporary MySQL stock/ledger postconditions passed and databases were removed; persistent clinic/UAT data was not changed.

Responsive viewport coverage: 2560 x 900, 1920 x 900, 1440 x 900, 1366 x 768, 1280 x 800, 1024 x 768, 820 x 1180, 768 x 1024, 700 x 900, 390 x 844, 360 x 800, and 320 x 740. Assertions check full page width is preserved, the table alone is capped, the final action reaches the table edge, header alignment, page/list/field horizontal overflow, overlapping row regions, reachable expanded inputs, and footer bounds. Tablet/phone dialogs are also checked for outer and inner overflow. Simulation fixtures include a long medicine name and Tablet/Kapsul unit. Screenshots of wide desktop, tablet, phone, expanded details, and dialogs were opened for visual inspection.

The initial matrix run caught a test timing issue immediately after viewport resize: the width had changed before the resize listener recalculated available height. The runner now waits for the actual workspace height and sidebar transition to settle before checking geometry. It does not change the page DOM or inject styling to make screenshots pass. Evidence remains ignored in `storage/app/qa/nightly-workspace-browser/`, including `Responsif_*.png`, `Rincian_*.png`, `Dialog_*.png`, and `results.json`.

## Follow-up: Actual Panel And Full-Width Table

2026-10-22: inspected the user's already-open UAT report in the Codex in-app browser without editing or submitting inventory inputs. The 1360 px table cap from the preceding stage was the remaining cause: at a 2560 px viewport, the workspace measured 2272 px and the table 1360 px, leaving **912 px** unused at the right. At 1920 px, the corresponding gap was **272 px**. Earlier tests accepted that cap and checked overflow, not full-width coverage.

Removed only the table maximum-width rule. Workspace padding/header/toolbar/footer remain unchanged, proportional columns now fill the full available width, and tablet/phone container queries remain in place. Live DOM-backed measurements after reloading the saved report showed table/workspace widths matching at **1632 px** (1920 x 1080), **2272 px** (2560 x 1440), **736 px** (1024 x 768), and **358 px** (390 x 844): **zero right-side gap** and no horizontal overflow in all four. No UAT data form was submitted. Temporary browser viewport overrides were reset to the user's original panel size (1015 x 884).

Actual frontend build and **12/12 isolated workspace browser checks** passed again. The desktop geometry test now requires the table's right edge to match the workspace and includes 1440 x 900, 1920 x 1080, 2560 x 1440, and 3840 x 2160. Existing tablet/phone/input-preservation checks also passed. Temporary MySQL postconditions confirmed unchanged fixture stock (Gudang 50, Apotek 20) and consistent reconciliation; the database was removed. This one-rule CSS correction did not rerun PHPUnit, the partial suite, or PDFs; their counts above remain prior-stage results. Final local screenshot evidence includes `Desktop_1920x1080.png`, `Desktop_2560x1440.png`, and `Desktop_3840x2160.png` in the ignored QA directory.
