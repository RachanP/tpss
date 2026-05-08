# TPSS Data Dictionary

**Teaching & Practicum Scheduling System — คณะพยาบาลศาสตร์ มหาวิทยาลัยมหิดล**

> อ้างอิง: `database/migrations/` (25 ไฟล์) + `mock/er_v1.jpg` | 8 พ.ค. 2569

---

## สารบัญตาราง

| # | ตาราง | ชื่อไทย | Module | Phase |
|---|--------|---------|--------|-------|
| 1 | `users` | ผู้ใช้งาน | M10 | 1 |
| 2 | `user_roles` | บทบาทผู้ใช้ (pivot) | M10 | 1 |
| 3 | `departments` | ภาควิชา | M1 | 1 |
| 4 | `instructor_profiles` | ประวัติอาจารย์ | M1 | 1 |
| 5 | `instructor_availability` | ความพร้อมสอน | M1 | 1 |
| 6 | `curriculums` | หลักสูตร | M1 | 1 |
| 7 | `academic_years` | ปีการศึกษา | M1 | 1 |
| 8 | `location_types` | ประเภทสถานที่ | M1 | 1 |
| 9 | `rooms` | ห้อง/สถานที่ฝึก | M1 | 1 |
| 10 | `activity_types` | ประเภทกิจกรรม | M1 | 1 |
| 11 | `courses` | รายวิชา | M2 | 1 |
| 12 | `course_prerequisites` | วิชาบังคับก่อน (pivot) | M2 | 1 |
| 13 | `course_offerings` | รายวิชาที่เปิดสอน | M2 | 1 |
| 14 | `course_offering_instructors` | อาจารย์ประจำวิชา (pivot) | M2 | 1 |
| 15 | `practicum_series` | ชุดฝึกปฏิบัติ | M2 | 1 |
| 16 | `student_groups` | กลุ่มนักศึกษา | M1 | 1 |
| 17 | `schedules` | ตารางสอน (กิจกรรม) | M3 | 1 |
| 18 | `schedule_instructors` | ผู้สอนในกิจกรรม (pivot) | M3 | 1 |
| 19 | `schedule_student_groups` | กลุ่มนักศึกษาในกิจกรรม (pivot) | M3 | 1 |
| 20 | `notifications` | การแจ้งเตือน | M5/M7 | 1 |
| 21 | `audit_logs` | บันทึกการเปลี่ยนแปลง | M12 | 2 |
| 22 | `system_settings` | ตั้งค่าระบบ | M10 | 1 |
| 23 | `course_offering_approvals` | ประวัติการอนุมัติ | M11 | 2 |
| 24 | `schedule_conflicts` | ความขัดแย้งที่ตรวจพบ | M4/M5 | 2 |
| 25 | `sessions` | Laravel Session | — | — |

---

## Enum Values สรุป

| ตาราง.คอลัมน์ | ค่าที่เป็นได้ | คำอธิบาย |
|---------------|-------------|----------|
| `user_roles.role` | `admin`, `staff`, `course_head`, `executive`, `instructor` | 5 บทบาท |
| `rooms.status` | `active`, `inactive`, `maintenance` | สถานะห้อง |
| `activity_types.category` | `lecture`, `practicum`, `thesis`, `other` | หมวดกิจกรรม |
| `courses.course_type` | `theory`, `practicum`, `theory_practicum` | ประเภทวิชา |
| `course_offerings.approval_status` | `draft`, `pending`, `published`, `rejected` | สถานะอนุมัติระดับวิชา |
| `course_offering_instructors.role_in_course` | `coordinator`, `secretary`, `instructor`, `group_advisor`, `preceptor` | บทบาทในวิชา |
| `schedules.status` | `draft`, `pending_approval`, `approved`, `revised` | สถานะกิจกรรม |
| `notifications.type` | `conflict`, `warning_quota_exceeded`, `warning_missing_info`, `warning_capacity`, `warning_no_schedule`, `approval_update` | ประเภทแจ้งเตือน |
| `course_offering_approvals.action` | `submit`, `approve`, `reject`, `revise` | การดำเนินการอนุมัติ |
| `schedule_conflicts.severity` | `conflict`, `warning` | ระดับความรุนแรง |
| `schedule_conflicts.conflict_type` | `instructor_overlap`, `room_overlap`, `group_overlap` | ประเภท conflict (บล็อกบันทึก) |
| `schedule_conflicts.warning_type` | `quota_exceeded`, `capacity_exceeded`, `missing_info`, `no_schedule`, `outside_availability` | ประเภท warning (บันทึกได้) |

