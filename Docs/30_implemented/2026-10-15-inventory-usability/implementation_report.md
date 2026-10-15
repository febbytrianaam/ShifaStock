# Implementation Report: Inventory Usability

## Date Completed

2026-10-15

## What Shipped

- Receipt entry uses spreadsheet-style rows with searchable database medicines. The dropdown can extend outside the table viewport.
- Input and receipt history are separate tabs. Successful entry opens history; pagination preserves the tab.
- Supplier invoice numbers are stored separately from generated receipt references and included in movement notes.
- Invoice units come from the selected medicine's packaging and stock unit. Operational packaging takes precedence over import proposals.
- Same-unit receipts always use conversion 1, including server-side normalization. Missing package conversions are not silently treated as 1.
- Stock quantity is calculated from invoice quantity and conversion; the visible result is read-only.
- Medicine operational packaging is shown separately from expandable research/import proposals. Packaging edits update the existing package instead of silently leaving the old default behind.
- Transfer, issue, return, and special adjustment forms show the stock unit and available batch quantities for the selected medicine/location/batch.
- Transfer and issue forms show an estimated FEFO batch allocation. Saving still uses the existing transactional stock checks.
- Damaged, lost, dropped, and expired conditions always decrease stock; both UI and backend enforce this.
- Daily approval shows current system stock, physical count, and expected stock change, with a SweetAlert confirmation.
- Monthly opname explains its cutoff and difference adjustment separately from approved daily reports.
- Monitoring distinguishes physical inspection results from current system stock and shows the latter's update time.
- Transaction reversal is labeled Batalkan Transaksi and explains the linked counter-movement.
- Quantity formatting removes trailing zeroes without discarding real fractions throughout stock views, monitoring, reports, and printed cards.

## Earlier Work Included In This Delivery

- Direct stock-card links from transfer source/destination.
- Printable single-logo clinic stock card with configured clinic address.
- Sidebar follow-up label correction.
- Reference workbook and the previously used medicine-review import script. Database import results are local data, not a fresh automatic import on deployment.

## Main Modules

- InventoryController, NightlyCheckController, MedicineIndex, InventoryReceipt, Quantity formatter.
- Receipt, transfer, issue, return, special adjustment, monitoring, daily approval, opname and stock-card views.
- Shared stock-entry context component, searchable dropdowns and receipt JavaScript.
- Additive migration: supplier_invoice_number on inventory_receipts.
- QuantityTest and InventoryUsabilityTest regression coverage.

## Verification

- `php artisan migrate --force`: additive supplier invoice migration applied successfully to local MySQL.
- `php -d extension=pdo_sqlite vendor/bin/phpunit --filter 'InventoryFlowTest|InventoryUsabilityTest|QuantityTest|GuestRoleTest'`: 10 tests, 90 assertions passed. SQLite was enabled for this command only; system PHP configuration was not changed.
- Regression tests cover receipt/transfer/count stock flow, returns, special adjustments, guest restrictions, independent supplier invoice storage, same-unit conversion normalization, and damaged-stock direction enforcement.
- Broader regression checks exposed optional notes being read as mandatory on returns/adjustments; both now handle missing notes safely.
- Existing stock-flow test now explicitly selects real dashboard data and freezes its clock so a physical-count movement falls within the tested stock-card period.
- `php scripts/verify-inventory-usability.php`: seven read-only local MySQL page-render checks passed (receipt input/history, transfer, issue, return, special adjustment, monitoring).
- `php artisan view:cache`: passed.
- `npm run build`: passed.
- Changed PHP syntax checks and `git diff --check`: passed.
- Browser automation discovery timed out, so interactive visual QA and screenshots are not claimed as completed.

## Known Limitations

- FEFO previews and approval comparisons are a page-load snapshot; other transactions can change availability before saving.
- Available stock in the form is explicitly batch stock. Physical-count adjustments can leave aggregate stock different from batch totals; existing server checks remain authoritative.
- Historical receipts retain a blank supplier invoice number until there is a supported correction workflow; generated references are not reinterpreted as supplier invoice numbers.
- Review-import proposal fields remain available for legacy data. Operators should confirm operational packaging against the physical pack/faktur.
- No integration with Neoklinik is introduced.

## Rollback Notes

Revert application changes together. Roll back the new migration only after preserving supplier invoice numbers, because its down migration removes that column. Existing stock and movement records are not rewritten by this migration.
