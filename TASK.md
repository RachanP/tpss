# TASK.md

## Last Updated
<!-- Update this date at the start or end of each AI-assisted session. -->
2026-05-14

## Current Sprint
<!-- Keep this to the active sprint/module only; do not record project history here. -->
Sprint 3 — M2 Course Management (18–19 พ.ค. 2569)

## Current Focus
<!-- Name the exact feature/page/file being worked on now, not a broad module. -->
รอ Sprint 2 review + เตรียม Factories สำหรับ Sprint 3

## Allowed Directories
<!-- List only paths the AI may modify for the current session. Tighten this before coding. -->
- `app/Http/Controllers/`
- `app/Models/`
- `database/factories/`
- `resources/views/shared/course_offerings/`
- `resources/views/staff/`
- `resources/views/course_head/`
- `routes/web.php`
- `tests/Feature/`

## Scope Boundary
<!-- Treat these as hard constraints. Move permanent rules into MEMORY.md. -->
- Do not modify migrations, seeders, or views outside Allowed Directories.
- Do not change Laravel, Blade, Alpine.js, MySQL, or RBAC architecture.
- Do not add React, Vue, Inertia, or a frontend SPA architecture.
- ห้าม implement ปุ่ม edit ให้ `executive` role.

## Active Tasks
<!-- Keep at most 7 one-line tasks; replace this list each session. -->
- [ ] รอ review Sprint 2 จากพี่ + test จากเพื่อน
- [ ] เตรียม CourseOffering Factory + Seeder
- [ ] วาง route และ Controller skeleton สำหรับ M2

## Definition of Done
<!-- Make completion measurable for the current session only. -->
- Sprint 2 ผ่าน review + test ครบ
- Factory + Seeder สำหรับ CourseOffering พร้อมใช้งาน
- Controller skeleton ครอบคลุม CRUD + confirm/skip offering

## Blocked / Waiting
<!-- Record unanswered questions or external blockers; clear this when resolved. -->
- รอ review Sprint 2 จากพี่ + test จากเพื่อน

## Completed This Sprint
<!-- Add completed items only; keep this short and prune when sprint changes. -->
- Sprint 1 (M10 Login/RBAC) ✅
- Sprint 2 (M1 Master Data) ✅
- Claude Code context setup (commands, memory, permissions) ✅

## Next Up
<!-- Add only 2-3 backlog items; do not write implementation plans here. -->
- Sprint 3: M2 Course Management — CourseOffering CRUD + confirm/skip workflow
- Sprint 4: M3 Schedule Management