---

## 1. `users` — ผู้ใช้งาน

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสผู้ใช้ |
| `username` | VARCHAR(100) | UNIQUE | รหัสเข้าระบบ เช่น staff_01 |
| `name` | VARCHAR(255) | NOT NULL | ชื่อ-สกุล |
| `email` | VARCHAR(255) | UNIQUE | อีเมล |
| `password` | VARCHAR(255) | NOT NULL | รหัสผ่าน (hashed) |
| `is_active` | BOOLEAN | DEFAULT true | สถานะใช้งาน |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |
| `deleted_at` | TIMESTAMP | NULL | Soft Delete |

> ไม่มีคอลัมน์ `role` — บทบาทอ้างอิงจาก `user_roles` เสมอ

---

## 2. `user_roles` — บทบาทผู้ใช้ (Pivot)

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `user_id` | BIGINT UNSIGNED | PK, FK→users CASCADE | รหัสผู้ใช้ |
| `role` | ENUM | PK | `admin`/`staff`/`course_head`/`executive`/`instructor` |
| `is_primary` | BOOLEAN | DEFAULT false | role เริ่มต้นเมื่อ login |
| `created_at` | TIMESTAMP | NULL | วันที่กำหนดบทบาท |

---

## 3. `departments` — ภาควิชา

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสภาควิชา |
| `name` | VARCHAR(255) | NOT NULL | ชื่อภาควิชา |
| `head_user_id` | BIGINT UNSIGNED | FK→users, NULL | หัวหน้าภาควิชา |
| `secretary_user_id` | BIGINT UNSIGNED | FK→users, NULL | เลขานุการภาควิชา |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 4. `instructor_profiles` — ประวัติอาจารย์

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสประวัติ |
| `user_id` | BIGINT UNSIGNED | UNIQUE, FK→users | อ้างอิงผู้ใช้ (1:1) |
| `title` | VARCHAR(100) | NULL | ตำแหน่งวิชาการ เช่น รศ.ดร. |
| `department_id` | BIGINT UNSIGNED | FK→departments, NULL | ภาควิชาที่สังกัด |
| `teaching_quota` | INTEGER | NULL | ภาระงานสอน (ชม./เทอม) |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 5. `instructor_availability` — ความพร้อมสอน

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัส |
| `user_id` | BIGINT UNSIGNED | FK→users | อาจารย์ |
| `day_of_week` | TINYINT | NOT NULL | 0=อา., 1=จ., ..., 6=ส. |
| `start_time` | TIME | NOT NULL | เวลาเริ่ม |
| `end_time` | TIME | NOT NULL | เวลาสิ้นสุด |
| `is_available` | BOOLEAN | DEFAULT true | พร้อมสอนหรือไม่ |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 6. `curriculums` — หลักสูตร

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสหลักสูตร |
| `name` | VARCHAR(255) | NOT NULL | เช่น พยาบาลศาสตรบัณฑิต (ปรับปรุง 2565) |
| `effective_year` | INTEGER | NOT NULL | ปีที่เริ่มใช้ (ค.ศ.) |
| `is_active` | BOOLEAN | DEFAULT true | ยังใช้อยู่ |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 7. `academic_years` — ปีการศึกษา

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสปีการศึกษา |
| `name` | VARCHAR(255) | NOT NULL | เช่น 2569 |
| `semester` | INTEGER | NOT NULL | 1, 2, 3 |
| `start_date` | DATE | NOT NULL | วันเริ่ม (ค.ศ.) |
| `end_date` | DATE | NOT NULL | วันสิ้นสุด (ค.ศ.) |
| `is_active` | BOOLEAN | NOT NULL | ปีปัจจุบัน |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

