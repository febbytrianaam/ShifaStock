# ShifaStock Documentation

This folder is the AI-friendly documentation system for ShifaStock. It separates current product truth, working notes, future plans, implementation history, references, and archived material so humans and AI agents can continue the project without re-discovering the same context.

## Start Here

- [Partial daily submission and read-only explanations](30_implemented/2026-10-22-partial-daily-submission/implementation_report.md)

- [Dashboard stock monitoring alignment](30_implemented/2026-10-22-dashboard-stock-monitoring/implementation_report.md)

- [Dedicated daily-check print report](30_implemented/2026-10-21-daily-check-print-report/implementation_report.md)

- [Add medicines to daily drafts](30_implemented/2026-10-21-add-medicines-to-draft/implementation_report.md)

- [Selected daily physical checks](30_implemented/2026-10-21-selected-daily-check/implementation_report.md)

- [Product overview](00_current/product_overview.md)
- [Current state](00_current/current_state.md)
- [Architecture](00_current/architecture.md)
- [AI handoff](00_current/ai_handoff.md)
- [Testing strategy](00_current/testing_strategy.md)
- [Security](00_current/security.md)
- [Deployment, backup, and restore](00_current/deployment_backup.md)
- [Privacy and retention](00_current/privacy_and_retention.md)
- [Current roadmap](00_current/roadmap.md)
- [Local demo and UAT guide](00_current/uat_guide.md)
- [Capstone screenshots](Capstone_Screenshots/README.md)
- [Final Capstone testing](Capstone_Testing_Final/Ringkasan_Pengujian.md)
- [Next-step plan](20_plans/active/next-step-plan.md)
- [PRD completion manual QA plan](20_plans/active/prd-completion-manual-qa-plan.md)

## Folder Lifecycle

- `00_current/` contains factual truth about the product as it exists now. Keep it concise, current, and free of speculative brainstorming.
- `10_ai_workspace/` is for rough thinking, open questions, and decisions. Brainstorm in `brainstorming/`; durable decisions go in `decisions/` as ADRs.
- `20_plans/` is for future work. Use `active/` for ready or in-progress plans and `backlog/` for later ideas.
- `30_implemented/` is historical truth. Each shipped feature should get `YYYY-MM-DD-short-feature-name/` with `plan.md` and `implementation_report.md`.
- `40_references/` is for external research, screenshots, source material, and copied references.
- `90_archive/` keeps old or superseded docs for history.

## Rules For Future AI Agents

- Inspect the code before updating docs.
- Keep current truth in `00_current/`; keep ideas in `10_ai_workspace/` or `20_plans/`.
- Do not invent certainty. Write `Unknown` or `Assumption` where needed.
- After shipping a meaningful feature, add a folder under `30_implemented/` and update this README if the history index changes.
- Preserve old decisions and plans unless the user explicitly asks for cleanup.
- Prefer relative links between docs.
- Preserve legacy reference files in this `docs/` tree unless the user asks for a cleanup; older screenshots, prior handoff material, import notes, and prototype assets came from the previous `Docs/` folder.

## Implementation History Index

- [2026-10-21 Multi-item stock transfers](30_implemented/2026-10-21-multi-item-transfer/)

- [2026-10-21 Friendly error pages](30_implemented/2026-10-21-friendly-error-pages/)

- [2026-10-21 Isolated UAT and combined read-only Guest](30_implemented/2026-10-21-uat-guest/)

- [2026-10-21 Real dashboard, local demo cleanup, and first-login guide](30_implemented/2026-10-21-dashboard-onboarding/)

- [2026-10-09 Foundation, auth, and dashboard](30_implemented/2026-10-09-foundation-auth-dashboard/)
- [2026-10-09 Master medicine and import workflow](30_implemented/2026-10-09-master-medicine-import/)
- [2026-10-09 Daily stock check and stock opname](30_implemented/2026-10-09-nightly-check-stock-opname/)
- [2026-10-09 RBAC, user CRUD, and audit log](30_implemented/2026-10-09-rbac-users-audit-log/)
- [2026-10-13 Inventory workflows and batch stock](30_implemented/2026-10-13-inventory-workflows-batch-stock/)
- [2026-10-13 UI, validation, and monitoring polish](30_implemented/2026-10-13-ui-validation-monitoring-polish/)
- [2026-10-13 Documentation system](30_implemented/2026-10-13-ai-documentation-system/)
- [2026-10-14 PRD gap completion](30_implemented/2026-10-14-prd-gap-completion/)
- [2026-10-14 Superadmin controls](30_implemented/2026-10-14-superadmin-controls/)

Use [the implementation report template](30_implemented/implementation_report_template.md) when recording future shipped work.

- [2026-10-19 Capstone screenshots and final testing](30_implemented/2026-10-19-capstone-final-testing/)

