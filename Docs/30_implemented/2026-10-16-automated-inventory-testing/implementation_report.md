# Implementation Report: Automated Inventory Testing

## Date

2026-10-16

## Shipped

- `php scripts/test-inventory.php` runs the full suite with forced SQLite `:memory:` configuration, a unique unused config-cache path, and optional process-local SQLite extension loading. Missing SQLite stops the runner before running database tests.
- JUnit output: `storage/app/qa/inventory/phpunit-results.xml` (generated and ignored by Git).
- Integration tests exercise mixed box conversions in one receipt, draft posting, FEFO allocations across two batches, outgoing stock, supplier return, damage adjustment, reversal, unchanged daily count, batch-specific monthly opname, reports and printable stock cards.
- Aggregate stock, batch sums and net movement ledger are compared after every batch-aware step.
- Negative cases verify expired batch rejection, insufficient eligible stock despite enough total stock, and insufficient source-location stock, without partial stock writes.
- `php artisan inventory:reconcile [--json]` audits the configured database without changing records. It includes pairs found only in the aggregate table, only in batch balances, or only in the ledger, and detects wrong batch/medicine identity.
- Audit exit codes: 0 for no detected discrepancy, 1 for discrepancy. The JSON report includes checked-pair count, mismatched-pair count, per-pair quantities/differences, and batch identity problems.

## Defect Reproduced And Fixed

FEFO could incorrectly reject an exact transfer of 0.8 from batch quantities 0.1 and 0.7 because binary floating-point subtraction left a tiny positive remainder. FEFO remainders and balance additions now round to the database's four-decimal precision. A regression test consumes the exact available amount and checks aggregate, batch and ledger agreement.

## Confirmed Gap, Not Automatically Fixed

Historical finding from the initial testing pass. Resolved by the subsequent [daily batch reconciliation implementation](../2026-10-16-daily-batch-reconciliation/implementation_report.md) on 2026-10-16. The diagnostic regression now requires rejection of a physical difference without verified batch counts; the combined suite passes 48 tests and 495 assertions. The initial reproduction below is retained as the reason for that fix, not a description of current behavior.

A separate diagnostic test creates stock 10 and approves a daily physical count of 7. Aggregate stock and ledger become 7, but batch stock remains 10. The audit correctly reports a stock-minus-batch difference of -3 and exits 1. This test intentionally proves detection, not batch-level reconciliation.

Actual physical-count differences need verified batch counts or an explicitly approved allocation workflow. No automatic batch guess, stock rewrite, or migration of clinic data is included.

## Verification

- `php scripts/test-inventory.php`: **38 tests / 367 assertions passed**. JUnit report generated at `storage/app/qa/inventory/phpunit-results.xml`.
- Local MySQL read-only audit on 2026-10-16: 3 medicine/location pairs checked, 0 mismatches, 0 batch identity issues.
- That local result describes the current recorded data only; it does not eliminate the reproduced daily-count gap or verify physical shelf stock.
- New service and command PHP syntax checks passed.
- `git diff --check`: passed.
- No browser UI or frontend assets changed in this work.

## Limits

- SQLite regression tests do not simulate competing MySQL sessions or prove production backup restoration.
- The reconciliation service loads grouped pairs in memory; it is suitable for the current clinic dataset, not a benchmark for large multi-site databases.
- No UAT claim is made about how clearly staff understand labels or whether medicine packaging data match physical packs.

## Rollback

Remove the runner, audit service/command and new tests together if needed. Reverting the four-decimal arithmetic fix restores the reproduced FEFO bug. No schema migration or clinic stock modification needs reversing.
