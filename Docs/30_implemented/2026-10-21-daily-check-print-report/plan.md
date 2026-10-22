# Daily Check Print Report

Date: 2026-10-21, Asia/Jakarta. Status: implemented and verified.

1. Replace printing the operational page with a dedicated authenticated report view using the daily-check read permission.
2. Print saved report data only: clinic identity, scope/status, operator/reviewer, physical quantities, conditions/requests/notes, and saved batch counts.
3. Warn before opening a report when the current draft has unsaved changes; do not silently autosave or print stale values without feedback.
4. Use A4 portrait (user-requested follow-up on 2026-10-22; originally landscape), repeat table headers, include all saved items irrespective of UI filters/pagination/master activation, and distinguish uncounted from zero.
5. Verify role access, read-only behavior, escaping and stored units, real browser navigation, generated PDF contents/page geometry, and rendered pages using isolated simulation data.
