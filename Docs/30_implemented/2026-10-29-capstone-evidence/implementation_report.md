# Capstone Evidence Refresh - 29 October 2026

## Outcome

Documentation-only refresh based on clean application commit **`05ba910ce8804ffc9e049a17e96295bb59714cb9`**. The user requested evidence for Capstone Tugas II and then a single folder dated 29 October 2026. No feature, UI, permission, stock algorithm, or database schema was changed.

- [Report-ready dated folder](../../Capstone_Screenshots/2026-10-29/README.md): **15 PNGs**, eight primary and seven supporting, directly in one folder; suggested figures 22-29 with supporting a/b suffixes.
- [Technical evidence and captions](../../evidence/2026-10-29/README.md): source screenshots, actual logs/JUnit/JSON, manifest, and documentation-only reproduction scripts.
- [Visual verification](../../evidence/2026-10-29/Verifikasi_Visual.md): all images opened, readable content reviewed, no loading/error/credential exposure, and documented field-width limitations.

## Verified Features

| Category | Actual Browser Evidence |
| --- | --- |
| SS-01 transfer | Two items entered and actually posted under one reference; batch allocation follows FEFO; result history captured separately. |
| SS-02 shared daily draft | Petugas Simulasi creates and saves two counts, Apoteker Simulasi continues and saves the third; creator, last worker, and activity history verified. Remains draft. |
| SS-03 Excel issue import | Real upload/matching validation and actual posting from a synthetic workbook; two source transactions allocate across two batches. Not live API integration. |
| SS-04 stock per batch | Two batches show ED, stock by Gudang/Apotek, batch totals, and near-expiry/safe indicators. Locations are grouped within each batch row. |
| SS-05 returns and conditions | Three tablets returned from Gudang and two damaged tablets removed from Apotek; pre-posting forms and special-condition result distinguished in captions. |
| SS-06 batch dropdown | Same batch number stocked at both locations; changing Gudang to Apotek preserves the correct Apotek option/label and available batch quantity of 40. |

## Actual Executions

Started **14:03 WIB on 29 October 2026**. Final feature capture completed **14:16 WIB**. Backend and import results were run once in this session and reused during capture retries on the same baseline; retries are not additional test totals.

| Check | Actual Result | Evidence |
| --- | --- | --- |
| PHPUnit / isolated SQLite | **213 tests / 2347 assertions**, no failures, errors, or skips; JUnit 80.68 seconds | [JUnit](../../evidence/2026-10-29/testing/phpunit-results.xml), [log](../../evidence/2026-10-29/testing/PHPUnit.log) |
| Import browser / temporary MySQL | **16/16 groups passed** | [Browser JSON](../../evidence/2026-10-29/testing/Playwright.json), [log](../../evidence/2026-10-29/testing/Playwright.log) |
| Import MySQL postconditions | Stock and batch totals both 80, three unique source lines; concurrent workers/retry/overlap protection passed | [MySQL JSON](../../evidence/2026-10-29/testing/MySQL.json) |
| Additional feature browser | **5/5 groups passed**, covering the five non-import categories above | [Capture JSON](../../evidence/2026-10-29/testing/Feature_Capture.json) |
| Stock reconciliation | Zero mismatched medicine/location pairs and zero batch-identity issues; expected post-transaction balances verified | [Reconciliation](../../evidence/2026-10-29/testing/Capture_Reconciliation.json) |
| Frontend build | Passed | [Build log](../../evidence/2026-10-29/testing/Build.log) |
| Artifact checks | 15 distinct source PNGs at 1440 x 900, nonblank pixel samples, source/copy SHA-256 agreement, captions and links verified | [Source manifest](../../evidence/2026-10-29/manifest.json), [delivery manifest](../../Capstone_Screenshots/2026-10-29/manifest.json) |
| Cleanup | Read-only verification found zero generated evidence/import databases remaining | [Cleanup result](../../evidence/2026-10-29/testing/Cleanup_Verification.json) |

Runtime: Windows, PHP **8.5.9**, Laravel **13.35.0**, Chrome headless via Playwright; local SQLite/MySQL fixtures only. PHPUnit grouping in the readable report aggregates actual class-level counts without double counting nested suites.

## Safety and Limitations

- Existing 22 root PNGs for figure numbers 01-21, including 02b, stayed byte-identical during collection. Pre-existing dirty files elsewhere were preserved, not reverted or silently staged.
- Passwords and application keys were generated at runtime and passed through process environment, not embedded in documentation. No source clinical workbook, patient/doctor identity, `.env`, database dump, session, or private fixture profile is included.
- Initial attempts encountered real onboarding and SweetAlert controls and a wrong runner assumption about batch-row grouping. Actual failure records are retained and explained, not presented as application failures. The runner, not application code, was corrected.
- Windows temporary-server cleanup uses its private loopback listener to identify the owned PHP server; it does not stop the UAT/clinic server. Only generated database names are eligible for cleanup.
- Test PNGs are documentation tables rendered from actual artifacts, not new application features. Application screenshots are unmodified browser captures; flat-folder copies are byte-identical.
- Daily approval/finalization, expired-condition posting, physical printing, complete load/security assessment, and manual clinic UAT are not established by this evidence. Existing historical test counts remain separately dated.

## Publication

The initial handoff prohibited automatic commit/push. After delivery, the user explicitly requested **update docs, commit, and push**. This documentation package is therefore published separately; its Git commit is not relabeled as the tested application baseline.

Includes the dated screenshot folder, technical artifacts and sanitized logs, reproduction scripts, root/documentation/current/implemented indexes, and this report/plan. Excludes older root Capstone screenshot/manifest changes, ZIP archives, `scripts/verify-capstone.cjs`, `scripts/capstone-supplement.cjs`, and local analysis notes. No production deployment or Git history rewrite.

Rollback is documentation-only: reverting the publication commit removes this package and its links without changing application stock or schema. Do not roll back the existing feature commit just to remove these screenshots.
