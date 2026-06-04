# Database — Enum Values, Schema, Migrations

## Enum Values (อ้างอิงจาก `database/migrations/`)

```php
// user_roles.role  — users ไม่มี role column, query จาก user_roles เสมอ
['admin', 'staff', 'course_head', 'executive', 'instructor']

// academic_years.phase  (สถานะระดับระบบ — Admin ควบคุม)
['preparation', 'scheduling', 'published']

// schedules.status  (slot ระดับกิจกรรม)
['draft', 'pending_approval', 'approved', 'revised']

// course_offerings.approval_status  (ระดับรายวิชาต่อปี — Course Head + Executive)
['draft', 'pending', 'published', 'rejected']

// course_offerings.course_type  ❌ DROPPED ทั้ง column (V2 baseline consolidation)
// เดิม Sprint 3 ทำเป็น nullable + UI ลบทิ้ง → V2 consolidate ตัด column ออกจาก
// create_course_offerings_table baseline เลย (ตรวจ db-check 4 มิ.ย.: column ไม่มีอยู่จริง)
// UI infer จาก lecture_hours + lab_hours + requires_practicum_rotation
//
// ⚠️ คนละตัวกับ courses.course_type ที่ "ยังอยู่" = enum('theory','practicum','theory_practicum')
//    nullable, default null (UI ไม่ใช้ — infer แทน) — drop เฉพาะฝั่ง offering เท่านั้น

// course_offering_instructors.role_in_course  (Sprint 3 — varchar(100))
// เก็บเฉพาะ 'coordinator' marker — role จริงดูที่ course_role_id FK
// ค่าเก่า ['coordinator','secretary','instructor','group_advisor','preceptor'] เลิกใช้

// curriculums.education_level  (19 พ.ค. — รองรับ ป.โท/ป.เอก)
['bachelor', 'master', 'doctorate']

// courses.academic_level  ❌ ลบทิ้งแล้ว 19 พ.ค.
// เหตุผล: ระดับการศึกษาเป็น property ของหลักสูตร ไม่ใช่ของรายวิชา
// → ย้ายไปอยู่ที่ curriculums.education_level

// rooms.status
['active', 'inactive', 'maintenance']

// notifications.type
['conflict', 'warning_quota_exceeded', 'warning_missing_info',
 'warning_capacity', 'warning_no_schedule', 'approval_update']

// course_offering_approvals.action  (Phase 2 — M11)
['submit', 'approve', 'reject', 'revise']

// schedule_conflicts.severity  (Phase 2 — M4/M5)
['conflict', 'warning']

// schedule_conflicts.conflict_type  (บล็อกบันทึก)
['instructor_overlap', 'room_overlap', 'group_overlap']

// schedule_conflicts.warning_type  (บันทึกได้แต่ต้องแก้)
['quota_exceeded', 'capacity_exceeded', 'missing_info', 'no_schedule', 'outside_availability']
```

## Two-Layer Status System (ตกลงแล้ว 16 พ.ค. — implement Sprint 3)

```
ชั้น 1 — ระดับระบบ (academic_years.phase):
  preparation → scheduling → published
  Admin เปิด/ปิดผ่าน Settings tab "ปีการศึกษา" (column "ช่วงจัดตาราง")
  เปิดทั้งปีพร้อมกัน — ไม่ใช่ทีละวิชา
  ⚠️ Master Data Cleanup (V2): academic_years เป็น "ปี" (ตัด semester) → phase ต่อปี ไม่ใช่ต่อเทอม

ชั้น 2 — ระดับรายวิชา (course_offerings.approval_status):
  draft → pending → published / rejected
  Course Head ส่ง → Executive อนุมัติ
```

- `course_offerings.status` (locked/open) **ถูกยกเลิก** — ใช้ `academic_years.phase` แทน
- Guard: ห้ามสร้าง schedule ถ้า `academic_year.phase != 'scheduling'`

## Schedule Status — State Machine

```
[สร้างกิจกรรม] → draft → pending_approval → approved/published
                               ↓ Reject + comment
                            revised → (แก้ไขแล้วส่งใหม่) → draft
```

