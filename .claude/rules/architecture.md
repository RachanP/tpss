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
   - ⚠️ **SUPERSEDED บางส่วน (30 พ.ค. — ดู "Master Data Cleanup Phase (V2)")**: ส่วน "เปิดรายวิชาอัตโนมัติตอนเปลี่ยนปี" ยังถูกต้อง แต่ scope ของ `academic_years` เปลี่ยนจาก "เทอม" → "ปี" และ auto-open ดูแค่ active curriculum (ไม่ผูกเทอม)
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
    ⚠️ default_semester จะถูกตัดใน Master Data Cleanup (V2) — วิชาเปิดทั้งปี

Course Offerings (ปัจจุบัน=ต่อเทอม → จะเปลี่ยนเป็น ราย-ปี ใน Cleanup V2 — ตัวกลาง Master ↔ Schedule)
├── สร้างอัตโนมัติเมื่อ Admin เปิด scheduling phase (Cleanup V2: auto-open ทุกวิชาใน active curriculum)
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

## Requirement V2 Direction — 🔲 PROPOSED (ยังไม่ implement · pending demo 2 มิ.ย. 2569)

> แหล่งอ้างอิง: `Doc/จากอาจารย์/เอกสาร/รายละเอียดระบบจัดตารางสอน_V2.docx`
> ทั้งหมดนี้เป็น **ทิศทางที่ยังไม่ได้ลงมือ** — ห้ามถือว่ามีในโค้ดจนกว่าจะมี commit จริง + ลบ label นี้
> Schema ที่เสนอ ดู `database.md` หัวข้อ "V2 Proposed Schema"

V2 ชี้ว่าตารางคณะพยาบาลเป็น "ตารางบริหารการเรียนการสอน/ฝึกปฏิบัติแบบหลายกลุ่ม หลายกิจกรรม หลายสถานที่ หลายผู้สอน" ไม่ใช่ timetable รายสัปดาห์ — มี 7 implication ที่กระทบ design ปัจจุบัน:

#### Phase Pipeline (ยืนยันโมเดล 30 พ.ค. — กลุ่มนักศึกษามา *หลัง* อนุมัติ)

```
1. Setup (Admin)        : master data + cohort กลุ่มชั้นปี (จำนวนคน, ป.ตรี ปี1=กลุ่มใหญ่ / ปี3-4=4 กลุ่ม)
                          + วิชา "เปิดทั้งปี" ไม่ผูกเทอม
2. Course Offering      : หัวหน้าวิชา ดู/แก้รายละเอียดที่ Admin จัดมา + จัดการชุดผู้สอน
   (หัวหน้าวิชา)          + แอดอาจารย์เข้ามาช่วยจัดตาราง + สร้างกิจกรรม (สถานที่ + ใครสอน)
                          → ยังไม่มีกลุ่มนักศึกษาในขั้นนี้
3. Approval (executive) : ตรวจ "ภาระงาน + เวลา/ตารางชน" — ไม่ดูกลุ่มนักศึกษา
4. หลังอนุมัติ           : อาจารย์ทุกคนในวิชาคุยกัน + จัดกลุ่มนักศึกษา (subgroup) เอง
   (instructors)         ปี3-4 = เวียนวิชาข้ามเทอม (กลุ่ม1: เทอม1 ผู้สูงอายุ→เทอม2 อายุรกรรม / กลุ่ม2 สลับ)
5. เผยแพร่ + แจกตาราง    : ใช้ cohort + เทอม แบ่ง "กลุ่มไหนเทอมนี้ทำกิจกรรมอะไร" → ตารางรายกลุ่มชั้นปี
   รายกลุ่ม
```

⚠️ **ผลกระทบสำคัญ:** ระบบปัจจุบันมี **capacity gate** บังคับ `sum(student_groups.student_count) ≤ capacity_required` **ตอน save slot** — ถ้ากลุ่มมา step 4 (หลังอนุมัติ) gate นี้ต้องเปลี่ยนเป็น optional/deferred ไม่งั้นหัวหน้าวิชาสร้าง slot ไม่ผ่านตั้งแต่ step 2

