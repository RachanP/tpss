-- =============================================
-- TPSS DATABASE SCHEMA (REVISED VERSION)
-- รองรับระบบจัดตารางสอนและฝึกปฏิบัติคณะพยาบาลศาสตร์
-- แก้ไข: Roles M10-04, Co-Teaching, Flexible Activity Types
-- =============================================

-- 1. ข้อมูลหลักสูตร (Curriculums)
CREATE TABLE `curriculums` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL COMMENT 'เช่น พยาบาลศาสตรบัณฑิต (ปรับปรุง 2565)',
    `effective_year` INT NOT NULL COMMENT 'ปีที่เริ่มใช้',
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL
);

-- 2. ข้อมูลผู้ใช้งานและสิทธิ์ (M10)
-- หมายเหตุ: 1 user มีได้หลาย role — ดู user_roles ด้านล่าง
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE COMMENT 'รหัสเข้าระบบ เช่น staff_01, porntip.w',
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    `deleted_at` TIMESTAMP NULL
);

-- 2.0 บทบาทของผู้ใช้ (Multi-Role — 1 user มีได้หลาย role)
-- M10: RBAC ตรวจสอบจาก user_roles ไม่ใช่ users.role
CREATE TABLE `user_roles` (
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role` ENUM('admin', 'staff', 'course_head', 'executive', 'instructor') NOT NULL,
    `is_primary` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'role ที่ login ครั้งแรกเข้าหน้าจอนี้ก่อน',
    `created_at` TIMESTAMP NULL,
    PRIMARY KEY (`user_id`, `role`),
    CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_ur_role` (`role`)
);

-- 2.1 ภาควิชา (Departments - Master Data)
CREATE TABLE `departments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL COMMENT 'เช่น ภาควิชาการพยาบาลอายุรศาสตร์และศัลยศาสตร์',
    `head_user_id` BIGINT UNSIGNED NULL COMMENT 'หัวหน้าภาควิชา',
    `secretary_user_id` BIGINT UNSIGNED NULL COMMENT 'เลขานุการภาควิชา',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `fk_dept_head` FOREIGN KEY (`head_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_dept_secretary` FOREIGN KEY (`secretary_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- 2.2 โปรไฟล์ข้อมูลอาจารย์เฉพาะทาง
CREATE TABLE `instructor_profiles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `title` VARCHAR(100) NULL COMMENT 'คำนำหน้า/ตำแหน่งทางวิชาการ',
    `department_id` BIGINT UNSIGNED NULL,
    `teaching_quota` INT NULL COMMENT 'ภาระงานสอนตามเกณฑ์ (ชั่วโมง/เทอม)',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `fk_ip_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ip_dept` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
);

-- 2.3 เวลาว่างของอาจารย์ (Instructor Availability)
CREATE TABLE `instructor_availability` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `day_of_week` TINYINT NOT NULL COMMENT '0=Sun, 1=Mon, ..., 6=Sat',
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `is_available` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `fk_ia_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- 3. ข้อมูลปีการศึกษา
CREATE TABLE `academic_years` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL COMMENT 'เช่น 2569',
    `semester` INT NOT NULL COMMENT 'เช่น 1, 2, 3',
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `is_active` BOOLEAN NOT NULL DEFAULT FALSE,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    UNIQUE KEY `academic_years_unique` (`name`, `semester`)
);

-- 4. ประเภทสถานที่ (Location Types - Master Data)
CREATE TABLE `location_types` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL COMMENT 'เช่น Lecture, Lab, Ward, Online, External',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL
);

-- 4.1 ข้อมูลสถานที่/ห้องเรียน/แหล่งฝึก
CREATE TABLE `rooms` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `room_code` VARCHAR(255) NOT NULL UNIQUE,
    `room_name` VARCHAR(255) NOT NULL,
    `building` VARCHAR(100) NULL,
    `capacity` INT NOT NULL,
    `location_type_id` BIGINT UNSIGNED NOT NULL,
    `equipment_type` JSON NULL,
    `address` TEXT NULL COMMENT 'ที่อยู่แหล่งฝึกภายนอก เช่น โรงพยาบาล, รพ.สต., ชุมชน',
    `status` ENUM('active', 'inactive', 'maintenance') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `fk_room_loc_type` FOREIGN KEY (`location_type_id`) REFERENCES `location_types`(`id`)
);