- `schedules.status` = สถานะระดับ **slot** (แต่ละกิจกรรม)
- `course_offerings.approval_status` = สถานะระดับ **รายวิชาต่อปี**
- `schedules.capacity_required` — Schedule Suite enforce ว่า sum(`student_groups.student_count`) ของ groups ที่ผูกใน slot ≤ ค่านี้
- Validation error key `'schedule'` ใน ScheduleController ส่งเป็น **array of messages** (ไม่ใช่ string implode) — UI render bullet list

## การจัดการวันที่

- **Database**: เก็บเป็น **ค.ศ. (Gregorian)** เสมอ — `2026`, `2026-05-11`
- **UI**: แสดงเป็น **พ.ศ.** (+543) — `2569`, `11 พ.ค. 2569`
- **Helper**: `toBE(int $year): int` → `$year + 543`
- **Academic Year**: ปีการศึกษา 2568 = เริ่ม ส.ค. 2025 ถึง พ.ค. 2026

## Course Role & Template Pool (Sprint 3)

```
course_roles                          ← master list (seed 5 roles)
└── id, name_th, sort_order
    หัวหน้าวิชา / เลขานุการวิชา / อาจารย์ผู้สอน / อาจารย์ประจำกลุ่ม / อาจารย์พี่เลี้ยง

course_instructors (pivot)            ← template ระดับวิชา
└── course_id, user_id, course_role_id (nullable FK)

course_offering_instructors (pivot)   ← snapshot ระดับ offering
└── + course_role_id FK (nullable, ON DELETE SET NULL)
└── role_in_course = 'coordinator' marker เท่านั้น
```

- **Lock semantics**: course template ล็อกเมื่อ offering ใดเข้า `scheduling` หรือ `published` phase
- **Sync direction**: template → offering snapshot ตอน `openSchedulingWindow` (ไม่ sync กลับ)
- **Coordinator**: auto-assign จาก `courses.head_instructor_id` (ไม่อยู่ใน role dropdown ของ UI)
- **Default role** เมื่อเพิ่มอาจารย์ผ่าน UI = "อาจารย์ผู้สอน"
- **Course Pool routes**: `admin.course_pool.*` + `staff.course_pool.*` (Staff inherits Admin controller)

## Curriculum Program Metadata (19 พ.ค. — รองรับ ป.โท/ป.เอก)

```
curriculums
├── education_level enum('bachelor','master','doctorate')  default 'bachelor'
├── duration_years tinyint                                 default 4
├── uses_year_level boolean                                default true
│   ├── true  → ใช้ระบบชั้นปี (cohort) — ป.ตรี
│   └── false → ใช้ prerequisite + หน่วยกิตสะสม — ป.โท/ป.เอก
└── total_credits_required smallint nullable               (required เมื่อ uses_year_level=false)
```

- **courses.default_year_level**: nullable, capped ด้วย `curriculum.duration_years`
- **courses.is_required**: boolean default true (`true`=วิชาบังคับ, `false`=วิชาเลือก) — แทน `academic_level` เดิม
- **Cascade**: toggle `uses_year_level: true → false` ใน updateCurriculum จะ `default_year_level=null` ให้วิชาในหลักสูตรทั้งหมด
- **Auto-defaults ตอนสร้าง curriculum ใหม่** (Alpine):
  - bachelor → `duration_years=4, uses_year_level=true`
  - master   → `duration_years=2, uses_year_level=false, total_credits_required=36`
  - doctorate → `duration_years=3, uses_year_level=false, total_credits_required=48`

## Prerequisite Location (Sprint 3)

- เก็บที่ **course level** (Master Data) ใน table `course_prerequisites`
- ไม่ใช่ที่ offering level — prerequisite เป็น property ของวิชา ไม่เปลี่ยนตามรอบเปิดสอน
- Validation: `Rule::notIn([$course->id])` ป้องกัน self-prerequisite

## Course Code — Format & Uniqueness Policy (ตกลง 18 พ.ค.)

