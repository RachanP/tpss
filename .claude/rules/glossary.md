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
| ชุดผู้สอน template (ระดับวิชา) | `course_instructor` (pivot) |
| บทบาทในวิชา (master list) | `course_role` |
| หน้าจัดการ template ผู้รับผิดชอบ | `course_pool` (route + view) |
| กิจกรรม / ตารางสอน (แต่ละ slot) | `schedule` |
| ชุดฝึกปฏิบัติ | `practicum_series` |
| กลุ่มนักศึกษา | `student_group` |
| ห้อง / สถานที่ฝึก | `room` |
| ประเภทสถานที่ | `location_type` |
| ประเภทกิจกรรม | `activity_type` |
| หัวข้อกิจกรรม/บทเรียน (ต่อวิชา, V4) | `activity_topic` |
| ปีการศึกษา | `academic_year` |
| ปฏิทินการศึกษา (แยกตามหลักสูตร/ชั้นปี, V4) | `academic_calendar` |
| รอบการประเมิน PA (V4) | `pa_round` |
| สัดส่วนภาระงาน PA ที่อาจารย์กรอกเอง (V4) | `instructor_pa_allocation` |
| กลุ่มย่อย (ของกลุ่มชั้นปี, V4) | `student_cohorts.parent_id` (self-ref) |
| ระดับการศึกษา (ของหลักสูตร) | `curriculums.education_level` enum |
| ปริญญาตรี / โท / เอก | `bachelor` / `master` / `doctorate` |
| จำนวนปีของหลักสูตร | `curriculums.duration_years` |
| รูปแบบการจัดชั้นปี (cohort vs credit) | `curriculums.uses_year_level` boolean |
| หน่วยกิตขั้นต่ำของหลักสูตร | `curriculums.total_credits_required` |
| วิชาบังคับ / วิชาเลือก | `courses.is_required` boolean (true=บังคับ) |
| ชั้นปีตามแผน | `courses.default_year_level` (nullable เมื่อ curriculum.uses_year_level=false) |
| ภาระงาน | `workload` / `teaching_quota` |
| การซ้อนทับเวลา (บล็อกบันทึก) | `conflict` |
| คำเตือน (บันทึกได้แต่ต้องแก้) | `warning` |
| อนุมัติ | `approve` → status: `published` |
| ตีกลับ | `reject` → status: `rejected` / `revised` |
| บทบาทในวิชา (legacy marker) | `role_in_course` varchar — เก็บ 'coordinator' เท่านั้น |
| บทบาทในวิชา (Sprint 3+) | `course_role_id` FK → `course_roles` |
| หัวหน้าวิชา (auto-coordinator) | `course_roles.name_th = 'หัวหน้าวิชา'` |
| เลขานุการวิชา | `course_roles.name_th = 'เลขานุการวิชา'` |
| อาจารย์ผู้สอน | `course_roles.name_th = 'อาจารย์ผู้สอน'` (default) |
| อาจารย์ประจำกลุ่ม | `course_roles.name_th = 'อาจารย์ประจำกลุ่ม'` |
| อาจารย์พี่เลี้ยง | `course_roles.name_th = 'อาจารย์พี่เลี้ยง'` |
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
| M2 | Course Management (รวม Course Pool — Sprint 3) — **Prerequisite ย้ายไป M1 แล้ว** |
| M3 | Schedule Management |
| M4 | Conflict Checking |
| M5 | Smart Warning System (Phase 2) |
| M6 | Workload Management (Phase 2) |
| M7 | Search & Filter |
| M8 | Views & Calendar |
| M9 | Reporting (Phase 2) |
| M11 | Approval Workflow (Phase 2) |
| M12 | Audit Trail (Phase 2) |
