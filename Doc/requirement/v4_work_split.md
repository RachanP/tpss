# แผนแบ่งงาน V4 — 3 Branch / 3 Fullstack

> อ้างอิง requirement: `Doc/requirement/requirement_v4.md` (ข้อ 13 = สรุป 7 ข้อ)
> ทุกคนเป็น fullstack จึงแบ่งตาม "โดเมนของงาน" ไม่แยก FE/BE
> Base ของทั้ง 3 branch = `to-serve`

## Branch A — `feat/v4-schedule-groups` (งานหนักสุด — แนะนำให้ Lead/คนถนัดสุด)

**ขอบเขต:** V4 ข้อ 2 (หัวหน้าวิชาจัดกลุ่มเอง — L) + ข้อ 7 (สร้างกิจกรรมลากช่วงวันที่ — M)

**ทำไมรวมกัน:** ทั้งคู่แก้ "หน้าจัดตาราง" หนัก จึงให้คนเดียวเป็นเจ้าของ slot modal เพื่อกันชนกัน

**ไฟล์ที่เป็นเจ้าของ (ห้ามคนอื่นแตะ):**
- `app/Http/Controllers/CourseHead/ScheduleController.php`, `CourseHead/CourseOfferingController.php`
- `resources/views/shared/schedules/index.blade.php` + `_schedule_modals.blade.php`
- `ScheduleConflictChecker.php` (เพิ่ม cross-course group conflict)
- Models `StudentGroup`, `StudentCohort` + migration `student_groups.cohort_group_id`

**งานหลัก:** เอา group selector กลับเข้า modal · capacity gate ใหม่ (เทียบจำนวน นศ.ชั้นปี ไม่ใช่ `capacity_required`) · date-range bulk (ต่อยอด `storeSeries`, ข้ามวันหยุด/สอบผ่าน `AcademicCalendar`)

## Branch B — `feat/v4-master-data` (Master Data)

**ขอบเขต:** V4 ข้อ 1 (หัวข้อกิจกรรม dropdown — M) + ข้อ 4 (หลักสูตรนับบริการวิชาการอย่างเดียว — S)

**ไฟล์ที่เป็นเจ้าของ:**
- `app/Http/Controllers/Admin/MasterDataController.php` (+ Staff inherit)
- `resources/views/shared/master_data/index.blade.php`
- migration: ตาราง activity topics (ผูกรายวิชา) + flag `curriculums` ประเภทบริการวิชาการ

**งานหลัก:** CRUD หัวข้อกิจกรรมต่อวิชา + flag หลักสูตร · ทำ endpoint + partial dropdown ส่งให้ Branch A เสียบใน modal (ดู coordination)

## Branch C — `feat/v4-rbac-pa-ops` (สิทธิ์ + PA + ปฏิบัติการ)

**ขอบเขต:** ข้อ 3 (executive=หัวหน้าภาค gate — S) + ข้อ 5 (อาจารย์กรอก PA เอง — M) + ข้อ 6 (สลับเวร + timestamp — S/M)

**ไฟล์ที่เป็นเจ้าของ:**
- `app/Http/Controllers/Admin/UserController.php` / RBAC middleware (gate กำหนดหัวหน้าภาค)
- หน้า self-service PA (instructor) + `AlertController` PA logic + `instructor_profiles`
- ข้อ 6: action "เปลี่ยนผู้สอน" + last-updated timestamp + audit log

---

## จุดที่ต้องประสาน (หน้า schedule = ของ Branch A)

3 จุดนี้แตะ `shared/schedules/` ซึ่ง Branch A เป็นเจ้าของ จึงต้องทำตาม merge order เพื่อกันชน:

| Branch | สิ่งที่ไปเสียบในหน้า schedule | ทำเมื่อ |
|--------|------------------------------|---------|
| B | `<select>` หัวข้อกิจกรรม ใน modal | หลัง A merge แล้ว rebase |
| C | ปุ่มเปลี่ยนผู้สอน + แถบ timestamp (header) | หลัง A merge แล้ว rebase |

## Merge Order

```
A schedule-groups  ──merge──▶ to-serve   (เจ้าของ modal ลงก่อน)
        │
B master-data       ──rebase บน to-serve──▶ เสียบ dropdown ──merge──▶
C rbac-pa-ops       ──rebase บน to-serve──▶ เสียบ swap+timestamp ──merge──▶
```

- migration ไม่ชนกัน (คนละตาราง · forward-only · `migrate:fresh --seed`)
- ใช้ `--force-with-lease` บน branch ตัวเองเท่านั้น · ห้าม force push branch เพื่อน

## ก่อนเปิด PR (DoD ย่อ)

ผ่าน unit test · code review · ทดสอบ Chrome/Edge · ไม่มี conflict ค้าง · เพิ่ม `data-testid` ทุก element ที่ E2E จะใช้ · responsive (มือถือ ≤390px สำหรับหน้า course_head)

## ภาระงานสมดุล

- Branch A = L+M (หนักสุด)
- Branch B = M+S
- Branch C = S+M+S

---

## เคาะ decision V4 (4 มิ.ย.) ที่เกี่ยวกับงานนี้

- หัวหน้าวิชาจัดกลุ่มนักศึกษาเอง (ยืนยันกลับทิศ — เริ่มได้เลย ไม่ต้อง re-confirm)
- ปี 3-4 = 4 กลุ่มใหญ่ (ไม่ใช่ 2 A/B)
- rotation = ปีละ 4 รอบ (มิดเทอมเป็นเส้นแบ่งในแต่ละเทอม) + สลับกลุ่มข้ามเทอม
- เลขานุการวิชา = อยู่ใน course_instructors template แต่หัวหน้าวิชาแก้ที่ offering ได้
- capacity gate ต้องทบทวน: ฐานเทียบ = จำนวน นศ.ชั้นปี ไม่ใช่ `capacity_required`
