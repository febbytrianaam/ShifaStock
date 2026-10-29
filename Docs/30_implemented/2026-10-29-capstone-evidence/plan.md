# Capstone Evidence Refresh Plan

Date: 2026-10-29. This records the scope of the completed documentation handoff, not a proposal to modify application features.

## Scope

1. Verify application baseline `05ba910` and inspect actual routes, views, and existing runners.
2. Preserve the original 21 figure numbers and all 22 existing root PNGs, including figure 02b.
3. Use temporary local databases and synthetic accounts/medicines; never seed, clear, or post against persistent clinic/UAT stock.
4. Capture multi-item transfers, shared daily drafts, Excel issue import, stock per batch, returns/damaged stock, and correct location-dependent batch selection through real browser interactions.
5. Rerun PHPUnit, import-browser/MySQL checks, and frontend build. Render readable test tables only from actual XML/JSON; retain raw evidence and automation-attempt limitations.
6. Inspect all images, dimensions, hashes, privacy, and cleanup. Distinguish pre-posting forms, posted outcomes, and saved drafts from approvals.
7. Package eight primary and seven supporting images in one dated folder with captions, index, and manifest; keep technical artifacts separate.
8. Update implemented/current indexes and publish only this scoped package after the user's explicit commit/push request.

## Exclusions

- No application UI, feature, permission, inventory rule, or schema changes.
- No production deployment, clinic migration, actual patient/doctor data, credential publication, or history rewrite.
- No claim that automated checks replace manual clinic UAT.
- No inclusion or rollback of unrelated pre-existing Capstone screenshot edits, ZIP archives, helper changes, or local analysis notes.

## Completion

All capture and verification steps completed. See [implementation report](implementation_report.md) and [actual technical evidence](../../evidence/2026-10-29/README.md). Publication is separately authorized by the user's subsequent request; Git history identifies its documentation commit.