-- 5. ประเภทกิจกรรม (สร้างใหม่เป็น Master Data ตามข้อเสนอแนะ)
-- รองรับ: ปฐมนิเทศ, SDL, Round Ward, สรุปเคส, Conference, สอบ Procedure
CREATE TABLE `activity_types` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `color_code` VARCHAR(10) DEFAULT '#3498db',
    `is_practicum` BOOLEAN DEFAULT FALSE,
    `category` ENUM('lecture', 'practicum', 'thesis', 'other') NOT NULL DEFAULT 'lecture' COMMENT 'ใช้คำนวณ Teaching Load weight สำหรับ PA-QA: lecture×3, practicum×1.5, thesis×3-5',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL
);

-- 6. ข้อมูลรายวิชา
CREATE TABLE `courses` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `course_code` VARCHAR(255) NOT NULL,
    `curriculum_id` BIGINT UNSIGNED NOT NULL,
    `name_th` VARCHAR(255) NOT NULL,
    `name_en` VARCHAR(255) NULL,
    `course_type` ENUM('theory', 'practicum', 'theory_practicum') NOT NULL DEFAULT 'theory',
    `requires_practicum_rotation` BOOLEAN NOT NULL DEFAULT FALSE,
    `credits` INT NOT NULL,
    `lecture_hours` INT NOT NULL DEFAULT 0,
    `lab_hours` INT NOT NULL DEFAULT 0,
    `color_code` VARCHAR(10) NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    `deleted_at` TIMESTAMP NULL,
    CONSTRAINT `fk_course_curriculum` FOREIGN KEY (`curriculum_id`) REFERENCES `curriculums`(`id`),
    UNIQUE KEY `uk_course_code_curriculum` (`course_code`, `curriculum_id`)
);

-- 6.1 วิชาที่ต้องเรียนมาก่อน (Prerequisites)
CREATE TABLE `course_prerequisites` (
    `course_id` BIGINT UNSIGNED NOT NULL,
    `prerequisite_course_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`course_id`, `prerequisite_course_id`),
    CONSTRAINT `fk_cp_course` FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cp_prereq` FOREIGN KEY (`prerequisite_course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
);

-- 7. ข้อมูลกลุ่มนักศึกษา
CREATE TABLE `student_groups` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `group_code` VARCHAR(255) NOT NULL,
    `parent_id` BIGINT UNSIGNED NULL COMMENT 'อ้างอิงกลุ่มแม่ เพื่อรองรับกลุ่มย่อย เช่น A1.1 เป็นลูกของ A1',
    `curriculum_id` BIGINT UNSIGNED NOT NULL,
    `academic_year_id` BIGINT UNSIGNED NOT NULL,
    `student_count` INT NOT NULL DEFAULT 0,
    `color_code` VARCHAR(10) NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `fk_sg_parent` FOREIGN KEY (`parent_id`) REFERENCES `student_groups`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sg_curriculum` FOREIGN KEY (`curriculum_id`) REFERENCES `curriculums`(`id`),
    CONSTRAINT `fk_sg_academic_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`)
);

-- 8. การเปิดสอนรายวิชา (Instructor Pool ถูกออกแบบมาดีแล้ว)
CREATE TABLE `course_offerings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `course_id` BIGINT UNSIGNED NOT NULL,
    `academic_year_id` BIGINT UNSIGNED NOT NULL,
    `coordinator_id` BIGINT UNSIGNED NOT NULL COMMENT 'หัวหน้าวิชา',
    `approval_status` ENUM('draft', 'pending', 'published', 'rejected') NOT NULL DEFAULT 'draft',
    `rejection_reason` TEXT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    `deleted_at` TIMESTAMP NULL,
    CONSTRAINT `fk_co_course` FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`),
    CONSTRAINT `fk_co_academic_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`),
    CONSTRAINT `fk_co_coordinator` FOREIGN KEY (`coordinator_id`) REFERENCES `users`(`id`)
);

