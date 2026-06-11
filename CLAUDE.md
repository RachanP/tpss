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

1. `Doc/เอกสารเพิ่มเติม/TPSS_Product_Backlog_v2.1.docx` ← **แหล่งอ้างอิงหลัก**
2. `Doc/จากอาจารย์/เอกสาร/รายละเอียดระบบจัดตารางสอน_V1.pdf` — workflow, ลักษณะตารางสอน
   - `storage/tpss_detail_pages/page-00.jpg` ถึง `page-25.jpg` — export เป็นภาพเรียงหน้า อ่าน comment สีน้ำเงินจากอาจารย์ได้ชัดกว่า PDF
3. `Doc/เอกสาร ISO/WP-03_Software-Requirements-Specification_ProjectName_v1.0.pdf` ← **อ่านก่อนพัฒนาทุก Sprint**
4. `Doc/เอกสาร ISO/WP-02_Project-Plan_ProjectName_v1.0.pdf` — Project plan
5. `Doc/เอกสาร ISO/WP-01_Agreement_Statement-of-Work_ProjectName_v1.0.pdf` — SOW
6. `flowchart/overview.pdf` และ `flowchart/roles.pdf` — Swimlane ล่าสุด

## Rules (อ่านเมื่อเกี่ยวข้อง)

| ไฟล์ | เนื้อหา |
|------|---------|
| [.claude/rules/database.md](.claude/rules/database.md) | Enum values, state machine, date handling, migrations, student group schema |
| [.claude/rules/rbac.md](.claude/rules/rbac.md) | 5 roles, user_roles pivot, active_role, role switcher, shared view pattern |
| [.claude/rules/ui.md](.claude/rules/ui.md) | Blade+Alpine, Impeccable Design, typography, mock files, accordion pattern |
| [.claude/rules/sprint-status.md](.claude/rules/sprint-status.md) | Sprint plan, Sprint 2 done, M3 constraints, pending questions, DoD |
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
- ตรวจสอบ Product Backlog **v2.1** ก่อนเสนอ design หรือ implementation ใดๆ
- Requirement update 29 พ.ค. 2569: ระบบต้องเน้น **ฟอร์มกรอกตารางที่ใช้ง่าย + smart check + publish/report** มากกว่า auto scheduling; ดู `.claude/rules/architecture.md`
- Master Data decision 30 พ.ค. 2569: สำหรับ demo/current phase อย่าเสนอเพิ่ม field หลักสูตรต่อปี, track นานาชาติ, campus/location kind หรือ workload flag ซ้ำ เว้นแต่มี requirement ใหม่ชัดเจน; ดู `.claude/rules/architecture.md#master-data-scope-decisions--30-พค-2569`
- ✅ Master Data Cleanup (V3) — เสร็จครบ scope (31 พ.ค. 2569) บน branch `feat/v2-requirement`: **academic_year = "ปี" (เทอม 1/2/ฤดูร้อน + วันสอบ), วิชาเปิดทั้งปี, offering ราย-ปี, ผู้บริหารอนุมัติทั้งปี, student_cohorts, holidays (auto-fetch), activity_types.counts_toward_workload** — REQUIRED ปิดครบ เหลือแค่ `rooms.campus` (optional, ไม่อยู่ใน V3); งานถัดไป = schedule/rotation phase (ไม่ใช่ master data) ดู `.claude/rules/sprint-status.md` + `architecture.md#master-data-cleanup-phase-v2`
- ✅ **Requirement V4 (จากประชุมลูกค้า — `Doc/requirement/requirement_v4.md`)** — **implement + merge เข้า `to-serve` แล้ว** (3 branch: `feat/v4-schedule-groups` A, `feat/v4-master-data` B, `feat/v4-rbac-pa-ops` C): ข้อ 1 หัวข้อกิจกรรม dropdown (`activity_topics`), ข้อ 2 หัวหน้าวิชาจัดกลุ่ม นศ. เอง + cross-course GROUP conflict, ข้อ 3 executive=หัวหน้าภาค gate, ข้อ 4 `curriculums.counts_service_only`, ข้อ 5 อาจารย์กรอก PA เอง (`Instructor/PaController` + `pa_rounds`/`instructor_pa_allocations`), ข้อ 6 สลับเวร+timestamp, ข้อ 7 ลากช่วงวันที่ (series). **🆕 ข้อ 8 (ใหม่ นอกลิสต์ 7 ข้อเดิม): ปฏิทินแยกตามหลักสูตร/ชั้นปี** (`academic_calendars`). ⚠️ **กลับทิศ**: หัวหน้าวิชา *จัด* กลุ่มนักศึกษาเองตั้งแต่กรอกตาราง (อิงจำนวน นศ.ชั้นปี) — group selector + routes `student_groups.*` กลับเข้า course head แล้ว. ดู `.claude/rules/architecture.md` หัวข้อ Requirement V4
- เมื่อเริ่มงาน UI ให้รัน `node .agents/skills/impeccable/scripts/load-context.mjs`
- ⚠️ **แก้โค้ด/refactor/เปลี่ยน UI/flow → อัปเดต test ในงานเดียวกันเสมอ** (รักษา `data-testid` + selector + assertion ให้ตรง แล้วรัน PHPUnit + Playwright ให้เขียวก่อนถือว่าเสร็จ) ดู `.claude/rules/testing.md` หัวข้อ "กฎเหล็ก: แก้โค้ด → อัปเดต test"
- **ผู้บริหาร = Read-only + Approve/Reject เท่านั้น** — ห้าม implement UI ให้แก้ไขตาราง
- Export รายงานเป็น PDF และ Excel; รองรับ PC, tablet, mobile เบื้องต้น
