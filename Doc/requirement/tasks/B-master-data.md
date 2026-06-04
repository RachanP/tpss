# TASKS — Branch B: `feat/v4-master-data`

> แผนรวม: `Doc/requirement/v4_work_split.md` · requirement: `requirement_v4.md`
> ขอบเขต: V4 ข้อ 1 (หัวข้อกิจกรรม dropdown — M) + ข้อ 4 (หลักสูตรบริการวิชาการ — S)

## งานที่ต้องทำ

### ข้อ 1 — หัวข้อกิจกรรมสำเร็จรูป (Activity Topic Templates) (M)
- [ ] migration: ตาราง activity topics ผูกรายวิชา (course_id หรือ activity_type) — code, name, sort_order
- [ ] CRUD ใน Master Data ให้ Admin กรอกหัวข้อ/บทเรียนต่อวิชาล่วงหน้า
- [ ] endpoint คืนรายการหัวข้อตามวิชา (ให้หน้า schedule เรียกใช้)
- [ ] partial dropdown `<select>` หัวข้อกิจกรรม (ยังพิมพ์เองได้ = free text)
- [ ] **ประสานกับ Branch A**: เสียบ dropdown ใน slot modal **หลัง A merge เข้า to-serve แล้ว rebase** (A เป็นเจ้าของ modal)

### ข้อ 4 — หลักสูตรนับบริการวิชาการอย่างเดียว (S)
- [ ] migration: flag ใน `curriculums` ระบุประเภท "นับงานบริการวิชาการอย่างเดียว" (ไม่นับชั่วโมงทำการปกติ)
- [ ] UI Master Data หลักสูตร: เพิ่ม field/ตัวเลือกประเภทนี้
- [ ] เตรียมข้อมูลให้ Module Workload (เฟสถัดไป) แยกหมวด "บริการวิชาการ"

## ไฟล์ที่เป็นเจ้าของ
- `app/Http/Controllers/Admin/MasterDataController.php` (+ Staff inherit)
- `resources/views/shared/master_data/index.blade.php`
- migration ใหม่ (activity topics + curriculums flag)

## หมายเหตุ merge
- migration ไม่ชนกับ branch อื่น (คนละตาราง)
- เฉพาะ dropdown ในหน้า schedule = ของ A → rebase บน to-serve หลัง A merge แล้วค่อยเสียบ
- ก่อน PR: unit test ผ่าน · `data-testid` ครบ
