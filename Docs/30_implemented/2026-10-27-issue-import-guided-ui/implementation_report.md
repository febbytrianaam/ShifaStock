# Guided Excel Issue Import

## What Changed

Completed 2026-10-27 in the working tree based on commit `2d2e8ef0bfded0f39c164e8ed7fb4c109a909459`. This report describes uncommitted work, not a new release or deployment.

The previous page combined recording setup, mapping, warnings, history, and posting. The import now has three distinct steps:

1. **Pilih file:** file/location and a short introduction; existing recording settings and history collapse out of the primary form. Initial recording setup remains explicit.
2. **Cocokkan obat:** show missing/invalid mappings first, keep saved choices inspectable, and explain conversion as `1 BUTIR pada Excel berisi: 1 Tablet`. Exact codes remain suggestions. Optional bulk confirmation only affects visible valid selections; editing medicine/factor clears approval.
3. **Periksa & simpan:** show quantities, grouped blocking reasons and actionable instructions, then mandatory attestations and the final SweetAlert stock-reduction confirmation. Local review filters do not select a subset for posting.

Matching/review tables stack on tablet/phone. Guest is forced into read-only review even when requesting the matching step explicitly. Saving mappings routes to the next applicable step without posting stock.

## Safeguards Preserved

Immutable recording cutoff after first new posting, explicit source/unit approval, required final attestations, atomic stock writes, FEFO allocation, source identity and duplicate/changed-source detection, stock-card history, protected posted documents, and backend permissions remain in force. Guidance never recommends moving a cutoff to force old transactions through.

No migration, clinical workbook import, persistent UAT stock write, account/permission edit, production deployment, commit, or remote push was performed for this UI change.

## Files And Modules

- `app/Http/Controllers/IssueImportController.php`: step routing, readable validation attributes, mapping redirect.
- `app/Services/IssueImportService.php`: presentation flags for mapping validity and new source groups; no allocation/posting rule change.
- `app/Support/IssueImportGuidance.php`: readable titles and corrective actions for existing blockers.
- `resources/views/components/issue-import-steps.blade.php`: accessible step navigation.
- `resources/views/inventory/issue-imports/{index,match,show}.blade.php`: separate forms, conversion wording, grouped warnings and review filters.
- `resources/js/issue-imports.js`: period disclosure, matching visibility, explicit bulk approval, invalidation and local filters.
- `resources/css/app.css`: scoped stacked matching/review rows.
- `tests/Feature/IssueImportTest.php`, `scripts/test-issue-import.php`, `scripts/test-issue-import-browser.cjs`: routing, consent, date guidance, responsive and inventory regression coverage.
- Current-state, implementation indexes, and UAT operator guide updated.

## Actual Verification

Fresh executions on 2026-10-27, PHP 8.5.9, Laravel test environment with isolated SQLite plus disposable MySQL/browser fixtures. Browser evidence was recorded at 11:44 WIB; all test fixtures are simulations.

| Check | Result | Local evidence |
| --- | --- | --- |
| Focused PHPUnit import suite | 28 passed, 311 assertions | `storage/app/qa/neoklinik-import-analysis/Import_Ux_PHPUnit.xml` |
| Full PHPUnit suite | 207 passed, 2,196 assertions, zero failures/errors/skipped | `storage/app/qa/neoklinik-import-analysis/Full_PHPUnit_Import_Ux.xml` |
| Playwright | 12 scenario groups passed, no recorded browser errors | `storage/app/qa/issue-imports/e3ee5a749b1e6201/Playwright.json` |
| MySQL posting/concurrency | Passed; stock total and batch total both 80; three unique source lines | Same directory, `MySQL.json` |
| Frontend build | `npm run build` passed | Actual terminal execution |
| PHP formatting | Targeted Laravel Pint passed | Actual terminal execution |

The focused suite is included within the full suite, not 28 additional tests. Browser groups include nested viewport/scenario checks and are not counted as PHPUnit tests.

Playwright checks upload without stock changes, explicit searchable mapping approval, saved-choice reuse, bulk confirmation excluding blanks and resetting on edits, grouped cutoff/mapping guidance, non-writing review filters, FEFO/card links, reimport deduplication, changed-source blocking, draft deletion/cancellation, posted-history protection, and Guest denial. Main screens were checked at 2560, 1440, 768, and 390 px widths; stacked input tables were also checked on tablet/phone.

Earlier runner failures were test-harness issues: a bulk checkbox correctly refused to stay selected with no eligible choices, and a mutable fixture date shifted the intended cutoff. The harness was corrected; the successful complete rerun above is the reported result. Earlier failed evidence was not relabeled as successful.

## Visual Review