```
Storage:     เก็บตามที่ user พิมพ์ — "NSBS 111" (มี space) คงไว้
Regex:       [A-Za-z0-9 _-]+ บังคับ (ไม่อนุญาต Thai/CJK ใน ID)
Uniqueness:  REPLACE(UPPER(course_code), ' ', '') = ?  ต่อ curriculum_id
             → "NSBS 111" กับ "NSBS111" ถือเป็น duplicate (whitespace-insensitive)
Route bind:  Course::getRouteKeyName() = 'course_code'
             Course::resolveRouteBinding ใช้ limit(2) → ถ้าเจอ > 1 row abort(404)
URL:         /admin/course-pool/NSBS%20111 (space encoded เป็น %20)
```

**Why this design (ไม่ normalize):**
- ลูกค้าพิมพ์ "NSBS 111" → DB เก็บ "NSBS 111" → display "NSBS 111" — ตรงตามที่กรอก
- Duplicate prevention ยังอยู่ — REPLACE-based check จับ "NSBS111" submitted ทับ "NSBS 111" stored
- Trade-off: `courseCodeExistsInCurriculum` ไม่ใช้ DB index → full scan (acceptable: course tables เล็ก)

**Migration:** `2026_05_18_120000_normalize_course_codes` มีอยู่แต่ไม่ active ใน flow ปัจจุบัน (legacy จากความพยายาม normalize ที่ rollback แล้ว) — เก็บไว้เป็น migration history ที่ idempotent บน data ปัจจุบัน

## Student Group — Schema Pattern

```
courses.capacity = 270  ← max รวม (Master Data M1)

course_offerings
└── student_groups: FK → course_offering_id  ← ไม่ใช่ curriculum_id+year_level
    └── NSBS 301/2569: A1(30), A2(30), ... A9(30)
```

- Conflict checking เช็คแค่ **room + instructor** ไม่เช็ค student overlap ข้ามวิชา
- TPSS ไม่ track รายคน — กลุ่มคือ slot ที่มี capacity

## Seeder Pattern (สำคัญ)

- `CourseOfferingSeeder` ดึง `coordinator_id` จาก **`courses.head_instructor_id`** เสมอ — ห้ามหยิบ course_head คนแรกในระบบ
- `CourseSeeder` กำหนด `head_instructor_id` ต่อวิชาตาม mapping: ราชันย์ → NSBS 111/212, พรภิมล → NSBS 213/221
- `DevM2VisualVerificationSeeder` ถูกลบแล้ว — base seeder ครบ ไม่ต้องใช้ seeder แยก

## Migrations Sprint 2 (ต้อง run ถ้ายังไม่ได้รัน)

- `drop_is_practicum_from_activity_types_table` — ลบ `is_practicum` (redundant)
- `add_assigned_staff_to_courses_table` — FK `assigned_staff_id` → users
- `add_capacity_to_courses_table` — `capacity` INT
- `refactor_student_groups_to_course_offering` — FK จาก curriculum+year → course_offering_id
- `create_course_staff_table` — many-to-many `courses` ↔ `users` (แทน `assigned_staff_id`)
- `drop_assigned_staff_from_courses_table` — ลบ FK เดิม
- `add_employee_id_to_users_table` — ย้าย `employee_id` มาอยู่ใน `users` (ออกจาก `instructor_profiles`)
- `add_requires_capacity_to_location_types_table` — `boolean requires_capacity default true` — ห้องในประเภทที่ไม่ต้องการความจุจะไม่โดน alert

## Migrations Sprint 3 (Course Pool + Course Role)

- `make_course_type_nullable_on_courses_table` — `courses.course_type` nullable (UI ลบทิ้งแล้ว, infer แทน) — ✅ ยังจริง (courses ยังมี column nullable) · ⚠️ alter migration นี้ถูก consolidate เข้า create-table baseline แล้ว (ไม่มีไฟล์แยก) เช่นเดียวกับ alter อื่น ๆ ในลิสต์นี้ — ตรวจสอบจาก `migrate:status` จะเห็นแค่ `create_*` baselines
- `create_course_roles_table` — master list ของบทบาทในวิชา
- `change_role_in_course_to_varchar_on_course_offering_instructors` — enum → varchar(100)
- `refactor_course_roles_remove_code_add_fk` — ลบ `code`, เพิ่ม FK `course_role_id` ใน offering pivot
- `drop_is_active_from_course_roles` — `course_roles` ใช้แค่ master ไม่ต้องเปิด/ปิด
- `create_course_instructors_table` — pivot template ระดับวิชา (course-level pool)
- `add_course_role_id_to_course_instructors_table` — FK `course_role_id` ใน template pivot

