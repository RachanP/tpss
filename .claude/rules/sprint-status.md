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
| **Sprint 4-6** | **20–26 พ.ค.** | **M3 + M4 + M8 → รวมเป็น Schedule Suite** | **🟡 พร้อมเริ่ม (ดู Schedule Suite plan)** |
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

## Sprint 3 Bug Report — 18 พ.ค. 2569 (bug_sprint2_18_05_69.pdf)

15 bugs จากการ test M1/M2/M10 — แบ่งเป็น 2 branch ขนาน:

### Branch A — `fix/m1-master-data-bugs` (Friend 1)
1. รหัสวิชาซ้ำในหลักสูตรเดียวกันได้ — ต้อง unique constraint `(curriculum_id, course_code)`
2. CSV export ภาษาไทยเพี้ยนใน Excel — ต้อง prepend UTF-8 BOM
3. CSV template ของ courses/rooms ไม่รองรับภาษาไทย + field ใหม่
4. Empty state "ไม่พบข้อมูล" ไม่ขึ้นทุก tab M1 (ยกเว้น instructor)
5. เลือก head=secretary คนเดียวกัน → confirm แล้วบันทึกไม่สำเร็จ
15. หน้าจัดการผู้ใช้: ไม่ขึ้น % PA criteria + ข้อมูลหายเมื่อ validation error (ต้อง `withInput()`)

### Branch B — `fix/m2-ux-bugs` (Friend 2 หรือ defer ไป Phase 2)
6. URL `/admin/course-pool/1` ใช้ id — ควรใช้รหัสวิชา (`getRouteKeyName`)
7. รายวิชาในหน้าจัดการของ course head **แสดงซ้ำ** — query JOIN ทำให้แถวซ้ำ
8. Admin dashboard ไม่เห็นสถานะระบบ + ไม่มี shortcut (UX)
9. ไม่มี filter "วิชาเปิดสอน vs ปิดสอน" + badge "ยังไม่มีหัวหน้าวิชา" (UX)
10. Sidebar เด้งขึ้นบนสุดเองหลังกด "ตั้งค่าระบบ" — scroll position หาย
11. URL `/maker/course-offerings/1` ใช้ id — เหมือนกับ #6 (รวม PR เดียวได้)
12. เพิ่ม badge "กลุ่มเต็ม" / "เหลือ N คน" ในหน้า course offering list
13. Layout course offering list table แน่นไป
14. หน้า course offering detail: layout ทับ + คลิก input ไม่ได้ (z-index/pointer-events)

**กฎก่อนแก้:** Bug #6, #10, #14 ต้อง **merge เข้า sprint ก่อน Sprint 4 เริ่ม** เพราะกระทบ M3 (route binding, sidebar conflict, show page layout)

## Post-Bugfix Parallel Plan (อัปเดต 18 พ.ค.)

หลัง bug fix เสร็จ ทำ 3 งานขนานกัน — แยก page ปลอดภัยถ้าทำตาม [widget contract](.claude/rules/ui.md#dashboard-widget-contract-สำคัญเมื่อทำงานขนาน):

| คน | งาน | ไฟล์หลัก |
|----|-----|----------|
| Lead (Rachan) | Dashboard polish ของ admin/staff/course_head + เขียน E2E/Feature test ตามหลัง | `views/{admin,staff,course_head}/dashboard.blade.php` + `views/shared/dashboard/*` |
| Friend 1 | Audit Log สำหรับ M10/M1/M2 | NEW: `audit-logs/*`, migration, trait/observer |
| Friend 2 | **Schedule Suite (M3+M4+M8 รวมกัน)** ดูหัวข้อด้านล่าง | `CourseHead/ScheduleController.php` (มี foundation), NEW views `schedules/*` |

**Sidebar slots พร้อมแล้ว** ใน `components/sidebar.blade.php`: "ตารางสอน" (admin/staff/course_head) และ "Audit Logs" (admin) เป็น `href="#"` รอเสียบ route → ไม่ต้องเพิ่ม structure ใหม่

## Schedule Suite — รวม Sprint 4+5+6 (ตกลง 18 พ.ค.)

**เหตุผลที่รวม:** M3 (สร้าง slot) → M4 (conflict check ตอน save) → M8 (calendar view) เป็น feature เดียวจากมุมผู้ใช้ — แยกสปรินต์แล้วทดสอบเร่อ ๆ ใช้งานไม่ได้จริง

| Phase | Scope | ระยะเวลา | คนทำ |
|-------|-------|---------|------|
| **A — MVP** | M3 CRUD slots (list view) + M4 conflict check ตอน save + phase guard | 5-7 วัน | Friend 2 |
| **B — UX สมจริง** | M4 real-time check (debounced AJAX) + warning badges (quota, missing info) | 3-4 วัน | Friend 2 |
| **C — Calendar** | M8 calendar view (เดือน/สัปดาห์) + click-empty-cell-to-add + filter | 3-4 วัน | **Lead (Rachan)** ช่วยหลัง dashboard เสร็จ |

**กฎสำคัญสำหรับ Lead เข้าไปช่วย:**
- ต้อง merge dashboard เข้า sprint ให้เสร็จ **100% ก่อน** ค่อยข้ามไปช่วย calendar
- Phase C ใช้ **Test After Merge** flow (ดู [.claude/rules/testing.md](.claude/rules/testing.md#test-after-merge-flow))
- Phase C **ไม่ขนานกับ Phase A/B** — Friend 2 หยุดทำงานบน feature branch ก่อน Lead เริ่ม

**ข้อระวัง:** ถ้าลูกค้าอยากเห็น calendar ตั้งแต่ test รอบแรก ต้องสลับ Phase C ขึ้นก่อน Phase B

## Known Bugs / Hotfixes

- `AdminUserController:32` — `reset(reset($x))` ส่ง value แทน reference → แก้แล้ว (afa38ae)
- `AdminUserManagementTest::test_admin_can_create_user` — แก้แล้วใน `test/user-management-coverage` (8ecdfb1) เพิ่ม `employee_id` field + ลบ `withSession` chain

## Git Branching

```
main ← production-ready
  └── sprint ← integration (ใช้แทน develop)
        ├── feature/admin-dashboard-alerts  ✅ merge แล้ว
        ├── 7-m7-search_and_filter          ✅ merge แล้ว
        ├── 3-m2-course_management          ✅ merge แล้ว (18 พ.ค.)
        ├── test/user-management-coverage   🟡 รอ review/merge (8ecdfb1)
        ├── fix/m1-master-data-bugs         🔲 Friend 1
        ├── fix/m2-ux-bugs                  🔲 Friend 2 (urgent: #6, #10, #14)
        └── (after bugs) M3 Schedule        🔲 พร้อมเริ่มหลัง bug merge
```
