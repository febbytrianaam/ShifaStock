# Product Overview

## Summary

ShifaStock is a Laravel-based inventory monitoring application for Klinik Shifa Medika. It helps clinic staff manage medicine master data, incoming stock, stock transfers, daily physical checks, expiry monitoring, low-stock monitoring, stock cards, reports, users, role permissions, and audit logs.

## Target Users

- Petugas Farmasi: daily stock operations, receipts, transfers, returns, checks, and monitoring.
- Kepala Apoteker: medicine governance and review/finalization of stock checks.
- Administrator Sistem: user management, RBAC matrix, and audit log review.
- Guest: view-only demo access.

## Core Problem

The clinic needs a practical way to track medicine stock across locations, monitor expiry and shortages, and reconcile physical stock counts without relying only on scattered spreadsheets or manual notes.

## Current Value Proposition

ShifaStock provides one operational source for medicine master data, stock balances per location, batch balances, stock movements, draft/finalized receiving documents, transfer history, nightly physical checks, expiry monitoring, and role-based access.

## Main Workflows

- Login with username/password.
- Manage master medicine data and verification status.
- Receive medicine into warehouse, either as draft or posted document.
- Transfer stock between warehouse and pharmacy using explicit batch or automatic FEFO allocation.
- Return stock to PBF/supplier.
- Record special stock adjustments such as damaged, lost, dropped, unclaimed, or expired medicine.
- Perform daily physical stock checks and review/finalize them.
- Monitor low stock, shortage requests, and expiry by batch/location.
- Generate stock card and inventory reports.
- Manage users, RBAC permissions, and audit logs.

## What Success Looks Like

- Staff can find and update stock data without duplicate spreadsheets.
- Stock movements are traceable through references, batches, locations, and creators.
- Expired or soon-to-expire batches are visible before they become operational risk.
- Guest/demo role can inspect workflows without changing data.
- RBAC prevents unauthorized write actions.

## Assumptions And Unknowns

- Assumption: the first production database target is MySQL, matching local Laragon setup.
- Assumption: use in the clinic is currently internal, not public internet exposure.
- Assumption: ShifaStock is standalone for medicine inventory management and does not integrate with patient-service systems.
- Unknown: final retention policy for audit logs and stock movement history.
- Unknown: production hosting, backup, and monitoring plan.

