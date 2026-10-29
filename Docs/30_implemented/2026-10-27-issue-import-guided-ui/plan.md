# Plan: Guided Excel Issue Import

## Request

Simplify the Neoklinik export import for nontechnical pharmacy staff, without weakening stock or transaction safeguards.

## Scope

1. Separate file selection, medicine matching, and final review into three steps.
2. Reuse valid approved mappings while keeping them inspectable. Express conversion as the contents of one source unit.
3. Require explicit matching approval; allow optional bulk confirmation only for visible valid choices and invalidate approval on edits.
4. Group blockers by cause with concrete next actions. Preserve recording boundaries rather than recommending unsafe date changes.
5. Stack input/review tables on small screens. Retain history, deletion, and Guest read-only behavior.
6. Verify using synthetic fixtures and isolated SQLite/MySQL databases, actual browser runs, and frontend build. Record evidence and update operator guidance.

## Boundaries

No clinical workbook import, persistent inventory cleanup, schema change, permission change, partial posting, automatic mapping approval, or modification of FEFO/cutoff/duplicate rules. No remote push or production deployment.

## Completion

Implemented and verified on 2026-10-27. See `implementation_report.md`. Manual clinic usability testing remains pending.

## Follow-up Requests

Keep the medicine list high on the page, move document context to the information rail, and add Excel-like numbers/dates/source detail without exposing clinical identities. For clinic staff, retain a compact grouped worklist, use plain column/status wording, and provide local medicine search that never discards entered selections. These follow-ups are implemented and verified in the report; inventory and privacy boundaries remain unchanged.
