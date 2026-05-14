# MEMORY.md

AI Agent: Read this file before every session.
These decisions are considered project memory and must not be silently reverted.

## Architecture Decisions
[DECISION] Use Laravel 13 + PHP 8.3 for backend - Matches current composer requirements and project stack - 2026-05-12
[DECISION] Use Blade templates + Alpine.js in production UI - Keeps server-rendered institutional workflow simple and consistent - 2026-05-12
[DECISION] Use MySQL 8.0+ as the database target - Required for current relational scheduling schema and migrations - 2026-05-12
[DECISION] Keep 25 existing migration files as schema source of truth - They already cover Phase 1 and prepared Phase 2 tables - 2026-05-12
[DECISION] Use `CLAUDE.md` as the primary AI development guide - It preserves TPSS workflow, RBAC, domain, and implementation rules - 2026-05-12
[DECISION] Use root `TASK.md` for short-term work and root `MEMORY.md` for long-term rules - Separates sprint focus from permanent architecture memory - 2026-05-12
[DECISION] Use Thai-first UX copy for domain UI - Reduces cognitive load for Faculty of Nursing staff - 2026-05-12
[DECISION] Follow "Mahidol Navy Data Shell" visual system - Supports formal, legible, data-dense institutional work - 2026-05-12
[DECISION] Treat role switching and RBAC as first-class workflow concerns - Users may have multiple operational roles - 2026-05-12
[DECISION] Treat Conflict Detection as core scheduling logic, not decoration - It protects timetable integrity before approval/publishing - 2026-05-12
[DECISION] Use `sprint` as the integration branch for all active development - Feature/fix branches must target `sprint`, while `main` stays stable/release only - 2026-05-14

## Rejected Approaches
[REJECTED] React/Vue/Inertia production architecture - Rejected because production stack is Laravel Blade + Alpine.js and SPA churn would violate scope.
[REJECTED] Consumer SaaS visuals, glassmorphism, gradients, playful chrome - Rejected because TPSS requires formal institutional trust and dense data scanning.
[REJECTED] Dark mode as default - Rejected because design direction is light-mode workspace with navy chrome.
[REJECTED] Emoji-based buttons or status indicators - Rejected because institutional UX requires formal SVG icons and explicit Thai labels.
[REJECTED] Decorative color use - Rejected because saturated colors must remain semantic conflict/warning/success signals.
[REJECTED] Weekly-recurring-only timetable model - Rejected because TPSS uses block schedules, rotations, exceptions, and practicum periods.
[REJECTED] Single-role-only user model - Rejected because staff, maker, lecturer, approver, and admin responsibilities may overlap.
[REJECTED] Directly editing migrations without request - Rejected because migration history is a contract and schema changes need explicit approval.
[REJECTED] Replacing domain Thai terminology with generic English labels - Rejected because local staff workflows are Thai-first and institution-specific.
[REJECTED] Opening feature/fix PRs directly into `main` - Rejected because `main` is stable/release only; active development integrates through `sprint`.

## Git Workflow Rules (NON-NEGOTIABLE)
All active feature/fix branches must be created from latest `sprint`.
All active feature/fix PRs must target `sprint`.
Never open feature/fix PRs directly into `main`.
`main` is stable/release only and must not receive direct development work.
Before starting new work, run `git switch sprint` and `git pull --ff-only`, then create the branch.

## Recovery Note
Feature/fix work was previously merged into `main` by mistake.
The repo was recovered by syncing `main -> sprint`.
Do not repeat this; future active development must use `feature-or-fix-branch -> sprint`.

## Domain Rules (NON-NEGOTIABLE)
Block Schedule is not a simple recurring weekly class timetable.
Rotation Schedule must support groups moving across practicum sites and experience types.
Conflict blocks invalid schedule saves; Warning allows save but must remain visible.
Course Head creates and submits schedules; Executive reviews and approves or rejects.
Executive review is read-only except approve/reject and rejection reason.
Instructor conflicts must check the global instructor identity across courses.
Instructor Pool belongs to a course offering and feeds activity assignment.
Activities may have multiple instructors for team supervision.
Schedules must support multiple student subgroups, locations, and activity types.
Workload validation must consider instructor quota and annual/weekly workload rules.
Approval workflow must preserve submitted schedule state and decision history.
Audit Trail must record meaningful system actions for accountability.

## Known Constraints
Project serves Faculty of Nursing, Mahidol University; institutional tone matters.
Primary users include staff and course heads working long sessions on PC/tablet.
Users vary widely in age; legibility and explicit controls outrank novelty.
Thai labels, Thai errors, and Thai operational copy are default for UI.
English is acceptable for technical terms and code-level identifiers.
No React, Vue, Inertia, or SPA rewrite in production.
Do not weaken tests or validation to make implementation easier.
Do not modify migrations unless explicitly requested for the task.
Database schema includes Phase 1 and prepared Phase 2 tables.
Use full academic titles where instructor names are displayed.

## Design Guardrails
Use Mahidol Navy as primary chrome and action color.
Use light mode workspace with high-contrast text and calm surfaces.
Use saturated red/yellow/green only for conflict, warning, success, or status.
Prefer dense but readable tables, grids, tabs, sidebars, and operational forms.
Use explicit Thai labels over ambiguous icon-only controls.
Use Feather/Lucide-style outlined SVG icons when icons are needed.
Use 2-4 px corners for controls and 8-10 px for cards/surfaces.
Avoid gradients, glassmorphism, decorative images, heavy animation, and playful styling.
Maintain Thai body line-height around 1.55 or higher for readability.
Every pixel should support scanning, comparison, or action.

## Terminology Lock
TPSS = Teaching & Practicum Scheduling System.
Block Schedule = ตารางสอนแบบช่วงต่อเนื่องหลายสัปดาห์.
Rotation Schedule = ตารางหมุนเวียนแหล่งฝึก/ประสบการณ์.
Course Head = หัวหน้าวิชา / Maker.
Executive = Approver.
Staff = Support Staff.
Instructor Pool = รายชื่ออาจารย์ประจำวิชา.
Global Instructor ID = รหัสประจำตัวอาจารย์สำหรับตรวจชนข้ามวิชา.
Conflict = ข้อชนที่บล็อกการบันทึก.
Warning = ข้อเตือนที่บันทึกได้แต่ต้องแสดง.
Course Offering = รายวิชาที่เปิดสอนในปี/ภาคการศึกษา.
Practicum Series = ชุดกิจกรรมฝึกปฏิบัติ.
Activity Type = ประเภทกิจกรรมการเรียน/ฝึก.
Student Group = กลุ่มนักศึกษา.

## Never Suggest Again
Do not suggest replacing Blade + Alpine.js with React, Vue, Inertia, or Next.js.
Do not suggest generic calendar SaaS UI as the primary model.
Do not suggest decorative gradients, emoji chrome, or playful consumer styling.
Do not suggest simplifying TPSS to a weekly class timetable.
Do not suggest bypassing RBAC for faster screens.
Do not suggest deleting or rewriting existing migrations without explicit instruction.
Do not suggest English-only UX for operational screens.

## Open Questions
Which module is the next active implementation focus after workflow memory setup?
Should `TASK.md` become the only short-term agent handoff file, or coexist with `GitCommands.md`?
What is the approved policy for untracked local helper docs such as `GitCommands.md`?
When M2 starts, which directories should be added to `TASK.md` Allowed Directories?
