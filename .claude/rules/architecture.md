# Architecture — Domain Logic, Workflow, PA Criteria

## Workflow หลักของระบบ

```
[Setup Data] → [Create Course] → [Manual Input Schedule] → [Smart Check]
→ [Fix Errors] → [Validate Overall] → [Approve] → [Publish]
→ [Operate & Adjust] → [Report]
```

- **Conflict (Error)** → บันทึกไม่ได้ / แจ้งเตือนสีแดง
- **Warning** → บันทึกได้ แต่ต้องแก้ไข

## Requirement Update — 29 พ.ค. 2569 (comment สีน้ำเงินจากอาจารย์)

> แหล่งอ่านง่าย: `storage/tpss_detail_pages/page-00.jpg` ถึง `page-25.jpg`
> ชุดนี้ export จาก `รายละเอียดระบบจัดตารางสอน_V1.pdf` และอ่าน annotation/comment สีน้ำเงินได้ชัดกว่า PDF

### Product Direction

1. **ระบบคือ data-entry + validation tool ไม่ใช่ auto scheduler เป็นหลัก**
   - อาจารย์ระบุว่า "จริง ๆ อาจเป็นการกรอกข้อมูลมากกว่า จัดตารางให้"
   - หัวหน้าวิชา/อาจารย์ต้องกรอกตารางเองได้ง่าย ไม่ซับซ้อน และใช้ซ้ำได้ข้ามวิชา
   - Auto scheduling/rotation automation เป็น future enhancement หลังจาก workflow manual ใช้งานจริง
2. **หัวหน้าวิชาเป็น key user และเป็น key data owner**
   - หัวหน้าวิชาจัดตารางเอง และเป็นผู้ดูแลข้อมูลรายวิชา กลุ่ม กิจกรรม ผู้สอน
   - UX ต้องลดการกรอกซ้ำ และช่วยคัดลอก/ทำซ้ำ slot
3. **ผู้บริหารต้องเห็น dashboard ภาระงานอาจารย์**
   - ต้องเห็นว่าอาจารย์แต่ละคนสอนระดับใดบ้าง จำนวนเท่าไร
   - ป.ตรีเป็น block ซับซ้อน ส่วน ป.โท/ป.เอก มีนักศึกษาประมาณ 10-50 คนต่อหลักสูตร
4. **เผยแพร่ตารางให้หลายกลุ่ม**
   - Publish ต้องไปถึงอาจารย์ นักศึกษา และงานบริการการศึกษา/เจ้าหน้าที่
   - นักศึกษาเห็นตารางของกลุ่มตนเอง, อาจารย์เห็นตารางของตนเอง, เจ้าหน้าที่เห็นตารางทั้งหมด

### Master Data Implications

1. **หลักสูตรเปลี่ยนทุก 5 ปี**
   - ชื่อวิชา/รหัสวิชาต้องผูกกับ version หลักสูตร
   - Report/filter ต้องเลือกหลักสูตรและปีหลักสูตรได้ ไม่ใช้ชื่อวิชาลอย ๆ
2. **รายวิชาต้องรองรับหลักสูตรนานาชาติ/สองภาษา**
   - ต้องมี metadata ระดับหลักสูตรหรือรายวิชาว่าเป็นหลักสูตรปกติ/นานาชาติ/สองภาษา
3. **กลุ่มนักศึกษาในรายวิชาให้หัวหน้าวิชาจัดเอง**
   - UI กลุ่มหลัก/กลุ่มย่อยต้อง self-service และแก้ไขง่ายใน course offering
4. **ประเภทกิจกรรมต้องมีผลต่อ workload**
   - ปฐมนิเทศมักไม่นับชั่วโมงให้อาจารย์
   - SDL ไม่นับชั่วโมงให้อาจารย์
   - ต้องเพิ่ม/รองรับกิจกรรม "วิทยานิพนธ์" และ "ดุษฎีนิพนธ์"
   - `activity_types` ควรมี flag เช่น `counts_toward_workload`

### Master Data Scope Decisions — 30 พ.ค. 2569

> ใช้กับ demo/current phase ก่อน ห้ามเสนอเพิ่ม field ซ้ำถ้ายังไม่มี requirement เชิงรายงานหรือ workflow ใหม่มายืนยัน

