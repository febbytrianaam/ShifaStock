# Inventory Flow Safeguards

Implemented: 2026-10-16.

## Fixes

- Special adjustments require a batch. Server validation rejects wrong medicine ownership and insufficient source batch stock. All accepted changes update aggregate stock and batch stock together.
- Adjustment quantities must be at least 0.0001 with at most four decimal places, preventing a positive request from becoming a zero-value database movement.
- The batch selector follows medicine, location, and direction. Existing zero-balance batches are available for increases only. New batches still use receipt or opening-stock workflows.
- Forced reduction conditions notify dependent controls when the direction changes.
- Updating one's own account preserves the existing role instead of unconditionally promoting the editor to administrator when user-management permission has been delegated.
- Reversal eligibility is checked again after locking the original movement in the transaction.
- Monthly opname saves and finalizations lock and reload the session status. Finalization rejects missing batch details and batch quantities changed since the session snapshot.

## Verification

- Isolated SQLite suite: 51 tests, 541 assertions passed.
- Regression coverage includes mandatory/wrong batches, zero-balance increases, adjustment reversals, guest denial, delegated self-update, stale opname quantities, and repeated finalization.
- Production frontend build, Blade compilation, local read-only page rendering, and diff checks passed.
- Local read-only inventory audit: three medicine/location pairs, no discrepancies.

## Scope And Remaining Risks

Follow-up on 2026-10-18: [Opname audit and recount](../2026-10-18-opname-audit/implementation_report.md) resolves the missing net-zero/new-batch/unit checks, recount workflow, and quantity-validation pass described below. The original limitations are retained as historical context.

Reviewed stock route middleware, medicine Livewire authorization, login/logout, user self-update, special adjustments, reversals, and monthly opname save/finalization. This is a targeted code review, not a comprehensive penetration test or MySQL concurrent-load test.

Monthly opname validation compares recorded batch quantities; it does not detect net-zero intervening movements, newly stocked batches outside the session, or stock-unit changes. A stale session is now blocked from finalization, but a guided restart/recount workflow is still needed. Do not bypass the guard by editing database snapshots. Historical batchless data are not rewritten automatically. Existing four-decimal quantity handling in other transaction endpoints merits a separate consistency pass.
