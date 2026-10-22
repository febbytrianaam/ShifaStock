# Shared Daily Draft Plan

Date: 2026-10-22 WIB. User request: petugas and apoteker can continue daily stock-check drafts created by either role, with clear creator and worker attribution.

## Scope

1. Inspect current routes, permission gates, draft ownership, batch validation, and review transactions.
2. Permit save/add/submit by an authenticated operational user while keeping the creator unchanged.
3. Add item-level last-editor snapshots and report activity history within the same write transaction.
4. Require draft versions and reject stale writes under the existing inventory lock; retain unsaved web input for comparison with a fresh tab.
5. Show creator, last worker, per-item attribution, activity history, and attribution in the standalone report.
6. Preserve creator-only cancellation, review authority, locked submitted/approved reports, zero/null semantics, inventory snapshots, and FEFO.
7. Exercise both role directions, rollback, attribution, stale input, concurrent workers, and existing browser workflows using isolated databases.
8. Apply only the additive migration to persistent UAT through its guarded runner; update operator/developer guidance. No clinic migration/reset, role grants, commit, or push.

## Acceptance

- Both role directions can continue a draft and add medicines without changing its creator.
- Unchanged rows retain their previous worker; successful actions record authenticated actor/time.
- A stale form cannot overwrite, submit, add to, or cancel a newer draft.
- No stock change occurs from saving a draft; all existing stock/review guards remain enforced.
- Legacy reports do not acquire invented operator history.

Implementation and actual verification: [report](implementation_report.md).
