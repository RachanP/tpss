# TPSS Data Dictionary

**Teaching & Practicum Scheduling System (TPSS) - คณะพยาบาลศาสตร์ มหาวิทยาลัยมหิดล**

อัปเดตล่าสุด: 29 พฤษภาคม 2569
แหล่งอ้างอิงหลัก: `database/migrations/` จำนวน 39 ไฟล์, `app/Models/`, seeders เฉพาะ master data, และ `tpss_er_diagram.html`

เอกสารนี้สรุปโครงสร้างฐานข้อมูลทั้งระบบ รวมตาราง domain ของ TPSS และตารางระบบของ Laravel เช่น `migrations`, `sessions`, `cache`, `cache_locks`, `jobs`

---

## สารบัญตาราง

### Domain / Master Data Tables

| # | ตาราง | ความหมาย |
|---|---|---|
| 1 | `users` | ผู้ใช้งานระบบ |
| 2 | `departments` | ภาควิชา/หน่วยงาน |
| 3 | `instructor_profiles` | โปรไฟล์และภาระงานอาจารย์ |
| 4 | `instructor_availability` | ช่วงเวลาที่อาจารย์พร้อม/ไม่พร้อมสอน |
| 5 | `curriculums` | หลักสูตร |
| 6 | `academic_years` | ปีการศึกษาและภาคการศึกษา |
| 7 | `location_types` | ประเภทสถานที่ |
| 8 | `rooms` | ห้องเรียน/สถานที่ฝึก |
| 9 | `activity_types` | ประเภทกิจกรรมการเรียนการสอน |
| 10 | `courses` | รายวิชาแม่แบบ |
| 11 | `course_roles` | บทบาทอาจารย์ในรายวิชา |
| 12 | `course_offerings` | รายวิชาที่เปิดสอนในปี/ภาคการศึกษา |
| 13 | `practicum_series` | ชุด/รอบฝึกปฏิบัติ |
| 14 | `student_groups` | กลุ่มนักศึกษาของรายวิชาที่เปิดสอน |
| 15 | `schedules` | รายการตารางสอน/ตารางฝึก |
| 16 | `schedule_templates` | แม่แบบตารางสอนรายสัปดาห์สำหรับสร้างตารางซ้ำอัตโนมัติ |
| 17 | `system_settings` | ค่าตั้งค่าระบบ |

### Pivot / Relation Tables

| # | ตาราง | ความหมาย |
|---|---|---|
| 18 | `user_roles` | บทบาทผู้ใช้หลายบทบาท |
| 19 | `course_prerequisites` | วิชาบังคับก่อน |
| 20 | `course_staff` | เจ้าหน้าที่ที่รับผิดชอบรายวิชาแม่แบบ |
| 21 | `course_instructors` | อาจารย์ใน pool รายวิชาแม่แบบ |
| 22 | `course_offering_instructors` | อาจารย์ใน pool ของรอบเปิดสอน |
| 23 | `schedule_instructors` | อาจารย์ผู้สอนในรายการตาราง |
| 24 | `schedule_student_groups` | กลุ่มนักศึกษาที่เข้าร่วมรายการตาราง |

### Workflow / Audit / Conflict Tables

| # | ตาราง | ความหมาย |
|---|---|---|
| 25 | `notifications` | การแจ้งเตือน |
| 26 | `audit_logs` | ประวัติการเปลี่ยนแปลง |
| 27 | `course_offering_approvals` | ประวัติการอนุมัติรายวิชาที่เปิดสอน |
| 28 | `schedule_conflicts` | ผล conflict/warning แบบเดิมรายรายการ |
| 29 | `schedule_conflict_runs` | รอบการประมวลผล conflict แบบ batch |
| 30 | `schedule_conflict_results` | ผล conflict ของแต่ละรอบ |
| 31 | `schedule_conflict_result_scopes` | สิทธิ์/ขอบเขตการมองเห็นผล conflict |

### Laravel / System Tables

| # | ตาราง | ความหมาย |
|---|---|---|
| 32 | `migrations` | ประวัติ migration ของ Laravel |
| 33 | `sessions` | session ของ Laravel |
| 34 | `cache` | cache key/value ของ Laravel |
| 35 | `cache_locks` | cache lock ของ Laravel |
| 36 | `jobs` | คิวงาน background ของ Laravel |

---

## Enum Values