1. **หลักสูตรใช้โครงสร้างปัจจุบันได้**
   - `curriculums` มีปีหลักสูตร/ระดับการศึกษา/ระยะเวลา/สถานะใช้งานแล้ว
   - `courses.curriculum_id` ผูกวิชาเข้าหลักสูตร และตอนเปลี่ยนปีการศึกษาระบบดู active curriculum เพื่อเปิดรายวิชาอัตโนมัติ
   - ยังไม่ต้องเพิ่ม `academic_year_curriculum` หรือ filter หลักสูตรต่อปี เพราะหลักสูตรที่ใช้งานจริงในปีหนึ่งมีไม่มาก
   - หลักสูตรนานาชาติ/สองภาษาให้กรอกในชื่อหลักสูตรก่อน ยังไม่ต้องมี field แยกจนกว่าจะต้อง filter/report ตาม track
2. **ประเภทกิจกรรมเป็น user-configurable**
   - ผู้ใช้เพิ่ม activity type เองได้ และเลือกหมวด `lecture`, `practicum`, `thesis`, `other` เพื่อจัดกลุ่มชั่วโมงในอนาคต
   - Demo/current phase ทำ helper text อธิบายหมวดให้ชัดพอ ยังไม่ต้องเพิ่ม `counts_toward_workload`
   - Workload calculation เป็น Phase 2 จึงยังไม่ต้องบังคับ logic หัก/ไม่นับชั่วโมงสำหรับ SDL, ปฐมนิเทศ ฯลฯ
3. **ประเภทสถานที่ใช้เพื่อ conflict/capacity**
   - `location_types` บอกว่าเป็นห้องหรือสถานที่ขนาดใหญ่/สถานที่พิเศษ และใช้กับ logic ชน + ความจุ
   - รายละเอียดสถานที่อยู่ที่ `rooms` แล้ว เช่น code, name, building, capacity, equipment/address, status
   - ยังไม่ต้องเพิ่ม campus/location kind แยก เว้นแต่มี requirement ให้ report/filter ตามข้อมูลนั้นจริง
4. **Course roles ต้อง seed ให้ครบบทบาทจาก requirement**
   - Course roles ที่ต้องมี: หัวหน้าวิชา, เลขานุการวิชา, ผู้ช่วยเลขานุการวิชา, อาจารย์ผู้สอน, อาจารย์ประจำกลุ่ม, อาจารย์พี่เลี้ยง, Preceptor/ผู้ควบคุมแหล่งฝึก
   - Seeder ต้อง idempotent และเติม description/sort order ให้ครบเพื่อให้ Course Pool/Offering ใช้งานต่อได้

### Schedule Workflow Implications

1. **Schedule entry ขั้นต่ำต่อ 1 slot**
   - วันที่, เวลา, รายวิชา, กลุ่ม, ประเภทกิจกรรม, สถานที่, อาจารย์, หมายเหตุ
2. **ป.ตรีปี 3-4 มี pattern สอนซ้ำแต่เปลี่ยนกลุ่ม**
   - ใน 1 ภาคมีการสอนซ้ำเดิม 2 ครั้ง แต่กลุ่มเปลี่ยน
   - สรุปได้ประมาณ 4 รอบต่อภาค (1 ภาคมี 2 ครั้ง)
   - Series/duplicate workflow ต้องรองรับ "คัดลอกโครงตารางเดิมแล้วเปลี่ยนกลุ่ม"
3. **Approval**
   - ผู้รับผิดชอบคือหัวหน้าวิชา/ผู้มีอำนาจ
   - เปลี่ยนสถานะ Draft → Approved, lock ข้อมูลบางส่วน, บันทึก approval log
4. **Operation Phase**
   - ของจริงมีการเปลี่ยนแปลงระหว่างภาค
   - เมื่อแก้แล้วต้องตรวจ conflict ใหม่, เปลี่ยนสถานะเป็น `revised`, บันทึกประวัติ, แจ้งผู้เกี่ยวข้องเป็น optional ใน v1

### Reporting Priority

1. **PDF สำคัญมาก**
   - อาจารย์ระบุว่า PDF จำเป็นเพื่อแจกนักศึกษาได้ ไม่ต้องพิมพ์ใหม่
   - Prioritize PDF export ตารางรายวิชา/รายกลุ่ม ก่อน report ที่ซับซ้อนกว่า
2. Reports ขั้นต่ำ:
   - ตารางสอนรายวิชา
   - ตารางรายอาจารย์
   - ตารางรายกลุ่มนักศึกษา
   - ตารางใช้ห้อง/สถานที่
   - workload รายบุคคล
   - conflict report
   - warning report

## ลักษณะพิเศษของตารางสอน (ต้องเข้าใจก่อนพัฒนา M3)

