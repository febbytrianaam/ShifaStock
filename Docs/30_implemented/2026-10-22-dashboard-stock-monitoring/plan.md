# Dashboard Stock Monitoring Alignment

Date: 2026-10-22, Asia/Jakarta. Scope: dashboard minimum-stock widget, not inventory rules or permissions.

1. Reproduce the mismatch: no approved daily checks, but current transaction stock is at or below its configured minimum.
2. Share the existing minimum-stock query between Monitoring and the dashboard KPI/list.
3. Label the dashboard source explicitly, link to the filtered minimum-stock section, and distinguish missing configuration from sufficient stock.
4. Preserve historical daily-check monitoring, the minimum threshold rule, FEFO, RBAC, and persistent UAT/clinic data.
5. Execute isolated backend and real browser regressions, inspect desktop/mobile screenshots, and perform a read-only UAT comparison.