> UNIQUE (`name`, `semester`) | DB=ค.ศ. UI=พ.ศ.(+543)

---

## 8. `location_types` — ประเภทสถานที่

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัส |
| `name` | VARCHAR(100) | NOT NULL | Lecture, Lab, Ward, Online, External |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 9. `rooms` — ห้อง/สถานที่ฝึก

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสห้อง |
| `room_code` | VARCHAR(255) | UNIQUE | รหัสห้อง |
| `room_name` | VARCHAR(255) | NOT NULL | ชื่อห้อง |
| `building` | VARCHAR(100) | NULL | อาคาร |
| `capacity` | INTEGER | NOT NULL | ความจุ |
| `location_type_id` | BIGINT UNSIGNED | FK→location_types | ประเภทสถานที่ |
| `equipment_type` | JSON | NULL | อุปกรณ์ |
| `address` | TEXT | NULL | ที่อยู่แหล่งฝึกภายนอก |
| `status` | ENUM | NOT NULL | `active`/`inactive`/`maintenance` |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 10. `activity_types` — ประเภทกิจกรรม

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัส |
| `name` | VARCHAR(100) | NOT NULL | Lecture, Lab, Round Ward ฯลฯ |
| `color_code` | VARCHAR(10) | DEFAULT '#3498db' | สีแสดงในตาราง |
| `is_practicum` | BOOLEAN | NULL | เป็นฝึกปฏิบัติหรือไม่ |
| `category` | ENUM | NOT NULL | `lecture`/`practicum`/`thesis`/`other` |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 11. `courses` — รายวิชา

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสรายวิชา |
| `course_code` | VARCHAR(255) | NOT NULL | รหัสวิชา เช่น NSG101 |
| `curriculum_id` | BIGINT UNSIGNED | FK→curriculums | หลักสูตรที่สังกัด |
| `name_th` | VARCHAR(255) | NOT NULL | ชื่อวิชา (ไทย) |
| `name_en` | VARCHAR(255) | NULL | ชื่อวิชา (อังกฤษ) |
| `course_type` | ENUM | NOT NULL | `theory`/`practicum`/`theory_practicum` |
| `requires_practicum_rotation` | BOOLEAN | NOT NULL | ต้องมี Rotation ฝึกหรือไม่ |
| `credits` | INTEGER | NOT NULL | หน่วยกิต |
| `lecture_hours` | INTEGER | NOT NULL | ชม.บรรยาย |
| `lab_hours` | INTEGER | NOT NULL | ชม.ปฏิบัติการ |
| `color_code` | VARCHAR(10) | NULL | สีแสดงในตาราง |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |
| `deleted_at` | TIMESTAMP | NULL | Soft Delete |

> UNIQUE (`course_code`, `curriculum_id`)

---

## 12. `course_prerequisites` — วิชาบังคับก่อน (Pivot)

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `course_id` | BIGINT UNSIGNED | PK, FK→courses CASCADE | วิชา |
| `prerequisite_course_id` | BIGINT UNSIGNED | PK, FK→courses CASCADE | วิชาบังคับก่อน |

---

## 13. `course_offerings` — รายวิชาที่เปิดสอน (ต่อปีการศึกษา)

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัส |
| `course_id` | BIGINT UNSIGNED | FK→courses | รายวิชา |
| `academic_year_id` | BIGINT UNSIGNED | FK→academic_years | ปีการศึกษา |
| `coordinator_id` | BIGINT UNSIGNED | FK→users | หัวหน้าวิชา |
| `approval_status` | ENUM | NOT NULL | `draft`/`pending`/`published`/`rejected` |
| `rejection_reason` | TEXT | NULL | เหตุผลตีกลับ |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |
| `deleted_at` | TIMESTAMP | NULL | Soft Delete |

> `approval_status` = สถานะระดับ **รายวิชาต่อปี** (ส่งอนุมัติทั้งวิชา)

---