-- 8.1 อาจารย์ที่อยู่ใน Pool ของรายวิชานี้
CREATE TABLE `course_offering_instructors` (
    `course_offering_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role_in_course` ENUM('coordinator', 'secretary', 'instructor', 'group_advisor', 'preceptor') DEFAULT 'instructor',
    PRIMARY KEY (`course_offering_id`, `user_id`),
    CONSTRAINT `fk_coi_offering` FOREIGN KEY (`course_offering_id`) REFERENCES `course_offerings`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_coi_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- 9. ข้อมูลกลุ่มการฝึกปฏิบัติ (Practicum Block)
CREATE TABLE `practicum_series` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `course_offering_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `fk_ps_co` FOREIGN KEY (`course_offering_id`) REFERENCES `course_offerings`(`id`)
);

-- 10. ข้อมูลตารางสอน (Schedules - แก้ไข: ดึง user_id ออก และเปลี่ยนประเภทกิจกรรมเป็น Master Data)
CREATE TABLE `schedules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `course_offering_id` BIGINT UNSIGNED NOT NULL,
    `activity_type_id` BIGINT UNSIGNED NOT NULL COMMENT 'โยงไป Master Data Table',
    `room_id` BIGINT UNSIGNED NULL,
    `practicum_series_id` BIGINT UNSIGNED NULL,
    `teaching_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `topic` VARCHAR(255) NULL,
    `capacity_required` INT UNSIGNED NULL COMMENT 'จำนวนนักศึกษาที่รองรับสำหรับ activity นี้ — ใช้ตรวจ warning_capacity เทียบกับ student_count ของกลุ่ม',
    `status` ENUM('draft', 'pending_approval', 'approved', 'revised') NOT NULL DEFAULT 'draft',
    `remark` TEXT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `fk_sch_course_offering` FOREIGN KEY (`course_offering_id`) REFERENCES `course_offerings`(`id`),
    CONSTRAINT `fk_sch_activity` FOREIGN KEY (`activity_type_id`) REFERENCES `activity_types`(`id`),
    CONSTRAINT `fk_sch_room` FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`),
    CONSTRAINT `fk_sch_series` FOREIGN KEY (`practicum_series_id`) REFERENCES `practicum_series`(`id`),
    INDEX `idx_sch_date_offering` (`teaching_date`, `course_offering_id`) COMMENT 'เร่ง query Teaching Load per semester สำหรับ Phase 2 API'
);

-- 10.1 ตารางผู้สอนร่วม (Co-Teaching / Team Supervision - เพิ่มใหม่ตามข้อเสนอแนะ)
CREATE TABLE `schedule_instructors` (
    `schedule_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `is_lead` BOOLEAN DEFAULT FALSE COMMENT 'ระบุว่าเป็นผู้สอนหลักในคาบนั้นหรือไม่',
    PRIMARY KEY (`schedule_id`, `user_id`),
    CONSTRAINT `fk_si_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_si_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_si_user` (`user_id`) COMMENT 'เร่ง query Teaching Load รายอาจารย์สำหรับ Phase 2 API'
);

-- 10.2 ตารางผูกกลุ่มนักศึกษา (Multiple Groups)
CREATE TABLE `schedule_student_groups` (
    `schedule_id` BIGINT UNSIGNED NOT NULL,
    `student_group_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`schedule_id`, `student_group_id`),
    CONSTRAINT `fk_ssg_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ssg_group` FOREIGN KEY (`student_group_id`) REFERENCES `student_groups`(`id`) ON DELETE CASCADE
);

-- 11. ระบบตรวจสอบและประวัติ (Audit Trail - ออกแบบไว้ดีแล้วตาม ISO 29110)
CREATE TABLE `audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `action` VARCHAR(255) NOT NULL,
    `table_affected` VARCHAR(255) NOT NULL,
    `record_id` BIGINT UNSIGNED NOT NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
);

-- 12. การแจ้งเตือน
CREATE TABLE `notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `schedule_id` BIGINT UNSIGNED NULL,
    `course_offering_id` BIGINT UNSIGNED NULL COMMENT 'ใช้สำหรับ approval_update notification ระดับรายวิชา (M11)',
    `type` ENUM('conflict', 'warning_quota_exceeded', 'warning_missing_info', 'warning_capacity', 'warning_no_schedule', 'approval_update') NOT NULL,
    `message` VARCHAR(255) NOT NULL,
    `is_read` BOOLEAN NOT NULL DEFAULT FALSE,
    `created_at` TIMESTAMP NULL,
    CONSTRAINT `fk_noti_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_noti_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_noti_co` FOREIGN KEY (`course_offering_id`) REFERENCES `course_offerings`(`id`) ON DELETE CASCADE
);

