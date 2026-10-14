# Deployment, Backup, And Restore Checklist

This document is the production-readiness checklist for ShifaStock. Fill the unchecked items with the actual clinic hosting details before real operation.

## Environment

- PHP 8.3 or newer with `pdo_mysql`, `mbstring`, `openssl`, `ctype`, `json`, `tokenizer`, `xml`, `fileinfo`, and `curl`.
- MySQL or MariaDB database dedicated to ShifaStock.
- Composer install with `--no-dev` in production.
- Node/Vite assets built with `npm run build`.
- Web server points to Laravel `public/`.
- `APP_ENV=production`, `APP_DEBUG=false`, and a stable `APP_KEY`.
- HTTPS enabled before non-demo users access the app.

## Release Steps

1. Pull the intended Git commit.
2. Run `composer install --no-dev --optimize-autoloader`.
3. Run `npm ci` and `npm run build` when frontend assets changed.
4. Run `php artisan migrate --force`.
5. Run `php artisan optimize:clear`.
6. Run `php artisan config:cache`, `route:cache`, and `view:cache`.
7. Verify login, dashboard, master obat, penerimaan, transfer, pengeluaran, kartu stok, stock opname, laporan, and audit log.

## Backup Policy

- Database backup: at least daily while the clinic is operating.
- File backup: include `.env`, uploaded/import reference files if any, and storage files used by the app.
- Keep at least one off-machine backup copy.
- Test restore on a separate database before trusting the backup process.

## Restore Procedure

1. Put the app in maintenance mode: `php artisan down`.
2. Restore the database dump to the target database.
3. Restore required storage files.
4. Confirm `.env` points to the restored database.
5. Run `php artisan migrate --force` only after confirming the restored schema state.
6. Clear caches: `php artisan optimize:clear`.
7. Bring the app online: `php artisan up`.
8. Verify stock balance, batch balance, kartu stok, and audit log against the expected restore point.

## Retention Notes

- Inventory movements, stock opname sessions, receipt documents, audit logs, and user management history should be retained long term unless the clinic approves a deletion policy.
- User accounts should be deactivated instead of deleted when they have operational history.
- Demo credentials must be changed or removed before production use.

## Open Production Decisions

- Exact hosting provider and server owner.
- Backup storage location and retention duration.
- Restore-test schedule.
- SSL certificate renewal owner.
- Whether exports need to be archived outside ShifaStock.
