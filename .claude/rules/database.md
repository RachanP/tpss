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

// course_offerings.course_type  (Sprint 3 — column ทำเป็น nullable, UI ลบทิ้งแล้ว)
// UI infer จาก lecture_hours + lab_hours + requires_practicum_rotation
['theory', 'practicum', 'theory_practicum']

// course_offering_instructors.role_in_course  (Sprint 3 — varchar(100))
// เก็บเฉพาะ 'coordinator' marker — role จริงดูที่ course_role_id FK
// ค่าเก่า ['coordinator','secretary','instructor','group_advisor','preceptor'] เลิกใช้

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
  Admin เปิด/ปิดผ่าน Settings tab "ช่วงจัดตาราง"
  เปิดทั้งภาคเรียนพร้อมกัน — ไม่ใช่ทีละวิชา

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

## Prerequisite Location (Sprint 3)

- เก็บที่ **course level** (Master Data) ใน table `course_prerequisites`
- ไม่ใช่ที่ offering level — prerequisite เป็น property ของวิชา ไม่เปลี่ยนตามรอบเปิดสอน
- Validation: `Rule::notIn([$course->id])` ป้องกัน self-prerequisite

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

- `make_course_type_nullable_on_courses_table` — `course_type` nullable (UI ลบทิ้งแล้ว, infer แทน)
- `create_course_roles_table` — master list ของบทบาทในวิชา
- `change_role_in_course_to_varchar_on_course_offering_instructors` — enum → varchar(100)
- `refactor_course_roles_remove_code_add_fk` — ลบ `code`, เพิ่ม FK `course_role_id` ใน offering pivot
- `drop_is_active_from_course_roles` — `course_roles` ใช้แค่ master ไม่ต้องเปิด/ปิด
- `create_course_instructors_table` — pivot template ระดับวิชา (course-level pool)
- `add_course_role_id_to_course_instructors_table` — FK `course_role_id` ใน template pivot

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