Inspected actual PNG output for mobile matching, desktop review, latest mobile review, and grouped date/mapping warnings. Inputs and primary actions were readable without horizontal overflow. Evidence includes `Pencocokan_390.png`, `Pratinjau_390.png`, and `Petunjuk_Perbaikan.png` in the successful run directory. Screenshots contain synthetic inventory, not clinical identities.

## Limits And Rollback

Automated checks do not prove that clinic staff understand the workflow; a short operator UAT remains necessary. Source semantics, racikan quantities, product identity, and the first recording boundary still require a responsible operator's confirmation. Imports are still all-or-nothing for new rows when blockers exist; filtering does not bypass them.

Reverting this presentation change must retain the prior backend validation, approval and stock protections. No new schema or data rollback is required. Preserve existing mappings, source ledger and posted history.

## Follow-up: List Left, Information Right

User feedback requested less vertical travel through the matching screen. On 2026-10-27, matching and review were changed to a desktop workspace with an unframed medicine list at left and a 320 px information/action rail at right. The rail contains summary counts, instructions or grouped warnings, and existing confirmations/save controls. Repeated medicine-shape guidance is explained once in the rail rather than repeated in every row. The document header and step navigation remain above the workspace.

At viewport widths of 1280 px or more, the rail is sticky and list scrolling is independent. Heights are fitted to each region's actual page position, with CSS fallback; a first browser run exposed the previous fixed height allowance exceeding the viewport, which was corrected before the successful rerun. Smaller screens stack the rail below the list, retaining mobile table labels and all existing controls. Scroll regions are keyboard-focusable and labeled. The existing mapping form still wraps both columns, and the final posting form retains every required backend attestation. No stock, cutoff, FEFO, or permission rules changed.

Additional files: shared `resources/views/components/issue-import-summary.blade.php`; existing matching/review views, scoped CSS, import JavaScript, synthetic fixture and browser runner. No migration or persistent UAT/clinic write was needed. The configured local UAT login endpoint was read-only checked and returned HTTP 200; this is not a new production deployment.

Fresh verification on this follow-up tree:

- Full isolated PHPUnit: **207 tests, 2,196 assertions passed**, evidence `storage/app/qa/inventory/phpunit-results.xml`.
- Playwright: **13 scenario groups passed**, including 48 synthetic medicine groups with desktop left/right geometry, visible stable matching save action during list scrolling, stacked narrow layout, and no table/page horizontal overflow.
- Matching and review widths: **2560, 1920, 1440, 1280, 1024, 768, 390, and 320 px**. Actual PNGs at desktop and phone sizes visually inspected.
- MySQL workers: passed, final total/batch stock both 80 and three unique source lines, without duplicate deductions.
- Frontend build, targeted Pint, and Git whitespace checks passed.

Successful browser/MySQL evidence: `storage/app/qa/issue-imports/7e68f835bf3e6483/`. Screenshots include `Panel_Pencocokan_1440.png`, `Panel_Pencocokan_2560.png`, and `Panel_Pemeriksaan_390.png`; JSON logs are `Playwright.json` and `MySQL.json`. The earlier height-failure evidence remains in its separate run folder and is not reported as a pass. No commit or remote push performed.

## Follow-up: Compact Top Area

Further user feedback on 2026-10-27 identified excess space above the medicine list. For active previews, the document heading, period/count metadata, step navigation, and status/error notices now live in the information rail rather than spanning the top of the page. Matching/review begin directly below the existing issue tabs. A shared `issue-import-context` component retains document identity, a collapsible file-details section, compact accessible step links, history navigation, deletion and notices. Matching instructions are collapsible; posted/expired document headers and the upload step retain their previous layout.

The mapping form now occupies the left region only. Its save button in the rail targets that form using the native `form` attribute. The deletion form stays independent, avoiding nested forms or accidental deletion-field submission during mapping. Required validation, explicit approval, unsaved-change protection, and all inventory rules remain unchanged.

Actual fresh verification on this working tree:

- Full isolated PHPUnit: **208 tests / 2,214 assertions passed**, including a new regression for context/step placement, independent forms, external mapping-button association, and no stock writes from viewing either step. Evidence: `storage/app/qa/inventory/phpunit-results.xml`.
- **13 Playwright groups passed**, retaining the eight viewport sizes and 48-medicine fixture. Desktop assertions require the list to start within 24 px of the issue tabs, verify rail placement, visible/stable mapping save controls, and no horizontal overflow. File/instruction disclosure, mapping save, delete/cancel, Guest restrictions and stock posting regressions passed.
- MySQL workers passed with stock/batch totals 80 and three unique source lines. No duplicate deduction.
- Frontend build, targeted Pint and whitespace checks passed. No new migration, persistent inventory change, commit, or push.