1. **Block Schedule** — ไม่ซ้ำรายสัปดาห์ แต่เป็น block ต่อเนื่องหลายสัปดาห์
2. **หลายกลุ่มย่อย** — 1 วิชา 300+ คน แบ่งเป็น A1–A9, B1–B9 ฯลฯ
3. **Parallel Activities** — วันเดียวกัน แต่ละกลุ่มทำกิจกรรมต่างกัน
4. **Rotation Schedule** — กลุ่มหมุนเวียนระหว่างแหล่งฝึกและประเภทประสบการณ์
5. **Exception-based** — ตารางเปลี่ยนตามสัปดาห์ มีวันหยุด กิจกรรมพิเศษ

## Course Head Offering Filter (Bug Report 28 พ.ค.)

หน้าจัดตารางของหัวหน้าวิชา (`CourseHead\ScheduleController::coordinatorScheduleOfferings` + `coordinatorScheduleOfferingRedirectTarget`) ต้อง:

1. **Filter `courses.status = 'active'` เสมอ** — ใช้ `withActiveCourse()` scope ที่มีอยู่แล้ว ห้ามดึงวิชาที่ปิดสอนมาให้หัวหน้าจัดตาราง (หัวหน้าวิชาเดิมอาจยังเป็น `head_instructor_id` ของวิชาที่ปิดไปแล้วใน Master Data — แต่ไม่ควรต้องรับผิดชอบ workload)
2. **Default selection** — เรียงตาม `courses.course_code` ASC แล้วเลือกตัวแรก เป็นค่าเริ่มต้นเมื่อ user เข้าหน้าตารางสอนโดยยังไม่ระบุ offering (เปลี่ยนจากเดิมที่ใช้ `updated_at DESC` — ลำดับสุ่มและสับสน)
3. **Empty state ตามสถานะระบบ** (หน้าแจ้งเตือนการชนด้วย):
   - `academic_year` ยังไม่มี active หรือยัง `preparation` → "อยู่ในสถานะเตรียมข้อมูล (ยังไม่ถึงช่วงเวลาการจัดตารางเรียน)"
   - `phase = scheduling` แต่ user ไม่มี offering ที่รับผิดชอบ → "ไม่พบรายวิชาที่ต้องจัดตารางสอนในระบบ"
   - ห้ามแสดง "กำลังตรวจสอบการชน…" ตอนยังไม่มีข้อมูลให้ตรวจ

## Schedule Slot Validation Gates (Schedule Suite — 24 พ.ค.)

บังคับใน `CourseHead\ScheduleController` ตอน `store/update` (และ `CourseHead\CourseOfferingController::storeInstructor` สำหรับ pool):

1. **Department gate** — ผู้สอนที่เลือก (ใน slot + ที่เพิ่มเข้า instructor pool) ต้องมี `instructor_profiles.department_id == courses.department_id` เท่านั้น
2. **Capacity gate** — sum(`student_groups.student_count`) ของ groups ที่ผูกกับ slot ต้อง ≤ `capacity_required` ของ slot
3. **Lead instructor required** — ต้องมี `lead_instructor_id` 1 คน
4. **In-course conflict** — instructor/room/group overlap ภายใน offering บล็อกบันทึก (error key `'schedule'` ส่งเป็น array of messages)
5. **Cross-course conflict** ✅ — implement แล้วผ่าน `ScheduleConflictChecker::bulkConflictMap()` + `ScheduleConflictReadRepository` (merge `42e4810`) — `buildOwnedConflictMap()` ดึง schedules ทั้งระบบที่ overlap date window แล้ว pairwise compare instructor/room ข้ามวิชา + หน้า `/course_head/schedule/conflicts` แสดงผลแบบ 4-card summary

## Academic Year Activation Lock (22 พ.ค.)

`AdminSettingController::storeYear/updateYear` block การตั้ง `is_active=true` ของปีการศึกษาใดๆ หากมี `AcademicYear` อื่นเหลือ `phase='scheduling'` — Admin ต้องปิด scheduling window เดิม (phase → `published` หรือกลับ `preparation`) ก่อนสลับปี active เพื่อกัน data drift

## Instructor & Conflict Logic

1. **Instructor Pool (2-layer — Sprint 3)**:
   - **Course-level template** (`course_instructors`): Admin/Staff จัดผ่าน Course Pool — เก็บ default ผู้สอน + บทบาท
   - **Offering-level snapshot** (`course_offering_instructors`): sync จาก template ตอน Admin เปิด scheduling phase
   - Course Head แก้ไขได้เฉพาะ offering-level ระหว่าง `scheduling` phase
   - Template ล็อกเมื่อ offering ใดเข้า `scheduling`/`published` แล้ว (ป้องกัน drift)