| ตาราง.คอลัมน์ | ค่าที่เป็นได้ | ความหมาย |
|---|---|---|
| `academic_years.phase` | `preparation`, `scheduling`, `published` | สถานะระดับปี/ภาคการศึกษา |
| `curriculums.education_level` | `bachelor`, `master`, `doctorate` | ระดับการศึกษา |
| `rooms.status` | `active`, `inactive`, `maintenance` | สถานะห้อง/สถานที่ |
| `activity_types.category` | `lecture`, `practicum`, `thesis`, `other` | หมวดกิจกรรม |
| `courses.course_type` | `theory`, `practicum`, `theory_practicum` | ประเภทวิชา |
| `courses.status` | `active`, `inactive` | สถานะรายวิชา |
| `course_offerings.approval_status` | `draft`, `pending`, `published`, `rejected` | สถานะอนุมัติระดับรอบเปิดสอน |
| `schedules.status` | `draft`, `pending_approval`, `approved`, `revised` | สถานะรายการตาราง |
| `user_roles.role` | `admin`, `staff`, `course_head`, `executive`, `instructor` | บทบาทผู้ใช้ |
| `notifications.type` | `conflict`, `warning_quota_exceeded`, `warning_missing_info`, `warning_capacity`, `warning_no_schedule`, `approval_update` | ประเภทการแจ้งเตือน |
| `course_offering_approvals.action` | `submit`, `approve`, `reject`, `revise` | การกระทำใน workflow อนุมัติ |
| `course_offering_approvals.from_status` | `draft`, `pending`, `published`, `rejected` | สถานะเดิม |
| `course_offering_approvals.to_status` | `draft`, `pending`, `published`, `rejected` | สถานะใหม่ |
| `schedule_conflicts.conflict_type` | `instructor_overlap`, `room_overlap`, `group_overlap` | ประเภท conflict |
| `schedule_conflicts.warning_type` | `quota_exceeded`, `capacity_exceeded`, `missing_info`, `no_schedule`, `outside_availability` | ประเภท warning |
| `schedule_conflicts.severity` | `conflict`, `warning` | ระดับความรุนแรง |
| `schedule_conflict_runs.status` | `pending`, `processing`, `ready`, `failed`, `missing` | สถานะรอบประมวลผล conflict |
| `schedule_conflict_runs.source` | `observer`, `pivot`, `manual`, `scheduled`, `bulk_import` | ที่มาของการสั่งตรวจ conflict |
| `schedule_conflict_results.conflict_type` | `instructor_overlap`, `room_overlap`, `group_overlap` | ประเภท conflict ของผล batch |
| `schedule_conflict_result_scopes.scope_type` | `course_head_user`, `admin_global`, `executive_academic_year` | ขอบเขตผู้เห็นผล conflict |

---

## 1. `users` - ผู้ใช้งานระบบ

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสผู้ใช้ |
| `prefix` | VARCHAR(50) | NULL | คำนำหน้า |
| `username` | VARCHAR(100) | UNIQUE, NOT NULL | รหัสเข้าระบบ เช่น `staff_01` |
| `employee_id` | VARCHAR(50) | UNIQUE, NULL | รหัสพนักงาน |
| `name` | VARCHAR(255) | NOT NULL | ชื่อ-สกุล |
| `email` | VARCHAR(255) | UNIQUE, NOT NULL | อีเมล |
| `password` | VARCHAR(255) | NOT NULL | รหัสผ่านแบบ hash |
| `is_active` | BOOLEAN | DEFAULT true | สถานะเปิดใช้งาน |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |
| `deleted_at` | TIMESTAMP | NULL | soft delete |

---

## 2. `departments` - ภาควิชา/หน่วยงาน

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสภาควิชา |
| `name` | VARCHAR(255) | UNIQUE, NOT NULL | ชื่อภาควิชา |
| `head_user_id` | BIGINT UNSIGNED | FK -> `users.id`, NULL | หัวหน้าภาควิชา |
| `secretary_user_id` | BIGINT UNSIGNED | FK -> `users.id`, NULL | เลขานุการภาควิชา |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 3. `instructor_profiles` - โปรไฟล์และภาระงานอาจารย์

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสโปรไฟล์ |
| `user_id` | BIGINT UNSIGNED | FK -> `users.id`, UNIQUE | ผู้ใช้ที่เป็นอาจารย์ |
| `title` | VARCHAR(100) | NULL | ตำแหน่ง/คำนำหน้าทางวิชาการ |
| `department_id` | BIGINT UNSIGNED | FK -> `departments.id`, NULL | ภาควิชาที่สังกัด |
| `employment_type` | VARCHAR(255) | NULL | ประเภทการจ้างงาน |
| `hired_at` | DATE | NULL | วันที่บรรจุ |
| `academic_degree` | VARCHAR(255) | NULL | วุฒิการศึกษา |
| `is_english_passed` | BOOLEAN | DEFAULT false | ผ่านเกณฑ์ภาษาอังกฤษหรือไม่ |
| `teaching_pct` | INT | DEFAULT 50 | สัดส่วนภาระงานสอน (%) |
| `research_pct` | INT | DEFAULT 20 | สัดส่วนภาระงานวิจัย (%) |
| `service_pct` | INT | DEFAULT 10 | สัดส่วนบริการวิชาการ (%) |
| `culture_pct` | INT | DEFAULT 10 | สัดส่วนศิลปวัฒนธรรม/พัฒนาองค์กร (%) |
| `other_pct` | INT | DEFAULT 10 | สัดส่วนงานอื่น (%) |
| `teaching_quota` | INT | NULL | โควตาชั่วโมงสอนต่อปี |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 4. `instructor_availability` - ช่วงเวลาพร้อมสอน

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสรายการ |
| `user_id` | BIGINT UNSIGNED | FK -> `users.id` | อาจารย์ |
| `day_of_week` | TINYINT | NOT NULL | วันในสัปดาห์: 0=Sun, 1=Mon, ..., 6=Sat |
| `start_time` | TIME | NOT NULL | เวลาเริ่ม |
| `end_time` | TIME | NOT NULL | เวลาสิ้นสุด |
| `is_available` | BOOLEAN | DEFAULT true | พร้อมสอนหรือไม่ |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 5. `curriculums` - หลักสูตร

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสหลักสูตร |
| `name` | VARCHAR(255) | NOT NULL | ชื่อหลักสูตร |
| `effective_year` | INT | NOT NULL | ปีที่เริ่มใช้หลักสูตร |
| `education_level` | ENUM | DEFAULT `bachelor` | ระดับการศึกษา |
| `duration_years` | TINYINT UNSIGNED | DEFAULT 4 | จำนวนปีของหลักสูตร |
| `uses_year_level` | BOOLEAN | DEFAULT true | ใช้ระบบชั้นปีหรือไม่ |
| `total_credits_required` | SMALLINT UNSIGNED | NULL | หน่วยกิตขั้นต่ำ |
| `is_active` | BOOLEAN | DEFAULT true, NULL | สถานะใช้งาน |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 6. `academic_years` - ปีการศึกษา

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสปี/ภาคการศึกษา |
| `name` | VARCHAR(255) | NOT NULL | ปีการศึกษา เช่น 2569 |
| `semester` | INT | NOT NULL | ภาคการศึกษา เช่น 1, 2, 3 |
| `start_date` | DATE | NOT NULL | วันที่เริ่มภาคการศึกษา |
| `end_date` | DATE | NOT NULL | วันที่สิ้นสุดภาคการศึกษา |
| `is_active` | BOOLEAN | NOT NULL | สถานะใช้งาน |
| `phase` | ENUM | DEFAULT `preparation` | สถานะระดับระบบ |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

