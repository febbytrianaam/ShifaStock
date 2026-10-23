# Daily Count Guidance Plan

Date: 2026-10-23 WIB.

User request: explain the stock-check workflow above the Pemeriksaan Stok form so operators understand initial stock, system stock, and physical quantities.

1. Replace the short generic description on the daily start page with an unframed explanation before the form.
2. Explain location/unit, zero versus unchecked, recorded transactions, and approval timing with a 10-to-2 tablet example.
3. Add a collapsed explanation in the counting workspace, using the report's actual location without duplicating the full start-page guide.
4. Preserve the compact full-width table, inventory rules, permissions, and separate print report.
5. Add rendered-page tests and real browser checks for readable desktop/tablet/phone guidance and keyboard expansion.
6. Use isolated test databases; do not reset clinic or persistent UAT data. Publication was authorized after implementation; see the implementation report.

Actual outcome: [implementation report](implementation_report.md).

Follow-up requested the same day: provide descriptions on hover/focus of the detail-table headings, with explicit non-submitting help buttons, Escape/outside dismissal, mobile equivalents, and tooltips that are not clipped by the table scroller. Reuse the existing Lucide library and native browser popovers; do not change stock or permission logic.