Latest browser/MySQL evidence: `storage/app/qa/issue-imports/15d85b583113e9cc/`. Visually inspected actual `Pencocokan_1440.png`, `Panel_Pencocokan_1440.png`, and `Panel_Pencocokan_390.png`; data is synthetic. These results supersede earlier counts for the latest working tree without changing historical evidence.

## Follow-up: Excel Details And Clinic-Friendly Search

User requests on 2026-10-27 added row/date details and emphasized ease of use for clinic staff. The grouped matching/review lists now have **No.** and **Tanggal Jual (WIB)** columns. The number identifies the existing grouped worklist, not a sale or original Excel row. Dates come from source records, including seconds; multiple timestamps are honestly shown as a first/last range rather than assigned a fabricated single date.

Each medicine has an expandable, full-width source table with original Excel row number, sale timestamp, medicine code/name, original quantity/unit, racikan yes/no, and readable status. Rows sort by `source_row` to match the original file even when its dates are not ordered. Original quantities are distinct from converted stock deductions. Patient/doctor fields, raw sale/racikan identifiers, and medical identifiers are neither recovered nor added. Only already sanitized payload fields are displayed; no parser, schema, inventory service, or posting-rule change was needed. Existing drafts show the new details without reuploading.

For usability, source `Kuantitas` is labeled **Jumlah**, opaque mapping messages use existing readable guidance, and review headings say **Jumlah di Excel**, **Stok yang akan berkurang**, and **Status**. Matching adds local code/name search, a result/empty state, and explicit visible-choice bulk wording. Searching preserves every entered/checked value; Enter in the search input does not submit the form. Saving still includes all explicitly checked choices, not just search results. Matching/review filters hide their associated detail rows as well, so hidden medicines cannot leave orphan transaction details visible.

New components: `issue-import-source-period.blade.php` and `issue-import-source-details.blade.php`. Existing matching/review views, scoped CSS, import JS and tests were updated. Nested source-table styles are isolated from the parent tables. On phone/tablet, source rows stack with visible labels and use the main list scroll rather than introducing another vertical scroll region. The existing compact page top and right information rail remain.

### Actual Latest Verification

- Full isolated PHPUnit: **209 tests / 2,266 assertions passed**, evidence `storage/app/qa/inventory/phpunit-results.xml`. New coverage verifies a two-day range, original unsorted-file row positions, quantities/units/racikan, correct numbering, privacy exclusions and no view-time stock writes.
- **15 Playwright scenario groups passed**, including source detail values and no horizontal overflow at 2560/1440/768/390 px, mobile detail scroll behavior, search persistence, visible-only bulk approval, empty search, Enter safety, matching/review detail filtering, the existing eight-width long-list checks, posting/deletion/duplicate and Guest regressions.
- MySQL concurrent workers passed; total/batch stock both 80, three unique source lines, no duplicate deduction.
- Frontend build, targeted Pint, JS syntax and whitespace checks passed. One earlier PHPUnit assertion expected an obsolete technical warning; it was updated to the new readable wording while preserving the blocked-write/stock assertions, then the full suite passed again.

Successful final browser/MySQL evidence: `storage/app/qa/issue-imports/4e01f7a590ec57d2/`, including `Playwright.json`, `MySQL.json`, `Rincian_Excel_1440.png` and `Rincian_Excel_390.png`. Actual desktop/mobile PNGs visually inspected. All fixtures are synthetic and run against disposable QA databases; no clinical export import or persistent UAT inventory change. No commit or push performed. Clinic operator UAT remains necessary; automated passes do not establish that every user finds the UI easy.

## Follow-up: Explicit Confirmation Guidance

On 2026-10-27 the user found **Obat atau satuannya belum cocok** unclear. Its presentation now says **Pilihan obat dan satuan perlu dikonfirmasi**, distinguishing pending confirmation from a claim that a product is wrong. Full review guidance specifies selecting the correct name/form/dose, checking unit contents with `1 BUTIR = 1 Tablet`, checking **Benar**, and pressing **Simpan Pilihan & Lanjutkan**. It explains that automatic values are suggestions, and that a previously saved choice may need review after master changes; stock has not been reduced.

Matching has explicit automatic-suggestion versus invalid-saved-choice labels and an always-visible short instruction above its save controls when confirmation is needed. Blocked source statuses expose a native **Cara memperbaiki** disclosure, with concise action text for this case rather than repeating a long paragraph in each narrow table cell. Other existing blockers retain their own guidance. Service messages used by posting/token checks, source records, FEFO, quantities, permissions and required approval remain unchanged.