Unique: `name + semester`

---

## 7. `location_types` - ประเภทสถานที่

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสประเภทสถานที่ |
| `name` | VARCHAR(100) | UNIQUE, NOT NULL | ชื่อประเภท เช่น Lecture, Lab, Ward, Online, External |
| `requires_capacity` | BOOLEAN | DEFAULT true | ต้องระบุความจุหรือไม่ |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 8. `rooms` - ห้องเรียน/สถานที่ฝึก

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสห้อง/สถานที่ |
| `room_code` | VARCHAR(255) | UNIQUE, NOT NULL | รหัสห้อง/สถานที่ |
| `room_name` | VARCHAR(255) | NOT NULL | ชื่อห้อง/สถานที่ |
| `building` | VARCHAR(100) | NULL | อาคาร |
| `capacity` | INT | NULL | ความจุ |
| `location_type_id` | BIGINT UNSIGNED | FK -> `location_types.id` | ประเภทสถานที่ |
| `equipment_type` | JSON | NULL | อุปกรณ์/คุณลักษณะของห้อง |
| `address` | TEXT | NULL | ที่อยู่หรือรายละเอียดสถานที่ภายนอก |
| `status` | ENUM | NOT NULL | สถานะห้อง |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 9. `activity_types` - ประเภทกิจกรรม

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสประเภทกิจกรรม |
| `name` | VARCHAR(100) | NOT NULL | ชื่อประเภทกิจกรรม |
| `color_code` | VARCHAR(10) | DEFAULT `#3498db`, NULL | สีที่ใช้แสดงบนตาราง |
| `category` | ENUM | NOT NULL | หมวดกิจกรรม |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 10. `courses` - รายวิชาแม่แบบ

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสรายวิชา |
| `course_code` | VARCHAR(255) | NOT NULL | รหัสวิชา |
| `curriculum_id` | BIGINT UNSIGNED | FK -> `curriculums.id` | หลักสูตร |
| `department_id` | BIGINT UNSIGNED | FK -> `departments.id`, NULL | ภาควิชาที่รับผิดชอบ |
| `head_instructor_id` | BIGINT UNSIGNED | FK -> `users.id`, NULL | หัวหน้าวิชาแม่แบบ |
| `name_th` | VARCHAR(255) | NOT NULL | ชื่อรายวิชาภาษาไทย |
| `name_en` | VARCHAR(255) | NULL | ชื่อรายวิชาภาษาอังกฤษ |
| `course_type` | ENUM | NULL | ประเภทวิชา |
| `default_year_level` | INT | NULL | ชั้นปีตามแผน |
| `default_semester` | INT | NULL | ภาคเรียนตามแผน |
| `requires_practicum_rotation` | BOOLEAN | DEFAULT false | ต้องมี rotation ฝึกปฏิบัติหรือไม่ |
| `is_required` | BOOLEAN | DEFAULT true | เป็นวิชาบังคับหรือไม่ |
| `credits` | INT | NOT NULL | หน่วยกิต |
| `lecture_hours` | INT | DEFAULT 0 | ชั่วโมงทฤษฎี |
| `lab_hours` | INT | DEFAULT 0 | ชั่วโมง lab/ปฏิบัติ |
| `self_study_hours` | INT | DEFAULT 0 | ชั่วโมงศึกษาด้วยตนเอง |
| `capacity` | INT UNSIGNED | NULL | จำนวนนักศึกษาสูงสุดที่รับได้ |
| `color_code` | VARCHAR(10) | NULL | สีแสดงผลรายวิชา |
| `status` | ENUM | DEFAULT `active` | สถานะรายวิชา |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |
| `deleted_at` | TIMESTAMP | NULL | soft delete |

Unique: `course_code + curriculum_id`

---

## 11. `course_roles` - บทบาทอาจารย์ในรายวิชา

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสบทบาท |
| `name_th` | VARCHAR(255) | NOT NULL | ชื่อบทบาทภาษาไทย |
| `sort_order` | TINYINT UNSIGNED | DEFAULT 0 | ลำดับแสดงผล |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 12. `course_offerings` - รายวิชาที่เปิดสอน

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสรอบเปิดสอน |
| `course_id` | BIGINT UNSIGNED | FK -> `courses.id` | รายวิชาแม่แบบ |
| `academic_year_id` | BIGINT UNSIGNED | FK -> `academic_years.id` | ปี/ภาคการศึกษา |
| `coordinator_id` | BIGINT UNSIGNED | FK -> `users.id` | หัวหน้าวิชาในรอบเปิดสอน |
| `approval_status` | ENUM | NOT NULL | สถานะอนุมัติ |
| `rejection_reason` | TEXT | NULL | เหตุผลการตีกลับ/ไม่อนุมัติ |
| `total_student_count` | INT UNSIGNED | NULL | จำนวนนักศึกษารวม |
| `planned_lecture_hours` | INT UNSIGNED | NULL | ชั่วโมงทฤษฎีที่วางแผน |
| `planned_lab_hours` | INT UNSIGNED | NULL | ชั่วโมง lab ที่วางแผน |
| `planned_practicum_hours` | INT UNSIGNED | NULL | ชั่วโมงฝึกปฏิบัติที่วางแผน |
| `teaching_weeks` | TINYINT UNSIGNED | NULL | จำนวนสัปดาห์ที่สอน |
| `requires_practicum_rotation` | BOOLEAN | DEFAULT false | รอบเปิดสอนนี้ต้องจัด rotation หรือไม่ |
| `practicum_note` | TEXT | NULL | หมายเหตุเมื่อ override จาก master data |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |
| `deleted_at` | TIMESTAMP | NULL | soft delete |

