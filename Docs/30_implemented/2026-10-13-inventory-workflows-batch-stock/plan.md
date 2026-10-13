# Plan: Inventory Workflows And Batch Stock

## Summary

Expand inventory operations beyond basic receipts/transfers by adding receipt documents, batch-level stock, returns, special adjustments, stock card, dependent searchable dropdowns, and FEFO transfer support.

## Goals

- Track batch stock per location.
- Support multi-item receipt documents with draft/final posting.
- Support supplier/PBF returns.
- Support special stock adjustments.
- Add stock card report with required filters.
- Add automatic FEFO allocation when transfer batch is omitted.
- Make medicine and batch selection searchable and dependent on selected medicine/location.

## Non-goals

- Do not build supplier purchase orders.
- Do not integrate patient prescription usage.
- Do not replace current aggregate stock balances.

## Implementation Details

- Add `batch_stock_balances`.
- Add `inventory_receipts` and `inventory_receipt_items`.
- Extend `InventoryStockService` for batch stock and FEFO allocation.
- Add new routes and permissions for returns, stock card, and special adjustments.
- Add searchable select JavaScript with dependency support.
- Add server-side validation for batch/location stock availability.

## Test Plan

- Test receipt draft and post.
- Test transfer with explicit batch.
- Test transfer without batch using FEFO split.
- Test return and special adjustment.
- Test stock card filters.
- Run `InventoryFlowTest`.
- Run build and Blade compile checks.

## Acceptance Criteria

- Batch stock stays aligned with aggregate stock.
- Transfer cannot take stock from unavailable batch/location.
- Stock card shows movement history with running balance when location/date are selected.
- Dropdowns avoid mismatched medicine/batch/location selections.

## Assumptions

- Batch stock starts from posted receipts.
- Historical stock before this feature may not have full batch-level detail.

