# Security

## Current Security Posture

ShifaStock uses Laravel session authentication, username/password login, active account checks, route-level authorization gates, and a database-backed RBAC matrix. Passwords are hashed through Laravel model casts.

## Auth And Access Status

- Auth routes are handled by `SessionController`.
- Protected routes use `auth` and `active` middleware.
- Feature access uses `can:*` middleware mapped to permission names in `Permissions`.
- Administrator has locked access to dashboard, user management, permissions, and audit logs.
- Guest role is view-only by default.

## Known Risks

- Production deployment state is unknown.
- Password reset/change workflow is intentionally restricted; current policy says password updates should go through super/admin user management.
- Demo/local accounts must not be reused in production.
- RBAC matrix changes need manual verification.
- Audit logs include potentially sensitive operational metadata.

## Secrets Handling

- `.env` must not be committed.
- `LOCAL_DEMO_PASSWORD` is local-only and intentionally not documented with a value.
- No external API secrets are known in the current codebase.

## Abuse And Rate-Limit Considerations

- No explicit login rate limiting was confirmed during this inspection.
- If deployed beyond a trusted local/internal network, add or confirm Laravel login throttling, HTTPS, secure cookies, backups, and monitoring.

## Deployment Security Notes

Before production use:

- Set `APP_ENV=production`, `APP_DEBUG=false`, and a strong `APP_KEY`.
- Use HTTPS.
- Use unique production passwords.
- Run migrations with `php artisan migrate --force`.
- Restrict database access.
- Configure backups.
- Review file permissions for `storage/` and `bootstrap/cache/`.