## 14. `course_offering_instructors` — อาจารย์ประจำวิชา / Instructor Pool (Pivot)

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `course_offering_id` | BIGINT UNSIGNED | PK, FK→course_offerings CASCADE | รายวิชาที่เปิดสอน |
| `user_id` | BIGINT UNSIGNED | PK, FK→users CASCADE | อาจารย์ |
| `role_in_course` | ENUM | NULL | `coordinator`/`secretary`/`instructor`/`group_advisor`/`preceptor` |

> Instructor Pool — หัวหน้าวิชาเพิ่มรายชื่ออาจารย์จาก HR เข้ามาในรายวิชา ไม่ผูกติดกลุ่มนักศึกษา

---

## 15. `practicum_series` — ชุดฝึกปฏิบัติ

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสชุดฝึก |
| `course_offering_id` | BIGINT UNSIGNED | FK→course_offerings | รายวิชาที่เปิดสอน |
| `name` | VARCHAR(255) | NOT NULL | ชื่อชุดฝึก |
| `start_date` | DATE | NOT NULL | วันเริ่ม (ค.ศ.) |
| `end_date` | DATE | NOT NULL | วันสิ้นสุด (ค.ศ.) |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 16. `student_groups` — กลุ่มนักศึกษา

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสกลุ่ม |
| `group_code` | VARCHAR(255) | NOT NULL | รหัสกลุ่ม เช่น A1, B3 |
| `curriculum_id` | BIGINT UNSIGNED | FK→curriculums | หลักสูตร |
| `academic_year_id` | BIGINT UNSIGNED | FK→academic_years | ปีการศึกษา |
| `student_count` | INTEGER | NOT NULL | จำนวนนักศึกษา |
| `color_code` | VARCHAR(10) | NULL | สีแสดงในตาราง |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 17. `schedules` — ตารางสอน (กิจกรรมแต่ละ slot)

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสกิจกรรม |
| `course_offering_id` | BIGINT UNSIGNED | FK→course_offerings | รายวิชาที่เปิดสอน |
| `activity_type_id` | BIGINT UNSIGNED | FK→activity_types | ประเภทกิจกรรม |
| `room_id` | BIGINT UNSIGNED | FK→rooms, NULL | ห้อง/สถานที่ |
| `practicum_series_id` | BIGINT UNSIGNED | FK→practicum_series, NULL | ชุดฝึก (ถ้าเป็นฝึก) |
| `teaching_date` | DATE | NOT NULL | วันที่สอน (ค.ศ.) |
| `start_time` | TIME | NOT NULL | เวลาเริ่ม |
| `end_time` | TIME | NOT NULL | เวลาสิ้นสุด |
| `topic` | VARCHAR(255) | NULL | หัวข้อ/เนื้อหา |
| `capacity_required` | INT UNSIGNED | NULL | จำนวนนักศึกษาที่รองรับ — ใช้ตรวจ `warning_capacity` |
| `sub_group_label` | VARCHAR(20) | NULL | ป้ายกลุ่มย่อย เช่น a, b, 1, 2 → A1a, A1b |
| `status` | ENUM | NOT NULL | `draft`/`pending_approval`/`approved`/`revised` |
| `remark` | TEXT | NULL | หมายเหตุ |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

> INDEX (`teaching_date`, `course_offering_id`) | `status` = สถานะระดับ **slot** (แต่ละกิจกรรม)

---

## 18. `schedule_instructors` — ผู้สอนในกิจกรรม (Pivot)

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `schedule_id` | BIGINT UNSIGNED | PK, FK→schedules CASCADE | กิจกรรม |
| `user_id` | BIGINT UNSIGNED | PK, FK→users CASCADE | อาจารย์ผู้สอน |
| `is_lead` | BOOLEAN | NULL | ผู้สอนหลักในคาบนั้น |

> INDEX (`schedule_id`, `is_lead`) | รองรับ Team Supervision (หลายอาจารย์ต่อ 1 กิจกรรม)

---

## 19. `schedule_student_groups` — กลุ่มนักศึกษาในกิจกรรม (Pivot)

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `schedule_id` | BIGINT UNSIGNED | PK, FK→schedules CASCADE | กิจกรรม |
| `student_group_id` | BIGINT UNSIGNED | PK, FK→student_groups CASCADE | กลุ่มนักศึกษา |