Index: `academic_year_id + coordinator_id`

---

## 13. `practicum_series` - ชุด/รอบฝึกปฏิบัติ

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสชุดฝึก |
| `course_offering_id` | BIGINT UNSIGNED | FK -> `course_offerings.id` | รายวิชาที่เปิดสอน |
| `name` | VARCHAR(255) | NOT NULL | ชื่อชุด/รอบฝึก |
| `start_date` | DATE | NOT NULL | วันที่เริ่ม |
| `end_date` | DATE | NOT NULL | วันที่สิ้นสุด |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 14. `student_groups` - กลุ่มนักศึกษา

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสกลุ่ม |
| `course_offering_id` | BIGINT UNSIGNED | FK -> `course_offerings.id`, RESTRICT DELETE | รายวิชาที่เปิดสอน |
| `group_code` | VARCHAR(255) | NOT NULL | รหัสกลุ่ม เช่น A1, B2 |
| `student_count` | INT | NOT NULL | จำนวนนักศึกษาในกลุ่ม |
| `color_code` | VARCHAR(10) | NULL | สีแสดงผลกลุ่ม |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

Unique: `course_offering_id + group_code`

---

## 15. `schedules` - รายการตารางสอน/ตารางฝึก

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสรายการตาราง |
| `course_offering_id` | BIGINT UNSIGNED | FK -> `course_offerings.id` | รายวิชาที่เปิดสอน |
| `activity_type_id` | BIGINT UNSIGNED | FK -> `activity_types.id` | ประเภทกิจกรรม |
| `room_id` | BIGINT UNSIGNED | FK -> `rooms.id`, NULL | ห้อง/สถานที่ |
| `practicum_series_id` | BIGINT UNSIGNED | FK -> `practicum_series.id`, NULL | ชุด/รอบฝึกปฏิบัติ |
| `schedule_template_id` | BIGINT UNSIGNED | FK -> `schedule_templates.id`, NULL | แม่แบบที่ใช้สร้างรายการตารางนี้ |
| `series_week_number` | TINYINT UNSIGNED | NULL | สัปดาห์ลำดับที่ในชุดตารางที่สร้างจากแม่แบบ |
| `start_date` | DATE | NULL | วันที่เริ่ม block schedule |
| `end_date` | DATE | NULL | วันที่สิ้นสุด block schedule |
| `teaching_date` | DATE | NULL หลัง migration 2026-05-20 | วันที่สอนแบบวันเดียว legacy/compatibility |
| `start_time` | TIME | NOT NULL | เวลาเริ่ม |
| `end_time` | TIME | NOT NULL | เวลาสิ้นสุด |
| `topic` | VARCHAR(255) | NULL | หัวข้อ/กิจกรรม |
| `capacity_required` | INT UNSIGNED | NULL | จำนวนผู้เรียนที่ต้องรองรับ |
| `sub_group_label` | VARCHAR(20) | NULL | ป้ายกลุ่มย่อย เช่น a, b, 1, 2 |
| `status` | ENUM | NOT NULL | สถานะรายการตาราง |
| `remark` | TEXT | NULL | หมายเหตุ |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

Indexes: `teaching_date + course_offering_id`, `course_offering_id + start_date + end_date`, `course_offering_id + start_date + end_date + start_time`, `course_offering_id`, `room_id + start_date + end_date`, `schedule_template_id + series_week_number`

---

## 16. `schedule_templates` - แม่แบบตารางสอนรายสัปดาห์

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสแม่แบบตาราง |
| `course_offering_id` | BIGINT UNSIGNED | FK -> `course_offerings.id` | รายวิชาที่เปิดสอน |
| `activity_type_id` | BIGINT UNSIGNED | FK -> `activity_types.id` | ประเภทกิจกรรม |
| `weekday` | TINYINT UNSIGNED | NOT NULL | วันในสัปดาห์: 1=Monday, 7=Sunday |
| `start_time` | TIME | NOT NULL | เวลาเริ่ม |
| `end_time` | TIME | NOT NULL | เวลาสิ้นสุด |
| `start_week` | TINYINT UNSIGNED | NOT NULL | สัปดาห์แรกที่ใช้แม่แบบ |
| `end_week` | TINYINT UNSIGNED | NOT NULL | สัปดาห์สุดท้ายที่ใช้แม่แบบ |
| `starts_on` | DATE | NULL | วันที่เริ่มจริงของช่วงแม่แบบ |
| `ends_on` | DATE | NULL | วันที่สิ้นสุดจริงของช่วงแม่แบบ |
| `topic` | VARCHAR(255) | NULL | หัวข้อ/กิจกรรม |
| `capacity_required` | INT UNSIGNED | NULL | จำนวนผู้เรียนที่ต้องรองรับ |
| `sub_group_label` | VARCHAR(20) | NULL | ป้ายกลุ่มย่อย เช่น a, b, 1, 2 |
| `created_by` | BIGINT UNSIGNED | FK -> `users.id`, NULL | ผู้สร้างแม่แบบ |
| `updated_by` | BIGINT UNSIGNED | FK -> `users.id`, NULL | ผู้แก้ไขแม่แบบล่าสุด |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

