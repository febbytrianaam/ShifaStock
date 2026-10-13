# Plan: RBAC, User CRUD, And Audit Log

## Summary

Add user management, role permission matrix, guest role, and audit logs for administrative accountability.

## Goals

- Allow administrator to create, edit, and deactivate users.
- Add matrix-based permissions by role.
- Add guest role with view-only access.
- Add audit log screen and logging for important admin actions.
- Prevent admin from losing required core admin access.

## Non-goals

- Do not introduce Spatie Permission unless needed.
- Do not expose demo passwords in docs.
- Do not allow profile/password self-service outside admin policy.

## Implementation Details

- Add `Role` enum.
- Add `Permissions` support class.
- Store matrix values in `role_permissions`.
- Add user CRUD controller/views.
- Add `audit_logs` table and audit log viewer.
- Apply `can:*` middleware to menu routes.

## Test Plan

- Verify matrix defaults for each role.
- Verify admin locked permissions remain enabled.
- Verify guest can view but not operate.
- Verify user CRUD writes audit records.
- Verify unauthorized routes return 403.

## Acceptance Criteria

- Admin can manage users and permissions.
- Guest role is safe for demos.
- Role changes affect menus and routes.
- Audit log shows key administrative actions.

## Assumptions

- Current project size does not require Spatie Permission.
- Password updates are handled by admin/superadmin workflow.

