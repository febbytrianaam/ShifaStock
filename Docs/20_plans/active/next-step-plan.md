# Next-Step Plan: Manual QA And Production Readiness Pass

## Summary

Run a structured manual QA pass across all roles and core inventory workflows, then fix any blocking issues before adding new product scope.

## Goals

- Verify current workflows from the browser, not only from tests.
- Confirm RBAC behaves correctly for administrator, petugas farmasi, kepala apoteker, and guest.
- Capture any remaining confusing UX, validation, or reporting issues.
- Identify production readiness gaps.

## Non-goals

- Do not add prescription/POS integrations.
- Do not replace the RBAC system.
- Do not redesign the full UI.
- Do not create purchase order automation.

## Implementation Details

1. Seed local demo data.
2. Login as each role.
3. Test menu visibility and route access.
4. As petugas, create receipt draft, post receipt, transfer stock, create return, create special adjustment, and create daily check draft.
5. As kepala apoteker, review and approve/return daily check.
6. As guest, verify view-only behavior.
7. Check dashboard real/demo mode, expiry filters, stock card, reports, shortage monitoring, and audit log.
8. Check superadmin reference/settings menus, RBAC reset, role preview, and audit CSV export.
9. Document results in a dated folder under `docs/30_implemented/`.

## Test Plan

- Run `npm run build`.
- Run `php artisan view:cache` then `php artisan view:clear`.
- Run `php artisan test` after enabling `pdo_sqlite`.
- Perform manual browser checklist above.

## Acceptance Criteria

- No role can access unauthorized write routes.
- Core inventory flows update stock and batch balances correctly.
- User-facing validation messages are clear.
- Expiry and stock card filters return understandable data.
- Superadmin controls are visible only to administrator and do not lock out core admin access.
- Known blockers are documented or fixed.

## Assumptions

- Local database can be reset or seeded safely during QA.
- `pdo_sqlite` can be enabled for full test runs.
- The user will confirm any clinic-specific workflow edge cases.