Indexes: `course_offering_id + start_week + end_week`, `course_offering_id + weekday + start_time`

---

## 17. `system_settings` - ค่าตั้งค่าระบบ

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัส setting |
| `setting_key` | VARCHAR(100) | UNIQUE, NOT NULL | key เช่น `current_academic_year_id` |
| `setting_value` | TEXT | NULL | ค่า setting |
| `description` | VARCHAR(255) | NULL | คำอธิบาย |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

---

## 18. `user_roles` - บทบาทผู้ใช้

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `user_id` | BIGINT UNSIGNED | PK composite, FK -> `users.id`, CASCADE DELETE | ผู้ใช้ |
| `role` | ENUM | PK composite | บทบาท |
| `is_primary` | BOOLEAN | DEFAULT false | บทบาทหลักเมื่อเข้าสู่ระบบ |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |

Primary key: `user_id + role`

---

## 19. `course_prerequisites` - วิชาบังคับก่อน

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `course_id` | BIGINT UNSIGNED | PK composite, FK -> `courses.id`, CASCADE DELETE | รายวิชาหลัก |
| `prerequisite_course_id` | BIGINT UNSIGNED | PK composite, FK -> `courses.id`, CASCADE DELETE | รายวิชาบังคับก่อน |

Primary key: `course_id + prerequisite_course_id`

---

## 20. `course_staff` - เจ้าหน้าที่ประจำรายวิชา

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `course_id` | BIGINT UNSIGNED | PK composite, FK -> `courses.id`, CASCADE DELETE | รายวิชาแม่แบบ |
| `user_id` | BIGINT UNSIGNED | PK composite, FK -> `users.id`, CASCADE DELETE | เจ้าหน้าที่ |

Primary key: `course_id + user_id`

---

## 21. `course_instructors` - อาจารย์ใน pool รายวิชาแม่แบบ

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสรายการ |
| `course_id` | BIGINT UNSIGNED | FK -> `courses.id`, CASCADE DELETE | รายวิชาแม่แบบ |
| `user_id` | BIGINT UNSIGNED | FK -> `users.id`, CASCADE DELETE | อาจารย์ |
| `course_role_id` | BIGINT UNSIGNED | FK -> `course_roles.id`, NULL ON DELETE, NULL | บทบาทในรายวิชา |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

Unique: `course_id + user_id`

---

## 22. `course_offering_instructors` - อาจารย์ใน pool รอบเปิดสอน

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `course_offering_id` | BIGINT UNSIGNED | PK composite, FK -> `course_offerings.id`, CASCADE DELETE | รายวิชาที่เปิดสอน |
| `user_id` | BIGINT UNSIGNED | PK composite, FK -> `users.id`, CASCADE DELETE | อาจารย์ |
| `role_in_course` | VARCHAR(100) | DEFAULT `instructor` | marker เช่น `coordinator`; บทบาทจริงอ้าง `course_role_id` |
| `course_role_id` | BIGINT UNSIGNED | FK -> `course_roles.id`, NULL ON DELETE, NULL | บทบาทในรายวิชา |

Primary key: `course_offering_id + user_id`

---

## 23. `schedule_instructors` - อาจารย์ผู้สอนในรายการตาราง

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `schedule_id` | BIGINT UNSIGNED | PK composite, FK -> `schedules.id`, CASCADE DELETE | รายการตาราง |
| `user_id` | BIGINT UNSIGNED | PK composite, FK -> `users.id`, CASCADE DELETE | อาจารย์ผู้สอน |
| `is_lead` | BOOLEAN | NULL | เป็นผู้สอนหลักหรือไม่ |

Primary key: `schedule_id + user_id`  
Indexes: `schedule_id + is_lead`, `user_id + schedule_id`

---

## 24. `schedule_student_groups` - กลุ่มนักศึกษาในรายการตาราง

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `schedule_id` | BIGINT UNSIGNED | PK composite, FK -> `schedules.id`, CASCADE DELETE | รายการตาราง |
| `student_group_id` | BIGINT UNSIGNED | PK composite, FK -> `student_groups.id`, CASCADE DELETE | กลุ่มนักศึกษา |

Primary key: `schedule_id + student_group_id`  
Index: `student_group_id + schedule_id`

---

## 25. `notifications` - การแจ้งเตือน

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสแจ้งเตือน |
| `user_id` | BIGINT UNSIGNED | FK -> `users.id` | ผู้รับแจ้งเตือน |
| `schedule_id` | BIGINT UNSIGNED | FK -> `schedules.id`, NULL | รายการตารางที่เกี่ยวข้อง |
| `course_offering_id` | BIGINT UNSIGNED | FK -> `course_offerings.id`, NULL | รายวิชาที่เปิดสอนที่เกี่ยวข้อง |
| `type` | ENUM | NOT NULL | ประเภทแจ้งเตือน |
| `message` | VARCHAR(255) | NOT NULL | ข้อความ |
| `is_read` | BOOLEAN | NOT NULL | อ่านแล้วหรือไม่ |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |

---