### 1. หัวหน้าวิชา = สร้างกิจกรรม + ใส่อาจารย์ → ส่งผู้บริหาร (scope แคบ — ยืนยัน 30 พ.ค.)
- **core loop หัวหน้าวิชา (จากลูกค้า 30 พ.ค.): สร้างกิจกรรม → ใส่อาจารย์ผู้รับผิดชอบ → ส่งผู้บริหารอนุมัติ. จบ.**
- **หัวหน้าวิชา *ไม่* ยุ่งกับกลุ่มนักศึกษา** — group เป็นคนละ concern (ดูข้อ 2)
- core loop นี้ = ระบบปัจจุบันทำอยู่แล้ว (สร้าง slot + `schedule_instructors` + `course_offerings` ส่งขออนุมัติ) → ฝั่งหัวหน้าวิชาแทบไม่ต้องแก้
- **ผู้อนุมัติ = ผู้บริหาร (executive)** ผ่าน `course_offerings.approval_status` — ตรงระบบปัจจุบัน (resolves open Q เดิม)
- doc บรรทัด 98/123: ภาคปฏิบัติ ป.ตรี อาจารย์มักแบ่งกันจัด → **delegate ไปที่ instructor** (ไม่ใช่หัวหน้าวิชา)
- เสนอ (delegation): `course_offering_instructors.schedule_permission enum('view','schedule','manage_groups')` — เปิดให้อาจารย์ที่ได้รับมอบหมายช่วยจัด (รวมจัดกลุ่มย่อย)

### 2. กลุ่มนักศึกษา = 2 ระดับ + เกิด *หลังอนุมัติ* (ไม่ใช่งานหัวหน้าวิชา) ✅ ถอด UI หัวหน้าวิชาแล้ว
- **ระดับ cohort (Master/Setup โดย Admin)**: กลุ่มชั้นปีต่อหลักสูตร ป.ตรี + จำนวนคน (ปี1=กลุ่มใหญ่, ปี3-4=4 กลุ่ม) — Admin กรอกตั้งแต่ Setup
- **ระดับ subgroup (หลังอนุมัติ โดยอาจารย์)**: อาจารย์ทุกคนในวิชาคุยกัน + ซอยกลุ่มย่อยเอง (step 4 ของ pipeline) — ไม่ใช่หัวหน้าวิชา ไม่ใช่ Admin
- doc บรรทัด 11/122-125: ปี 3-4 = 4 กลุ่มใหญ่ (~80 คน) ตั้งแต่ต้น → ซอยเป็น subgroup ต่อวิชา
- ปัจจุบัน: `student_groups` ผูก `course_offering_id` อย่างเดียว → ไม่มี identity ข้ามวิชา → publish รายกลุ่มข้ามวิชาไม่ได้
- เสนอ: `student_cohorts` ระดับ Setup Data + `student_groups.cohort_group_id` FK
- ✅ **DONE (เคาะ + ทำแล้ว):** ถอดหน้าจัดกลุ่มย่อยออกจาก course management ของหัวหน้าวิชาทั้งหมด — ลบ 5 controller methods (`storeStudentGroup`/`bulkStoreStudentGroups`/`updateStudentGroup`/`bulkDestroyStudentGroups`/`destroyStudentGroup`) + 5 routes `maker.course_offerings.student_groups.*` + card "กลุ่มนักศึกษา" ใน show + badge ในหน้า list · **เก็บตาราง `student_groups` + model + pivot `schedule_student_groups` ไว้** สำหรับเฟส "อาจารย์จัดกลุ่มหลังอนุมัติ"
- ✅ **slot decouple แล้ว:** `ScheduleController::validateSchedule` ทำ `student_group_ids` เป็น `['nullable','array']` เสมอ (คง instructor required) · เอา gate "ต้องสร้างกลุ่มก่อนจึงเพิ่ม slot" ออกจากหน้าจัดตาราง · capacity gate no-op เมื่อไม่มีกลุ่ม (มีอยู่แล้ว) · modal slot โชว์ group selector เป็น optional + note "จัดกลุ่มหลังอนุมัติ"