## Migrations Sprint 3 Hardening (19 พ.ค. — consolidated into create-table baselines)

- `add_program_metadata_to_curriculums_table` — `education_level`, `duration_years`, `uses_year_level`, `total_credits_required`
- `drop_academic_level_from_courses_table` — ลบ `courses.academic_level` (ย้ายไป curriculums.education_level)
- `add_is_required_to_courses_table` — `boolean is_required default true` แทน academic_level

**หมายเหตุ**: ทั้ง 3 รายการนี้ถูก consolidate เข้า create-table migrations เดิม (ไม่มี alter migration แยก) — flow ทีมคือ `migrate:fresh --seed` เสมอ ไม่จำเป็นต้องเก็บ alter migration ไว้สำหรับ forward-only path

## PA Criteria Config

เก็บใน `system_settings` key `pa_criteria_config` เป็น JSON:

```json
{
  "อาจารย์": {
    "t": {"min": 20, "max": 70},
    "r": {"min": 20, "max": 70},
    "s": {"min": 5,  "max": 20},
    "c": {"min": 5,  "max": 15},
    "o": {"min": 0,  "max": 20}
  },
  "ผู้ช่วยอาจารย์":        { ... },
  "ผู้ช่วยอาจารย์_ปตรี":   { ... },
  "ผู้ช่วยอาจารย์_คลินิก": { ... },
  "ผู้ช่วยอาจารย์_ปฏิบัติ":{ ... }
}
```

- keys `t/r/s/c/o` = สอน/วิจัย/บริการฯ/ศิลปะฯ/มอบหมาย
- **ห้ามใช้ string format เดิม** (`"20-70%"`) — `AlertController::getPaViolations()` จะ error
- `AdminSettingController::defaultPaCriteria()` คืนค่า default ถ้า DB ว่างหรือ format เก่า

**Phase 2 tables (เตรียมไว้แล้ว):** `course_offering_approvals`, `schedule_conflicts`
ER Diagram: `mock/er_v1.jpg`

## V2 Schema — แยกเป็น 3 สถานะ (DONE / DECIDED-cleanup / PROPOSED)

> ⚠️ ทีมใช้ `migrate:fresh --seed` เสมอ → consolidate เข้า create-table baselines ไม่ทำ alter แยก (pattern Sprint 3 Hardening)
> Rationale ดู `architecture.md` "Requirement V2 Direction" + "Master Data Cleanup Phase (V2)"

### ✅ DONE (branch `feat/v2-requirement` — Master Data Cleanup เสร็จครบ scope V3)
```
student_cohorts  :  (curriculum_id, year_level nullable, code, student_count)
                    year_level=null สำหรับหลักสูตรที่ uses_year_level=false (ป.โท/ป.เอก)
                    unique(curriculum_id, year_level, code)
academic_years   :  ตัด column semester → unique(name) · phase/is_active ต่อ "ปี"
terms            :  NEW ลูกของปี (academic_year_id, sequence, name, start_date, end_date,
                    midterm_start, midterm_end, final_start, final_end)  ← ช่วงสอบเก็บเป็น "สัปดาห์สอบ"
                    เพิ่มได้ตามจริง: ปกติ 2 เทอม · ภาคฤดูร้อน = เพิ่มรายการที่ 3 (optional)
                    ช่วงปิดภาคเรียน = derive จากช่องว่างระหว่างเทอม (ไม่เก็บแยก)
                    วันปีการศึกษา (start/end) = derive จาก min/max ของ terms
courses          :  ตัด default_semester (วิชาเปิดทั้งปี)
course_offerings :  academic_year_id → ราย-ปี · unique(course_id, academic_year_id)
                    auto-open: ทุกวิชาที่ course.curriculum.is_active = true (เลิกผูกเทอม)
holidays         :  NEW (date unique, name, remark, source enum-ish 'google'|'manual')
                    auto-fetch Google Thai ICS ตอนสร้าง/แก้ปี · refresh ลบเฉพาะ source=google
                    คงของ manual + ปีอื่น · SSL ใช้ resources/certs/cacert.pem
activity_types   :  + counts_toward_workload boolean default true (default ตามหมวด: other=false)
```

