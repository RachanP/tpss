# Sprint Status — ณ 16 พ.ค. 2569

## Phase Overview

| Phase | ชื่อ | สถานะ |
|-------|------|-------|
| Phase 1–3 | Initiation → Design | ✅ เสร็จ |
| Phase 4–5 | Development | 🟢 Sprint 1+2+M7 เสร็จ merge แล้ว, Sprint 3 (M2) กำลังดำเนินการ |
| Phase 5 | Testing | 🟡 Internal Testing กำลังดำเนินการ |
| Phase 6–7 | Deployment → Closure | ยังไม่เริ่ม (4–7 มิ.ย. 2569) |

## Sprint Plan — Phase 1 (193 SP)

| Sprint | วันที่ | Module | สถานะ |
|--------|--------|--------|-------|
| Sprint 1 | 11–12 พ.ค. | M10 Login/RBAC | ✅ 100% |
| Sprint 2 | 12–15 พ.ค. | M1 Master Data | ✅ 100% |
| **Sprint 3** | **18–19 พ.ค.** | **M2 Course Management** | **🟡 กำลังดำเนินการ (branch: 2-m2-Course-Management)** |
| Sprint 4 | 20–22 พ.ค. | M3 Schedule Management | — |
| Sprint 5 | 21–26 พ.ค. | M4 Conflict Checking | — |
| Sprint 6 | 22–26 พ.ค. | M8 Views & Calendar | — |
| Sprint 7 | 20–27 พ.ค. | M7 Search & Filter | ✅ merge เข้า sprint แล้ว (16 พ.ค.) |

## Sprint 2 (M1) — สิ่งที่เสร็จแล้ว

- Shared Views: `views/shared/master_data/`, `views/shared/settings/`
- Staff\MasterDataController extends Admin\MasterDataController
- Staff\SettingController — จัดการ academic_year ได้ทั้งหมด, ไม่เห็น tab PA
- Lock icon บน tab ที่ Staff ดูอย่างเดียว
- Accordion drill-down: dept→อาจารย์, curriculum→วิชา, location_type→ห้อง
- Student Groups ย้ายออกจาก M1 → ไปสร้างใน M2 ตอน confirm offering
- `requires_capacity` boolean บน `location_types` — ห้องในประเภทที่ไม่ต้องการความจุ (เช่น ชุมชน) ไม่โดนแจ้งเตือน
- Admin Dashboard + role-based dashboards (executive, course_head, instructor, staff)
- Alerts system: `AlertController` + `/admin/alerts` page + dashboard widget
- PA criteria schema เปลี่ยนจาก string → `{min: int, max: int}` ต่อแต่ละด้าน

## Sprint 3 (M2) — สิ่งที่ทำแล้ว (16 พ.ค.)

- `CourseOfferingController` (CourseHead) — ครบทุก action: update, storeInstructor, destroyInstructor, storeStudentGroup, updateStudentGroup, destroyStudentGroup, storePrerequisite, destroyPrerequisite
- **ลบ** `archive` action ออกจาก course_head — course head ไม่มีสิทธิ์ archive
- **Seeder fix**: `coordinator_id` ใน `CourseOfferingSeeder` ดึงจาก `courses.head_instructor_id` แทนหยิบ course_head คนแรก
- **UserSeeder**: ราชันย์ (admin_01) เพิ่ม role `course_head`
- **CourseSeeder**: ราชันย์ → NSBS 111, NSBS 212 / พรภิมล → NSBS 213, NSBS 221
- **ลบ** `DevM2VisualVerificationSeeder` และ test — base seeder ครบแล้ว
- Migration `refactor_course_offering_status_to_scheduling_window` — เพิ่ม locked/open (**จะ rollback** แล้วทำ `academic_years.phase` แทน)
- ScheduleController (M3 foundation): index, create, store — เพื่อนทำไว้แล้ว

## Sprint 3 (M2) — งานที่ยังค้าง

- [ ] Rollback migration locked/open → ทำ migration `add_phase_to_academic_years_table` แทน
- [ ] `AdminSettingController::openSchedulingWindow` / `closeSchedulingWindow` — ปรับให้ใช้ phase แทน bulk status
- [ ] Settings tab "ช่วงจัดตาราง" (admin only) — แสดงสถานะ phase ต่อ academic year + ปุ่มเปลี่ยน phase
- [ ] CourseHead index view — แสดง phase ของ academic year, disable ปุ่มจัดตารางถ้า phase = preparation
- [ ] CourseHead show view — ลบ archive section, แสดง phase status
- [ ] ScheduleController guard — ห้ามสร้าง schedule ถ้า `academic_year.phase != 'scheduling'`
- [ ] Course offering index: แสดง capacity จาก `courses.capacity` แทน total_student_count