### 3. ปีการศึกษา = "ปี" ไม่ใช่ "เทอม" · วิชาเปิดทั้งปี · เทอม/รอบ = dimension ของ slot
- doc บรรทัด 159: schedule entry ระบุ ปี + ภาค + ปีปรับปรุงหลักสูตร ต่อรายการ (ไม่ผูกที่ตัววิชา)
- ปัจจุบัน: `academic_years` จริง ๆ คือ "เทอม" (`unique(name,semester)`, phase/is_active ต่อเทอม) → เปิดจัดตารางทีละเทอม, `course_offerings` ราย-เทอม
- เสนอ: ยกระดับ `academic_years` เป็นปีจริง + ตาราง `semesters/terms` (child) + `course_offerings` ราย-ปี + slot ถือ `semester_id`
- **ขัด decision เดิม** "เปิดทั้งภาคเรียนพร้อมกัน (scope = เทอม)" → ต้อง re-confirm ว่าเปลี่ยน scope เป็นปี

### 4. Rotation หลังสอบ — 2 รอบต่อเทอม (สำคัญสุด)
- doc บรรทัด 311: ป.ตรี ปี 3-4 ขึ้นฝึก **สลับวนกลุ่ม 2 ครั้งใน 1 ภาค** → ต้องสรุปภาระงานแยก 2 รอบ/ภาค
- ตัวอย่างปี 3: สัปดาห์ 1-8 A1=RANS326/A2=RANS327 → **สอบ** → สัปดาห์ 9-16 สลับ A1↔A2 (exam week = เส้นแบ่งรอบ)
- เสนอ: `rotation_rounds` (เทอม → รอบ 1|2, start/end) + `rotation_assignments` (cohort_group × offering × round) เป็น "แผนหมุนเวียน" ที่ scaffold schedule + workload per-round

### 5. Cross-course GROUP conflict — ต้องเพิ่มใหม่ (ปัจจุบัน by-design ไม่เช็ค)
- ⚠️ database.md: ตอนนี้เช็คแค่ room + instructor "ไม่เช็ค student overlap ข้ามวิชา"
- พอกลุ่มเป็น cohort ใช้ร่วมข้ามวิชา → กลุ่มเดียวห้ามอยู่ 2 วิชาพร้อมกัน กลายเป็นเรื่องจริง
- เสนอ: ขยาย `ScheduleConflictChecker::bulkConflictMap()` ให้ pairwise compare cohort_group ข้ามวิชาเพิ่มจาก instructor/room

### 6. Copy-with-group-swap + workload per-round
- Priority 6 (`schedule_templates`+`storeSeries`) ทำ "ซ้ำรายสัปดาห์" แล้ว — rotation ต้องการ "คัดลอกโครงรอบ 1 → รอบ 2 สลับ mapping กลุ่ม" (เช็คว่า series รับ remap group ต่อรอบได้ไหม)
- workload widget ใช้ quota — Phase 2 ต้องคำนวณจาก schedule จริง group by `rotation_round_id`

### 7. รายละเอียดที่ V2 เพิ่มใหม่ (กระทบ master-data decision เดิม)
- **2 วิทยาเขต** (doc บรรทัด 19): ทฤษฎี→ศาลายา LRC, ปฏิบัติ→ศิริราช (บางกอกน้อย) — ขัด master-data decision 30 พ.ค. ที่เลื่อน campus field → reconsider
- **activity_type 0 ชั่วโมง** (doc บรรทัด 141/144): ปฐมนิเทศ/SDL ไม่นับ workload — ตรงกับ Priority 3 backlog (`counts_toward_workload`)

### Resolved (ยืนยัน 30–31 พ.ค.)
- ✅ **ผู้อนุมัติ = ผู้บริหาร (executive)** ตรวจภาระงาน + เวลา/ชน — ตรงระบบปัจจุบัน ไม่เปลี่ยน
- ✅ **อนุมัติ = ทั้งปี (per-year) ไม่ใช่ราย-เทอม** (เคาะ 31 พ.ค.) — ผู้บริหารอนุมัติวิชานั้นทีเดียวทั้งปี
  - พิจารณา per-term (ตามเอกสารพิม ข้อ 5.2) แล้ว แต่เลือก **per-year** เพื่อให้ approval ก้อนเดียวจบ/ไม่ต้องอนุมัติซ้ำ 2 รอบ
