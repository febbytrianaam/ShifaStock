# Selected Daily Physical Checks

Date: 2026-10-21, Asia/Jakarta. Status: implemented and verified.

## Scope

Allow a daily report to cover all eligible masters or only medicines selected by the operator. This is not an independent purchasing workflow.

## Completed Steps

1. Persist scope, defaulting existing reports and legacy requests to complete checks.
2. Add a searchable checkbox list at report creation, retained selections, and SweetAlert feedback.
3. Store only selected medicines as report items; retain existing draft/submission, per-batch validation, review, locking, and stale-stock checks.
4. Preserve one report per date/location; reopening must not replace scope or items.
5. Resolve latest approved results per medicine/location for monitoring, dashboard, and historical comparison.
6. Verify isolated PHPUnit, MySQL workers, real browser flow, mobile layout, and Guest restrictions.
7. Apply the additive migration to UAT only without resetting tester transactions; record results and limitations.
8. Follow-up: replace count-unit free text with a master-linked dropdown, preserve incomplete-master/stale-draft warnings, and rerun tests before publication.
