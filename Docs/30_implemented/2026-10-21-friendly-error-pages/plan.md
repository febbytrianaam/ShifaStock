# Friendly Error Pages

Date: 2026-10-21.

## Scope

- Replace Laravel's standard HTML error views for HTTP 403, 404, and 500.
- Use clinic identity, a light responsive layout, and practical Indonesian instructions.
- Keep recovery navigation safe after failed stock writes; never automatically repeat a POST.
- Keep views independent of the database, authentication/session reads, Vite, and JavaScript.
- Preserve exception reporting, status codes, JSON responses, permissions, and stock rules.

## Verification

- Regression-test real Guest permission denial, unknown routes, server exceptions, and JSON responses.
- Render views without frontend assets or database/session/authentication dependencies.
- Use an isolated Playwright process for desktop 1440 x 900 and mobile 390 x 844 checks.
- Verify actual UAT 404 rendering without changing clinic/UAT transactions.
- Run the isolated full PHPUnit suite and frontend build.

Status: implemented; see the implementation report for executed results and limitations.
