# Implementation Report: Receipt Workflow Safety

## Date

2026-10-15

## Shipped

- Receipt history offers Edit Draf. Existing supplier, invoice, destination, physical receipt time, batch, expiry, and quantity values are restored into the entry form.
- Draft updates replace item rows inside a transaction and retain the original document reference/creator. Saving a draft does not create stock movements. Editing, posting, and cancellation lock the document and reject already posted receipts.
- Supplier/invoice duplicate checks ignore the current draft and compare case-insensitive values with surrounding whitespace removed. Drafts and posted documents both count as potential matches. Browser preflight uses SweetAlert; server-side validation requires explicit acknowledgment as well.
- Posting an older draft with a matching invoice redirects to its edit form for review. No duplicate stock is posted automatically.
- Editable POST/Livewire forms track unsaved values. In-app navigation uses SweetAlert; browser close/reload uses the browser's required native unload prompt. Restored invalid server input remains protected. Successful submission/medicine saves do not leave stale warnings.
- Receipt validation highlights the relevant cell, displays Indonesian messages and row numbers, and focuses the first invalid field. Required fields are applied only to populated rows; genuinely empty rows are ignored. Sparse row keys survive server validation, while removing rows updates visible labels and validation.
- Stock-shortage shortcuts prefill the medicine and transfer destination. Expiry shortcuts prefill medicine, batch, and source location for supplier returns or condition recording. Both view permission and inventory-operation permission are required for action links.
- Prefill query parameters are validated, including batch/medicine consistency. Following a shortcut does not write stock or create a transaction.

## Modules

- InventoryController, NightlyCheckController, web routes.
- Receipt, transfer, return, condition, shortage, expiry and layout views.
- Shared receipt-field-error and shortage-action Blade components.
- Receipt validation/duplicate preflight and reusable form-safety JavaScript.
- ReceiptWorkflowTest, browser-receipt-workflow.cjs, and read-only rendering fixture generation.

## Verification

- Full PHPUnit suite: **31 tests / 277 assertions passed**, using `php -d extension=pdo_sqlite vendor/bin/phpunit`. SQLite is enabled only for this process; local MySQL data are not reset by these tests.
- `npm run build`, `php artisan view:cache`, and `git diff --check`: passed.
- `php scripts/verify-inventory-usability.php --fixtures`: seven read-only local page-render checks passed.
- `tests/browser-receipt-workflow.cjs`: passed row-level validation, same-unit conversion, canceling navigation with unsaved values, native unload protection, duplicate-invoice cancel/confirm, and preservation of the draft submit action. Untouched forms remain clean; restored invalid input stays protected.
- Desktop (1440x1000) and mobile (390x844) screenshots reviewed. No document-level horizontal overflow on mobile; item columns scroll inside the table.
- Generated QA artifacts (ignored by Git): `storage/app/qa/inventory/receipt-desktop.png` and `receipt-mobile.png`.
- Browser checks use locally rendered HTML and real compiled assets. Duplicate responses and final submission are intercepted; persistence is tested separately with SQLite.
- Existing tests had stale label/route assertions from before the operational modules and simplified navigation shipped. Their expectations now cover the actual live menu/status labels, while retaining database and access-control assertions.

## Limitations

- Duplicate matching is a warning, not a database uniqueness constraint; deliberate continuation is allowed. Different supplier spellings are not treated as the same supplier.
- Draft item rows still require complete valid batch/expiry/quantity data to save. This work enables reopening/editing those drafts rather than introducing autosave or partial-row storage.
- Browser close/reload cannot display a custom SweetAlert; browsers control that prompt.
- Monitoring shortcuts do not choose the transfer source or quantity on the user's behalf. Stock availability is rechecked by existing backend rules when saving.
- SQLite concurrency tests do not simulate competing MySQL sessions; document locks are implemented but no concurrent-session stress test is claimed.

## Rollback

Revert these application changes together. No schema migration or bulk stock change is introduced by this work. Existing receipt and movement data remain intact.
