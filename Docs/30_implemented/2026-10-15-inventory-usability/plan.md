# Plan: Inventory Usability

## Scope

- Simplify medicine packaging fields; separate operational packaging from research/import proposals.
- Separate system receipt reference from supplier invoice number.
- Keep receipt entry focused on spreadsheet-style item rows and move history to a separate tab.
- Explain stock-changing actions with plain Indonesian labels.
- Show stock units, available batch stock, and an estimated FEFO allocation before transfers/issues.
- Clarify daily count approval, monthly opname, monitoring data sources, and transaction cancellation.
- Prevent damaged, lost, dropped, and expired conditions from increasing stock.
- Preserve fractional stock while removing redundant decimal zeroes.
- Document verification, commit changes locally, and push to the existing main branch.

## Verification

- Run focused quantity-format and inventory regression tests.
- Apply the additive supplier invoice migration.
- Compile Blade templates and build frontend assets.
- Check changed PHP syntax and Git diff whitespace.
- Record any unavailable database-test or browser verification honestly.