-- 13. ตารางตั้งค่ากลางของระบบ (System Settings)
CREATE TABLE `system_settings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE COMMENT 'เช่น current_academic_year_id',
    `setting_value` TEXT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL
);

-- =============================================
-- PHASE 2 — เตรียมไว้ล่วงหน้า (Sub-Sprint 1–5)
-- =============================================

-- P2-1. ประวัติการอนุมัติ (M11 — Approval Workflow)
-- เก็บทุก action: submit, approve, reject, revise พร้อม comment
-- ทำให้ Executive และ Course Head ดู audit trail การอนุมัติย้อนหลังได้
CREATE TABLE `course_offering_approvals` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `course_offering_id` BIGINT UNSIGNED NOT NULL,
    `actor_user_id` BIGINT UNSIGNED NOT NULL COMMENT 'ผู้ดำเนินการ (Course Head หรือ Executive)',
    `action` ENUM('submit', 'approve', 'reject', 'revise') NOT NULL,
    `comment` TEXT NULL COMMENT 'เหตุผลตีกลับ หรือหมายเหตุประกอบ',
    `from_status` ENUM('draft', 'pending', 'published', 'rejected') NULL COMMENT 'สถานะก่อนเปลี่ยน',
    `to_status` ENUM('draft', 'pending', 'published', 'rejected') NOT NULL COMMENT 'สถานะหลังเปลี่ยน',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_coa_offering` FOREIGN KEY (`course_offering_id`) REFERENCES `course_offerings`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_coa_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users`(`id`),
    INDEX `idx_coa_offering` (`course_offering_id`, `created_at`)
);

-- P2-2. Indexes เพิ่มเติมสำหรับ Reporting (M9) และ Workload (M6)
-- เพิ่มหลัง table สร้างเสร็จ (ทำใน migration แยก ถ้า table มีข้อมูลจำนวนมากแล้ว)
ALTER TABLE `schedules`
    ADD INDEX `idx_sch_status` (`status`),
    ADD INDEX `idx_sch_teaching_date` (`teaching_date`);

ALTER TABLE `course_offerings`
    ADD INDEX `idx_co_approval_status` (`approval_status`),
    ADD INDEX `idx_co_academic_year` (`academic_year_id`, `approval_status`);

ALTER TABLE `schedule_instructors`
    ADD INDEX `idx_si_schedule_lead` (`schedule_id`, `is_lead`);

-- P2-3. ตารางเก็บ conflict ที่ตรวจพบ (M4/M5 — Conflict & Warning cache)
-- ใช้ cache ผล conflict detection เพื่อไม่ต้องคำนวณใหม่ทุกครั้ง
-- invalidate เมื่อมีการเปลี่ยนแปลง schedule ที่เกี่ยวข้อง
CREATE TABLE `schedule_conflicts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `schedule_id` BIGINT UNSIGNED NOT NULL COMMENT 'schedule ที่ตรวจพบปัญหา',
    `conflicting_schedule_id` BIGINT UNSIGNED NULL COMMENT 'schedule ที่ชนกัน (NULL ถ้าเป็น warning ไม่ใช่ conflict)',
    `conflict_type` ENUM('instructor_overlap', 'room_overlap', 'group_overlap') NULL COMMENT 'ประเภท conflict (บล็อกบันทึก)',
    `warning_type` ENUM('quota_exceeded', 'capacity_exceeded', 'missing_info', 'no_schedule', 'outside_availability') NULL COMMENT 'ประเภท warning (บันทึกได้แต่ต้องแก้)',
    `severity` ENUM('conflict', 'warning') NOT NULL,
    `message` VARCHAR(255) NOT NULL,
    `is_resolved` BOOLEAN NOT NULL DEFAULT FALSE,
    `resolved_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sc_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sc_conflict` FOREIGN KEY (`conflicting_schedule_id`) REFERENCES `schedules`(`id`) ON DELETE CASCADE,
    INDEX `idx_sc_schedule` (`schedule_id`, `is_resolved`),
    INDEX `idx_sc_severity` (`severity`, `is_resolved`)
);