# ADR-0001: Tech Stack

## Status

Accepted

## Context

ShifaStock is an internal clinic inventory application. It needs authentication, role-based access, server-rendered operational pages, database-backed workflows, fast local development, and enough frontend behavior for charts, confirmations, and searchable selects.

The repository currently uses Laravel, Livewire, Blade, Tailwind CSS, Vite, MySQL, Chart.js, and SweetAlert2.

## Decision

Keep the current Laravel-centered stack:

- Laravel 13 and PHP 8.3+ for backend, routing, validation, auth, and database access.
- Blade and Livewire 3 for server-rendered UI and master medicine interactions.
- Tailwind CSS 4 and Vite for frontend assets.
- MySQL as the runtime database.
- Chart.js for dashboard charts.
- SweetAlert2 for user-friendly confirmations and validation prompts.

## Consequences

- The app stays simple for local clinic deployment and maintenance.
- Server-side validation and authorization remain the source of truth.
- Livewire can handle data-heavy forms without building a separate SPA.
- Frontend interactivity remains lightweight.
- The team must keep PHP, Composer, Node/npm, and MySQL environments healthy.

## Alternatives Considered

- Full SPA with API backend: rejected for now because the app is mostly operational forms and tables.
- Spatie Permission: deferred because the current RBAC matrix is small and project-specific.
- SQLite-only deployment: not chosen for runtime because README and local setup target MySQL.
- Dark dashboard/admin template: not chosen because the clinic context needs a clean, light, work-focused UI.

## Date

2026-10-13

