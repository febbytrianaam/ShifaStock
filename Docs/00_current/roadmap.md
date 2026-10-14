# Roadmap

## Current Focus

Stabilize the current inventory workflows and validate them manually with real clinic scenarios before production use.

## Phase 1: Stabilization And QA

- Goal: confirm existing core workflows behave correctly across roles.
- Output: completed manual smoke test notes, fixed critical bugs, verified RBAC matrix.
- Gate: can staff complete receipt, transfer, daily check, return, adjustment, expiry monitoring, and stock card without developer assistance?

## Phase 2: Operational Hardening

- Goal: make production use safer.
- Output: backup/restore guide, deployment checklist, environment checklist, retention policy, production seed policy.
- Gate: can the app be restored from backup and operated without demo credentials or uncertain permissions?

## Phase 3: Reporting Improvements

- Goal: make reports more useful for recurring clinic operations.
- Output: improved stock card exports, expiry exports, shortage summaries, and filtered reports.
- Gate: can staff produce weekly/monthly inventory evidence directly from ShifaStock?

## Phase 4: Stock Policy And Procurement Support

- Goal: support minimum stock policy and supplier follow-up without overbuilding.
- Output: configurable minimum stock per medicine/location and printable/exportable purchase request recap.
- Gate: can low-stock data reliably inform purchase decisions?

## What Not To Build Yet

- Public patient-facing features.
- Complex supplier purchase order automation.
- Prescription, POS, or patient-service integration; ShifaStock stays focused on medicine inventory management.
- Multi-tenant clinic support.
- Advanced analytics until operational data quality is proven.