## 26. `audit_logs` - ประวัติการเปลี่ยนแปลง

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัส audit log |
| `user_id` | BIGINT UNSIGNED | FK -> `users.id` | ผู้กระทำ |
| `category` | VARCHAR(80) | NULL, INDEX | หมวดภาษาไทยสำหรับแสดงผล |
| `action` | VARCHAR(255) | NOT NULL | การกระทำ |
| `table_affected` | VARCHAR(255) | NOT NULL | ตารางที่ถูกเปลี่ยน |
| `record_id` | BIGINT UNSIGNED | NOT NULL | id ของ record ที่เกี่ยวข้อง |
| `old_values` | JSON | NULL | ค่าเดิม |
| `new_values` | JSON | NULL | ค่าใหม่ |
| `description` | VARCHAR(500) | NULL | คำอธิบายสำหรับแสดงผล |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP, NULL, INDEX | วันที่เกิดเหตุการณ์ |

---

## 27. `course_offering_approvals` - ประวัติการอนุมัติ

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสรายการอนุมัติ |
| `course_offering_id` | BIGINT UNSIGNED | FK -> `course_offerings.id` | รายวิชาที่เปิดสอน |
| `actor_user_id` | BIGINT UNSIGNED | FK -> `users.id` | ผู้ดำเนินการ |
| `action` | ENUM | NOT NULL | การกระทำ |
| `comment` | TEXT | NULL | เหตุผล/หมายเหตุ |
| `from_status` | ENUM | NULL | สถานะเดิม |
| `to_status` | ENUM | NOT NULL | สถานะใหม่ |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP, NULL | วันที่ดำเนินการ |

Index: `course_offering_id + created_at`

---

## 28. `schedule_conflicts` - ผล conflict/warning แบบเดิม

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัส conflict/warning |
| `schedule_id` | BIGINT UNSIGNED | FK -> `schedules.id` | schedule ที่ตรวจพบปัญหา |
| `conflicting_schedule_id` | BIGINT UNSIGNED | FK -> `schedules.id`, NULL | schedule ที่ชนกัน |
| `conflict_type` | ENUM | NULL | ประเภท conflict |
| `warning_type` | ENUM | NULL | ประเภท warning |
| `severity` | ENUM | NOT NULL | ระดับปัญหา |
| `message` | VARCHAR(255) | NOT NULL | ข้อความอธิบาย |
| `is_resolved` | BOOLEAN | NOT NULL | แก้ไขแล้วหรือไม่ |
| `resolved_at` | TIMESTAMP | NULL | วันที่แก้ไข |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP, NULL | วันที่สร้าง |

Indexes: `severity + is_resolved`, `schedule_id + is_resolved`

---

## 29. `schedule_conflict_runs` - รอบประมวลผล conflict

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสรอบประมวลผล |
| `academic_year_id` | BIGINT UNSIGNED | FK -> `academic_years.id`, CASCADE DELETE | ปี/ภาคการศึกษาที่ตรวจ |
| `status` | ENUM | DEFAULT `pending` | สถานะรอบประมวลผล |
| `generation` | INT UNSIGNED | NOT NULL | ลำดับ generation ของผลตรวจ |
| `source` | ENUM | DEFAULT `manual` | ที่มาของการสั่งตรวจ |
| `requested_at` | TIMESTAMP | NULL | เวลาที่ร้องขอ |
| `started_at` | TIMESTAMP | NULL | เวลาที่เริ่มประมวลผล |
| `finished_at` | TIMESTAMP | NULL | เวลาที่เสร็จ |
| `failed_at` | TIMESTAMP | NULL | เวลาที่ล้มเหลว |
| `error_message` | TEXT | NULL | ข้อความ error |
| `result_count` | INT UNSIGNED | DEFAULT 0 | จำนวนผล conflict |
| `metadata` | JSON | NULL | metadata เพิ่มเติม |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

Unique: `academic_year_id + generation`  
Indexes: `academic_year_id + status`, `status`

---

## 30. `schedule_conflict_results` - ผล conflict แบบ batch

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสผล conflict |
| `run_id` | BIGINT UNSIGNED | FK -> `schedule_conflict_runs.id`, CASCADE DELETE | รอบประมวลผล |
| `academic_year_id` | BIGINT UNSIGNED | FK -> `academic_years.id`, CASCADE DELETE | ปี/ภาคการศึกษา |
| `schedule_id` | BIGINT UNSIGNED | NOT NULL | schedule ต้นทาง |
| `conflicting_schedule_id` | BIGINT UNSIGNED | NOT NULL | schedule ที่ชนกัน |
| `conflict_type` | ENUM | NOT NULL | ประเภท conflict |
| `resource_type` | VARCHAR(50) | NULL | ประเภท resource เช่น instructor, room, group |
| `resource_id` | BIGINT UNSIGNED | NULL | id ของ resource |
| `message` | VARCHAR(255) | NOT NULL | ข้อความอธิบาย |
| `pair_key` | VARCHAR(191) | NOT NULL | key สำหรับกันผลซ้ำของคู่ conflict |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

Unique: `run_id + pair_key + schedule_id`  
Indexes: `run_id + schedule_id`, `academic_year_id + schedule_id`, `academic_year_id + conflict_type`, `resource_type + resource_id`

---

