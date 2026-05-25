# TPSS — Teaching & Practicum Scheduling System
**คณะพยาบาลศาสตร์ มหาวิทยาลัยมหิดล**

## ภาพรวมโครงการ

| รายการ | รายละเอียด |
|--------|-----------|
| ชื่อระบบ | Teaching & Practicum Scheduling System (TPSS) |
| ลูกค้า | คณะพยาบาลศาสตร์ มหาวิทยาลัยมหิดล |
| ผู้พัฒนา | ราชันย์ พิพัฒน์ และทีม |
| ระยะเวลา | 25 เม.ย. 2569 – 7 มิ.ย. 2569 (6 สัปดาห์) |
| Story Points | 280 SP / 61 User Stories (Phase 1: 193 SP, Phase 2: 87 SP) |
| มาตรฐาน | ISO/IEC 29110 — ต้องมี traceability และ audit trail |

## Tech Stack (ห้ามเสนอ React, Vue, Inertia.js)

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | Blade + Alpine.js v3.x (CDN) |
| Database | MySQL 8.0+ |
| CSS | Impeccable Design + `mock/production/ui/ui_kits/tpss/styles.css` |

## เอกสารบังคับอ่าน (ตามลำดับ)

1. `Doc/เอกสารเพิ่มเติม/TPSS_Product_Backlog_v2.2.docx` ← **แหล่งอ้างอิงหลัก**
2. `Doc/จากอาจารย์/รายละเอียดระบบจัดตารางสอน_V1.pdf` — workflow, ลักษณะตารางสอน
3. `Doc/เอกสาร ISO/WP-03_Software-Requirements-Specification_ProjectName_v1.0.pdf` ← **อ่านก่อนพัฒนาทุก Sprint**
4. `Doc/เอกสาร ISO/WP-02_Project-Plan_ProjectName_v1.0.pdf` — Project plan
5. `Doc/เอกสาร ISO/WP-01_Agreement_Statement-of-Work_ProjectName_v1.0.pdf` — SOW
6. `flowchart/overview.pdf` และ `flowchart/role.pdf` — Swimlane ล่าสุด

## สถานะปัจจุบัน

- Branch หลักสำหรับ integration คือ `sprint`.
- `sprint` ปัจจุบันมี M10, M1, M2 และ M7 merge แล้ว รวมถึง alerts/audit log บางส่วนที่พร้อมใช้งาน.
- Schedule Suite (M3 + M4 + M8) อยู่ระหว่างพัฒนาใน branch แยก `origin/4-m3-schedule-suite-testPat` และยังไม่ถือเป็น feature ที่พร้อมใช้ใน `sprint`.
- จุดที่พบเกี่ยวกับ schedule route/view ใน `sprint` ให้ถือเป็นงานค้างของ M3 ที่กำลังพัฒนา ไม่ใช่บัค regression ของระบบที่ merge แล้ว.

## Rules (อ่านเมื่อเกี่ยวข้อง)

| ไฟล์ | เนื้อหา |
|------|---------|
| [.claude/rules/database.md](.claude/rules/database.md) | Enum values, state machine, date handling, migrations, student group schema |
| [.claude/rules/rbac.md](.claude/rules/rbac.md) | 5 roles, user_roles pivot, active_role, role switcher, shared view pattern |
| [.claude/rules/ui.md](.claude/rules/ui.md) | Blade+Alpine, Impeccable Design, typography, mock files, accordion pattern |
| [.claude/rules/sprint-status.md](.claude/rules/sprint-status.md) | Sprint plan, M1/M2/M7 done, Schedule Suite constraints, pending questions, DoD |
| [.claude/rules/glossary.md](.claude/rules/glossary.md) | ไทย↔code mapping, naming conventions, module IDs |
| [.claude/rules/architecture.md](.claude/rules/architecture.md) | Workflow, schedule complexity, instructor logic, PA criteria, curriculum arch |
| [.claude/rules/testing.md](.claude/rules/testing.md) | Feature tests, Playwright E2E setup, workers=1, data-testid convention |

## Custom Commands (`.claude/commands/`)

| Command | ใช้เมื่อ |
|---------|---------|
| `/sprint-start` | เริ่ม Sprint ใหม่ — โหลด context + DoD checklist |
| `/feature <M2>` | Implement feature ตาม Module/US ID — plan ก่อน รอยืนยัน แล้วค่อย code |
| `/db-check` | ตรวจ migration status + enum + FK integrity |
| `/sync-context` | อัปเดตไฟล์ context (sprint-status, database, memory) ให้ตรงกับโปรเจกต์ปัจจุบัน |

## หมายเหตุสำหรับ Claude

- อ้างอิง Module ID (M1, M2...) และ User Story ID (M3-01...) เมื่อพูดถึงฟีเจอร์
- ตรวจสอบ Product Backlog **v2.2** ก่อนเสนอ design หรือ implementation ใดๆ
- เมื่อเริ่มงาน UI ให้รัน `node .agents/skills/impeccable/scripts/load-context.mjs`
- **ผู้บริหาร = Read-only + Approve/Reject เท่านั้น** — ห้าม implement UI ให้แก้ไขตาราง
- Export รายงานเป็น PDF และ Excel; รองรับ PC, tablet, mobile เบื้องต้น
