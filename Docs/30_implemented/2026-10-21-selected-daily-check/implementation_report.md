# Selected Daily Physical Checks

Date: 2026-10-21, Asia/Jakarta; verification around 15:07 WIB, with final reruns in the local QA outputs.
Source verified before publication: working tree based on `25835db472c92a31eceed8ee5c36cb51406c6380`, including the UAT routing, multi-transfer, and count-unit dropdown follow-ups. The user subsequently requested documentation, commit, and push; the resulting publication commit is recorded in Git history.

## Operator Flow

1. Open Pemeriksaan Stok > Harian; choose date and location.
2. Choose Semua obat (default) or Obat tertentu. Search medicine names/codes and check the desired medicines; changing search does not clear selections.
3. Start the report. Complete mode contains all eligible masters; selected mode contains only chosen masters. Eligibility retains the existing active-or-imported rule.
4. Save an incomplete draft, or complete every medicine within that report before submitting. Blank means unchecked; explicit 0 means physically empty. Requested quantity remains available for the shortage summary.
5. Pharmacist reviews and approves. Only report medicines at that location can be adjusted. Other medicines/locations are not inferred empty or modified.

Scope appears in daily list, report header, printable/shareable summary, and approved-check list in inventory reports. Review text identifies the number of affected medicines.

## Count Unit Dropdown

Satuan Hitung now uses a native dropdown, not free text. When the medicine has a stock unit, that unit is automatically selected and is the only offered unit; purchase units are not treated as equivalent without conversion. When a master has no unit, the dropdown provides active reference units without guessing a selection and explicitly warns that the medicine master needs completion. The existing backend rejection of positive counts without a master unit remains unchanged.

A counted draft whose stored unit differs from the current master displays a warning and requires explicit unit selection after the operator checks the quantity; its number/unit are not silently reinterpreted or written on page load. Submitted/approved read-only views retain the recorded unit. Client-side submission warns if the dropdown is unselected; draft saving remains possible.

## Safety And Compatibility

- Additive migration adds `nightly_checks.scope`, default `complete`; old reports and requests without scope retain complete behavior.
- Selected mode requires at least one eligible, distinct medicine. Unselected medicines are not inserted as blank/zero report items.
- Existing batch validation, total/batch/ledger reconciliation, snapshots, inventory mutex, date limits, ownership, and pharmacist permissions are unchanged. No FEFO rewrite.
- One report per date/location remains. Reopening never replaces scope/items. Owners may cancel their own draft and recreate it; submitted/approved reports cannot be replaced that way.
- This remains physical counting, not a stock-neutral requisition or purchase order. Approval can adjust selected stock. Requested quantity/summary does not send supplier orders.

## Related Read-Path Correction

Previously dashboard/monitoring used the latest report per location; partial reports would hide other medicines' shortages. New `LatestNightlyItems::query()` resolves the latest approved counted result per medicine/location using a correlated `NOT EXISTS`, ordered by report date and ID. A new sufficient count removes only that medicine's old shortage. Other results remain visible with their count dates.

Historical comparison uses the same query strictly before the current report date, displaying each medicine's comparison date. Draft/submitted reports, other locations, and older backdated reports cannot incorrectly override the latest approved result. Source descriptions explain this behavior and do not imply unchecked medicines are sufficient.

## Changed Areas

- `NightlyCheckController`, `PrototypeController`, and new `app/Queries/LatestNightlyItems.php`.
- `database/migrations/2026_10_21_150000_add_scope_to_nightly_checks.php`.
- Nightly index/show/shortages, dashboard source labels, approved reports list, page description, onboarding, and `resources/js/daily-counts.js`.
- `SelectedNightlyCheckTest`, `test-selected-nightly-browser.cjs`, and isolated `selected-nightly` fixture phase.
- `php scripts/uat.php migrate`: loads private UAT environment, validates safety/database, runs additive migrations without seed/reset or the clinic `.env` connection.

## Actual Results

| Execution | Result | Evidence |
| --- | --- | --- |
| Full isolated SQLite suite, after unit dropdown | 124 tests / 1306 assertions passed | Ignored `storage/app/qa/inventory/phpunit-results.xml` |
| New feature cases | Twelve tests passed, included above | `tests/Feature/SelectedNightlyCheckTest.php` |
| Selected-check Playwright | 8/8 passed, desktop 1440 x 900 and mobile 390 x 844 | Ignored `storage/app/qa/selected-nightly-browser/results.json` |
| Operational Playwright regression | 13/13 passed | Ignored `storage/app/qa/inventory-browser/Browser_final.json` |
| MySQL competing workers | Nine scenarios passed | Actual output and private `storage/app/qa/inventory/mysql/` fixtures |
| Independent browser postconditions | Apotek 20 to 0; Gudang remains 50; one approved item; no reconciliation mismatches | Actual PHP fixture postconditions |
| Frontend build / Blade compile | Both passed | Actual build and `view:cache` execution |
| UAT separation | Clinic fingerprint unchanged, UAT grants limited, clinic read denied | `php scripts/uat.php verify` at 15:07 WIB; ignored `uat-isolation.json` |

Browser coverage includes default scope, search/selection persistence, empty-selection SweetAlert without POST, incomplete draft, blocked incomplete submission, explicit batch zero and requested quantity, real pharmacist approval, mobile overflow, and Guest restrictions. Writes/responses are not mocked. Inventory fixtures use randomly named temporary MySQL databases, removed afterward; persistent UAT and clinic data are not used as fixtures.

The selected-check browser suite was rerun after the dropdown change and verifies the selected master unit, absence of unrelated unit options, and persistence after draft save. The earlier 121-test/1280-assertion run preceded this follow-up. An initial mixed inline/block Blade directive caused a view compilation failure during the new tests; it was corrected to the template's existing inline directive style, then the full suite was rerun successfully. Failed executions are not counted as passes.

Screenshots `Pemeriksaan_Pilih_Obat_Desktop.png`, `Pemeriksaan_Pilih_Obat_Mobile.png`, and `Pemeriksaan_Tertentu_Terkirim.png` were opened and visually inspected. These ignored local files contain simulation data, not replacement Capstone evidence. No passwords appear in public docs/screenshots.

## UAT And Limitations

The scope migration was applied only to `shifastock_uat` through the guarded runner, without deleting/reseeding masters or transactions. Clinic database and main `.env` were not migrated/changed. Other installations need normal additive migrations before using this version; never use `migrate:fresh` on clinic/UAT data.

The existing port-4002 UAT server can use the feature after refreshing. Existing same-date/location reports retain their original scope. Formal staff acceptance remains pending; automated results are not manual acceptance.
