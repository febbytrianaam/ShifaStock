# Capstone Evidence And Final Testing Plan

Recorded: 2026-10-19. This records the completed work; actual test executions took place on 2026-10-18 WIB.

1. Inspect implemented routes and modules, then capture original application screens through Playwright at 1440 x 900 using a separate simulated MySQL database.
2. Preserve raw test output and provide a readable table report without presenting it as an application feature.
3. Reproduce the cross-location batch-label bug in Kondisi Khusus before changing the searchable-select component.
4. Preserve native option identity during selection, dependency changes, prefill, and submission without changing batch IDs, permissions, FEFO, or stock calculations.
5. Add ten functional scenario groups and backend regression tests; rerun PHPUnit, competing-process MySQL tests, frontend build, and actual HTTP browser tests.
6. Retain before/after evidence, distinguish automated/browser/manual testing, and record tested base commit plus working-tree source hashes.
7. Review screenshots and publication contents, update implemented/current documentation, then commit and push after user authorization.

Production deployment, clinic-data modifications, Git history rewriting, and manual UAT are outside this work.
