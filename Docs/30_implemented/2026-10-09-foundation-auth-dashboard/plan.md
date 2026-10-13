# Plan: Foundation, Auth, And Dashboard

## Summary

Build the initial ShifaStock Laravel foundation with login, role-aware application shell, dashboard, and shared inventory UI patterns.

## Goals

- Provide a working Laravel application for Klinik Shifa Medika.
- Add username-based login and logout.
- Create a clean clinical UI shell with sidebar and topbar.
- Provide dashboard views for demo and real operational data.
- Establish base roles and active account checks.

## Non-goals

- Do not build external integrations.
- Do not implement full production deployment automation.
- Do not add patient-facing features.

## Implementation Details

- Use Laravel session auth with username field.
- Add `EnsureActiveAccount` middleware.
- Build a shared app layout in Blade.
- Implement dashboard controller data mode for demo and real data.
- Use Chart.js for dashboard graphing.
- Use Tailwind CSS for the light clinical UI.

## Test Plan

- Login/logout manually with seeded accounts.
- Verify inactive accounts cannot continue.
- Verify dashboard route requires auth and permission.
- Run feature tests where environment supports database driver.
- Run `npm run build`.

## Acceptance Criteria

- Authenticated users land on dashboard.
- Logout works from the topbar.
- Dashboard can show demo and real mode.
- Layout is usable across the core pages.

## Assumptions

- The first environment is local/Laragon with MySQL.
- Demo users are local-only.

