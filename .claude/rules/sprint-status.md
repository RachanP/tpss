# Sprint Status — ณ 28 พ.ค. 2569

## Phase Overview

| Phase | ชื่อ | สถานะ |
|-------|------|-------|
| Phase 1–3 | Initiation → Design | ✅ เสร็จ |
| Phase 4–5 | Development | 🟢 Sprint 1+2+3+M7 merge แล้ว, Schedule Suite (M3+M4+M8) Phase A/B/C ส่งบน branch `4-m3-schedule-suite-testPat` |
| Phase 5 | Testing | 🟡 Internal Testing กำลังดำเนินการ |
| Phase 6–7 | Deployment → Closure | ยังไม่เริ่ม (4–7 มิ.ย. 2569) |

## Sprint Plan — Phase 1 (193 SP)

| Sprint | วันที่ | Module | สถานะ |
|--------|--------|--------|-------|
| Sprint 1 | 11–12 พ.ค. | M10 Login/RBAC | ✅ 100% |
| Sprint 2 | 12–15 พ.ค. | M1 Master Data | ✅ 100% |
| Sprint 3 | 18–19 พ.ค. | M2 Course Management | ✅ merge เข้า sprint แล้ว (18 พ.ค.) |
| **Sprint 4–6** | **20–28 พ.ค.** | **M3 + M4 + M8 → Schedule Suite** | ✅ merge เข้า sprint แล้ว — รวม Cross-course conflict (branch `4-m4-cross-course-conflict` merge `42e4810`) |
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
- `academic_years.phase` (preparation → scheduling → published) — จัดการในแท็บ "ปีการศึกษา" (column "ช่วงจัดตาราง")
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

### Sprint 3 Hardening — 19 พ.ค.

- **academic_level refactor** (commit `5f436b5`): ย้าย `courses.academic_level` → `curriculums.education_level` (ระดับการศึกษาเป็น property ของหลักสูตร)
- **รองรับ ป.โท/ป.เอก**: เพิ่ม `curriculums.{education_level, duration_years, uses_year_level, total_credits_required}` — ป.โท/ป.เอกใช้ระบบ prerequisite + หน่วยกิตสะสมแทนระบบชั้นปี
- **`courses.is_required`** boolean แทน academic_level (วิชาบังคับ/เลือก)
- **`courses.default_year_level`** เป็น nullable, capped ตาม `curriculum.duration_years`
- **updateCurriculum cascade**: toggle `uses_year_level: true → false` → `default_year_level=null` ทุกวิชาในหลักสูตร
- **Settings UI merge** (commit `b05381b`): รวม tab "ช่วงจัดตาราง" เข้า tab "ปีการศึกษา" — ตารางเดียวรวม phase column + ปุ่ม toggle (admin-only) staff เห็น read-only — ลด cognitive load
- **CSV importer**: รับ `is_required` column แบบ optional (default true) + preload curriculums (เลิก N+1)
- **เพิ่ม 4 tests**: master curriculum without year_level, course year_level capped by duration, credit-based requires total_credits, cascade clear on toggle

## Schedule Suite (M3 + M4 + M8) — ✅ merge เข้า sprint แล้ว

> งาน Friend 2 (pronpimon013) — commits 22–25 พ.ค. + Cross-course conflict (merge `42e4810`)

### M3 — Schedule CRUD
- `CourseHead\ScheduleController` — index/create/store/update/destroy + workspace mode + per-offering view
- View ใหม่: `course_head/schedules/index.blade.php` (list + calendar), `_form.blade.php` (modal-based)
- Realtime conflict checks ผ่าน debounced AJAX (commit `741e51d`)
- List warning badges (quota / missing info / capacity) (commit `80fd486`)
- Dashboard widget "ตารางสอนที่กำลังจะถึง" (commit `3026295`)
- Optional `ScheduleFlowSeeder` (`php artisan db:seed --class=ScheduleFlowSeeder`) — พาระบบจาก fresh seed → state จัดตารางพร้อมข้อมูลตัวอย่าง

