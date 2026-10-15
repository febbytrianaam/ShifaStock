# Plan: Receipt Workflow Safety

Implement the five approved usability follow-ups:

1. Reopen and edit receipt drafts; keep posted documents immutable.
2. Warn about matching supplier and invoice numbers, with explicit acknowledgment before continuing.
3. Protect unsaved forms during navigation and browser close/reload.
4. Show receipt validation beside the exact row/field, retaining user input.
5. Add permission-aware monitoring shortcuts that prefill transaction forms without changing stock.

Use existing receipt transactions, audit logger, SweetAlert, RBAC, and stock validation. Verify persistence and access controls with isolated SQLite tests; verify browser interactions against locally rendered fixtures and built assets without writing stock to the live MySQL database.