Fresh verification: **210 PHPUnit tests / 2,291 assertions passed**, including new coverage for suggestions, confirmation instructions, valid saved choices, changed master units, and no view-time stock writes. Evidence: `storage/app/qa/inventory/phpunit-results.xml`. **15 browser groups passed**, now exercising the source-status disclosure and its instructions without automatically checking approval. Existing eight-width layout, search, deletion, posting, duplicate, and Guest checks passed, as did MySQL concurrent workers (total/batch stock 80, three unique source lines), frontend build, targeted Pint and whitespace checks.

Final browser/MySQL evidence: `storage/app/qa/issue-imports/800def043ad91c33/`; actual `Petunjuk_Konfirmasi.png` visually inspected. Fixtures are synthetic and isolated. No persistent UAT/clinic inventory change, migration, commit or push.

## Follow-up: Resume After Switching To Manual Input

On 2026-10-27 the user reported that clicking Input Manual appeared to lose the import and required uploading/checking again. The stored preview was not being deleted: the import tab always linked to the upload page, while the existing draft was less discoverable in collapsed history.

The preview controller now remembers only document ID, current user ID, and matching/review step in the server session. A scoped class-backed `IssueTabs` component validates the pointer against an unposted draft with retained payload and age under seven days. The manual-page import tab targets that preview; manual/upload pages show **Lanjutkan pemeriksaan** with an explicit saved-versus-unsaved distinction. Posted/expired/deleted documents fall back to the upload/history route, and pointers from another user or Guest are not reused. Opening the upload page still allows a new file; it does not forcibly redirect.

No autosave or new business permission was added. Existing saved mappings and encrypted source payload remain the authoritative data. The standard unsaved-form SweetAlert still guards changed selections/factors; choosing to discard still discards local unsaved inputs. The browser regression explicitly changes a factor, chooses **Tetap di halaman**, verifies its value, restores the baseline, visits Input Manual, and returns through both the import tab and upload-page resume action to the same document without another upload. Inventory/FEFO, posting, expiry and deletion rules are unchanged.

Files: `app/View/Components/IssueTabs.php`, `IssueImportController.php`, the issue-tabs template, its three parent views, and regression tests. Parent component invocations were refreshed when converting from an anonymous to a class-backed component: an initial warm-cache PHPUnit run exposed stale anonymous invocations lacking the new view data. After recompilation through the updated parent templates, the full suite passed. No data reset or session purge was used.

Actual verification:

- **213 PHPUnit tests / 2,347 assertions passed**, including three new resume regressions covering saved document/step, no reupload/no stock write, expired/deleted/posted documents, and another user/Guest. Evidence: `storage/app/qa/inventory/phpunit-results.xml`.
- **16 Playwright scenario groups passed**, including the manual-tab/unsaved-warning/return scenario; existing posting, privacy, mobile layout, search, duplicate and Guest checks remain green. MySQL workers passed with stock/batch totals 80 and three unique source lines.
- Browser evidence: `storage/app/qa/issue-imports/a11ac90fb5cbf390/Playwright.json` and `MySQL.json`; actual `Lanjutkan_Impor_Dari_Manual.png` visually inspected. Browser run used fresh compiled views; the subsequent full PHPUnit rerun also verified previously cached parent views after the invocation refresh.
- Frontend build, targeted Pint and Git whitespace checks passed. No migration, persistent UAT/clinic stock edit, commit or push.

Session-based resumption is a convenience, not durable storage of unsaved edits. After logout/session expiry, existing nonexpired drafts remain available through import history.

## Publication Verification: 29 October 2026

At the user's request, the importer and its UI follow-ups are packaged together with documentation for commit/push to `main`, based on `2d2e8ef`. Earlier uncommitted/no-push statements above record the scope of those implementation turns, not a prohibition on this later authorized publication. Use Git history to identify the publication commit.

Fresh executions on **29 October 2026, Asia/Jakarta**:

| Check | Actual result | Local evidence |
| --- | --- | --- |
| Isolated full PHPUnit | 213 passed / 2,347 assertions, no failures | `storage/app/qa/inventory/phpunit-results.xml` |
| Playwright | 16 scenario groups passed | `storage/app/qa/issue-imports/368907df55004aa6/Playwright.json` |
| MySQL competing imports | Passed; total/batch stock 80, three unique source lines | Same run, `MySQL.json` |
| Frontend | `npm run build` passed | Actual terminal execution |
| Dependency manifest | `composer validate --no-check-publish` passed | Actual terminal execution |
| Formatting and whitespace | Targeted Pint and Git diff checks passed | Actual terminal execution |

Only synthetic fixtures and disposable QA databases were used. No clinic workbook was imported, no persistent UAT/clinic inventory or permissions changed, and no production deployment performed. Clinical exports, credentials, raw QA output, unrelated Capstone edits/archives and local workbook analysis notes are not part of this publication package. Checks for common secret patterns and staged file names are limited publication checks, not a repository-history security certification. Operator UAT and source-semantic confirmation remain necessary.
