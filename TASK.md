# TASK.md

## Last Updated
<!-- Update this date at the start or end of each AI-assisted session. -->
2026-05-14

## Current Sprint
<!-- Keep this to the active sprint/module only; do not record project history here. -->
Workflow hardening - branch target rules

## Current Focus
<!-- Name the exact feature/page/file being worked on now, not a broad module. -->
Update AI workflow docs so future sessions target `sprint`, not `main`.

## Allowed Directories
<!-- List only paths the AI may modify for the current session. Tighten this before coding. -->
- `TASK.md`
- `MEMORY.md`
- `docs/internal/GIT_WORKFLOW.md`

## Scope Boundary
<!-- Treat these as hard constraints. Move permanent rules into `MEMORY.md`. -->
- Do not modify migrations, seeders, controllers, models, routes, views, CSS, or tests in this task.
- Do not introduce feature code while setting up workflow memory files.
- Do not change Laravel, Blade, Alpine.js, MySQL, or RBAC architecture.
- Do not add React, Vue, Inertia, or a frontend SPA architecture.
- Do not rewrite CLAUDE.md, README.md, PRODUCT.md, DESIGN.md, or project docs.
- Active development PR target is `sprint`; never target `main` for feature/fix work.

## Active Tasks
<!-- Keep at most 7 one-line tasks; replace this list each session. -->
- [x] Read CLAUDE.md, README.md, PRODUCT.md, and DESIGN.md.
- [x] Read TASK.md and MEMORY.md.
- [x] Update permanent branch target rules in MEMORY.md.
- [x] Update short-term workflow focus in TASK.md.
- [x] Update docs/internal/GIT_WORKFLOW.md with `feature/fix -> sprint`.

## Definition of Done
<!-- Make completion measurable for the current session only. -->
- `MEMORY.md` states `sprint` is the integration branch.
- `TASK.md` warns not to target `main` for feature/fix PRs.
- `docs/internal/GIT_WORKFLOW.md` shows creating new work from latest `sprint`.
- Recovery note says previous mistaken `main` merge was fixed by syncing `main -> sprint`.

## Blocked / Waiting
<!-- Record unanswered questions or external blockers; clear this when resolved. -->
- None.

## Completed This Sprint
<!-- Add completed items only; keep this short and prune when sprint changes. -->
- Created AI workflow memory file drafts for TPSS.
- Locked branch workflow: feature/fix branches target `sprint`; `main` is stable/release only.

## Next Up
<!-- Add only 2-3 backlog items; do not write implementation plans here. -->
- Update Allowed Directories before any feature coding starts.
- Keep `TASK.md` current in 3-5 lines per session.
- Start each new feature/fix from latest `sprint`.
