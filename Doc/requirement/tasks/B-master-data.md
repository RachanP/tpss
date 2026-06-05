# TASKS — Branch B: `feat/v4-master-data`

> แผนรวม: `Doc/requirement/v4_work_split.md` · requirement: `requirement_v4.md`
> ขอบเขต: V4 ข้อ 1 (หัวข้อกิจกรรม dropdown — M) + ข้อ 4 (หลักสูตรบริการวิชาการ — S)

## งานที่ต้องทำ

### ข้อ 1 — หัวข้อกิจกรรมสำเร็จรูป (Activity Topic Templates) (M) ✅ CORE DONE

- [x] migration: ตาราง `activity_topics` ผูกรายวิชา (`course_id` cascade, name, sort_order)
- [x] Model `ActivityTopic` + `Course::topics()` hasMany (ordered) + eager-load ใน index
- [x] CRUD ใน Master Data: โมดัล "หัวข้อกิจกรรม" ต่อวิชา (เพิ่ม/ลบแถว + บันทึกทั้งชุด) · ปุ่มในแถววิชา + badge นับจำนวน
- [x] `syncCourseTopics` (replace ทั้งชุด, trim+กรองแถวว่าง, audit log) + route admin+staff
- [x] endpoint JSON `courseTopics` (`*.courses.topics.index`) คืนหัวข้อตามวิชา — ให้หน้า schedule เรียกใช้
- [x] test: `ActivityTopicTest` (5 tests — save ordered/replace/clear/json/cascade)
- [ ] partial dropdown `<select>` ในหน้า schedule (ยังพิมพ์เองได้ = free text) — **อยู่ในความรับผิดชอบ Branch A**
- [ ] **ประสานกับ Branch A**: เสียบ dropdown ใน slot modal **หลัง A merge เข้า to-serve แล้ว rebase** · A เรียก endpoint `courses.topics.index`

### ข้อ 4 — หลักสูตรนับบริการวิชาการอย่างเดียว (S) ✅ DONE

- [x] migration: flag `curriculums.counts_service_only` (boolean default false) ใน baseline
- [x] Model `Curriculum`: fillable + cast boolean + default attribute
- [x] validation + fill: `curriculumValidationRules` (`required|boolean`) + `normalizeCurriculumInput` coerce boolean (กัน checkbox ไม่ติ๊ก) + audit fields + clone carry-over
- [x] UI Master Data หลักสูตร: select "นับชั่วโมงปกติ / บริการวิชาการอย่างเดียว" + Alpine state + old() restore
- [x] test: `CurriculumServiceOnlyFlagTest` (5 tests — on/absent/checkbox-coerce/toggle/page)
- [ ] (เฟสถัดไป) Module Workload ใช้ flag นี้แยกหมวด "บริการวิชาการ"

### เพิ่มเติม (นอก scope ตั้งต้น แต่ลูกค้าขอระหว่างทาง) ✅ DONE

- [x] ปรับ modal หลักสูตรให้ใช้ง่าย: จัด section, auto รูปแบบชั้นปี + "ปรับเอง", toggle segmented (การนับภาระงาน/สถานะ)
- [x] กลุ่มย่อยจากกลุ่มใหญ่ (`student_cohorts.parent_id`): กลุ่มใหญ่ตัวอักษรล้วน + เพิ่มกลุ่มย่อย A1/A2 ใน modal (optional) · `CohortSubgroupTest` (6 tests)

## ไฟล์ที่เป็นเจ้าของ

- `app/Http/Controllers/Admin/MasterDataController.php` (+ Staff inherit)
- `resources/views/shared/master_data/index.blade.php`
- migration ใหม่ (activity topics + curriculums flag + student_cohorts.parent_id)

## หมายเหตุ merge

- migration ไม่ชนกับ branch อื่น (คนละตาราง)
- เฉพาะ dropdown ในหน้า schedule = ของ A → rebase บน to-serve หลัง A merge แล้วค่อยเสียบ
- ก่อน PR: unit test ผ่าน · `data-testid` ครบ
