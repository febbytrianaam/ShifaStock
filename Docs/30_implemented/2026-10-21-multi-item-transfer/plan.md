# Multi-Item Stock Transfers

Date: 2026-10-21.

## Request

Allow the Gudang-Apotek transfer form to enter multiple medicines together, like receipt item rows, instead of saving one medicine at a time.

## Implementation Scope

- One reference, source, destination, time, and document note, with up to 50 medicine/batch/quantity rows.
- Reuse searchable dropdowns, location-dependent batch identity, stock-unit context, existing FEFO service, inventory mutex, and ledger.
- Add/remove rows, SweetAlert validation/confirmation, clear per-row errors, retained failed input, and mobile table scrolling.
- Post every item in one transaction; never leave earlier rows committed if a later item fails.
- Preserve single-item payload compatibility, permissions, stock-card links, and movement-level reversal behavior. No draft-transfer workflow or new transaction tables.
- Test only on temporary databases. Do not seed, reset, or write inventory into persistent UAT/clinic databases.

## Verification

Full PHPUnit regression, MySQL concurrent posting, new desktop/mobile browser workflow, existing operational browser regressions, build, Blade compilation, and visual review. Actual results and initial test issues are recorded in the implementation report.