- ✅ **course_offering = ราย-ปี** — `academic_years` เป็น "ปี"
- ✅ **การสลับกลุ่ม A/B (semester swapping) อยู่ใน offering ปีเดียว** — offering ถือทั้งกลุ่ม A และ B + อาจารย์ทั้งปี (superset) · แต่ละ slot ติดป้าย เทอม + กลุ่ม → เทอม 1 กลุ่ม A / เทอม 2 กลุ่ม B อยู่ใน offering เดียวกัน อนุมัติทีเดียว
- ✅ **วิชาเปิดทั้งปี · เทอม = dimension ของ slot** ไม่ใช่ของวิชา
- ✅ **กลุ่มชั้นปี (cohort) ใน Master Data** — implement แล้ว (`student_cohorts`)

### Open Questions (เหลือไว้เคาะภายหลัง — ไม่บล็อก Master Data cleanup)
1. ✅ **RESOLVED (31 พ.ค.): กลุ่มนักศึกษา หัวหน้าวิชา *ไม่* จัด** — เคาะแล้วว่ายึดโมเดล 30 พ.ค. (subgroup เกิดหลังอนุมัติ โดยอาจารย์) ไม่ใช่ตามเอกสารพิม ข้อ 7 · ถอด UI/route/controller จัดกลุ่มออกจาก course management ของหัวหน้าวิชาแล้ว (ดูข้อ 2 ด้านบน)
2. **ปี 3-4 = 2 กลุ่มใหญ่ (A/B) หรือ 4 กลุ่ม?** — เอกสารพิมว่า 2 (A/B สลับเทอม) · ก่อนหน้าว่า 4 — cohort feature รองรับกี่กลุ่มก็ได้ ไม่บล็อก
3. ✅ **RESOLVED (31 พ.ค.): capacity gate deferred = ใช่** — slot save/อนุมัติได้โดยไม่มีกลุ่ม · `student_group_ids` optional, capacity gate no-op เมื่อไม่มีกลุ่ม (เช็คเฉพาะตอนมีกลุ่มจริง)
4. **รอบ rotation = 2 เสมอไหม** · **ตารางรายกลุ่มชั้นปี** Phase 1 หรือ 2?
5. **ใครกรอกกิจกรรมภาคปฏิบัติ** (เอกสารพิม ข้อ 11 — แผน A/B/C) → V1 ใช้แผน C (อาจารย์แจ้ง offline, เจ้าหน้าที่/หัวหน้ากรอก) · V1.5 ทำแผน B (instructor จัดเอง = Option B/delegation)

## Master Data Cleanup Phase (V2) — ✅ CORE เสร็จ (31 พ.ค.) · branch `feat/v2-requirement`

> ทิศทาง: เคลียร์ Master Data ให้นิ่ง (วิชาเปิดทั้งปี + ปีการศึกษาเป็นปีจริง) **เป็นฐานก่อน** แล้วค่อยทำ schedule/rotation/publish
> ทำบน branch `feat/v2-requirement` — `sprint` ยังเป็น demo fallback (term-based เดิม)
> สถานะ: ข้อ 1-5 ด้านล่าง **DONE + verified** (migrate:fresh เขียว · test 472/474, 2 fail = pre-existing ScheduleFlowSeeder)
> ⚠️ ยังไม่ merge เข้า sprint จน verify บนแอปจริงครบ

### ✅ สิ่งที่ทำเสร็จแล้ว (Master Data scope — DONE)

