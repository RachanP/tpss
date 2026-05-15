# Database — Enum Values, Schema, Migrations

## Enum Values (อ้างอิงจาก `database/migrations/`)

```php
// user_roles.role  — users ไม่มี role column, query จาก user_roles เสมอ
['admin', 'staff', 'course_head', 'executive', 'instructor']

// schedules.status  (slot ระดับกิจกรรม)
['draft', 'pending_approval', 'approved', 'revised']

// course_offerings.approval_status  (ระดับรายวิชาต่อปี)
['draft', 'pending', 'published', 'rejected']

// course_offerings.course_type
['theory', 'practicum', 'theory_practicum']

// course_offering_instructors.role_in_course
['coordinator', 'secretary', 'instructor', 'group_advisor', 'preceptor']

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

## Student Group — Schema Pattern

```
courses.capacity = 270  ← max รวม (Master Data M1)

course_offerings
└── student_groups: FK → course_offering_id  ← ไม่ใช่ curriculum_id+year_level
    └── NSBS 301/2569: A1(30), A2(30), ... A9(30)
```

- Conflict checking เช็คแค่ **room + instructor** ไม่เช็ค student overlap ข้ามวิชา
- TPSS ไม่ track รายคน — กลุ่มคือ slot ที่มี capacity

## Migrations Sprint 2 (ต้อง run ถ้ายังไม่ได้รัน)

- `drop_is_practicum_from_activity_types_table` — ลบ `is_practicum` (redundant)
- `add_assigned_staff_to_courses_table` — FK `assigned_staff_id` → users
- `add_capacity_to_courses_table` — `capacity` INT
- `refactor_student_groups_to_course_offering` — FK จาก curriculum+year → course_offering_id
- `create_course_staff_table` — many-to-many `courses` ↔ `users` (แทน `assigned_staff_id`)
- `drop_assigned_staff_from_courses_table` — ลบ FK เดิม
- `add_employee_id_to_users_table` — ย้าย `employee_id` มาอยู่ใน `users` (ออกจาก `instructor_profiles`)
- `add_requires_capacity_to_location_types_table` — `boolean requires_capacity default true` — ห้องในประเภทที่ไม่ต้องการความจุจะไม่โดน alert

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
