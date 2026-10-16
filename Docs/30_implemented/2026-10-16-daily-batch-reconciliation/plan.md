# Daily Batch Reconciliation Plan

## Objective

Prevent daily physical-count approval from changing total stock without updating the physical batch balances.

## Completed Scope

1. Store explicit batch counts and a submission-time stock snapshot.
2. Validate medicine ownership, whole-unit counts, stock unit, complete positive batches, and total consistency.
3. Apply total and batch differences with per-batch ledger movements in one approval transaction.
4. Reject changed stock while entering or reviewing a report; return and recount instead of guessing batch allocation.
5. Add compact batch entry, automatic totals, SweetAlert validation, and unsaved-change protection.
6. Verify persistence with isolated regression tests and interactions with intercepted browser fixtures.

## Boundaries

Do not rewrite historical stock automatically. Keep existing whole-unit daily counts. Legacy submitted reports without snapshots must be returned and submitted again.
