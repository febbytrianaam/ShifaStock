# Partial Daily Submission And Clear Validation

Date: 2026-10-22, Asia/Jakarta. Tested working tree based on `77ecbd2`; packaged with the previously pending draft-addition, portrait-print, and dashboard changes for the authorized commit/push. Use Git history for the publication hash; test metadata records the pre-commit base.

## Behavior

Choosing Semua obat or Obat tertentu defines the initial list, not a requirement to enter invented zeros for unexamined medicines. Draf can be saved with blank rows. When sending a mixed report, SweetAlert states the counted/uncounted totals and offers Kirim N obat, Simpan Draf, and Kembali. The backend independently requires the validated boolean `confirm_partial` for mixed submission and rejects an entirely blank report, even with confirmation. Existing full submissions do not need partial confirmation.

Blank quantities remain null; explicit zero is a counted empty stock. The original list and its `scope` are retained, including unchecked notes/requests. Only counted rows receive stock snapshots or enter approval. Approval requires at least one counted row and applies the existing quantity/unit/batch/stale-snapshot checks to each counted medicine. Unchecked medicines do not receive stock adjustments, batch mutations, ledger entries, or last-counted timestamps. Changes to unchecked stock do not invalidate a checked medicine's snapshot; changes to checked stock still block approval atomically.

Detail, review, WAG summary, and A4 portrait report show counted/uncounted coverage and explicitly warn that unexamined medicines are not empty. The UI says Daftar awal instead of implying that every listed medicine was fully examined. Historical/latest-count queries preserve previous valid counts; unchecked rows are excluded from history and the period's physical-count recap.

Validation now groups problems by cause, shows the number of affected medicines and at most three example names per group, and bounds the scrollable message height. All dynamic names use DOM `textContent`, not unescaped HTML. Perbaiki isian resets the relevant table filters/page, expands batch details, and focuses the first invalid field. Units and per-batch reconciliation are not guessed or relaxed. Cancelling a blocked daily submission also clears the provisional navigation allowance so dirty-form protection remains active.

## Read-Only Explanation

The reported non-editable table was the existing ownership guard, not a number-input defect. A read-only UAT check found that the 167-row draft was created by the pharmacist-role account; the user confirmed viewing it from the pharmacy-staff account. The report now explicitly explains read-only mode: view-only permission, another draft owner, submitted status requiring return for correction, or approved status. No owner reassignment, permission change, draft deletion, or operational UAT write was performed. Only the owning operator can edit a draft; submitted reports need the existing pharmacist correction flow.

## Actual Verification

| Check | Actual Result | Evidence |
| --- | --- | --- |
| Full isolated SQLite suite | 157 tests / 1621 assertions passed, including ten partial-submission tests and read-only messages | Ignored `storage/app/qa/inventory/phpunit-results.xml`; `tests/Feature/PartialNightlySubmissionTest.php` |
| New real HTTP browser suite | Seven cases passed: partial draft, cancel, save from confirmation, mobile send, pharmacist review, all-blank rejection, grouped/escaped warning with cross-page focus | Ignored `storage/app/qa/partial-nightly-browser/results.json` |
| Partial browser stock postconditions | Apotek 20 to 18; Gudang remains 50; unchecked medicine gets no new balance; inventory/batch/ledger reconciliation passed | Actual PHP postconditions in `capstone-final-browser.php partial-nightly` |
| Existing daily browser regression | 15/15 passed with updated explicit partial-confirmation expectations, plus independent approval postconditions | Ignored `storage/app/qa/selected-nightly-browser/results.json` |
| Existing operational browser regression | 16/16 passed with independent Gudang/Apotek and reconciliation checks | Ignored `storage/app/qa/inventory-browser/Browser_final.json` |
| Real PDF checks | One-page approved and eight-page long draft PDFs remain A4 portrait; all 70 medicine codes and repeated headings present, no orphan closing page; all nine rendered pages reviewed | Ignored `Laporan_Final_Pendek-1.png`, `Laporan_Final_Panjang-1.png` through `-8.png`, and actual pypdf checks |
| Build and syntax | Vite build, PHP/Node syntax, focused formatting, and whitespace checks passed | Actual commands |

Browser fixtures use random temporary MySQL databases removed afterward, not clinic or persistent UAT inventory. The stress draft has 30 simulation medicines, including a literal HTML-like name, to verify escaped grouping, bounded examples, and pagination. Screenshots capture the actual application after SweetAlert animations finish, not a substituted/mock interface. No physical-printer or manual staff acceptance test was performed. No schema migration or reseeding is needed.

## Findings During Verification

An initial backend assertion still expected the old generic items error; it now correctly expects explicit partial confirmation, without removing batch, ownership, or stale-stock checks. The first new browser runner assumed only one batch-details element; selection was corrected to the intended first medicine. Its HTML-safety assertion was also scoped to user-content HTML rather than SweetAlert's own built-in image element. Initial incomplete/failed runs are not counted as passing; complete reruns passed. Screenshot capture now waits for actual popup animations to finish.

## Limits

This is a physical-count report, not an independent purchase request. Partial submission locks the entire report until it is returned for correction; unchecked medicines can be counted after return, not silently added to an already approved report. One report per date/location, owner-only draft editing, FEFO, ledger rules, and permission boundaries remain unchanged.
