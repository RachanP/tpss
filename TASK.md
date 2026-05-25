# TASK.md

## Last Updated
<!-- Update this date at the start or end of each AI-assisted session. -->
2026-05-24

## Current Sprint
<!-- Keep this to the active sprint/module only; do not record project history here. -->
Sprint 4-6 — Schedule Suite (M3 + M4 + M8) (20–26 พ.ค. 2569)

## Current Focus
<!-- Name the exact feature/page/file being worked on now, not a broad module. -->
ตรวจเอกสารสถานะให้ตรงกับ `sprint` ปัจจุบัน และรอ Schedule Suite จาก branch พัฒนา

## Allowed Directories
<!-- List only paths the AI may modify for the current session. Tighten this before coding. -->
- `TASK.md`
- `CLAUDE.md`
- `.claude/rules/`
- `flowchart/`

## Scope Boundary
<!-- Treat these as hard constraints. Move permanent rules into MEMORY.md. -->
- รอบนี้แก้เฉพาะเอกสาร/สถานะ/ลิงก์อ้างอิง ไม่แก้ production code.
- Do not change Laravel, Blade, Alpine.js, MySQL, or RBAC architecture.
- Do not add React, Vue, Inertia, or a frontend SPA architecture.
- ห้าม implement ปุ่ม edit ให้ `executive` role.
- งาน M3/M4/M8 ที่ยังค้างให้ถือเป็นงานกำลังพัฒนา ไม่ใช่บัคของ `sprint` จนกว่าจะ merge/test

## Active Tasks
<!-- Keep at most 7 one-line tasks; replace this list each session. -->
- [x] ตรวจสถานะ `sprint` เทียบกับ remote branches หลัง fetch
- [x] อัปเดต `TASK.md` ให้ตรงกับ Sprint 4-6 / Schedule Suite
- [x] อัปเดต `CLAUDE.md` ให้ชี้ Product Backlog และ flowchart ที่มีอยู่จริง
- [ ] รอ Schedule Suite (`origin/4-m3-schedule-suite-testPat`) พัฒนาเสร็จแล้วค่อย review/merge/test

## Definition of Done
<!-- Make completion measurable for the current session only. -->
- เอกสารสถานะไม่อ้าง Sprint 2/3 เป็นงานปัจจุบันผิดบริบท
- ลิงก์เอกสารหลักใน `CLAUDE.md` ตรงกับไฟล์ที่มีจริงใน repo
- ระบุชัดว่า M3/M4/M8 ยังอยู่ระหว่างพัฒนาใน branch แยก

## Blocked / Waiting
<!-- Record unanswered questions or external blockers; clear this when resolved. -->
- Schedule Suite ยังอยู่ใน remote branch แยก ยังไม่ merge เข้า `sprint`
- เครื่องนี้เรียก `php artisan` ไม่ได้เพราะไม่พบคำสั่ง `php` ใน PATH

## Completed This Sprint
<!-- Add completed items only; keep this short and prune when sprint changes. -->
- Sprint 1 (M10 Login/RBAC) ✅
- Sprint 2 (M1 Master Data) ✅
- Sprint 3 (M2 Course Management) ✅
- M7 Search & Filter ✅
- Sprint 3 bug report ปิดแล้วตาม `.claude/rules/sprint-status.md` ✅
- Claude Code context setup (commands, memory, permissions) ✅

## Next Up
<!-- Add only 2-3 backlog items; do not write implementation plans here. -->
- Review/merge Schedule Suite เมื่อ branch พัฒนาพร้อม
- Test หลัง merge: Feature tests + Playwright เฉพาะ flow ที่กระทบ
- Phase 1 readiness check ก่อนปิดรอบพัฒนา
