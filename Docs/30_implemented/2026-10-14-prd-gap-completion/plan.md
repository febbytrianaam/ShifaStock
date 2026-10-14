# Plan: PRD Gap Completion

## Goal

Close the remaining implementation gaps identified from the ShifaStock PRD review.

## Scope

- Add outgoing stock / medicine issue workflow.
- Add traceable initial stock workflow.
- Add monthly stock opname sessions with snapshot, physical count, and pharmacist finalization.
- Add linked reversal for finalized stock movements.
- Add monitoring parameter UI for expiry horizon and minimum stock by location.
- Use minimum-stock parameters in monitoring.
- Reject expired batches for transfer/outgoing stock.
- Improve periodic reports with period reconciliation and CSV export.
- Add production deployment, backup, and restore documentation.
- Standardize user-facing terminology to use "stok" instead of "saldo" for clinic staff screens.

## Validation

- PHP syntax check for changed controllers/services.
- Route registration check.
- Database migration run.
- Blade compile check.
- User-facing terminology scan for remaining "saldo" text in app views and messages.