## 31. `schedule_conflict_result_scopes` - ขอบเขตการมองเห็นผล conflict

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัส scope |
| `run_id` | BIGINT UNSIGNED | FK -> `schedule_conflict_runs.id`, CASCADE DELETE | รอบประมวลผล |
| `result_id` | BIGINT UNSIGNED | FK -> `schedule_conflict_results.id`, CASCADE DELETE | ผล conflict |
| `academic_year_id` | BIGINT UNSIGNED | FK -> `academic_years.id`, CASCADE DELETE | ปี/ภาคการศึกษา |
| `scope_type` | ENUM | NOT NULL | ประเภทขอบเขตการมองเห็น |
| `user_id` | BIGINT UNSIGNED | NULL | ผู้ใช้ที่เห็นผลนี้ |
| `role` | VARCHAR(50) | NULL | role ที่เห็นผลนี้ |
| `course_offering_id` | BIGINT UNSIGNED | NULL | รอบเปิดสอนที่เกี่ยวข้อง |
| `created_at` | TIMESTAMP | NULL | วันที่สร้าง |
| `updated_at` | TIMESTAMP | NULL | วันที่แก้ไข |

Indexes: `scope_type + user_id + academic_year_id`, `scope_type + role + academic_year_id`, `course_offering_id + academic_year_id`, `run_id + scope_type`, `result_id`

---

## 32. `migrations` - Laravel Migrations

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AI | รหัสรายการ migration |
| `migration` | VARCHAR(255) | NOT NULL | ชื่อไฟล์ migration ที่รันแล้ว |
| `batch` | INT | NOT NULL | รอบ batch ที่ Laravel ใช้จัดกลุ่มการ migrate/rollback |

---

## 33. `sessions` - Laravel Session

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | VARCHAR(255) | PK | session id |
| `user_id` | BIGINT UNSIGNED | NULL, INDEX | ผู้ใช้ที่ผูกกับ session |
| `ip_address` | VARCHAR(45) | NULL | IP address |
| `user_agent` | TEXT | NULL | browser/user agent |
| `payload` | LONGTEXT | NOT NULL | ข้อมูล session |
| `last_activity` | INT | NOT NULL, INDEX | timestamp การใช้งานล่าสุด |

---

## 34. `cache` - Laravel Cache

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `key` | VARCHAR(255) | PK | cache key |
| `value` | MEDIUMTEXT | NOT NULL | ค่า cache |
| `expiration` | BIGINT | NOT NULL, INDEX | เวลา expiration |

---

## 35. `cache_locks` - Laravel Cache Locks

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `key` | VARCHAR(255) | PK | lock key |
| `owner` | VARCHAR(255) | NOT NULL | owner token |
| `expiration` | BIGINT | NOT NULL, INDEX | เวลา expiration |

---

## 36. `jobs` - Laravel Queue Jobs

| คอลัมน์ | ประเภท | Constraint / Default | คำอธิบาย |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AI | รหัสงานในคิว |
| `queue` | VARCHAR(255) | NOT NULL, INDEX | ชื่อ queue |
| `payload` | LONGTEXT | NOT NULL | ข้อมูล serialized job |
| `attempts` | TINYINT UNSIGNED | NOT NULL | จำนวนครั้งที่พยายามรัน |
| `reserved_at` | INT UNSIGNED | NULL | timestamp ตอน worker จองงาน |
| `available_at` | INT UNSIGNED | NOT NULL | timestamp ที่งานพร้อมรัน |
| `created_at` | INT UNSIGNED | NOT NULL | timestamp ตอนสร้างงาน |

---

## Foreign Key Relationships

```text
users
  -> user_roles.user_id
  -> instructor_profiles.user_id
  -> instructor_availability.user_id
  -> departments.head_user_id / secretary_user_id
  -> courses.head_instructor_id
  -> course_staff.user_id
  -> course_instructors.user_id
  -> course_offerings.coordinator_id
  -> course_offering_instructors.user_id
  -> schedule_instructors.user_id
  -> schedule_templates.created_by / updated_by
  -> notifications.user_id
  -> audit_logs.user_id
  -> course_offering_approvals.actor_user_id

departments
  -> instructor_profiles.department_id
  -> courses.department_id

curriculums
  -> courses.curriculum_id

academic_years
  -> course_offerings.academic_year_id
  -> schedule_conflict_runs.academic_year_id
  -> schedule_conflict_results.academic_year_id
  -> schedule_conflict_result_scopes.academic_year_id

location_types
  -> rooms.location_type_id

rooms
  -> schedules.room_id

activity_types
  -> schedules.activity_type_id
  -> schedule_templates.activity_type_id

courses
  -> course_prerequisites.course_id / prerequisite_course_id
  -> course_staff.course_id
  -> course_instructors.course_id
  -> course_offerings.course_id

course_roles
  -> course_instructors.course_role_id
  -> course_offering_instructors.course_role_id

course_offerings
  -> course_offering_instructors.course_offering_id
  -> practicum_series.course_offering_id
  -> student_groups.course_offering_id
  -> schedules.course_offering_id
  -> schedule_templates.course_offering_id
  -> notifications.course_offering_id
  -> course_offering_approvals.course_offering_id

practicum_series
  -> schedules.practicum_series_id

schedule_templates
  -> schedules.schedule_template_id

student_groups
  -> schedule_student_groups.student_group_id

schedules
  -> schedule_instructors.schedule_id
  -> schedule_student_groups.schedule_id
  -> notifications.schedule_id
  -> schedule_conflicts.schedule_id / conflicting_schedule_id
  -> schedule_conflict_results.schedule_id / conflicting_schedule_id

schedule_conflict_runs
  -> schedule_conflict_results.run_id
  -> schedule_conflict_result_scopes.run_id

schedule_conflict_results
  -> schedule_conflict_result_scopes.result_id
```

