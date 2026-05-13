# TPSS — Import Templates

ไฟล์ตัวอย่างสำหรับนำเข้าข้อมูลจำนวนมาก (CSV UTF-8)

---

## 1. users_import.csv — นำเข้าผู้ใช้งาน

**ตำแหน่งในระบบ:** Admin → จัดการผู้ใช้งาน → ปุ่ม "นำเข้าจากไฟล์"

| คอลัมน์ | บังคับ | ค่าที่ยอมรับ | หมายเหตุ |
|---------|--------|------------|---------|
| `prefix` | ✅ | นาย, นาง, นางสาว | คำนำหน้าชื่อ |
| `name` | ✅ | ข้อความ | ชื่อ-นามสกุลเต็ม |
| `email` | ✅ | email | ต้องไม่ซ้ำในระบบ |
| `username` | ✅ | ข้อความ ไม่มีช่องว่าง | ต้องไม่ซ้ำในระบบ |
| `password` | ✅ | ข้อความ | รหัสผ่านเริ่มต้น |
| `roles` | ✅ | `instructor\|staff\|course_head\|executive\|admin` | คั่นด้วย `\|` ถ้ามีหลาย role |
| `primary_role` | ✅ | role เดียว | role ที่ใช้ login ครั้งแรก |
| `employee_id` | — | ข้อความ | รหัสพนักงาน |
| `title` | — | ดูตารางด้านล่าง | ตำแหน่งทางวิชาการ |
| `academic_degree` | — | `doctoral`, `non_doctoral` | วุฒิการศึกษา |
| `department_name` | — | ชื่อภาควิชาในระบบ | ต้องตรงกับที่มีในระบบ |
| `employment_type` | — | `full_time`, `part_time` | ประเภทการจ้าง |
| `teaching_pct` | — | 0–100 | สัดส่วนงานสอน (%) |
| `hired_date` | — | YYYY-MM-DD | วันที่บรรจุ |

**ค่า `title` ที่รองรับ:**
`อาจารย์`, `ผู้ช่วยอาจารย์`, `ผู้ช่วยศาสตราจารย์`, `รองศาสตราจารย์`, `ศาสตราจารย์`

> **หมายเหตุ:** ถ้าไม่มีข้อมูล profile เลย (ไม่มี employee_id, title) จะสร้างแค่ user + role  
> ถ้ามี email หรือ username ซ้ำในระบบ → จะข้ามแถวนั้นและแจ้งใน error report

---

## 2. courses_import.csv — นำเข้ารายวิชา

**ตำแหน่งในระบบ:** Master Data → แท็บรายวิชา → ปุ่ม "นำเข้าจากไฟล์"

| คอลัมน์ | บังคับ | ค่าที่ยอมรับ |
|---------|--------|------------|
| `course_code` | ✅ | ข้อความ เช่น `NSBS 212` |
| `name_th` | ✅ | ข้อความภาษาไทย |
| `name_en` | — | ข้อความภาษาอังกฤษ |
| `curriculum_name` | ✅ | ต้องตรงกับชื่อหลักสูตรในระบบ |
| `department_name` | ✅ | ต้องตรงกับชื่อภาควิชาในระบบ |
| `credits` | ✅ | ตัวเลข |
| `lecture_hours` | — | ตัวเลข (default 0) |
| `lab_hours` | — | ตัวเลข (default 0) |
| `self_study_hours` | — | ตัวเลข (default 0) |
| `default_year_level` | — | 1–4 |
| `default_semester` | — | 1, 2, 3 |
| `status` | — | `active`, `inactive` (default active) |

> **course_type** คำนวณอัตโนมัติจาก lecture_hours และ lab_hours  
> ถ้า course_code + curriculum ซ้ำ → update ข้อมูลแทน (upsert)

---

## 3. rooms_import.csv — นำเข้าห้อง/สถานที่

**ตำแหน่งในระบบ:** Master Data → แท็บห้อง → ปุ่ม "นำเข้าจากไฟล์"

| คอลัมน์ | บังคับ | ค่าที่ยอมรับ |
|---------|--------|------------|
| `name` | ✅ | ชื่อห้อง |
| `code` | ✅ | รหัสห้อง ต้องไม่ซ้ำ |
| `location_type_name` | ✅ | ต้องตรงกับประเภทสถานที่ในระบบ |
| `capacity` | — | ตัวเลข |
| `floor` | — | ตัวเลข |
| `building` | — | ข้อความ |
| `status` | — | `active`, `inactive`, `maintenance` (default active) |

> ถ้า code ซ้ำ → update ข้อมูลแทน (upsert)

---

## ข้อกำหนดทั่วไป

- **Encoding:** UTF-8 (ไม่ใช่ UTF-8 BOM)
- **แถวแรก:** header เสมอ (ระบบข้ามแถวแรกอัตโนมัติ)
- **ขนาดไฟล์:** ไม่เกิน 5 MB
- **จำนวนแถว:** แนะนำไม่เกิน 500 แถวต่อครั้ง