## Design Decisions (ตกลงแล้ว 16 พ.ค.)

### Two-Layer Status System
- **ชั้น 1 — ระดับระบบ**: `academic_years.phase` (Admin ควบคุม)
  - `preparation` → เตรียมข้อมูล ห้าม course head จัดตาราง
  - `scheduling` → Admin เปิด ทุกวิชาในภาคนั้นจัดได้พร้อมกัน (fairness)
  - `published` → Executive อนุมัติครบ เผยแพร่แล้ว
- **ชั้น 2 — ระดับรายวิชา**: `course_offerings.approval_status` (Course Head + Executive)
  - `draft → pending → published / rejected`

### Scheduling Window
- Admin เปิด/ปิดผ่าน **Settings tab "ช่วงจัดตาราง"** (admin-only tab เหมือน PA)
- เปิด **ทั้งภาคเรียน** พร้อมกัน — ไม่ใช่ทีละวิชา (เพื่อความยุติธรรม ไม่มีใครได้เปรียบ)
- Email notification ตอนเปิด = **Phase 2** (future work)

### role_in_course คงไว้
- มีใน requirement จริง (เอกสารอาจารย์ข้อ 7: หัวหน้าวิชา, เลขานุการ, อาจารย์ประจำกลุ่ม, preceptor)
- Phase 1: UI hardcode `instructor` ไปก่อน ไม่มี dropdown
- Phase 2: ใช้สำหรับ M6 Workload และ report แยกประเภทบทบาท

## คำถามที่รอคำตอบจากลูกค้า (pending)

1. **เลขานุการวิชา** — จัดการใน M1 (ระดับวิชา) หรือ M2 (ระดับปีการศึกษา)?

## คำถามที่ได้คำตอบแล้ว

| คำถาม | คำตอบ |
|-------|-------|
| ผู้ประสานรายวิชา = course_head role เดียวกับหัวหน้าวิชาไหม? | ใช่ — role เดียวกัน |
| ใครกด "ยืนยันเปิด" ให้จัดตาราง? | **Admin** ผ่าน Settings tab "ช่วงจัดตาราง" |
| Course Head รู้ว่าวิชาถูกเปิดให้จัดตารางยังไง? | Phase 2 — email notification (Gmail) |

## ข้อค้นพบสำคัญสำหรับ M3 (Schedule Management)

> อ้างอิง: `Doc/ตัวอย่างตารางสอน/` (ปี 1-4, เทอม 1-2)

1. **Block-based** — ตารางไม่ซ้ำรายสัปดาห์ → `schedules` ต้องเก็บ `start_date`/`end_date` ไม่ใช่ `day_of_week`
2. **วันที่เฉพาะเจาะจง** — บางกิจกรรมระบุวันที่ตรงๆ เช่น "15-19 ก.ค. 2568"
3. **Parallel Groups** — วันเดียวกัน กลุ่ม A ward, กลุ่ม B ห้องเรียน → ทุก slot ต้อง link `student_group_id`
4. **Nested Groups** — ปี 3-4 แบ่ง A→A1/A2, B→B1/B2
5. **หลายอาจารย์ต่อกิจกรรม** — `schedule_instructors` pivot รองรับได้ ✅
6. **M2 ต้องเสร็จก่อน M3** — ทุก slot ต้อง FK → `course_offering_id` + `student_group_id`
7. **Guard**: ห้ามสร้าง schedule ถ้า `academic_year.phase != 'scheduling'`

## Definition of Done

- Code สมบูรณ์และผ่าน unit test
- ผ่าน code review
- ทดสอบ UI บน Chrome / Edge
- ไม่มี conflict ที่ยังไม่ได้แก้ไข
- บันทึกผลใน System Test Checklist
- เอกสาร (SRS / User Manual) อัปเดตแล้ว (ถ้าเกี่ยวข้อง)

## Known Bugs / Hotfixes (16 พ.ค.)

- `AdminUserController:32` — `reset(reset($x))` ส่ง value แทน reference → แก้แล้ว (afa38ae)

## Git Branching

```
main ← production-ready
  └── sprint ← integration (ใช้แทน develop)
        ├── feature/admin-dashboard-alerts  ✅ merge แล้ว
        ├── 7-m7-search_and_filter          ✅ merge แล้ว
        └── 2-m2-Course-Management          🟡 กำลังดำเนินการ
```
