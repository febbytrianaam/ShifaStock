# Daily Batch Reconciliation

Implemented: 2026-10-16.

## Behavior

- Physical differences require explicit counts for each currently stocked batch. Totals are calculated from entered batch counts; zero must be entered explicitly for an empty batch.
- Approval updates aggregate stock, batch balances, and batch-specific movement history atomically. Same-total redistribution is supported.
- Batch ownership, stock units, complete counts, and matching totals are validated server-side.
- Submission captures quantities, batches, stock unit, and the latest relevant movement ID. Approval rejects intervening transactions, including net-zero changes. The web form also detects transactions after the page was opened.
- Existing aggregate/batch inconsistencies block submission rather than being silently assigned to a batch.
- Old submitted reports without snapshots must be returned and resubmitted. Previously approved history remains readable.
- Batch fields use one compact JSON payload to avoid PHP input-variable truncation on large medicine lists. SweetAlert validation and unsaved-change protection include these fields.

## Verification

- `php scripts/test-inventory.php`: 48 tests, 495 assertions passed.
- `npm run build`: passed.
- Local fixture rendering: passed.
- `tests/browser-daily-batches.cjs`: validation, totals, unsaved changes, compact submission, and desktop/mobile overflow checks passed.
- Additive migration applied to local MySQL; existing stock records were not rewritten.
- `php artisan inventory:reconcile`: three medicine/location pairs checked, no discrepancies.

Browser fixtures intercept submission and do not write clinic stock. Backend tests use isolated SQLite; these results do not replace MySQL concurrent-load testing or verification of physical shelf quantities. Daily counts retain the existing whole-unit restriction. Historical discrepancies, if later detected, still require verified manual reconciliation.
