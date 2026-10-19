# Capstone Screenshots And Final Testing

Documentation finalized: 2026-10-19. Implementation and test evidence were produced on 2026-10-18 WIB.

## What Shipped

- Original application screenshots for 18 modules, plus a separate test-evidence image, RBAC matrix supplement, and recorded diagnostic evidence. Test data and accounts are simulated; screenshots are not modified to imply success.
- A readable offline test table derived from the initial JUnit/log evidence, explicitly identified as documentation rather than an application module.
- A fix for duplicate batch values across locations in the shared searchable-select component. Klik/select and submit now preserve the selected option index. Dependency changes find the same batch at the selected location or clear an incompatible choice; accessibility state follows option identity.
- Twelve new PHPUnit cases covering ten requested functional groups and two backend location regressions, plus an isolated live HTTP Playwright regression runner.
- Actual logs, source hashes, scenario tables, before/after screenshots, and limitations for Capstone sections F.2/F.3/F.4 and Table 2.

Controller queries deliberately provide balances from multiple locations for client-side filtering. The bug was selecting the first native option with the same batch value after the menu had already filtered correctly. No stock service, FEFO rule, schema, permission, or route changed.

## Files And Evidence

- Application: `resources/js/app.js`.
- Tests: `tests/Feature/CapstoneFunctionalTest.php`.
- Documentation runners: `scripts/capstone-capture.*`, `scripts/capstone-test-report.*`, `scripts/verify-capstone.cjs`, and `scripts/capstone-final-*`.
- [Initial screenshot package](../../Capstone_Screenshots/README.md).
- [Functional scenario table](../../Capstone_Testing_Final/Hasil_Pengujian_Fungsional.md).
- [Bug cause, fix, and regressions](../../Capstone_Testing_Final/Laporan_Perbaikan_Bug.md).
- [Final execution summary](../../Capstone_Testing_Final/Ringkasan_Pengujian.md).
- [Final artifact verification](../../Capstone_Testing_Final/Bukti_Pengujian/Verifikasi_Akhir.json).

## Tests Run

These are recorded executions, not new tests claimed on the documentation-finalization date.

| Execution On 2026-10-18 WIB | Actual Result |
| --- | --- |
| Initial screenshot evidence | 58 PHPUnit tests / 610 assertions; 7 MySQL scenarios; build passed |
| Final PHPUnit, SQLite in-memory | 70 tests / 733 assertions; 0 failure, error, or skipped |
| Final MySQL competing-process checks | 7 scenarios passed; generated database removed |
| Final frontend build | Vite build exit 0 |
| Final Playwright, live HTTP and simulated MySQL | 13 cases passed, including prefill at both locations, validation, posting, related dropdowns, monitoring, and guest access denial |
| Backend verification after browser posting | Gudang remained 50; Apotek changed from 20 to 18; 2 medicine/location pairs reconciled without discrepancies |
| Final evidence checks | 8 PNGs at 1440 x 900; hashes, local report links, source consistency, and secret-pattern checks passed |

Tested base: `edf1faa8e51f97927b1f78337c69b230b58be2d6` plus the then-uncommitted application fix and tests. This is not a claim that the clean base commit contains the fix. Evidence records source SHA-256 values; packaging/documentation changes after execution do not constitute a fresh functional test run.

## Deviations And Manual QA

- Native hidden options initially matched a broad Playwright selector; it was narrowed to the actual searchable menu.
- The guest test initially expected a CSRF meta tag; it now reads the original logout form token. Both automation failures are retained separately from final results.
- SweetAlert screenshots wait for the original animation to finish. No DOM/response replacement or screenshot compositing was used for deliverables.
- Screenshots were visually reviewed. Manual UAT by clinic staff, printer checks, and exhaustive cross-device verification remain **Belum Diuji**.
- Documentation paths and publication use the canonical `Docs/` directory recorded in Git, even though Windows may display it as `docs/`. New evidence must not create a second case-sensitive directory on GitHub.

## Limitations And Rollback

The initial screenshot package intentionally preserves the pre-fix finding and 58-test evidence. The final testing package supersedes its open batch-label finding, not its historical images. Passing bounded tests is not a complete security audit or production-load guarantee.

All databases used for capture/browser/concurrency were separate and removed afterward. No clinic data, credentials, session tokens, or production deployment are included. No Git history rewriting is performed.

To roll back the application fix, revert its changes in `resources/js/app.js` and rebuild assets; doing so reintroduces the reproduced duplicate-option label problem. No schema or inventory-data rollback is required. Documentation and runners can be removed separately without changing operational stock.
