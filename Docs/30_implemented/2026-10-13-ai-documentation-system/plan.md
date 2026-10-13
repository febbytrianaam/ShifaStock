# Plan: AI-Friendly Documentation System

## Summary

Create a documentation architecture that helps humans and AI agents understand the project, current state, architecture, risks, roadmap, plans, and implementation history.

## Goals

- Create `docs/` structure for current truth, AI workspace, plans, implementation history, references, and archive.
- Write useful starter docs based on actual repo inspection.
- Separate current facts from future ideas.
- Provide handoff material for future AI agents.
- Preserve legacy reference material.

## Non-goals

- Do not replace code comments or README entirely.
- Do not claim unknown production details as facts.
- Do not delete legacy reference files.

## Implementation Details

- Inspect README, composer/npm files, routes, controllers, models, migrations, tests, and existing docs.
- Create `docs/README.md` and `00_current` docs.
- Create ADR and plan/report templates.
- Add implemented-history folders after tracing shipped features.

## Test Plan

- Verify required files exist.
- Verify links and folder names are consistent.
- Verify no speculative work is placed in `00_current`.

## Acceptance Criteria

- Future agents know where to start.
- Current state, architecture, testing, security, privacy, roadmap, and handoff are documented.
- Implemented features are traceable.
- Unknowns are clearly marked.

## Assumptions

- `docs/` is the desired canonical docs path.
- Legacy `Docs/` content can live under the lowercase `docs/` tree on Windows.