### M4 — Conflict & Validation Hardening (commits `c4eebcd`, `d41055c`, `42e4810`)
- **Instructor / room / group overlap** ภายใน offering เดียวกัน บล็อกบันทึก (severity `conflict`)
- **Cross-course conflict** ✅ — instructor/room overlap ข้ามวิชาผ่าน `ScheduleConflictChecker::bulkConflictMap()` + `ScheduleConflictReadRepository` (read model สำหรับหน้า conflicts) — `buildOwnedConflictMap()` ใน `CourseHead\ScheduleController` ดึง schedules ทั้งระบบที่ overlap date window แล้ว pairwise compare
- **Department gate** — อาจารย์ที่เลือกใน slot และที่ add เข้า instructor pool ต้องมี `instructor_profiles.department_id == courses.department_id` (`ScheduleController::assertInstructorsBelongToCourseDepartment`, `CourseHead\CourseOfferingController::storeInstructor`)
- **Capacity gate** — sum(`student_groups.student_count`) ของ groups ที่เลือก ≤ `capacity_required` (`ScheduleController::assertSelectedGroupsFitCapacity`)
- Conflict error key `'schedule'` ส่งเป็น **array of messages** (ไม่ใช่ string implode) → UI render เป็น bullet list
- **Conflicts page** — `/course_head/schedule/conflicts` dedicated page (4-card dashboard summary + per-schedule detail + styled popover tooltip, portal pattern)

### M8 — Calendar Views (commit `96f3fa7`, `7633390`, `e8ea5e1`)
- Period filters: **day / week / month** — toggle ผ่าน query param `?period=day|week|month&date=YYYY-MM-DD`
- Date picker year range คำนวณจาก `AcademicYear.start_date/end_date` (`ScheduleController::scheduleDatePickerYearRange`)
- Label `นอกช่วงปีการศึกษา` เมื่อวันที่ตกนอก start/end ของปีการศึกษาที่ active
- Month grid testid: `schedule-month-calendar-co`
- Grid events align ตาม start_time/end_time (commit `e8ea5e1`)

### Settings — Academic Year Activation Lock (commit `4df394c`)
- `AdminSettingController::storeYear/updateYear` block ตั้งปีอื่น `is_active=true` ถ้ามี `AcademicYear` ใดเหลือ `phase='scheduling'`
- Error message: "ไม่สามารถตั้งปีการศึกษา ... เป็นปีปัจจุบันได้ เนื่องจากยังมีช่วงจัดตารางที่เปิดใช้งานอยู่"

### Tests เพิ่ม
- `ScheduleManagementTest::test_schedule_index_supports_day_week_and_month_periods` — period labels (`03/08/2569`, `สัปดาห์ที่ 1`, `สิงหาคม 2569`, `นอกช่วงปีการศึกษา`)
- `ScheduleManagementTest` capacity + department + conflict-array tests (commit `d41055c`)
- `SchedulingPhaseTest` — active-year switch lock ระหว่าง scheduling phase

## Post-Merge Branch Split (ตกลง 25 พ.ค.)

หลัง merge `4-m3-schedule-suite-testPat` เข้า sprint แล้ว แตก 3 branch ขนาน:

| คน | งาน | ขอบเขต |
|----|-----|--------|
| **Rachan (Lead)** | Course Offering UI fixes | หน้าจัดการ course offering — polish layout/UX ต่อ |
| **pronpimon** | M4 Cross-Course Conflict Check | ✅ merge แล้ว (`42e4810`) — ตรวจ conflict ห้อง/ผู้สอนข้ามรายวิชาภายในคณะ |
| **phuwadon** | User Management UX/UI | หน้าจัดการผู้ใช้ — polish + accessibility |

### Test Coverage (134 tests / 133 passing — pre-Schedule Suite)
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
- Admin เปิด/ปิดผ่าน Settings tab "ปีการศึกษา" (column "ช่วงจัดตาราง", admin-only — staff เห็นเป็น pill อย่างเดียว)
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
| ใครกด "ยืนยันเปิด" ให้จัดตาราง? | **Admin** ผ่าน Settings tab "ปีการศึกษา" (ปุ่ม toggle ใน column "ช่วงจัดตาราง") |
| Course Head รู้ว่าวิชาถูกเปิดให้จัดตารางยังไง? | Phase 2 — email notification (Gmail) |
| Prerequisite ระดับวิชาหรือระดับรอบเปิดสอน? | ระดับวิชา (M1 Master Data) |
| ป.โท/ป.เอก ใช้ระบบชั้นปีไหม? | ไม่ — ใช้ prerequisite + หน่วยกิตสะสม (`curriculums.uses_year_level=false`) |

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

