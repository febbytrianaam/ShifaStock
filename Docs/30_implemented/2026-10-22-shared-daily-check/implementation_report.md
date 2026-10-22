# Shared Daily Draft Implementation

Date: 2026-10-22 WIB. Verification completed approximately 23:01 WIB.
Tested base commit: `af37ff08a23b84a710eaafddd3d1d28dec234878` plus the working-tree changes described here. The user subsequently authorized publishing the feature, tests, and documentation to `origin/main`; use Git history for the publication commit. No production deployment is part of this work.

## Delivered Behavior

- Petugas and kepala apoteker with the existing operational permission can continue either user's daily stock-check draft, add medicines, save, and submit. The original `created_by` is never replaced.
- Header and saved summary identify the creator and last successful worker. Rincian identifies the last updater/time per medicine. The standalone A4-portrait report includes creator, last worker, and item worker names.
- The Activity tab records creation, saves, medicine additions, submission, return, and approval with authenticated actor/name/time. It paginates 20 actions per page and reopens the Activity tab when navigating activity pages.
- Only fields or batch counts that actually change update the item attribution. Saving/submitting unchanged rows does not reassign their worker. Successful no-op saves are still logged as zero medicines updated.
- Actor name snapshots preserve the name used at the time, even after account rename. Older reports show unknown worker/history explicitly; no invented backfill is performed.
- Save/add/submit/cancel require a draft revision and compare it inside the existing locked inventory transaction. A successful action increments the version. A stale request fails atomically without recording false activity or changing inventory.
- A stale web form retains unsaved input and its old version. Its warning links to the latest report in a separate tab for comparison. There is no automatic merge or unsafe retry against a refreshed version.
- The report list also displays creator and includes the current version in creator-only cancellation forms.

## Boundaries Preserved

Existing menu/operational permission checks still apply; no role grants or matrix changes were made. Guest/view-only accounts cannot edit. Submitted/approved reports remain locked; a privileged return for correction reopens the draft to operators. Only the creator can cancel a draft, with a current version. Review authority, one report per date/location, count-unit and batch validation, null versus zero, explicit partial confirmation, stock snapshots, inventory reconciliation, and FEFO remain unchanged. Draft saves do not move stock.

## Files

- `app/Http/Controllers/NightlyCheckController.php`: shared-draft gates, attribution, activity queries/writes, and revision conflict protection.
- `database/migrations/2026_10_22_160000_add_shared_daily_check_tracking.php`: draft revision, nullable item editor snapshots, and report activity table.
- `resources/views/nightly/show.blade.php`, `index.blade.php`, `partials/medicine-row.blade.php`, `print.blade.php`: attribution, conflict recovery link, activity tab, and print metadata.
- `resources/css/nightly-workspace.css`, `resources/js/nightly-workspace.js`: wrapping/mobile tabs and initial activity-page selection.
- `tests/Feature/SharedNightlyCheckTest.php`: 15 focused regression cases; related existing daily tests now supply the required version and expect authorized shared editing.
- `scripts/test-nightly-workspace-browser.cjs`: two-role shared work, retained stale input, and reverse-direction additions, alongside existing responsive coverage.
- `scripts/test-inventory-mysql.php`: a real two-process shared-draft race; `scripts/capstone-final-browser.php` updates its versioned daily fixture.

## Actual Verification

Environment: Windows, PHP 8.5.9, PHPUnit 12.5.38, Laravel 13, local MySQL, Chrome/Playwright, Vite 7.3.7. Runtime node dependencies were provided through `NODE_PATH`; no passwords were added to source, documentation, or screenshots.

| Execution | Actual Result | Evidence |
| --- | --- | --- |
| `php scripts/test-inventory.php` with process timeout 900 s | 172 tests, 1777 assertions; initial pass about 32 s, pre-publication rerun about 51 s, SQLite `:memory:` | `storage/app/qa/inventory/phpunit-results.xml` |
| `php scripts/test-inventory-mysql.php` | Ten competing-worker scenarios passed; includes stale shared save, single approval, FEFO, reversal, duplicate references, unit/receipt, first receipts, and multi-transfer rollback | Actual command output; private ignored worker records under `storage/app/qa/inventory/mysql/` |
| `php scripts/capstone-final-browser.php nightly-ui` | 15/15 real HTTP cases passed, including both sharing directions and old-page rejection; original Gudang 50 / Apotek 20 unchanged, reconciliation clean | `storage/app/qa/nightly-workspace-browser/results.json` and PNGs |
| `php scripts/capstone-final-browser.php partial-nightly` | 7/7 passed; only counted Apotek medicine adjusted 20 to 18, Gudang stayed 50 | `storage/app/qa/partial-nightly-browser/` |
| `php scripts/capstone-final-browser.php regression` | 16/16 passed; operational batch/location, monitoring, authentication, Guest and posting checks | `storage/app/qa/inventory-browser/` |
| `npm.cmd run build` | Passed | Actual Vite output |
| Changed controller/PHP runner and CJS syntax; `git diff --check` | Passed | Actual command output |
| `php scripts/uat.php verify`, 23:00 WIB | Clinic inventory unchanged; UAT grant isolation passed; UAT read of clinic denied | `storage/app/qa/uat-isolation.json` |