หมายเหตุ: `schedule_conflict_results.schedule_id`, `schedule_conflict_results.conflicting_schedule_id`, `schedule_conflict_result_scopes.user_id`, และ `schedule_conflict_result_scopes.course_offering_id` เป็น unsigned bigint สำหรับอ้างอิงเชิงตรรกะ แต่ migration ปัจจุบันไม่ได้ประกาศ foreign key constraint โดยตรง

---

## Indexes และ Unique Constraints สำคัญ

| ตาราง | Constraint / Index | รายละเอียด |
|---|---|---|
| `users` | UNIQUE | `username`, `employee_id`, `email` |
| `departments` | UNIQUE | `name` |
| `instructor_profiles` | UNIQUE | `user_id` |
| `academic_years` | UNIQUE | `name + semester` |
| `location_types` | UNIQUE | `name` |
| `rooms` | UNIQUE | `room_code` |
| `courses` | UNIQUE | `course_code + curriculum_id` |
| `course_offerings` | INDEX | `academic_year_id + coordinator_id` |
| `student_groups` | UNIQUE | `course_offering_id + group_code` |
| `schedules` | INDEX | `teaching_date + course_offering_id`, `course_offering_id + start_date + end_date`, `course_offering_id + start_date + end_date + start_time`, `course_offering_id`, `room_id + start_date + end_date`, `schedule_template_id + series_week_number` |
| `schedule_templates` | INDEX | `course_offering_id + start_week + end_week`, `course_offering_id + weekday + start_time` |
| `user_roles` | PK composite | `user_id + role` |
| `course_prerequisites` | PK composite | `course_id + prerequisite_course_id` |
| `course_staff` | PK composite | `course_id + user_id` |
| `course_instructors` | UNIQUE | `course_id + user_id` |
| `course_offering_instructors` | PK composite | `course_offering_id + user_id` |
| `schedule_instructors` | PK + INDEX | `schedule_id + user_id`, `schedule_id + is_lead`, `user_id + schedule_id` |
| `schedule_student_groups` | PK + INDEX | `schedule_id + student_group_id`, `student_group_id + schedule_id` |
| `audit_logs` | INDEX | `category`, `created_at` |
| `course_offering_approvals` | INDEX | `course_offering_id + created_at` |
| `schedule_conflicts` | INDEX | `severity + is_resolved`, `schedule_id + is_resolved` |
| `schedule_conflict_runs` | UNIQUE + INDEX | `academic_year_id + generation`, `academic_year_id + status`, `status` |
| `schedule_conflict_results` | UNIQUE + INDEX | `run_id + pair_key + schedule_id`, `run_id + schedule_id`, `academic_year_id + schedule_id`, `academic_year_id + conflict_type`, `resource_type + resource_id`, `run_id + academic_year_id + schedule_id + conflict_type` |
| `schedule_conflict_result_scopes` | INDEX | `scope_type + user_id + academic_year_id`, `scope_type + role + academic_year_id`, `course_offering_id + academic_year_id`, `run_id + scope_type`, `result_id`, `scope_type + user_id + academic_year_id + run_id + result_id` |
| `migrations` | PK | `id` |
| `sessions` | PK + INDEX | `id`, `user_id`, `last_activity` |
| `cache` | PK + INDEX | `key`, `expiration` |
| `cache_locks` | PK + INDEX | `key`, `expiration` |
| `jobs` | PK + INDEX | `id`, `queue` |

---

## Data Conventions

1. วันที่ในฐานข้อมูลเก็บเป็น ค.ศ. หรือ date/time มาตรฐานของ database ส่วน UI สามารถแสดงเป็น พ.ศ. ได้ผ่าน helper ของระบบ
2. `users`, `courses`, และ `course_offerings` ใช้ soft delete ผ่าน `deleted_at`
3. ระบบบทบาทผู้ใช้เป็น multi-role โดยเก็บที่ `user_roles` ไม่เก็บ role เดี่ยวใน `users`
4. รายวิชาแม่แบบ (`courses`) แยกจากรอบเปิดสอน (`course_offerings`) เพื่อให้แต่ละปี/ภาคปรับจำนวนคน ชั่วโมง และ rotation ได้
5. instructor pool มีสองระดับ: แม่แบบที่ `course_instructors` และรอบเปิดสอนที่ `course_offering_instructors`
6. ตารางสอนรองรับทั้งแบบวันเดียวผ่าน `teaching_date` และแบบ block ผ่าน `start_date`/`end_date`; หลัง migration 2026-05-20 `teaching_date` เป็น nullable เพื่อรองรับ block schedule
7. ตารางฝึกปฏิบัติใช้ `practicum_series` เป็นตัวแบ่งชุด/รอบ และผูกกับ `schedules.practicum_series_id`
8. `schedule_templates` เป็นแม่แบบรายสัปดาห์สำหรับสร้างตารางซ้ำอัตโนมัติ โดยรายการที่ถูกสร้างจะผูกกลับผ่าน `schedules.schedule_template_id` และเก็บลำดับสัปดาห์ไว้ใน `schedules.series_week_number`
9. conflict มีสองแนวทางที่อยู่ร่วมกัน: `schedule_conflicts` สำหรับผลรายรายการแบบเดิม และ `schedule_conflict_runs/results/scopes` สำหรับการตรวจแบบ batch/async
10. `notifications` ผูกได้ทั้งระดับ `schedule_id` และ `course_offering_id` เพื่อรองรับทั้ง conflict/warning และ approval update
11. ตาราง Laravel/system (`migrations`, `sessions`, `cache`, `cache_locks`, `jobs`) เป็น infrastructure ไม่ใช่ business data แต่รวมในเอกสารเพื่อให้ dictionary ครบทั้ง database