---

## 20. `notifications` — การแจ้งเตือน

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัส |
| `user_id` | BIGINT UNSIGNED | FK→users | ผู้รับแจ้งเตือน |
| `schedule_id` | BIGINT UNSIGNED | FK→schedules, NULL | กิจกรรมที่เกี่ยวข้อง |
| `course_offering_id` | BIGINT UNSIGNED | FK→course_offerings, NULL | รายวิชา (สำหรับ `approval_update`) |
| `type` | ENUM | NOT NULL | `conflict`/`warning_quota_exceeded`/`warning_missing_info`/`warning_capacity`/`warning_no_schedule`/`approval_update` |
| `message` | VARCHAR(255) | NOT NULL | ข้อความแจ้งเตือน |
| `is_read` | BOOLEAN | NOT NULL | อ่านแล้วหรือไม่ |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |

---

## 21. `audit_logs` — บันทึกการเปลี่ยนแปลง (Phase 2 — M12)

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัส |
| `user_id` | BIGINT UNSIGNED | FK→users | ผู้ดำเนินการ |
| `action` | VARCHAR(255) | NOT NULL | การกระทำ เช่น create, update, delete |
| `table_affected` | VARCHAR(255) | NOT NULL | ตารางที่ถูกเปลี่ยน |
| `record_id` | BIGINT UNSIGNED | NOT NULL | รหัสแถวที่ถูกเปลี่ยน |
| `old_values` | JSON | NULL | ค่าเดิม |
| `new_values` | JSON | NULL | ค่าใหม่ |
| `created_at` | TIMESTAMP | DEFAULT CURRENT | วันที่บันทึก |

---

## 22. `system_settings` — ตั้งค่าระบบ

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัส |
| `setting_key` | VARCHAR(100) | UNIQUE | เช่น `current_academic_year_id` |
| `setting_value` | TEXT | NULL | ค่าตั้งค่า |
| `description` | VARCHAR(255) | NULL | คำอธิบาย |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 23. `course_offering_approvals` — ประวัติการอนุมัติ (Phase 2 — M11)

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัส |
| `course_offering_id` | BIGINT UNSIGNED | FK→course_offerings | รายวิชาที่เปิดสอน |
| `actor_user_id` | BIGINT UNSIGNED | FK→users | ผู้ดำเนินการ (Course Head หรือ Executive) |
| `action` | ENUM | NOT NULL | `submit`/`approve`/`reject`/`revise` |
| `comment` | TEXT | NULL | เหตุผลตีกลับ หรือหมายเหตุประกอบ |
| `from_status` | ENUM | NULL | `draft`/`pending`/`published`/`rejected` |
| `to_status` | ENUM | NOT NULL | `draft`/`pending`/`published`/`rejected` |
| `created_at` | TIMESTAMP | DEFAULT CURRENT | วันที่ดำเนินการ |

> INDEX (`course_offering_id`, `created_at`) — ติดตามประวัติการอนุมัติตามลำดับเวลา

---

## 24. `schedule_conflicts` — ความขัดแย้งที่ตรวจพบ (Phase 2 — M4/M5)

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | BIGINT UNSIGNED | PK, AI | รหัส |
| `schedule_id` | BIGINT UNSIGNED | FK→schedules | schedule ที่ตรวจพบปัญหา |
| `conflicting_schedule_id` | BIGINT UNSIGNED | FK→schedules, NULL | schedule ที่ชนกัน (NULL ถ้าเป็น warning) |
| `conflict_type` | ENUM | NULL | `instructor_overlap`/`room_overlap`/`group_overlap` |
| `warning_type` | ENUM | NULL | `quota_exceeded`/`capacity_exceeded`/`missing_info`/`no_schedule`/`outside_availability` |
| `severity` | ENUM | NOT NULL | `conflict` (บล็อกบันทึก) / `warning` (บันทึกได้) |
| `message` | VARCHAR(255) | NOT NULL | ข้อความอธิบาย |
| `is_resolved` | BOOLEAN | NOT NULL | แก้ไขแล้วหรือไม่ |
| `resolved_at` | TIMESTAMP | NULL | วันที่แก้ไข |
| `created_at` | TIMESTAMP | DEFAULT CURRENT | วันที่ตรวจพบ |