Browser fixture databases and MySQL worker databases were created separately with simulated users and data and removed after successful runs. The 38 browser cases are the sum of three distinct suites, not 38 PHPUnit tests. Evidence stays in ignored local QA storage, not public Capstone artifacts.

Visual review opened the activity, stale warning, and pharmacist-created draft continued by staff screenshots. The workspace suite also checks responsive geometry from 320 px phones through 4K; the 320 x 740 layout was opened for inspection. Names/text fit and no new horizontal overlap was observed.

## Findings During Implementation

- Initial Blade output had adjacent closing directives that failed compilation; directive spacing was corrected before the passing runs.
- Required revision changed the daily request contract. Existing test fixtures were updated to supply the actual current version, including after review return, rather than weakening the production guard.
- A web test inspected flashed session data between redirect and GET, losing the transient error in the multi-request test harness. It now follows the actual redirect chain and asserts the rendered warning plus retained input. Independent real HTTP browser coverage also verifies the warning/link and preserved text.
- Final review found the list cancellation form also needed a hidden revision. It was added with a regression asserting the current value and non-creator restriction.

Earlier incomplete/failing runs are not counted as passing results above. A stale `Kendala_UI.png` from an older attempt may remain in ignored QA storage; it is not evidence of a current passing result. Use `results.json` and the named current screenshots.

## UAT Migration And Limitations

The additive migration was applied using `php scripts/uat.php migrate`, without seeds, reset, stock cleanup, creator reassignment, or role changes. The primary clinic database configured by `.env` was not migrated or written. Other environments must apply the migration to their intended database before serving this feature; do not run clinic migrations merely to verify UAT.

No persistent UAT report was edited to manufacture a worker history. Existing reports will begin accumulating activity on future successful saves. Historical actions before installation remain unknown. Activity is attached to its report and cascades on creator-authorized draft deletion, consistent with existing draft deletion behavior; this is not an immutable external audit archive.

Full PDF pagination/render regeneration and physical printing were not repeated for the new metadata; PHPUnit verifies report output. Manual clinic acceptance, cross-device user feedback, and production rollout are not claimed. See [operator guide](../../00_current/uat_guide.md) and [plan](plan.md).

## Follow-Up: Login 419 Triage

The user reported 419 on the public HTTPS UAT domain. A fresh isolated Chrome/Playwright context fetched `/login`: HTTP 200, HTTPS form action, CSRF field present, Secure/Lax host-scoped cookies, `Cache-Control: no-cache, private`, and Cloudflare cache status `DYNAMIC`. Submitting one generated nonexistent username returned HTTP 302 to login and a visible credential validation message, without 419. The first sandboxed domain attempt was network-denied; the permitted network retry produced these actual results. A local HTTP GET also returned the form, but its POST/login was not tested.

This demonstrates a working fresh-page CSRF round-trip; it does not prove successful valid-user login, reproduce the user's original browser session, or establish the exact cause. A stale/changed session remains a hypothesis. The operator guide explains opening a fresh HTTPS login page, comparing with Incognito, and not resubmitting the expired POST. No authentication code/configuration was changed, and CSRF checks, Secure cookies, application key, session data, credentials, and inventory were left intact. This diagnostic is separate from the 38 browser regression cases above.

## Publication Verification

On the user's subsequent commit/push request, the isolated backend suite was rerun against the final feature tree: **172 tests / 1777 assertions passed**, approximately 51 seconds, at about 23:30 WIB on 22 October 2026. Documentation additionally records the login triage without claiming a code fix. Browser, build, and MySQL counts above refer to the feature verification completed around 23:01, not new executions during publication. The package excludes unrelated Capstone screenshot/ZIP changes, their helper edits, private UAT profiles, and ignored QA evidence.
