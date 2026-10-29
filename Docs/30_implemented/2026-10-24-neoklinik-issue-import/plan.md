# Plan: Excel-Based Medicine Issues

## Scope

Add a file-based Neoklinik export importer inside Pengeluaran Obat. Keep manual issues available. No API integration, external credentials, patient records, master auto-creation, or changes to existing FEFO/permissions.

## Delivery

1. Parse the inspected 16-column XLSX format with a maintained parser; reject invalid/ambiguous/oversized inputs without storing the raw workbook.
2. Persist a private sanitized preview, explicit medicine/unit mappings, source identities, and a document-to-many-movements ledger.
3. Require a location and an initial recording boundary; lock that boundary on first posting. Explain the stock-opening/physical-count duplicate risk with an operator example.
4. Show compact per-drug/unit summaries with expandable source details. Keep mapping suggestions distinct from approved mappings.
5. Revalidate preview, permissions, master/unit, stock/batch reconciliation, counts/opening boundaries, and source fingerprints before atomic posting through existing stock services.
6. Detect overlapping exports and changed transactions independently of file name, row order, location, and generated OUT references. Preserve source identity after reversals.
7. Provide read-only history, original conversion snapshots, actor/audit tracking, and stock-card links. Expire unposted previews after seven days.
8. Test using in-memory SQLite, an isolated disposable MySQL database, synthetic workbooks, competing processes, and browser viewports. Apply the additive migration only to persistent UAT, without seeding or resetting it.
9. Follow-up: allow explicitly confirmed deletion of unposted/expired previews, using the same permission and inventory mutex. Preserve posted history, source ledger, mappings, and recording boundaries; record the deleting actor in audit.

## Acceptance Boundaries

- Upload never changes stock; unapproved mappings and unresolved rows block posting.
- New rows are atomic; duplicates are reported and skipped; changed source data requires review rather than automatic correction.
- Guest cannot upload, approve mappings, or post.
- No patient/doctor values or raw sale/request/dispense identifiers are retained in payloads or QA documentation.
- A single export cannot prove dispensing status, historical batch identity, compound meaning, or global key stability; operator confirmation and source clarification remain mandatory before real use.
- No production deployment, automatic clinical-file import, commit, or push is included in this implementation request.
