# TASKS — Branch A: `feat/v4-schedule-groups`

> แผนรวม: `Doc/requirement/v4_work_split.md` · requirement: `requirement_v4.md`
> ขอบเขต: V4 ข้อ 2 (หัวหน้าวิชาจัดกลุ่มเอง — L) + ข้อ 7 (ลากช่วงวันที่ — M)

## งานที่ต้องทำ

### ข้อ 2 — หัวหน้าวิชาจัดกลุ่มนักศึกษาเอง (L)
- [ ] เอา group selector กลับเข้า slot modal (`shared/schedules/_schedule_modals.blade.php`)
- [ ] ฐานการแบ่งกลุ่ม = จำนวน นศ. ในชั้นปีของปีนั้น (ผูก `student_cohorts`) ไม่ใช่ `capacity_required`
- [ ] migration `student_groups.cohort_group_id` (FK → student_cohorts, nullable)
- [ ] capacity gate ใหม่: เทียบ sum(student_count) กับจำนวน นศ.ชั้นปี (ทบทวน `assertSelectedGroupsFitCapacity`)
- [ ] cross-course GROUP conflict: ขยาย `ScheduleConflictChecker::bulkConflictMap()` ให้ pairwise compare cohort_group ข้ามวิชา (เพิ่มจาก instructor/room)
- [ ] ปี 3-4 = 4 กลุ่มใหญ่ (ปรับ seeder/ตัวอย่าง cohort)

### ข้อ 7 — สร้างกิจกรรมแบบลากช่วงวันที่ (M)
- [ ] UI เลือก Start–End date ใน modal (ใช้ `<x-thai-date-input>`) + เลือกเฉพาะวันในสัปดาห์ได้
- [ ] ต่อยอด `ScheduleController::storeSeries()` ให้รับช่วงวันที่
- [ ] ข้ามวันหยุด/สอบ/ปิดเทอมอัตโนมัติผ่าน `AcademicCalendar` (หรือแจ้งเตือน)
- [ ] แก้รายการลูกรายวันได้ภายหลังโดยไม่กระทบทั้งชุด

## ไฟล์ที่เป็นเจ้าของ (คนอื่นห้ามแตะ)
- `app/Http/Controllers/CourseHead/ScheduleController.php`
- `app/Http/Controllers/CourseHead/CourseOfferingController.php`
- `resources/views/shared/schedules/index.blade.php` + `_schedule_modals.blade.php`
- `app/Services/ScheduleConflictChecker.php`
- Models `StudentGroup`, `StudentCohort`

## หมายเหตุ merge
- **merge เข้า `to-serve` ก่อนเพื่อน** (เป็นเจ้าของ slot modal) — Branch B/C จะ rebase ตามทีหลัง
- ก่อน PR: unit test ผ่าน · `data-testid` ครบ · responsive ≤390px