> INDEX (`severity`, `is_resolved`) | INDEX (`schedule_id`, `is_resolved`)

---

## 25. `sessions` — Laravel Session

| คอลัมน์ | ประเภท | Constraint | คำอธิบาย |
|---------|--------|-----------|----------|
| `id` | VARCHAR | PK | Session ID |
| `user_id` | BIGINT UNSIGNED | FK→users, NULL, INDEX | ผู้ใช้ |
| `ip_address` | VARCHAR(45) | NULL | IP Address |
| `user_agent` | TEXT | NULL | Browser Agent |
| `payload` | LONGTEXT | NOT NULL | Session data (เก็บ `active_role`) |
| `last_activity` | INTEGER | NOT NULL, INDEX | Timestamp กิจกรรมล่าสุด |

> `active_role` เก็บใน session payload — Role Switcher เปลี่ยนค่านี้

---

## Foreign Key Relationships สรุป

```
departments ──┬── head_user_id ──────────► users
              └── secretary_user_id ─────► users

instructor_profiles ──┬── user_id ───────► users (1:1)
                       └── department_id ─► departments

instructor_availability ─── user_id ────► users

curriculums ─────────────── courses.curriculum_id
                └── student_groups.curriculum_id

academic_years ──┬── course_offerings.academic_year_id
                 └── student_groups.academic_year_id

location_types ────────── rooms.location_type_id

activity_types ────────── schedules.activity_type_id

courses ──┬── course_offerings.course_id
          ├── course_prerequisites.course_id
          └── course_prerequisites.prerequisite_course_id

course_offerings ──┬── course_offering_instructors.course_offering_id
                   ├── practicum_series.course_offering_id
                   ├── schedules.course_offering_id
                   ├── notifications.course_offering_id
                   └── course_offering_approvals.course_offering_id

users ──┬── user_roles.user_id
        ├── course_offerings.coordinator_id
        ├── course_offering_instructors.user_id (Instructor Pool)
        ├── schedule_instructors.user_id
        ├── notifications.user_id
        ├── audit_logs.user_id
        ├── course_offering_approvals.actor_user_id
        └── instructor_availability.user_id

rooms ──────────── schedules.room_id

practicum_series ──── schedules.practicum_series_id

schedules ──┬── schedule_instructors.schedule_id
            ├── schedule_student_groups.schedule_id
            ├── notifications.schedule_id
            └── schedule_conflicts.schedule_id / conflicting_schedule_id

student_groups ──── schedule_student_groups.student_group_id
```

---

## ข้อตกลงข้อมูล (Data Conventions)

1. **ปี/วันที่**: DB เก็บเป็น **ค.ศ. (Gregorian)** เสมอ — UI แสดง **พ.ศ.** (+543) ผ่าน helper `toBE()`
2. **Soft Delete**: `users`, `courses`, `course_offerings` ใช้ `deleted_at` — ไม่ลบถาวร
3. **Multi-role RBAC**: `users` ไม่มี `role` column — query จาก `user_roles` เสมอ, `is_primary=true` = role เริ่มต้น
4. **Conflict vs Warning**: `conflict` = บล็อกบันทึก (แดง), `warning` = บันทึกได้แต่ต้องแก้ (เหลือง)
5. **Instructor Pool**: อาจารย์ไม่ผูกติดกลุ่มนักศึกษา — เพิ่มเข้ามาในวิชาผ่าน `course_offering_instructors`, มอบหมายตอนสร้างกิจกรรมผ่าน `schedule_instructors`
6. **Team Supervision**: 1 กิจกรรมมีได้หลายอาจารย์ — `schedule_instructors` เป็น M:N, `is_lead` ระบุผู้สอนหลัก
7. **Phase 2 Tables**: `course_offering_approvals`, `schedule_conflicts` เตรียมไว้ล่วงหน้า — migration มีแล้ว แต่ยังไม่ implement logic