1. **ปีการศึกษา = "ปี" ไม่ใช่ "เทอม" + มีเทอมเป็นรายการลูก (พร้อมวันสอบ)**
   - `academic_years`: ตัด column `semester` ออก → unique(`name`) แทน unique(`name`,`semester`) · `phase`/`is_active` ต่อ "ปี" · 1 ปี = 1 row
   - เพิ่มตาราง `terms` (ลูกของปี): `academic_year_id`, `sequence`, `name`, `start_date`, `end_date`,
     `midterm_start`/`midterm_end`, `final_start`/`final_end` (ช่วงสอบกลางภาค/ปลายภาค — nullable, เก็บเป็นช่วง "สัปดาห์สอบ")
   - **เทอมยืดหยุ่น — เพิ่มได้ตามจริง ไม่ฟิกซ์ 2**: ปีปกติ = เทอม 1 + เทอม 2 · ปีที่มี **ภาคฤดูร้อน** = เพิ่มอีกรายการ (optional, name="ภาคฤดูร้อน")
   - **ช่วงปิดภาคเรียน = derive จากช่องว่างระหว่างเทอม** (ไม่เก็บ field แยก) → ปฏิทินขึ้นป้าย "ปิดภาคเรียน" อัตโนมัติ
   - หน้าจัดตารางหัวหน้าวิชาแบ่ง **section ตามเทอม** → ถ้าปีไหนมีภาคฤดูร้อนก็โผล่เป็น section เองอัตโนมัติ
   - **ทำไม**: หัวหน้าวิชาเห็นโครงปี + สัปดาห์สอบตอนวางกิจกรรม จะได้ไม่วางทับ · วันสอบยังเป็นเส้นแบ่งรอบหมุนเวียนปี 3-4 (V2 ข้อ 4)
   - **เปลี่ยนปีต้องกรอกใหม่** (วันที่ต่างทุกปี) — แต่ตอนเปิดปีใหม่ให้ **ลอกโครงปีก่อนมาเป็นค่าตั้งต้น** ให้ผู้ใช้แค่ขยับวันที่
2. **เลิกผูกรายวิชากับเทอม**
   - `courses`: ตัด `default_semester` (วิชาเปิดทั้งปี ไม่ผูกภาค)
   - UI Master Data รายวิชา: เอา field/คอลัมน์ "ภาค" ออก
3. **course_offering = ราย-ปี**
   - `course_offerings.academic_year_id` → ชี้ "ปี" · 1 วิชา = 1 offering ต่อปี (เลิกซ้ำต่อเทอม)
   - ปรับ unique/index `(course_id, academic_year_id)`
4. **Auto-open ดูแค่ active curriculum**
   - ตอน Admin เปิด scheduling/เปลี่ยนปี → auto-create offering ของ **ทุกวิชาที่ `course.curriculum.is_active = true`** (เลิก logic ผูกเทอม/`default_semester`)
   - คง critical-gate `active_courses_missing_head` ไว้ ตัดเงื่อนไขที่อิงเทอม
5. **เทอม = dimension ของ "slot/กิจกรรม" (ตั้งแต่หัวหน้าวิชาจัด) — ไม่ใช่ของวิชา**
   - หัวหน้าวิชาจัดกิจกรรม**ครอบทั้งปี** แล้วติดป้ายว่าแต่ละ slot อยู่เทอมไหน — เพราะ **เทอม 2 อาจเปลี่ยนคนสอน/เวลา** จากเทอม 1
   - instructor pool ของ offering = **superset ทั้งปี** · แต่ละ slot เลือกอาจารย์เองผ่าน `schedule_instructors` (มีอยู่แล้ว) → เทอม 2 เปลี่ยนคนได้โดยไม่กระทบเทอม 1
   - ตอนจัดกลุ่มหลังอนุมัติ: slot ติดป้ายเทอมไว้แล้ว → แมพ "กิจกรรมเทอมนี้ → กลุ่มไหน" ได้ตรง (ฐานของ rotation)
   - ตาราง `terms` (วัน+ช่วงสอบ) สร้างในรอบ cleanup นี้ (ข้อ 1) · ส่วน `schedules.term_id` (ให้ slot ระบุว่าอยู่เทอมไหน) + rotation = งาน **phase ถัดไป** (schedule)

