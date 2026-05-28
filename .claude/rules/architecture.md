# Architecture — Domain Logic, Workflow, PA Criteria

## Workflow หลักของระบบ

```
[Setup Data] → [Create Course] → [Manual Input Schedule] → [Smart Check]
→ [Fix Errors] → [Validate Overall] → [Approve] → [Publish]
→ [Operate & Adjust] → [Report]
```

- **Conflict (Error)** → บันทึกไม่ได้ / แจ้งเตือนสีแดง
- **Warning** → บันทึกได้ แต่ต้องแก้ไข

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
