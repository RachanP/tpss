# Sprint Status — ณ 18 พ.ค. 2569

## Phase Overview

| Phase | ชื่อ | สถานะ |
|-------|------|-------|
| Phase 1–3 | Initiation → Design | ✅ เสร็จ |
| Phase 4–5 | Development | 🟢 Sprint 1+2+3+M7 เสร็จ merge แล้ว, Sprint 4 (M3) เริ่ม |
| Phase 5 | Testing | 🟡 Internal Testing กำลังดำเนินการ |
| Phase 6–7 | Deployment → Closure | ยังไม่เริ่ม (4–7 มิ.ย. 2569) |

## Sprint Plan — Phase 1 (193 SP)

| Sprint | วันที่ | Module | สถานะ |
|--------|--------|--------|-------|
| Sprint 1 | 11–12 พ.ค. | M10 Login/RBAC | ✅ 100% |
| Sprint 2 | 12–15 พ.ค. | M1 Master Data | ✅ 100% |
| Sprint 3 | 18–19 พ.ค. | M2 Course Management | ✅ merge เข้า sprint แล้ว (18 พ.ค.) |
| **Sprint 4** | **20–22 พ.ค.** | **M3 Schedule Management** | **🟡 พร้อมเริ่ม** |
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

## Sprint 3 (M2) — สิ่งที่เสร็จแล้ว

### Two-Layer Status System
- `academic_years.phase` (preparation → scheduling → published) + Settings tab "ช่วงจัดตาราง"
- `course_offerings.approval_status` (draft → pending → published/rejected)
- ScheduleController + CourseOfferingController guard `phase != 'scheduling'`

### Course Pool (NEW — admin + staff)
- `course_roles` master table: หัวหน้าวิชา / เลขานุการวิชา / อาจารย์ผู้สอน / อาจารย์ประจำกลุ่ม / อาจารย์พี่เลี้ยง
- `course_instructors` pivot: template ระดับวิชา (course-level)
- `CoursePoolController` (admin + staff inherit): CRUD ชุดผู้สอน + เจ้าหน้าที่ + หัวหน้าวิชา
- Lock semantics: template ล็อกเมื่อมี offering เข้า scheduling/published phase แล้ว
- Sidebar เพิ่มเมนู "Course Pool"

### Course Offering — Hardening
- `openSchedulingWindow` — sync planning fields + instructor pool จาก template, ตัด filter `default_semester`
- Critical-gate ใหม่: `no_active_course`, `active_courses_missing_head` block การเปิด scheduling
- UI: 3-second countdown confirm + critical pill cards ก่อนกด "เปิดช่วงจัดตาราง"
- `bulkStoreStudentGroups` — สร้างหลายกลุ่มทีเดียวพร้อม auto-distribution + auto-color
- `course_offering_instructors.course_role_id` FK + `role_in_course` → varchar(100)
- Course-head show page: AJAX combobox + role chip dropdown (no reload)
- Practicum-note override flow: required เฉพาะตอน rotation ต่างจาก Master Data
- Executive ถูกกรองออกจาก available instructor pool
- `course_type` ทำเป็น nullable + ลบจาก UI (UI infer จาก lecture/lab/requires_practicum_rotation)

### Master Data — ย้าย Prerequisite
- Prerequisite ย้ายจาก per-offering (M2) → per-course (M1 Master Data)
- `MasterDataController::storeCourse/updateCourse` รับ `prerequisite_ids[]`
- `Rule::notIn([$course->id])` ป้องกัน self-prereq

### Removed (M3 ยังไม่เริ่ม)
- `resources/views/course_head/schedules/{index,create}.blade.php` ลบ
- `routes/web.php` ลบ schedule routes (controller method ยังอยู่ — orphan)

### Test Coverage (134 tests / 133 passing)
- `CoursePoolManagementTest` (18) — CRUD + lock + RBAC
- `CourseOfferingHardeningTest` (11) — template sync + bulk groups + critical gate
- `CourseOfferingShowPageTest` (13) — practicum_note override + AJAX flow
- `SchedulingPhaseTest` (13) — เพิ่ม critical-gate test, ลบ prereq/schedule guards
- `CourseOfferingManagementTest` updated — ลบ prereq tests, fix view assertions
- `AlertSystemTest` updated — `seedMinimalCriticals` รวม active course + head
- ลบ `ScheduleManagementTest` (routes deleted)

## Design Decisions (ตกลงแล้ว)