2. **Cross-Course Conflict (M4)**: ตรวจโดยอ้างอิง Global Instructor ID ข้ามทุกรายวิชาในคณะ
3. **Team Supervision**: เลือกอาจารย์ผู้สอนได้หลายท่านต่อ 1 กิจกรรม (via `schedule_instructors`)
4. **Workload Quota (M6)**: คำนวณจาก (สัปดาห์/ปี) × (ชม./สัปดาห์) → ชั่วโมงรวมต่อปี
5. **Name Display**: ไม่มีเว้นวรรคระหว่างตำแหน่ง/คำนำหน้ากับชื่อ (เช่น `อ.ดร.ราชันย์`)
6. **Executive Filter**: executive role ถูกกรองออกจาก available instructor pool (ใน `CoursePoolController` + `CourseOfferingController::show`)

## Curriculum & Course Offering Architecture

```
Curriculum (Master Plan) → education_level, duration_years, uses_year_level, total_credits_required
└── Course → default_year_level (nullable เมื่อ !uses_year_level), default_semester, is_required

Course Offerings (ต่อเทอม — ตัวกลาง Master ↔ Schedule)
├── สร้างอัตโนมัติเมื่อ Admin เปิด scheduling phase
└── Sync planning fields + instructor pool จาก course template
```

- **Curriculum types** (19 พ.ค.): รองรับ ป.ตรี/ป.โท/ป.เอก ผ่าน `education_level` — `uses_year_level=false` สำหรับหลักสูตรที่ใช้ prerequisite + หน่วยกิตสะสมแทนระบบชั้นปี (ทั่วไป ป.โท/ป.เอก)
- **Critical-gate**: ก่อนเปิด scheduling Admin ต้องเคลียร์ critical alerts ให้หมด — รวม `no_active_course`, `active_courses_missing_head`
- **Sync direction**: course template → offering snapshot (ทางเดียว) — ตอน `openSchedulingWindow`
- **Sync scope**: planning hours, capacity, teaching_weeks, requires_practicum_rotation, instructor pool (ลบ stale entries)
- Dashboard Course Head แสดงเฉพาะวิชาที่ offering อยู่ใน scheduling/published
- Inactive Curriculum → Force Update รายวิชาทั้งหมดใน curriculum → `inactive`
- Clone curriculum สำหรับ versioning (2569 → 2574) ไม่กระทบประวัติเดิม + clone metadata ทั้งหมด
- **Prerequisite** อยู่ที่ course level (M1 Master Data) ไม่ใช่ offering level

## Performance Agreement (PA) Criteria ปี 2569

เกณฑ์เก็บใน `system_settings.pa_criteria_config` (JSON `{min, max}`) — ดู `database.md` สำหรับ schema

**Title → PA Group mapping** (ใช้ใน `AlertController::paGroup()`):

| `instructor_profiles.title` | PA Group key |
|-----------------------------|-------------|
| อาจารย์, ผู้ช่วยศาสตราจารย์, รองศาสตราจารย์, ศาสตราจารย์ | `อาจารย์` |
| ผู้ช่วยอาจารย์ + `academic_degree = ปริญญาตรี` | `ผู้ช่วยอาจารย์_ปตรี` |
| ผู้ช่วยอาจารย์ (คลินิก) | `ผู้ช่วยอาจารย์_คลินิก` |
| ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ) | `ผู้ช่วยอาจารย์_ปฏิบัติ` |
| ผู้ช่วยอาจารย์ (degree อื่น) | `ผู้ช่วยอาจารย์` |

**PA Alert logic** (`AlertController::getPaViolations()` — returns Critical):
1. `teaching_pct + research_pct + service_pct + culture_pct + other_pct ≠ 100` → แจ้งเตือน
2. ค่าใดค่าหนึ่งออกนอก `[min, max]` ของ group นั้น → แจ้งเตือน

**โลจิกพิเศษ** (ยังไม่ implement — Phase 2):
- ผช.อาจารย์ บรรจุ **ก่อน 1 ต.ค. 2559** + จบ ป.เอก → ใช้เกณฑ์ **อาจารย์**
- ผช.อาจารย์ บรรจุ **ตั้งแต่ 1 ต.ค. 2559** + จบ ป.เอก (ภาษาอังกฤษไม่ผ่าน) → ใช้เกณฑ์ **ผช.ปกติ**

## HR / Instructor Data Integration

- Phase 1: กรอก manual ผ่าน M1 — Admin/Staff เป็นผู้กรอก
- Phase 2 (Future): sync กับ FIMS/HR ของมหาวิทยาลัย
- ห้าม hardcode ข้อมูลอาจารย์ — ดึงจาก `users` + `instructor_profiles` เสมอ
- `instructor_profiles.employee_id` = Global Instructor ID สำหรับ Conflict Check (Sprint 4)
- System Settings URL: `?tab=pa` หรือ `?tab=academic` เพื่อ Active Tab Persistence
