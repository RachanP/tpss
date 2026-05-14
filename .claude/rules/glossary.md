# Glossary — ไทย ↔ Code + Naming Conventions

## คำศัพท์ไทย ↔ Code / DB

| คำไทย | ชื่อใน Code / DB |
|-------|----------------|
| หัวหน้าวิชา / ผู้ประสานรายวิชา / Maker | `course_head` (role เดียวกัน ตาม SRS UC-04) |
| เจ้าหน้าที่ผู้ดูแลวิชา (ต่อวิชา) | `assigned_staff_id` (FK ใน `courses`) |
| เจ้าหน้าที่ / Support Staff | `staff` |
| ผู้บริหาร / Approver | `executive` |
| อาจารย์ผู้สอน / Instructor | `instructor` |
| ผู้ดูแลระบบ / Admin | `admin` |
| รายวิชา | `course` |
| รายวิชาที่เปิดสอน (ต่อปีการศึกษา) | `course_offering` |
| กิจกรรม / ตารางสอน (แต่ละ slot) | `schedule` |
| ชุดฝึกปฏิบัติ | `practicum_series` |
| กลุ่มนักศึกษา | `student_group` |
| ห้อง / สถานที่ฝึก | `room` |
| ประเภทสถานที่ | `location_type` |
| ประเภทกิจกรรม | `activity_type` |
| ปีการศึกษา | `academic_year` |
| ภาระงาน | `workload` / `teaching_quota` |
| การซ้อนทับเวลา (บล็อกบันทึก) | `conflict` |
| คำเตือน (บันทึกได้แต่ต้องแก้) | `warning` |
| อนุมัติ | `approve` → status: `published` |
| ตีกลับ | `reject` → status: `rejected` / `revised` |
| บทบาทในวิชา | `role_in_course` |
| หัวหน้าวิชา (บทบาทในวิชา) | `coordinator` |
| เลขานุการวิชา | `secretary` |
| อาจารย์ประจำกลุ่ม | `group_advisor` |
| อาจารย์พี่เลี้ยง | `preceptor` |
| ความพร้อมสอน | `instructor_availability` |
| ประวัติการอนุมัติ | `course_offering_approval` |
| ความขัดแย้งที่ตรวจพบ (cache) | `schedule_conflict` |
| บทบาทหลัก (เมื่อ login) | `is_primary` (ใน `user_roles`) |
| role ที่ใช้งานอยู่ใน session | `active_role` (session key) |

## Naming Conventions

| ประเภท | รูปแบบ | ตัวอย่าง |
|--------|--------|---------|
| Model | PascalCase | `CourseOffering`, `StudentGroup` |
| Controller | PascalCase + Controller | `ScheduleController`, `CourseOfferingController` |
| Route name | kebab-case + dot | `course-offerings.index`, `schedules.store` |
| View path | snake_case ตาม role หรือ `shared/` | `admin/settings.blade.php` |
| DB table | snake_case plural | `course_offerings`, `student_groups` |
| DB column | snake_case | `approval_status`, `created_at` |
| Alpine.js var | camelCase | `showModal`, `selectedInstructor` |
| CSS class | kebab-case | `sb-nav`, `card-header` |

> **ภาษาในโค้ด**: ตัวแปร/function/class ทั้งหมดเป็น **ภาษาอังกฤษ** — comment และ UI text เป็นภาษาไทย

## Module IDs (ใช้อ้างอิงเสมอเมื่อพูดถึงฟีเจอร์)

| Module | ชื่อ |
|--------|------|
| M10 | Login, RBAC & Admin Settings |
| M1 | Master Data Management |
| M2 | Course Management |
| M3 | Schedule Management |
| M4 | Conflict Checking |
| M5 | Smart Warning System (Phase 2) |
| M6 | Workload Management (Phase 2) |
| M7 | Search & Filter |
| M8 | Views & Calendar |
| M9 | Reporting (Phase 2) |
| M11 | Approval Workflow (Phase 2) |
| M12 | Audit Trail (Phase 2) |
