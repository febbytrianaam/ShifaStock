# Plan: PRD Completion Manual QA

## Goal

Verify the PRD completion work in the browser with realistic clinic workflows before commit/push or production use.

## Scope

- Confirm RBAC visibility for administrator, petugas farmasi, kepala apoteker, and guest.
- Verify the new Stok Awal workflow.
- Verify Penerimaan Obat draft, post, and cancel draft behavior.
- Verify Penerimaan Obat destination location and conversion snapshot behavior.
- Verify Pengeluaran Obat with explicit batch and FEFO automatic allocation.
- Verify Transfer Gudang-Apotek rejects expired or insufficient batch stock.
- Verify Retur PBF and Kondisi Khusus reduce or adjust stock correctly.
- Verify Kartu Stok running stock, movement labels, and reversal behavior.
- Verify Parameter Monitoring changes expiry horizon and minimum stock monitoring.
- Verify Stock Opname Periodik session creation, save, and kepala apoteker finalization.
- Verify Laporan period filters and CSV export.
- Confirm user-facing terminology says "stok", not "saldo", in operational screens.

## Test Data Needed

- At least two active medicines with units and dosage forms.
- At least two active locations: Gudang and Apotek.
- At least two batches for one medicine, one earlier expiry and one later expiry.
- One expired batch to validate rejection from transfer/outgoing stock.
- Users for administrator, petugas farmasi, kepala apoteker, and guest.

## Acceptance Criteria

- Guest can view allowed pages but cannot post write actions.
- Petugas can perform operational stock actions but cannot finalize stock opname or reverse final mutations unless the matrix explicitly grants it.
- Kepala apoteker can finalize stock opname, reverse final mutations, and manage monitoring parameters/master medicine.
- Administrator can manage users, permissions, and audit log; operational access follows the matrix.
- No finalized transaction is deleted; reversal creates a linked movement and updates stock.
- Reports reconcile Stok Awal, Masuk, Keluar, and Stok Akhir for the selected period.
- Browser confirmations use SweetAlert2 for destructive or final actions.

## Known Blocker

Automated feature tests currently cannot run in this local PHP environment because `pdo_sqlite` is unavailable. Browser QA and MySQL-backed local verification are the next best checks until the test driver is fixed.
