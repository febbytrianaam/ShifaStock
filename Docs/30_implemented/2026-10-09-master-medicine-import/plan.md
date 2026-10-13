# Plan: Master Medicine And Import Workflow

## Summary

Create database-backed master medicine management and import support for researched medicine data.

## Goals

- Store medicine master data in the database.
- Support search, pagination, verification status, active status, units, and medicine metadata.
- Import/preview researched medicine data.
- Keep unverified imported medicines safe until reviewed.

## Non-goals

- Do not automatically create stock balances from imported medicine rows.
- Do not integrate KFA/BPOM APIs yet.
- Do not create advanced packaging conversion workflow beyond stored metadata.

## Implementation Details

- Add inventory foundation tables and expanded medicine metadata.
- Build `MedicineIndex` Livewire screen.
- Add import service reading bundled data.
- Track verification and active status.
- Keep master medicine operations behind permission checks.

## Test Plan

- Verify medicine list loads from database.
- Search by code/name.
- Toggle active status.
- Verify imported rows remain reviewable before operational use.
- Run relevant feature tests.

## Acceptance Criteria

- Staff can inspect and manage master medicines.
- Imported candidates do not automatically create stock.
- Operational medicine choices are limited to active medicines with required metadata.

## Assumptions

- Imported research data is a starter dataset, not final clinical truth.
- Unit and dosage metadata must be reviewed by clinic staff.