### ✅ Master/Setup scope V3 — เสร็จครบ (31 พ.ค.)
> ตรวจ 31 พ.ค.: เทียบ V3 แล้ว REQUIRED ทั้งหมดปิดครบ เหลือแค่ optional ที่ไม่อยู่ใน V3:
- ✅ **`holidays`** (date, name, remark, **source**) — auto-fetch จาก Google Thai holidays ICS ตอน `storeYear/updateYear` (ตามช่วงปีปฏิทินที่ปีคร่อม via `HolidayService::syncForAcademicYearSpan`) · fail-safe (ดึงไม่ได้ → flash `holiday_warning` ไม่พัง flow) · ปุ่ม "ดึงวันหยุดซ้ำ" ต้องมีปี active ก่อน (ไม่ fallback ปีปฏิทิน) · refresh ลบเฉพาะ `source=google` ในช่วงปี คงของ `source=manual` + ปีอื่น · CRUD + highlight แถว manual ในหน้าตั้งค่า→ปีการศึกษา · SSL ใช้ bundled `resources/certs/cacert.pem` (MAMP Windows ไม่มี curl.cainfo) — DONE
- ✅ **`activity_types.counts_toward_workload`** (bool) — Admin ตั้งนับ/ไม่นับภาระงาน · default ตามหมวด (Alpine `applyWorkloadDefaultFromCategory`: other=ไม่นับ, อื่นๆ=นับ) ปรับเองได้ · `ReferenceDataCache` include column · ตาราง master data แยกคอลัมน์ "หมวดหมู่" (badge สีตามหมวด) + "ภาระงาน" (pill) — DONE · category `thesis` มีไว้รองรับวิทยานิพนธ์/ดุษฎีนิพนธ์แล้ว
- 🔲 (optional) `rooms.campus` ศาลายา/บางกอกน้อย — **ไม่อยู่ใน V3** (มาจาก V1/V2) — display ก่อน ยังไม่ผูก conflict · ยังไม่ทำ ไม่บล็อก

### ของใหม่จากเอกสารพิม V3/V4 (`Doc/จากอาจารย์/เอกสาร/tpss_system_summary_v3.md`)
> เอกสารสรุป requirement ฉบับเพื่อน (พิม) — ยืนยันหลายอย่างที่เราวางไว้ + เพิ่มของใหม่:
- **ตารางวันหยุดราชการ** `holidays` (date, name, remark) — ตารางขึ้นหมายเหตุ "งดการเรียนการสอน" + ไม่นับภาระงาน (ข้อ 2.4, 12.1) — NEW
- **กิจกรรมในสัปดาห์สอบ/วันหยุด ไม่นับภาระงานปกติ** — ผูกกับ `terms` (วันสอบ) + `holidays` (ข้อ 2.4) — workload เป็น Phase 2 แต่ data ต้องพร้อม
- **Dashboard เชิงภาพ** (donut/gauge/Gantt rotation map) แทน text wall — แยกตาม role (ข้อ 8.1) — งาน UI ก้อนใหญ่ Phase ถัดไป
- ✅ ยืนยัน: `student_cohorts` + semester swapping (ข้อ 12.1) ตรงกับที่ทำ · ตารางสอน=ตารางเรียน คนละมุม (ข้อ 1)

### Impact / ต้องแก้ตาม (forward-only, ทีมใช้ `migrate:fresh --seed`)
- Migrations: consolidate เข้า create-table baselines ของ `academic_years`/`courses`/`course_offerings` (ไม่ทำ alter แยก — pattern Sprint 3 Hardening)
- Guard `phase != 'scheduling'` + `AdminSettingController::storeYear/updateYear` activation lock → เปลี่ยนจาก per-term เป็น per-year
- `openSchedulingWindow` (sync planning + instructor pool) → loop ตาม active curriculum
- Migration ใหม่: `create_terms_table` (ลูกของ academic_years + ช่วงสอบ)
- Seeders: `AcademicYearSeeder` (1 row/ปี + เทอม 1/2 พร้อมวันสอบตัวอย่าง), `CourseSeeder` (ตัด default_semester), `CourseOfferingSeeder` (ราย-ปี)
- Tests: `SchedulingPhaseTest`, `CourseOfferingManagementTest`, `MasterDataRedirectTest`, schedule suite — ปรับ assertion ที่อิงเทอม
- Views: Master Data รายวิชา (ตัดภาค), Settings ปีการศึกษา (ตัด column semester + เพิ่มฟอร์มเทอม 1/2 + ช่วงสอบ + ปุ่ม "ลอกจากปีก่อน")
