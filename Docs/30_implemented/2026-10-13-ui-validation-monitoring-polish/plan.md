# Plan: UI, Validation, And Monitoring Polish

## Summary

Improve operational usability by making UI cleaner, reducing redundant sections, adding SweetAlert validation/confirmations, improving dashboard attention states, and adding filters to expiry monitoring.

## Goals

- Make topbar/sidebar/dashboard cleaner and less redundant.
- Replace browser `confirm()`/native validation where it confused users.
- Add informative validation prompts for required form fields.
- Add useful filters to expiry monitoring.
- Keep visual style light and clinic-appropriate.

## Non-goals

- Do not redesign the product into a dark command-center UI.
- Do not build a new frontend framework.
- Do not add new product scope beyond monitoring/filtering and UX polish.

## Implementation Details

- Use SweetAlert2 for confirmations and required-field prompts.
- Mark major forms with `data-required-alert` and `novalidate`.
- Add generic `data-confirm-action` SweetAlert handler.
- Add expiry monitoring filters: search, location, status, ED date range.
- Add attention styling for critical/near-expiry/low-stock states.
- Use page tabs for grouped related pages.

## Test Plan

- Submit forms with missing required fields and verify SweetAlert message.
- Test nonaktifkan pengguna confirmation.
- Test expiry filters individually and in combination.
- Run `npm run build`.
- Run Blade compile check.

## Acceptance Criteria

- Users get clear warnings when required form inputs are missing.
- Browser confirm dialogs are not used for app actions.
- Expiry monitoring can be filtered by medicine/batch, location, status, and ED date.
- UI remains light, compact, and clinic-friendly.

## Assumptions

- SweetAlert2 is acceptable for validation/confirmation consistency.
- Staff prefer clear Indonesian messages over native browser bubbles.

