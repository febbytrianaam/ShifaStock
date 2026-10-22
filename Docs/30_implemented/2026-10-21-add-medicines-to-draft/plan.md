# Add Medicines To Daily Drafts

Date: 2026-10-21, Asia/Jakarta. Status: implemented and verified.

## Scope

Allow the owner of a daily-check draft to add missing medicines without cancelling the report or losing current count inputs. Do not change Stok Awal permissions, stock calculations, FEFO, or submitted/approved report restrictions.

## Completed Steps

1. Add a searchable, multi-select dialog listing eligible medicine masters not already in the report, with Lucide add/close icons and cancel/Escape support.
2. Submit additions through the existing count form so current total, unit, batch counts, shortage flag, requested quantity, and note are saved together.
3. Lock the draft inside the inventory transaction, validate ownership/status/master eligibility, preserve existing item IDs, and append new items with blank counts.
4. Reject invalid/stale requests atomically and prevent duplicate report rows. Require newly added items to be counted before submission.
5. Verify isolated PHP regressions, actual HTTP browser flow across desktop/mobile, existing MySQL scenarios, build, Blade, and UAT isolation. Document evidence without publishing credentials or modifying tester data.