### ✅ DONE — Schedule phase (term dimension · branch feat/v2-requirement)
```
schedules : + term_id FK (nullable, → terms) · derive อัตโนมัติจาก start_date ผ่าน
            ScheduleTermObserver (ครอบ store/update/series/copyWeek) · null = ปิดภาคเรียน
```
- `App\Services\AcademicCalendar` — จำแนกวัน normal/holiday/exam/break/outside + `blockReasonForRange()`
- หน้าจัดตาราง: filter เทอม (default เทอมปัจจุบัน + เด้งปฏิทิน) · ลงสี grid/list (วันหยุด=อำพัน, สอบ/ปิดเทอม/เทอมอื่น=เทา) + chip/flag/term badge · บล็อกจัดกิจกรรมช่วงสอบ/ปิดเทอม/เทอมอื่น (server `assertScheduleNotOnBlockedDay` + realtime + client cell) · ตัด group selector ออกจาก modal (หัวหน้าวิชาไม่จัดกลุ่ม) — ⚠️ **V4 (4 มิ.ย.) กลับทิศ: หัวหน้าวิชาจัดกลุ่มเอง → ต้องเอา group selector กลับเข้า modal**

### 🔲 PROPOSED — phase ถัดไป (rotation · ยังไม่ทำ)
> 🆕 V4 (4 มิ.ย.): ปี 3-4 = **4 กลุ่มใหญ่** · rotation = ปีละ **4 รอบ** (มิดเทอมเป็นเส้นแบ่งในแต่ละเทอม → เทอมละ 2 รอบ) **+ สลับกลุ่มข้ามเทอม** · หัวหน้าวิชาจัดกลุ่มเองตั้งแต่กรอกตาราง (ฐาน = จำนวน นศ. ชั้นปี) ดู [[project-requirement-v4]]
```
schedules        :  + rotation_round_id FK
student_groups   :  + cohort_group_id FK nullable  ← subgroup อ้าง student_cohorts
rotation_rounds      : NEW (term_id, sequence 1|2, label, start/end) — sequence 1|2 = ก่อน/หลังมิดเทอมในเทอมนั้น
rotation_assignments : NEW (cohort_group_id, course_offering_id, rotation_round_id) — รองรับสลับกลุ่มข้ามเทอม
rooms : + campus (ศาลายา/บางกอกน้อย — display ก่อน · optional ไม่อยู่ใน V3)
```

### ✅ DONE — delegation (branch feat/v2-requirement)
```
course_offering_instructors : + schedule_permission string(20) default 'view'  ('view'|'schedule')
  consolidate เข้า create-table baseline (2026_05_08_072856) — ไม่มี alter แยก
```
- `CourseOffering::canBeScheduledBy(?int)` + `scopeSchedulableBy(int)` — coordinator **หรือ** instructor permission='schedule' **หรือ** staff ใน `course_staff` (assignedStaff ของวิชา)
- หัวหน้าวิชา toggle อาจารย์ผ่าน `maker.course_offerings.instructors.permission` (course_head only) · staff มอบหมายผ่าน modal รายวิชา (`course->assignedStaff()->sync`)
- route จัดตาราง slot = `CheckRole:course_head,instructor,staff` · จัดการ offering + conflict-badge = `course_head`

**Cross-course GROUP conflict:** ไม่ใช่ schema ใหม่ แต่ต้องขยาย logic — `ScheduleConflictChecker::bulkConflictMap()` เพิ่ม pairwise compare `cohort_group` ข้ามวิชา (ปัจจุบันเช็คแค่ instructor/room)
