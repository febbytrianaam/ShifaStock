# Plan: Automated Inventory Testing

1. Provide a one-command runner that forces isolated SQLite in-memory tests and generates JUnit output.
2. Test the full receipt/draft/post, FEFO transfer, outgoing stock, return, adjustment/reversal, daily count, monthly opname, report and stock-card cycle.
3. Compare aggregate stock, batch sums and movement ledger after each operation.
4. Add read-only reconciliation diagnostics, including records missing from one representation and wrong batch/medicine ownership.
5. Reproduce suspected daily-count discrepancies without guessing physical batch allocation.
6. Fix deterministic arithmetic defects proven by regression tests; document unresolved business-data gaps separately.
7. Run the complete suite and audit the current local database without modifying clinic stock.