### Two-Layer Status System
- **ชั้น 1 — ระดับระบบ**: `academic_years.phase` (Admin ควบคุม)
- **ชั้น 2 — ระดับรายวิชา**: `course_offerings.approval_status` (Course Head + Executive)

### Scheduling Window
- Admin เปิด/ปิดผ่าน Settings tab "ช่วงจัดตาราง" (admin-only)
- เปิด **ทั้งภาคเรียน** พร้อมกัน — fairness
- Critical-gate ต้องเคลียร์ก่อน
- Email notification = Phase 2 (future work)

### Course Role Management
- `course_roles` master + `course_instructors` template + `course_role_id` FK ใน offering pivot
- "หัวหน้าวิชา" auto-assign จาก `courses.head_instructor_id` (ไม่ใช่ role ใน dropdown)
- Default role เมื่อเพิ่มอาจารย์ = "อาจารย์ผู้สอน"
- Phase 2: ใช้สำหรับ M6 Workload report แยกประเภทบทบาท

### Prerequisite Location
- อยู่ที่ M1 Master Data (per-course) — ไม่ใช่ M2 (per-offering)
- Reason: prerequisite เป็น property ของวิชา ไม่เปลี่ยนตามรอบเปิดสอน

## คำถามที่รอคำตอบจากลูกค้า (pending)

1. **เลขานุการวิชา** — ใส่ใน course_instructors template หรือ per-offering แยก?

## คำถามที่ได้คำตอบแล้ว

| คำถาม | คำตอบ |
|-------|-------|
| ผู้ประสานรายวิชา = course_head role เดียวกับหัวหน้าวิชาไหม? | ใช่ — role เดียวกัน |
| ใครกด "ยืนยันเปิด" ให้จัดตาราง? | **Admin** ผ่าน Settings tab "ช่วงจัดตาราง" |
| Course Head รู้ว่าวิชาถูกเปิดให้จัดตารางยังไง? | Phase 2 — email notification (Gmail) |
| Prerequisite ระดับวิชาหรือระดับรอบเปิดสอน? | ระดับวิชา (M1 Master Data) |

## ข้อค้นพบสำคัญสำหรับ M3 (Schedule Management)

> อ้างอิง: `Doc/ตัวอย่างตารางสอน/` (ปี 1-4, เทอม 1-2)

1. **Block-based** — ตารางไม่ซ้ำรายสัปดาห์ → `schedules` ต้องเก็บ `start_date`/`end_date` ไม่ใช่ `day_of_week`
2. **วันที่เฉพาะเจาะจง** — บางกิจกรรมระบุวันที่ตรงๆ เช่น "15-19 ก.ค. 2568"
3. **Parallel Groups** — วันเดียวกัน กลุ่ม A ward, กลุ่ม B ห้องเรียน → ทุก slot ต้อง link `student_group_id`
4. **Nested Groups** — ปี 3-4 แบ่ง A→A1/A2, B→B1/B2
5. **หลายอาจารย์ต่อกิจกรรม** — `schedule_instructors` pivot รองรับได้ ✅
6. **M2 เสร็จแล้ว** — instructor pool พร้อมใช้ผ่าน `course_offering.instructorPool`
7. **Guard**: ห้ามสร้าง schedule ถ้า `academic_year.phase != 'scheduling'`

## Definition of Done

- Code สมบูรณ์และผ่าน unit test
- ผ่าน code review
- ทดสอบ UI บน Chrome / Edge
- ไม่มี conflict ที่ยังไม่ได้แก้ไข
- บันทึกผลใน System Test Checklist
- เอกสาร (SRS / User Manual) อัปเดตแล้ว (ถ้าเกี่ยวข้อง)

## Known Bugs / Hotfixes

- `AdminUserController:32` — `reset(reset($x))` ส่ง value แทน reference → แก้แล้ว (afa38ae)
- `AdminUserManagementTest::test_admin_can_create_user` — pre-existing test failure ("Call to a member function all() on array") ไม่เกี่ยวกับ M2 — แก้แยกใน sprint อื่น

## Git Branching

```
main ← production-ready
  └── sprint ← integration (ใช้แทน develop)
        ├── feature/admin-dashboard-alerts  ✅ merge แล้ว
        ├── 7-m7-search_and_filter          ✅ merge แล้ว
        ├── 3-m2-course_management          ✅ merge แล้ว (18 พ.ค.)
        └── (next) M3 Schedule Management   🔲 พร้อมเริ่ม
```