## Bug Report — 28 พ.ค. 2569 รอบ 2 🟡 BACKLOG

หลัง close 12 bugs รอบแรก ลูกค้า/Rachan แจ้งเพิ่ม 3 รายการ (priority: #0+#1 → #2 → #3)

### #0+#1 — Location Type "ใช้ร่วมกันได้" (Bug + UX) — S/M effort

Field `location_types.requires_capacity` มีอยู่แล้วแต่ครอบคลุมแค่ capacity alert — ต้องขยาย:

| ติ๊ก (`is_shared=true`) | ไม่ติ๊ก (default) |
|---|---|
| ❌ ไม่เตือน capacity | ✅ เตือน capacity |
| ❌ ไม่เช็ค room overlap ข้ามวิชา | ✅ เช็ค cross-course room conflict |
| ❌ ไม่เช็ค in-course room overlap | ✅ เช็ค (มีอยู่แล้ว) |

**UX:**
- เปลี่ยน label: `"ต้องระบุความจุ (จำนวนที่นั่ง)"` → `"สถานที่ใช้ร่วมกันได้ (ขนาดใหญ่)"`
- พลิก semantic — ต้อง migration พลิกค่า OR rename column → `is_shared`
- เพิ่ม description: `"เช่น โรงพยาบาล, ชุมชน, สนามฝึก — กิจกรรมหลายวิชาใช้สถานที่เดียวกันพร้อมกันได้"`

**ไฟล์เกี่ยวข้อง:** `database/migrations/...rename_or_add_is_shared.php`, `shared/master_data/index.blade.php`, `ScheduleConflictChecker.php` หรือ `ScheduleConflictIndex.php` (skip room overlap ถ้า `room.locationType.is_shared`), `Admin/AlertController.php` (skip capacity alert)

### #2 — Filter ตารางตามอาจารย์ (Enhancement) — S effort

หน้าตารางสอนหัวหน้าวิชา เพิ่ม dropdown filter "เลือกอาจารย์" ใน toolbar → ดูเฉพาะ slot ที่อาจารย์คนนั้นสอน รองรับทุก view (list/week/day/month)

**ไฟล์เกี่ยวข้อง:** `CourseHead/ScheduleController.php` (รับ `?instructor_id=` ใน `schedulePageData()`), `course_head/schedules/index.blade.php` (toolbar + filter UI + join `schedule_instructors`)

### #3 — Auto-duplicate กิจกรรมรายสัปดาห์ (Feature ใหญ่) — L effort, sprint ใหม่

หัวหน้าวิชาควรสร้าง slot ต้นแบบ (วันในสัปดาห์ + เวลา + ช่วงสัปดาห์) → ระบบ auto-generate slot ลูกทุกสัปดาห์ → แก้แค่ห้อง+กลุ่ม นศ. ต่อสัปดาห์ (workflow ของ practicum rotation จริง)

**Schema:** `schedules.practicum_series_id` มีอยู่แล้ว — อาจต้อง table ใหม่ `schedule_templates` ถ้า design ต้องการ separate template/instance

**คำถามที่ต้องถามลูกค้าก่อน design:**
- แก้ template ภายหลัง → slot ลูกที่แก้ไปแล้ว ควรถูก overwrite ไหม?
- จำนวนสัปดาห์ default = `course.teaching_weeks` หรือไม่?
- วันหยุด → skip auto หรือ user ลบเอง?

**Effort:** ~2-3 วัน, ขนาดเท่า Schedule Suite phase ย่อย — ควรเป็น sprint ของตัวเอง

---

## Bug Report — 28 พ.ค. 2569 (อัพเดต_แก้บัค.pdf) ✅ ปิดหมดแล้ว

12 รายการจาก test รอบ Schedule Suite — แบ่ง 3 branch ทำขนาน, merge เข้า sprint ครบทั้งหมด:

### Branch A — `fix/conflicts-page-detail` (Rachan, Lead) ✅ merge `ad76341`
หน้าแจ้งเตือนการชน + sidebar + tooltip:
1. ลบ dropdown ปีการศึกษา + เพิ่ม detail (อาจารย์/ห้อง/กลุ่ม) ในกล่องแจ้งเตือน + สรุปภาพรวม 4 cards
9. Empty state แยก `preparation` / `no_offerings` / `no_conflicts` — สร้าง `App\Support\CoordinatorEmptyState` ใช้ร่วมทุกหน้าฝั่ง course_head
10. Sidebar icon (CRITICAL=circle, WARNING=triangle) sync กับหน้า admin.alerts
11. Tooltip การชนเป็น styled popover ใน `_conflict_pill.blade.php` ใช้ **portal pattern** (escape transform/overflow ของ card cell ที่ block `position: fixed`)
12. Count "ดูทั้งหมด N" ใช้ `COUNT(DISTINCT conflicting_schedule_id)` ตรงกับ cards ที่ render หลัง `groupBy(schedule_id)` + preview fetch ครบทุก record ของ top N distinct schedules → preview = expanded view

### Branch B — `fix/schedule-calendar-ui` (pronpimon) ✅ merge `219becf`
Calendar render + mobile responsive (10 commits ใน `schedules/index.blade.php` + `app.css`):
4. ตารางสอนมุมสัปดาห์ scroll แนวนอนในจอ mobile (iPhone)
5. การ์ดมุมมองเดือน align กับ week/day grid styling
7. ป้าย "นอกช่วงปีการศึกษา" wrap ขึ้นบรรทัดบนเมื่อจอแคบ
8. Ghost card contain ใน column ของวันตัวเอง

### Branch C — `fix/schedule-logic-form` (phuwadon) ✅ merge `9b0b43f`
Controller logic + modal form (commit msg เดิม "แก้บัคยับๆครับเฮีย" — amend แล้ว):
2. Default รายวิชารหัสน้อยสุด (course_code ASC)
3. Modal เพิ่มกิจกรรม: clear default time + required validation
6. Filter วิชา `status='active'` ใน coordinator queries

### Lessons learned (ใช้กับงานขนานครั้งต่อไป)

- **แบ่งไฟล์ก่อน แล้วค่อยลงมือ** — phuwadon/pronpimon/Rachan แตะ `schedules/index.blade.php` ทั้ง 3 คน แต่ละคนละจุด → rebase ไม่ conflict สักครั้ง (เพราะวางแผนล่วงหน้า)
- **Merge order** (phuwadon → pronpimon → Rachan) ตามแผนเดิม รันได้ตามจริง
- **`--force-with-lease`** เป็น default บน branch ตัวเอง — safe ปฏิเสธถ้ามีคนทับ
- **Reword commit message ของเพื่อน** — ถ้าจำเป็น: reset sprint → cherry-pick + amend (preserve author) → force push sprint — ทำได้ในช่วง window สั้น ๆ ก่อนคนอื่น pull

---

## Sprint 3 Bug Report — 18 พ.ค. 2569 (bug_sprint2_18_05_69.pdf) ✅ ปิดหมดแล้ว

15 bugs จากการ test M1/M2/M10 — ปิดแล้วใน 2 branch:
- **Branch A** (`fix/m1-master-data-bugs`) — merged `d614817` + review fixes `6770a65, fe0ebee`
- **Branch B** (`fix/m2-ux-bugs`) — merged `23b4e0f` + review fixes `5ef4386`
- พร้อมเริ่ม Schedule Suite (M3+M4+M8) ได้แล้ว

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
        ├── test/user-management-coverage   ✅ merge แล้ว (8ecdfb1)
        ├── fix/m1-master-data-bugs         ✅ merge แล้ว (d614817 + 6770a65 + fe0ebee review fixes)
        ├── fix/m2-ux-bugs                  ✅ merge แล้ว (23b4e0f + 5ef4386 review fixes)
        └── (next) Schedule Suite M3+M4+M8  🟢 พร้อมเริ่ม
```
