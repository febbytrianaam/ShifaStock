# Unit And Concurrency Protection

Implemented: 2026-10-18.

## Unit Safety

Medicine model updates reject a changed/cleared stock unit when the medicine appears in stock balances, batch balances, movement history, receipt items, daily checks, or monthly opname items. Zero remaining stock does not erase history. The same model-level guard applies to ordinary Eloquent updates; application medicine saves run inside the shared transaction and roll back the entire edit when rejected.

Reference units cannot be renamed when associated medicines have inventory history. Unused medicines/units can still be corrected. Purchase-package factors remain editable independently. Direct SQL, migrations, and privileged database administration bypass Eloquent events and are not a supported way to convert historical stock units.

## Transaction Safety

An additive migration creates one inventory transaction mutex row. `InventoryTransaction::run` locks it before the application reads or changes inventory. Receipt posting/drafts, transfers, issues, opening stock, returns, adjustments, reversals, monitoring-parameter writes, daily checks, monthly sessions/recounts, medicine saves/import, and unit rename paths use the same wrapper. Existing inner balance/document locks remain. The wrapper retries transaction deadlocks up to three times and fails closed if the mutex row is missing.

Movement references are rechecked after acquisition so competing requests cannot pass validation simultaneously and post the same reference twice. Receipt-document references are similarly checked inside the transaction.

The global lock intentionally serializes writes for the current clinic workload. It is not a high-throughput multi-site architecture, and unusually long imports may delay posting. Apply the migration before enabling this application version; never remove the mutex row manually.

## MySQL-Specific Bug Found

MySQL JSON storage can reorder object keys. Strict PHP array-order comparison rejected valid daily/monthly snapshots after a database round-trip. Snapshot comparison now recursively orders keys before strict comparison, preserving value/type checks. The real MySQL daily-approval and monthly-finalization regressions exercise the round-trip; a unit regression covers reordered keys and changed values/types.

## Verification

- SQLite suite: 58 tests, 610 assertions passed.
- Separate MySQL runner: seven competing-process scenarios passed (FEFO oversell protection, reversal once, monthly finalization once, daily approval once, duplicate reference once, first receipt versus unit edit, and concurrent first balance creation).
- Each MySQL scenario uses real application controller/service paths with separate connections and verifies aggregate/batch/ledger agreement. The test observes the application's mutex; it does not add an outer serialization lock around controller calls.
- Disposable MySQL database removed after the run. A failed exploratory run also removed its database and exposed the JSON-ordering issue above.
- Additive mutex migration applied locally without rewriting stock.
- Blade compilation and diff checks passed; read-only clinic audit checked three medicine/location pairs and found no discrepancies.

## Deployment Checklist

1. Back up the destination database before deployment.
2. Run `php artisan migrate --force` to add the opname snapshot column and inventory mutex row before enabling writes.
3. Rebuild frontend assets with `npm run build` and refresh Blade caches as appropriate for the host.
4. Run `php artisan inventory:reconcile` and review any discrepancies without automatically rewriting historical stock.
5. Existing draft opname sessions without a snapshot must use Mulai Hitung Ulang before finalization.

## Audit Boundaries

These tests cover defined races, not all possible schedules, infrastructure failures, production load, or a complete security audit. HTTP RBAC is covered separately by feature tests; MySQL workers test controller/service transaction behavior directly. Duplicate supplier-invoice detection remains an operator-confirmed warning: different references with the same physical invoice are not automatically treated as the same transaction. Historical data are not rewritten and no automatic stock-unit conversion is introduced.
