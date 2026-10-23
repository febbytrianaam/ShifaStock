# Medicine Issue Guidance Implementation

Date: 2026-10-23 WIB. Tested base: `ba7045c7e23e9924297ab957b687b34c8f9b8003` plus current working-tree changes, including the earlier daily guidance/tooltips. The user subsequently authorized publishing the combined feature/test/documentation package to `origin/main`; use Git history for the publication commit. No production deployment is included.

## Delivered

- A visible, unframed explanation appears above the Pengeluaran Obat form, replacing the short generic description on that page only.
- Copy states that ShifaStock is not connected to Neoklinik and that recording there does not automatically reduce ShifaStock stock.
- It distinguishes issued quantity from remaining stock and identifies the source location, stock unit, automatic transaction reference, optional source reference without patient identities, and non-expired FEFO behavior.
- It explains immediate stock/batch reduction and stock-card movement after successful saving, without daily-review approval. The example is explicitly illustrative: 20 tablets minus an issue of 3 leaves 17, and the quantity field receives 3 rather than 17.
- It warns against duplicate recording, including consumption already included in a physical-count adjustment, and directs location transfers and supplier returns to their own workflows.
- Quantity and source-reference fields have concise `aria-describedby` hints. Existing validation, decimal precision, defaults, posting, permission, and stock rules are unchanged.

## Files

- `resources/views/inventory/issues.blade.php`: explanatory band, duplicate-description suppression, hints and source-reference placeholder.
- `tests/Feature/IssueGuidanceTest.php`: two regressions for content/order/accessibility, read-only Guest, forbidden posting, and no stock/movement writes from viewing.
- `scripts/capstone-final-browser.cjs`: one additional actual HTTP case for responsive guidance, unchanged form defaults, and screenshot evidence, using the existing isolated fixture.
- Operator/current documentation and implementation indexes.

## Actual Verification

Windows, PHP 8.5.9, PHPUnit 12.5.38, Chrome/Playwright, Vite 7.3.7, isolated SQLite memory tests and temporary MySQL browser fixtures.

| Execution | Actual Result | Evidence |
| --- | --- | --- |
| `php scripts/test-inventory.php`, process timeout 900 s | 179 tests / 1885 assertions passed, about 43 seconds | `storage/app/qa/inventory/phpunit-results.xml` |
| `php scripts/capstone-final-browser.php regression` | 17/17 operational browser cases passed, including the new guide at 1440 x 900, 768 x 1024, and 390 x 844 | `storage/app/qa/inventory-browser/Browser_final.json` |
| Fixture stock postconditions | Gudang stayed 50; Apotek 20 to 18 from the pre-existing special-adjustment regression only; reconciliation clean; temporary database removed | Actual fixture runner output |
| `npm.cmd run build`, browser-runner syntax, `git diff --check` | Passed | Actual command output |

Guide screenshots: `Panduan_Pengeluaran_1440.png`, `_768.png`, and `_390.png` under ignored `storage/app/qa/inventory-browser/`. The desktop and phone full-page captures were opened for visual review. Copy fits without page overflow, the guide precedes the form, and reading/resizing does not change quantity or generated reference. Fixture credentials are not in screenshots or public documentation.

No persistent UAT/clinic inventory was written. Existing operational regression writes occurred only in the generated disposable database. This is not a new dedicated issue-posting browser test, real clinic acceptance, or a MySQL concurrent-worker execution; the existing issue behavior is exercised by the full backend suite. Earlier 20-case daily-workspace results are from the separate tooltip feature, not part of the 17 cases here.

See [plan](plan.md) and [operator guide](../../00_current/uat_guide.md).

## Publication Verification

At the subsequent commit/push request on 23 October 2026, the final combined feature tree was retested on SQLite `:memory:`: **179 tests / 1885 assertions passed**, about 50 seconds, completed around 15:45 WIB. Browser results above and in the daily-guide report are actual earlier executions on the same date, not rerun during this publication step. Source syntax/whitespace checks and a fresh frontend build passed before committing. Unrelated Capstone screenshots/ZIPs, their helper edits, private UAT profiles, and ignored QA outputs are excluded. No inventory migration/reset or permission change is included.
