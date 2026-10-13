# Plan: Daily Stock Check And Stock Opname

## Summary

Implement daily physical stock checking, review/finalization by kepala apoteker, low-stock monitoring, history, and stock opname reporting.

## Goals

- Allow staff to create daily stock check drafts per date/location.
- Support saving, submitting, cancelling, reviewing, approving, and returning checks.
- Reconcile approved physical counts into stock balances.
- Expose shortage/low-stock monitoring and historical reports.
- Provide monthly stock opname recap from approved checks.

## Non-goals

- Do not model patient-level medicine usage.
- Do not create duplicate manual stock opname input separate from daily checks.
- Do not create automatic purchase orders.

## Implementation Details

- Create `nightly_checks` and `nightly_check_items`.
- Use stock balances as operational source.
- On approval, adjust stock balance and record an inventory movement for the difference.
- Add filters, pagination, printable view, copyable summary, and sticky table header.

## Test Plan

- Create a draft check.
- Save quantities.
- Submit check.
- Approve and verify stock balance reconciliation.
- Cancel draft with SweetAlert.
- Verify shortages/history/opname pages.

## Acceptance Criteria

- Approved checks become current stock truth for the location.
- Drafts can be cancelled safely.
- Kepala apoteker can approve or return submitted checks.
- Low/empty stock is visible from approved checks.

## Assumptions

- Daily physical checks are the current best way to account for medicine usage that is not tracked by prescription/POS integration.

