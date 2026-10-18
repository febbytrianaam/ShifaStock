# README Refresh

Implemented: 2026-10-18.

## Changes

- Updated the root README with current receipt, batch, daily-count, opname/recount, monitoring, reporting, RBAC, and audit features.
- Documented transaction serialization, historical-unit protection, snapshot validation, and reversal safeguards without claiming exhaustive security coverage.
- Corrected PHP requirements to 8.4.1 or newer for the current locked dependencies; clarified that composer.json alone still declares ^8.3.
- Explained local demo seeding, hidden password entry, and the distinction between reference seeding and physical stock entry.
- Added isolated SQLite/MySQL test commands, read-only reconciliation, browser checks, deployment ordering, and links to detailed documentation.

## Verification And Scope

- README links to local documentation resolve successfully.
- README pattern checks found no private key, service token, password hash, populated application key, or populated credential environment assignment.
- README contains configuration variable names and demo usernames, not credential values.
- git diff checks passed. Application tests were not rerun for this documentation-only change; the README dates the previously completed 58-test / 610-assertion result and seven MySQL concurrency scenarios.
- The user's sensitivity check was clarified to cover README only. No repository-wide safety claim, history rewrite, credential rotation, data removal, or application change is part of this update.
