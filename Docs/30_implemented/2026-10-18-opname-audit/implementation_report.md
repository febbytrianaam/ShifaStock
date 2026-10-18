# Opname Audit And Recount

Implemented: 2026-10-18.

## Confirmed Findings And Fixes

- Monthly opname compared batch amounts but missed intervening transactions whose net change was zero. New session snapshots store location and medicine movement revisions, aggregate quantities, all known batch balances, and stock-unit identities/names.
- Finalization now rejects changed transactions, new stock/batches, changed units, incomplete legacy snapshots, and empty sessions. Inconsistent aggregate/batch totals cannot become a new snapshot.
- Draft sessions support Mulai Hitung Ulang, protected by the same view/operate permissions as input. SweetAlert explains that all physical counts will be cleared. Previous saved counts and snapshots are preserved in the audit log; new item IDs prevent an old browser form from overwriting the refreshed session.
- Finalized sessions cannot be recounted. Legacy drafts without snapshots need recount before finalization. Historical final sessions are not rewritten.
- Receipt draft posting previously silently selected another location when its intended destination became inactive. Posting now stops and asks the operator to edit the destination explicitly. No fallback location receives stock.
- Receipt, transfer, issue, opening-stock, return, and special-adjustment quantities now enforce the database's four-decimal scale and per-value limit. Monthly physical counts enforce the same scale while allowing zero. Receipt conversion products are rounded to the storage scale and zero results are rejected.
- The isolated test runner accepts a bounded timeout override for slow hosts; it still uses SQLite in memory, never the clinic database.

## Verification

- Full isolated suite: 55 tests, 591 assertions passed. A first run timed out at the default 180 seconds; the completed rerun used INVENTORY_TEST_TIMEOUT=900.
- Synthetic page fixtures and Blade compilation passed.
- Browser daily-count regression passed.
- Browser opname recount: SweetAlert cancel/confirm, intercepted POST destination, and desktop/mobile overflow checks passed. Screenshots inspected; tests wait for sidebar transition completion before the mobile capture.
- Local MySQL additive snapshot migration applied successfully; no stock or existing session snapshot was automatically rewritten.
- Local read-only reconciliation: three medicine/location pairs, no discrepancies.

## Remaining Boundaries

Follow-up: [Unit and concurrency protection](../2026-10-18-unit-and-concurrency-protection/implementation_report.md) implements historical-unit guards and real MySQL concurrent-process regression coverage. The limitations below describe this earlier audit pass.

This is a targeted inventory-flow audit, not a complete penetration test or a proof of MySQL concurrent-load safety. Snapshot checks and record locks are covered by sequential regression tests; competing live database sessions still need explicit concurrency testing. This pass does not introduce automatic historical-stock repair or unit conversion of existing inventory. Master-data unit changes for medicines with history merit a separate policy decision and regression pass. Receipt duplicate-invoice warnings remain warnings with explicit confirmation, not a universal concurrent-posting idempotency guarantee.
