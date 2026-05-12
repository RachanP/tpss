# TPSS — Teaching & Practicum Scheduling System
**คณะพยาบาลศาสตร์ มหาวิทยาลัยมหิดล**

## คำสั่งสำหรับ Claude: อ่านเอกสารทุกครั้งที่เริ่ม Session ใหม่

เมื่อเริ่ม session ใหม่ใน folder นี้ ให้อ่านเอกสารต่อไปนี้ตามลำดับเพื่อทำความเข้าใจบริบทโปรเจกต์ทั้งหมด:

### เอกสารบังคับอ่าน (ตามลำดับ)

1. **`Doc/เอกสารเพิ่มเติม/TPSS_Product_Backlog_v2.1.docx`** — Product Backlog ฉบับล่าสุด v2.1 (user stories, sprint plan, story points ทั้งหมด — รวม M1-14 Curriculum Management แล้ว) ← **ใช้เป็นแหล่งอ้างอิงหลัก**
2. **`Doc/จากอาจารย์/รายละเอียดระบบจัดตารางสอน_V1.pdf`** — System requirements, workflow การใช้งาน, ลักษณะตารางสอนที่ซับซ้อน
3. **`Doc/เอกสาร ISO/WP-02_Project-Plan_ProjectName_v1.0.pdf`** — Project plan, timeline, scope
4. **`Doc/เอกสาร ISO/WP-01_Agreement_Statement-of-Work_ProjectName_v1.0.pdf`** — Statement of Work, ข้อตกลงโครงการ
5. **`Doc/เอกสาร ISO/WP-03_Software-Requirements-Specification_ProjectName_v1.0.pdf`** — SRS ฉบับล่าสุด ← **อ่านก่อนพัฒนาทุก Sprint**
6. **`flowchart/overview.pdf`** และ **`flowchart/roles.pdf`** — Flowchart Swimlane ล่าสุด (overview = ภาพรวมทุก role, roles = รายละเอียดแต่ละ role)

### เอกสารเพิ่มเติม (อ่านเมื่อเกี่ยวข้อง)

- **`Doc/เอกสารเพิ่มเติม/TPSS_Project_Schedule - ชีต1.csv`** — Project schedule รายละเอียด
- **`Doc/เอกสารเพิ่มเติม/TPSS_Backlog_Amendment_v2.2.docx`** — Amendment เพิ่ม M1-14 Curriculum Management (+2 SP) — อ่านเมื่อทำงาน Sprint 2 (M1)
- **`Doc/จากอาจารย์/Faculty Integrated Management System.pdf`** — ระบบ FIMS ที่เกี่ยวข้อง
- **`Doc/จากอาจารย์/SYS_archtech.pdf`** — System architecture
- **`Doc/จากอาจารย์/การพัฒนาฐานข้อมูลของ HR_SN 1.pdf`** — HR database
- **`Doc/จากอาจารย์/มหิดล.docx`** — ข้อมูลมหาวิทยาลัยมหิดล
- **`Doc/จากอาจารย์/ฐานข้อมูลส่งขอนแก่น.docx`** — ข้อมูล database

---

## ภาพรวมโครงการ

| รายการ | รายละเอียด |
|--------|-----------|
| ชื่อระบบ | Teaching & Practicum Scheduling System (TPSS) |
| ลูกค้า | คณะพยาบาลศาสตร์ มหาวิทยาลัยมหิดล |
| ผู้พัฒนา | ราชันย์ พิพัฒน์ และทีม |
| ระยะเวลา | 25 เม.ย. 2569 – 7 มิ.ย. 2569 (6 สัปดาห์) |
| จำนวน Sprint | 7 Sprints (Phase 1) + 5 Sub-Sprints (Phase 2) |
| Story Points รวม | 280 SP | 61 User Stories |

### สถานะ Phase (ณ 8 พ.ค. 2569)

| Phase | ชื่อ | ช่วงวันที่ | สถานะ |
|-------|------|-----------|-------|
| Phase 1 | Initiation | 25–29 เม.ย. 2569 | ✅ เสร็จแล้ว |
| Phase 2 | Requirements | 29 เม.ย. – 6 พ.ค. 2569 | ✅ เสร็จแล้ว |
| Phase 3 | Design | 4–8 พ.ค. 2569 | ✅ เสร็จสมบูรณ์ (UI Mockup + Database Migrations) |
| Phase 4-5 | Development | 11–28 พ.ค. 2569 | 🟢 เสร็จสิ้น Sprint 1 (Login, RBAC, Settings, User Mgmt) |
| Phase 5 | Testing | 11 พ.ค. – 2 มิ.ย. 2569 | 🟡 กำลังดำเนินการ (Internal Testing) |
| Phase 6 | Deployment | 4–5 มิ.ย. 2569 | ยังไม่เริ่ม |
| Phase 7 | Closure | 7 มิ.ย. 2569 | ยังไม่เริ่ม |

---

## โครงสร้าง Module และ Sprint

### 🔴 Phase 1 — ระบบ Version 1.0 (Development, 11–28 พ.ค. 2569) | 193 SP รวม

> **นี่คือ scope ที่กำลังพัฒนาให้มหิดลในโปรเจกต์นี้** — 7 Sprints ครอบคลุม M10, M1–M3, M4, M8, M7

| Sprint | วันที่ | Module | ชื่อ | SP |
|--------|--------|--------|------|----|
| Sprint 1 | 11–12 พ.ค. | M10 | Login, RBAC & Admin Settings | 24 | ✅ 100% (Admin User Mgmt, Role Switcher, System Settings) |
| Sprint 2 | 12–15 พ.ค. | M1 | Master Data Management | 43 | ✅ 100% (CRUD, Cloning, SweetAlert2, Integrity Logic) |
| Sprint 3 | 18–19 พ.ค. | M2 | Course Management | 19 |
| Sprint 4 | 20–22 พ.ค. | M3 | Schedule Management | 41 |
| Sprint 5 | 21–26 พ.ค. | M4 | Conflict Checking | 29 |
| Sprint 6 | 22–26 พ.ค. | M8 | Views & Calendar | 24 |
| Sprint 7 | 20–27 พ.ค. | M7 | Search & Filter | 13 |

### 🔵 Phase 2 — Future Release / Version 2.0 | 87 SP รวม

| Sub-Sprint | Module | ชื่อ | SP |
|------------|--------|------|----|
| Sub-Sprint 1 | M11 | Approval Workflow | 19 |
| Sub-Sprint 2 | M12 | Audit Trail | 15 |
| Sub-Sprint 3 | M5 | Smart Warning System | 18 |
| Sub-Sprint 4 | M6 | Workload Management | 16 |
| Sub-Sprint 5 | M9 | Reporting | 19 |

---

## ลักษณะสำคัญของตารางสอนในระบบนี้ (ต้องเข้าใจก่อนพัฒนา)

ตารางสอนของคณะพยาบาลศาสตร์มีความซับซ้อนสูงมาก ต่างจากตารางเรียนทั่วไป:

1. **Block Schedule** — ไม่ใช่ตารางซ้ำรายสัปดาห์ แต่เป็นแผนต่อเนื่องหลายสัปดาห์ตามช่วงฝึก
2. **หลายกลุ่มย่อย** — 1 วิชามีนักศึกษา 300+ คน แบ่งเป็นกลุ่ม A1–A9, B1–B9 ฯลฯ
3. **Parallel Activities** — ในวันเดียวกัน แต่ละกลุ่มทำกิจกรรมต่างกัน (round ward / ห้องเรียน / ชุมชน)
4. **หลายประเภทสถานที่** — ห้องเรียน, ห้องปฏิบัติการ, หอผู้ป่วย, โรงพยาบาล, ชุมชน, รพ.สต., ANC/LR/PP, online
5. **หลายประเภทกิจกรรม** — ปฐมนิเทศ, lecture, lab, SDL, กลุ่มย่อย, round ward, รับเคส, conference, post-conference, bedside teaching, reflection, สอบ procedure ฯลฯ
6. **หลายบทบาทผู้สอน** — หัวหน้าวิชา, เลขานุการวิชา, อาจารย์ผู้สอน, อาจารย์ประจำกลุ่ม, preceptor
7. **Rotation Schedule** — กลุ่มนักศึกษาหมุนเวียนระหว่างแหล่งฝึกและประเภทประสบการณ์
8. **Exception-based** — ตารางเปลี่ยนตามสัปดาห์ มีวันหยุด กิจกรรมพิเศษ ไม่ใช่ pattern คงที่
9. **เชื่อมกับ E-logbook** — ตารางผูกกับการติดตามประสบการณ์จริงของนักศึกษา

---

## โลจิกการจัดการอาจารย์และการตรวจสอบ Conflict (Instructor & Conflict Logic)

1. **Instructor Pool (รายชื่ออาจารย์ประจำวิชา):** หัวหน้าวิชา (Course Head) จะไม่ผูกอาจารย์ติดกับกลุ่มนักศึกษาแบบถาวรในหน้าตั้งค่าวิชา (M2) แต่ใช้วิธี **"เพิ่มรายชื่ออาจารย์ (Add Instructor)"** จากฐานข้อมูลกลาง (HR) เข้ามาในรายวิชา เพื่อสร้างเป็น Pool ของอาจารย์
2. **Cross-Course Conflict Checking (M4):** อาจารย์ 1 ท่านสามารถถูกเพิ่มชื่อสอนได้หลายวิชาอิสระจากกัน ดังนั้นระบบ Conflict Check จะต้องอ้างอิงจาก **"รหัสประจำตัวอาจารย์ (Global Instructor ID)"** ไปตรวจสอบการซ้อนทับข้ามทุกรายวิชาในคณะทั้งหมด
3. **Activity Assignment (M3):** เมื่อสร้างกิจกรรม หัวหน้าวิชาจะระบุผู้สอนโดยดึงจาก Instructor Pool ซึ่งรองรับ **Team Supervision (เลือกอาจารย์ผู้สอนได้หลายท่านต่อ 1 กิจกรรม)** 
4. **Workload Validation (M6):** ก่อนส่งอนุมัติ หัวหน้าวิชาต้องตรวจสอบความสมดุลของ "ภาระงานอาจารย์ (Workload)" โดยระบบคำนวณจาก **(จำนวนสัปดาห์/ปี) x (ชม. ทำงาน/สัปดาห์)** เพื่อหาชั่วโมงปฏิบัติงานรวมต่อปี (Quota)
5. - **Instructor Profile Data**: เก็บข้อมูลส่วนตัวเพิ่มเติม ได้แก่ **คำนำหน้าชื่อ (Prefix)**, **รหัสพนักงาน (Employee ID)**, ตำแหน่งทางวิชาการ, ประเภทการจ้างงาน, และสัดส่วนการปฏิบัติงาน (%) เพื่อใช้ในเกณฑ์ PA
6. - **Name Display Logic**: ระบบจัดการการแสดงผลชื่ออย่างชาญฉลาด โดยนำตำแหน่งทางวิชาการ, วุฒิการศึกษา (ดร.), และคำนำหน้าชื่อมาผสมกันอย่างถูกต้อง (เช่น **อ.ดร.ราชันย์**, **ผศ. สมศรี**, **ดร.สมบัติ**, **นายมานะ**) และกำจัดคำนำหน้าซ้ำซ้อนอัตโนมัติ

---

## เกณฑ์ภาระงาน (Performance Agreement - PA) ปี 2569

ระบบคำนวณสัดส่วนภาระงาน (%) อัตโนมัติตามตำแหน่งและวุฒิการศึกษา ดังนี้:

### 1. กลุ่มคณาจารย์ (อ., ผศ., รศ., ศ.)
- **การสอน**: 20-70%
- **การวิจัย**: 20-70%
- **บริการวิชาการ**: 5-20%
- **ศิลปวัฒนธรรม**: 5-15%
- **งานมอบหมายอื่น**: 0-20%

### 2. กลุ่มผู้ช่วยอาจารย์ (4 ประเภท)
| ประเภท | การสอน | วิจัย | บริการ | ศิลปะฯ | มอบหมาย |
|-------|-------|-------|-------|-------|-------|
| **ปกติ (ป.โท/เอก)** | ≤ 70% | 15-20% | 5-20% | 5-20% | 0-20% |
| **วุฒิ ป.ตรี** | 30-60% | 0% | 10-30% | 10-20% | 0-30% |
| **คลินิก** | ≤ 10% | 0-5% | 70-80% | 0-5% | 0-10% |
| **สอนภาคปฏิบัติ** | ≤ 70% | 0% | 5-20% | 5-20% | 0-20% |

### 3. โลจิกพิเศษ (หมายเหตุท้ายประกาศ)
- **หมายเหตุ 1**: ผู้ช่วยอาจารย์ที่บรรจุ **ก่อน 1 ต.ค. 2559** และจบ **ป.เอก** → ให้ใช้เกณฑ์เดียวกับ **"อาจารย์"**
- **หมายเหตุ 2**: ผู้ช่วยอาจารย์ที่บรรจุ **ตั้งแต่ 1 ต.ค. 2559** และจบ **ป.เอก** (แต่ภาษาอังกฤษไม่ผ่าน) → ให้ใช้เกณฑ์ **"ผู้ช่วยอาจารย์ปกติ"**

---

## สถาปัตยกรรมหลักสูตรและการเปิดรายวิชา (Curriculum & Course Offering Architecture)

เพื่อให้ระบบทำงานได้อย่างอัตโนมัติและลดภาระผู้ใช้งาน (Automation over Manual):

1. **Curriculum as Master Plan (หลักสูตรคือแม่แบบ)**: 
   - ข้อมูลหลักสูตรทำหน้าที่เป็น "แผนผังแผนการเรียน" (Master Template) 
   - ในแต่ละ **รายวิชา (Course)** จะมีการระบุ **ชั้นปีเริ่มต้น (`default_year_level`)** และ **ภาคเรียนเริ่มต้น (`default_semester`)** ไว้ล่วงหน้าตามแผนการเรียนของหลักสูตรนั้นๆ
2. **Dynamic Course Offering & Verification (การเปิดวิชาและตรวจสอบจริง)**:
   - ระบบใช้ตาราง **`course_offerings`** เป็นตัวกลางระหว่างแม่แบบ (Courses) และตารางสอนจริง (Schedules)
   - เมื่อขึ้นเทอมใหม่ ระบบจะสร้าง **"ร่างรายการเปิดวิชา" (Draft Offerings)** ตามแผนการเรียน
   - **Manual Override**: เจ้าหน้าที่ (Staff) สามารถเลือก **"ยืนยันเปิด" (Confirm)**, **"ข้ามการเปิด" (Skip)** ในกรณีที่วิชานั้นไม่เปิดสอนในปีนั้น หรือ **"เปิดวิชาพิเศษ"** ที่ไม่อยู่ในแผนได้
3. **Head of Course Dashboard (การแจ้งเตือนงานค้าง)**:
   - เฉพาะวิชาที่มีสถานะเป็น "เปิดสอน" ใน `course_offerings` ของเทอมปัจจุบันเท่านั้น ที่จะไปปรากฏใน Dashboard ของหัวหน้าวิชา
   - หัวหน้าวิชาจะเห็นภารกิจจัดตารางสอนเฉพาะวิชาที่สตาฟกดยืนยันเปิดแล้วเท่านั้น
4. **Consistency & Conflict Prevention**:
   - ระบบตรวจสอบการชนกันของตารางเรียน (Student Conflict) โดยอ้างอิงจากรายชื่อวิชาที่อยู่ใน `course_offerings` ภายใต้หลักสูตรและชั้นปีเดียวกัน

---

## Workflow หลักของระบบ

```
[Setup Data] → [Create Course] → [Manual Input Schedule] → [Smart Check]
→ [Fix Errors] → [Validate Overall (ตารางครบ & ภาระงานสมดุล)] → [Approve] → [Publish]
→ [Operate & Adjust] → [Report]
```

- **Conflict (Error)** → บันทึกไม่ได้ / แจ้งเตือนสีแดง
- **Warning** → บันทึกได้ แต่ต้องแก้ไข

---

## สิทธิ์ผู้ใช้งาน (RBAC) — 5 Roles

> ระบบมี **5 บทบาท** เท่านั้น (ไม่มี Role นักศึกษา)

| # | Role | สิทธิ์หลัก | ลักษณะการเข้าถึง |
|---|------|-----------|-----------------|
| 1 | System Admin (ผู้ดูแลระบบ) | จัดการ Master Data ทั้งหมด, จัดการสิทธิ์ผู้ใช้งาน, Admin Override แก้ไขตารางแทน Course Head ได้ | Read + Write ทุกส่วน |
| 2 | Support Staff (เจ้าหน้าที่) | กรอกข้อมูลพื้นฐาน (Master Data), **ร่วมกับ Course Head** บันทึกตารางสอน/ฝึกปฏิบัติ, ออกรายงาน | Read + Write (ตาราง + Master Data) |
| 3 | Course Head / Maker (หัวหน้าวิชา) | **ร่วมกับ Support Staff** สร้าง/แก้ไขตาราง, ตรวจสอบ Conflict/Warning, **ส่งขออนุมัติให้ผู้บริหาร** | Read + Write (ตาราง) |
| 4 | Executive / Approver (ผู้บริหาร) | **ดูตารางทั้งหมด** (View All), ดูรายงานภาระงาน/การใช้ห้อง, **อนุมัติ / ตีกลับตาราง** ที่ Course Head ส่งมา — ไม่สามารถสร้าง/แก้ไขตาราง/ข้อมูลพื้นฐานได้ | Read-only + Approve/Reject เท่านั้น |
| 5 | Instructor (อาจารย์ผู้สอน) | ดูตารางสอนและภาระงานของตนเองเท่านั้น, รับการแจ้งเตือนเมื่อตารางเปลี่ยน | Read-only (เฉพาะของตัวเอง) |

### ความสัมพันธ์ระหว่าง Role ที่สำคัญ
- **Support Staff ↔ Course Head**: ทำงานร่วมกันในการบันทึกและจัดตารางสอน/ฝึกปฏิบัติ
- **Course Head → Executive**: Course Head ส่งตารางขออนุมัติ → Executive พิจารณา Approve หรือ Reject (ส่งกลับให้แก้ไข)
- **Executive vs Instructor**: ทั้งสองเป็น "viewer" แต่ Executive เห็นตารางทั้งหมดและมีสิทธิ์ Approve/Reject ส่วน Instructor เห็นเฉพาะตารางตนเอง

---

## Definition of Done

- Code สมบูรณ์และผ่าน unit test
- ผ่าน code review
- ทดสอบ UI บน Chrome / Edge
- ไม่มี conflict ที่ยังไม่ได้แก้ไข
- บันทึกผลใน System Test Checklist
- เอกสาร (SRS / User Manual) อัปเดตแล้ว (ถ้าเกี่ยวข้อง)

---

## Tech Stack (ตัดสินใจแล้ว — 6 พ.ค. 2569)

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend | **Laravel** (PHP) | Laravel 13, PHP 8.3 |
| Frontend | **Blade** templates + **Alpine.js** | Alpine.js v3.x (CDN) |
| Database | **MySQL** | 8.0+ |
| CSS | Impeccable Design Framework + `ui/ui_kits/tpss/styles.css` | — |
| UI Reference | `mock/*.html` และ `ui/design_template/ui_kits/tpss/*.jsx` — ดูเป็น spec เท่านั้น ไม่ใช้ React ใน production | — |

### แนวทางการพัฒนา UI ด้วย Blade + Alpine.js
- **CSS**: คัดลอก CSS variables จาก `colors_and_type.css` มาใส่ใน `resources/css/app.css`
- **Alpine.js**: โหลดผ่าน CDN ใน layout หลัก ไม่ต้องใช้ Vite/npm build
- **Component**: ใช้ `x-` Blade components แทน React components
- **Interactivity**: ใช้ `x-data`, `x-show`, `x-on:click` ของ Alpine.js แทน React state/hooks
- **ตัวอย่าง**: sidebar toggle, modal เพิ่มกิจกรรม, dropdown ค้นหาอาจารย์ → ทำด้วย Alpine.js ได้หมด
- **DB Schema**: อ้างอิงจาก Laravel Migrations ใน `database/migrations/` (ครอบคลุม Phase 1 & 2 แล้ว)

---

## Naming Conventions (ใช้สม่ำเสมอทั้งโปรเจกต์)

| ประเภท | รูปแบบ | ตัวอย่าง |
|--------|--------|---------|
| Model | PascalCase | `CourseOffering`, `StudentGroup` |
| Controller | PascalCase + Controller | `ScheduleController`, `CourseOfferingController` |
| Route name | kebab-case + dot notation | `course-offerings.index`, `schedules.store` |
| View path | snake_case จัดตาม role | `maker/schedule/index.blade.php` |
| DB table | snake_case plural | `course_offerings`, `student_groups` |
| DB column | snake_case | `approval_status`, `created_at` |
| Alpine.js var | camelCase | `showModal`, `selectedInstructor` |
| CSS class | kebab-case | `sb-nav`, `card-header` |

> **ภาษาในโค้ด**: ชื่อตัวแปร/function/class ทั้งหมดเป็น **ภาษาอังกฤษ** — comment และ UI text เป็นภาษาไทย

---

## Glossary — ไทย ↔ Code

| คำไทย | ชื่อใน Code / DB |
|-------|----------------|
| หัวหน้าวิชา / Maker | `course_head` |
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
| ประเภทสถานที่ | `location_type` |
| ประวัติการอนุมัติ | `course_offering_approval` |
| ความขัดแย้งที่ตรวจพบ (cache) | `schedule_conflict` |
| บทบาทหลัก (เมื่อ login) | `is_primary` (ใน `user_roles`) |
| role ที่ใช้งานอยู่ใน session | `active_role` (session key) |

---

## Database Enum Values (อ้างอิงจาก `migration/database_v1.sql`)

```php
// user_roles.role  (pivot table — 1 user มีได้หลาย role, ดู database_v1.sql)
// users ไม่มี role column แล้ว — RBAC middleware ต้อง query จาก user_roles
// is_primary = true → role เริ่มต้นเมื่อ login, role switcher ใน sidebar เปลี่ยน active_role ใน session
['admin', 'staff', 'course_head', 'executive', 'instructor']

// schedules.status  (ตาราง slot รายกิจกรรม)
['draft', 'pending_approval', 'approved', 'revised']

// course_offerings.approval_status  (ระดับรายวิชาต่อปี)
['draft', 'pending', 'published', 'rejected']

// course_offerings.course_type
['theory', 'practicum', 'theory_practicum']

// course_offering_instructors.role_in_course
['coordinator', 'secretary', 'instructor', 'group_advisor', 'preceptor']

// rooms.status
['active', 'inactive', 'maintenance']

// notifications.type
['conflict', 'warning_quota_exceeded', 'warning_missing_info',
 'warning_capacity', 'warning_no_schedule', 'approval_update']

// course_offering_approvals.action  (Phase 2 — M11)
['submit', 'approve', 'reject', 'revise']

// schedule_conflicts.severity  (Phase 2 — M4/M5)
['conflict', 'warning']

// schedule_conflicts.conflict_type  (บล็อกบันทึก)
['instructor_overlap', 'room_overlap', 'group_overlap']

// schedule_conflicts.warning_type  (บันทึกได้แต่ต้องแก้)
['quota_exceeded', 'capacity_exceeded', 'missing_info', 'no_schedule', 'outside_availability']
```

---

## Schedule Status — State Machine

```
[Course Head / Staff สร้างกิจกรรม]
        │
        ▼
    ┌─────────┐
    │  draft  │ ◄─────────────────────────────┐
    └────┬────┘                               │
         │ ส่งขออนุมัติ (Course Head)          │ แก้ไขแล้วส่งใหม่
         ▼                                    │
┌──────────────────┐                  ┌───────┴──────┐
│ pending_approval │                  │   revised    │
│   (pending)      │──── Reject ─────►│  (rejected)  │
└────────┬─────────┘   + comment      └──────────────┘
         │ Approve (Executive)
         ▼
┌──────────────────┐
│    approved /    │  ← ตารางถูก Publish ให้ทุกคนเห็น
│    published     │
└──────────────────┘
```

> `schedules.status` = สถานะระดับ **slot** (แต่ละกิจกรรม)
> `course_offerings.approval_status` = สถานะระดับ **รายวิชาต่อปี** (ส่งอนุมัติทั้งวิชา)

---

## การจัดการวันที่และปีการศึกษา

- **Database**: เก็บเป็น **ค.ศ. (Gregorian)** เสมอ — `2026`, `2026-05-11`
- **UI**: แสดงเป็น **พ.ศ.** (+543) — `2569`, `11 พ.ค. 2569`
- **Helper**: สร้าง helper function `toBE(int $year): int` → `$year + 543`
- **Academic Year**: ปีการศึกษา 2568 = ปีการศึกษา BE ที่เริ่ม ส.ค. 2025 ถึง พ.ค. 2026

---

## HR / Instructor Data Integration

- **Phase 1 (โปรเจกต์นี้)**: กรอกข้อมูลอาจารย์ **manual** ในระบบผ่าน M1 (Master Data Management) — Admin/Staff เป็นผู้กรอก
- **Phase 2 (Future)**: วางแผน sync กับระบบ FIMS / HR ของมหาวิทยาลัย — ยังไม่ implement
- ห้าม hardcode ข้อมูลอาจารย์ — ดึงจาก `users` + `instructor_profiles` เสมอ
- **Instructor Employee ID**: เก็บใน `instructor_profiles.employee_id` เพื่อใช้ในการระบุตัวตนและตรวจสอบ Conflict ในอนาคต (Sprint 4)
- **Active Tab Persistence**: หน้า System Settings ใช้พารามิเตอร์ `tab=pa` หรือ `tab=academic` ใน URL เพื่อจำสถานะหน้าจอหลังบันทึกข้อมูล
- **Mathematical Symbol Helper**: ในหน้าตั้งค่าเกณฑ์ PA มีปุ่มช่วยคัดลอกสัญลักษณ์ `≤`, `≥`, `-`, `%` เพื่อความสะดวกในการบันทึกข้อมูลตามประกาศคณะฯ

---

## Git Branching Strategy

```
main          ← production-ready เท่านั้น
  └── develop ← integration branch รวม sprint ที่เสร็จแล้ว
        ├── sprint/1-m10-login
        ├── sprint/2-m1-master-data
        ├── sprint/3-m2-course-management
        └── ...
```

- Merge `sprint/*` → `develop` เมื่อ DoD ผ่านครบ
- Merge `develop` → `main` เมื่อผ่าน integration test

---

## หมายเหตุสำหรับ Claude

- อ้างอิง Module ID (M1, M2, M3...) และ User Story ID (M3-01, M4-02...) เมื่อพูดถึงฟีเจอร์
- ตรวจสอบ Product Backlog **v2.1** (`TPSS_Product_Backlog.pdf`) ก่อนเสนอการออกแบบหรือ implementation ใดๆ
- โปรเจกต์ใช้มาตรฐาน ISO/IEC 29110 — ต้องมี traceability และ audit trail

### Impeccable Design Context
โปรเจกต์นี้ใช้ระบบ **Impeccable Design** ในการควบคุมคุณภาพ Frontend:
- **Source of Truth**: `mock/production/ui/ui_kits/tpss/` (Styles + Primitives)
- **Design Docs**: `PRODUCT.md` (Brand/Users) และ `DESIGN.md` (Tokens/Rules) ใน Root
- **Metaphor**: "The Mahidol Navy Data Shell"
- **Style**: Professional Institutional — ขอบคม (2px), เส้นบาง (Hairline), ไม่มี Shadow (Flat), เน้น Data Density
- **UI Architecture**: Blade + Alpine.js (Production Mode)

> **คำสั่งสำหรับ Claude**: เมื่อเริ่มงาน UI ให้รัน `node .agents/skills/impeccable/scripts/load-context.mjs` และอ้างอิงคลาสจาก `ui/ui_kits/tpss/styles.css` เสมอ

- ระบบเป็น web-based (ไม่ต้องติดตั้งโปรแกรม) รองรับ PC, tablet, mobile เบื้องต้น
- Export รายงานเป็น PDF และ Excel
- **ผู้บริหาร = Read-only + Approve/Reject เท่านั้น** — ห้าม implement UI ให้แก้ไขตารางหรือ Master Data ได้
- **Stack = Laravel + Blade + Alpine.js + MySQL** — ห้ามเสนอโค้ดที่ใช้ React, Vue, หรือ Inertia.js
- **Multi-role RBAC**: `users` ไม่มี `role` column — query จาก `user_roles` เสมอ, RBAC middleware เช็ค `active_role` จาก session, `is_primary = true` คือ role เริ่มต้นเมื่อ login ครั้งแรก
- **Role Switcher**: พัฒนาเสร็จสมบูรณ์ใน Sidebar (Dropdown ▾) รองรับการสลับ active_role ใน session และ redirect ไปยัง dashboard ที่ถูกต้องทันที
- **User Management**: ระบบจัดการผู้ใช้งาน (Admin) รองรับการกำหนดหลายบทบาท (Multi-role), เลือกบทบาทหลัก (Primary), และเปิด-ปิดสถานะการใช้งาน (Active/Inactive) พร้อมระบบจัดการ **คำนำหน้าชื่อ (Prefix)** และ **วุฒิการศึกษา (Doctorate mapping)** ที่แสดงผลได้ถูกต้องตามระเบียบมหาวิทยาลัย มียูไอแบบ Role Cards และ Badge-primary (Dark Navy) สำหรับบทบาทหลัก

---

## สถานะ UI Prototype และ Production Mock (DS-02) — อัปเดต 8 พ.ค. 2569

### โครงสร้าง mock/ ปัจจุบัน

```
mock/
├── prototype/          ← HTML prototype (ใช้เป็น UI spec — แก้ไขได้เพื่ออัปเดต spec)
│   ├── login.html
│   ├── maker.html
│   ├── approver.html
│   ├── staff.html
│   ├── lecturer.html
│   └── admin.html
├── production/         ← ไฟล์ที่ปั้นสำหรับ production จริง
│   ├── login.html      ← production login (ใช้งานจริง)
│   ├── picture/
│   │   └── Mahidol_U_logo.png
│   └── ui/             ← Design System
│       ├── colors_and_type.css
│       ├── assets/     (logo-mark.svg, logo-lockup.svg)
│       ├── fonts/
│       ├── preview/    (comp-*.html, colors-*.html — เปิดใน browser ได้ทันที)
│       └── ui_kits/tpss/ (styles.css, App.jsx, Sidebar.jsx ฯลฯ — ดูเป็น spec)
```

### Prototype (`mock/prototype/`) — ครบ 6 Role

| ไฟล์ | Role | ฟีเจอร์หลัก |
|------|------|-------------|
| `login.html` | ทุก Role | Role selection → login form → redirect ตาม role |
| `lecturer.html` | อาจารย์ผู้สอน | ตารางสอน (list+grid toggle), ภาระงาน, วิชาที่รับผิดชอบ, notification strip, teaching_quota progress bar, role_in_course badge, **role switcher** (sidebar) |
| `maker.html` | หัวหน้าวิชา | ภาพรวม+conflict+warning (แยกสี), ตารางสอน, เพิ่มกิจกรรม (capacity_required), ส่งอนุมัติ, instructor pool พร้อม role_in_course, **role switcher** (sidebar) |
| `approver.html` | ผู้บริหาร | รออนุมัติ, ตารางทั้งหมด (filter ปี/ภาค), approve/reject, รายงานภาระงาน/ห้อง (utilization%) |
| `staff.html` | เจ้าหน้าที่ | ข้อมูลหลัก CRUD (instructors, rooms, student_groups, academic_years, curriculums, activity_types, **location_types, course_offerings, practicum_series, instructor_availability**), ตารางสอน, รายงาน |
| `admin.html` | ผู้ดูแลระบบ | Dashboard ระบบ, User & Role Management (**multi-role checkbox picker**), ตั้งค่าระบบ, Audit Logs, override |

> **Role Switcher**: user ที่มีหลาย role จะเห็น dropdown ▾ ใต้ชื่อตนเองใน sidebar — กดเพื่อสลับ context ระหว่าง role ได้ทันที (prototype: maker ↔ lecturer)

### Production (`mock/production/`) — กำลังพัฒนา

| ไฟล์ | สถานะ | หมายเหตุ |
|------|-------|---------|
| `login.html` | ✅ เสร็จ | ใช้ Mahidol logo จริง, clean production UI |
| `maker.html` | 🔲 ยังไม่เริ่ม | — |
| `approver.html` | 🔲 ยังไม่เริ่ม | — |
| `staff.html` | 🔲 ยังไม่เริ่ม | — |
| `lecturer.html` | 🔲 ยังไม่เริ่ม | — |
| `admin.html` | ✅ เสร็จ | Implemented as Blade view with RBAC and multi-role picker |

### Design System (`mock/production/ui/`)

| ไฟล์/โฟลเดอร์ | รายละเอียด |
|--------------|-----------|
| `colors_and_type.css` | CSS variables สีและ typography ทั้งระบบ — copy ไป `resources/css/app.css` |
| `assets/` | Logo TPSS (logo-mark.svg, logo-lockup.svg) |
| `preview/comp-*.html` | Component spec — เปิดใน browser ก่อน implement |
| `ui_kits/tpss/styles.css` | Component classes (sidebar, topbar, btn, pill, tag) |
| `ui_kits/tpss/*.jsx` | React UI Kit — ดูเป็น spec เท่านั้น ไม่ใช้ใน production |

### Database (`migration/`)

| ไฟล์ | รายละเอียด |
|------|-----------|
| mock/er_v1.jpg | ER Diagram — อัปเดตล่าสุดให้ตรงกับ schema ปัจจุบัน (8 พ.ค. 2569) |
| database/migrations/ | Laravel Migrations — ครอบคลุม Phase 1 และ Phase 2 ทั้งหมด (25 ไฟล์) |

**Phase 2 tables (เตรียมไว้ล่วงหน้า ท้ายไฟล์):**
- `course_offering_approvals` — approval history ทุก action (M11)
- `schedule_conflicts` — cache conflict/warning ที่ตรวจพบ (M4/M5)
- Additional indexes on `schedules`, `course_offerings`, `schedule_instructors` (M6/M9)

> **หมายเหตุ**: Laravel Migrations ครอบคลุม Phase 1 และ Phase 2 ครบแล้ว (25 ไฟล์) — ใช้เป็น reference หลักในการพัฒนา, `mock/er_v1.jpg` อัปเดตตรงกับ schema ปัจจุบันแล้ว

### สิ่งที่ต้องคำนึงในการออกแบบ Approver UI
จากการอ่าน Backlog v2.1 (M11, M8-03b) และ CLAUDE.md:

1. **M11-02**: Executive ต้อง "view submitted schedule + supporting data" ก่อนตัดสินใจ
   → ต้องมีหน้า review แบบ read-only ที่ดูได้ครบ (กิจกรรม, กลุ่ม, conflict ที่ยังค้างอยู่)
2. **M11-03**: Approve → status เปลี่ยนเป็น "Published"
3. **M11-04**: Reject → ต้องมีช่องใส่ comment (rejection reason) → ส่งกลับ Course Head
4. **M8-03b**: View All schedules ทุกรายวิชา (ไม่ใช่แค่ที่ pending)
5. **ห้ามมีปุ่ม edit ใดๆ** — Executive = read-only + approve/reject เท่านั้น
6. ควรแสดง **workload summary** และ **room utilization** (M6, M9) เพื่อประกอบการพิจารณา

---

## บันทึกความสอดคล้องเอกสาร (Last checked: 8 พ.ค. 2569)

> เอกสารทุกชิ้นสอดคล้องกันครบแล้ว ณ วันที่ 8 พ.ค. 2569

| รายการ | เอกสารที่ตรวจ | สถานะ |
|--------|--------------|-------|
| จำนวน Role (5 roles, ไม่มีนักศึกษา) | Flowchart v4, Backlog v2.1, CLAUDE.md | ✅ ตรงกัน |
| Story Points รวม (280 SP = 193+87, M1=43 SP) | Backlog v2.2 (Amendment), CLAUDE.md | ✅ อัปเดตแล้ว (7 พ.ค. 2569) |
| Sprint structure (7 Sprints + 5 Sub-Sprints) | Backlog v2.1, Schedule CSV, WP-02 Section 10, CLAUDE.md | ✅ ตรงกัน |
| Sprint dates (M2=18-19, M4=21-26, M7=20-27 พ.ค.) | Schedule CSV, WP-02 Section 10, Backlog v2.1, CLAUDE.md | ✅ ตรงกัน |
| WP-02 Section 9 Phase 2 label | WP-02 | ✅ อัปเดตแล้ว |
| Executive = view-all + approve/reject เท่านั้น | Flowchart v4, Backlog v2.1, CLAUDE.md | ✅ ตรงกัน |
| Course Head ส่งขออนุมัติ → Executive พิจารณา | Flowchart v4 (Swimlane ระยะที่ 7), Backlog v2.1 (M11) | ✅ ตรงกัน |
| Conflict = บล็อกบันทึก, Warning = บันทึกได้ | Flowchart v4 (Swimlane ระยะที่ 4), Backlog v2.1 (M4) | ✅ ตรงกัน |
| Product Backlog PDF ฉบับล่าสุด | TPSS_Product_Backlog.pdf | ✅ อัปเดตแล้ว (6 พ.ค. 2569) |
| ชื่อไฟล์ flowchart (overview.pdf / roles.pdf) | filesystem | ✅ อัปเดตแล้ว (6 พ.ค. 2569) |
| ชื่อไฟล์ Project Schedule (CSV ไม่ใช่ xlsx) | filesystem | ✅ อัปเดตแล้ว (6 พ.ค. 2569) |
| prototype ครบ 6 ไฟล์ใน mock/prototype/ | mock/prototype/ directory | ✅ อัปเดตแล้ว (7 พ.ค. 2569) |
| production/login.html เสร็จแล้ว | mock/production/ directory | ✅ อัปเดตแล้ว (7 พ.ค. 2569) |
| Design System ย้ายไป mock/production/ui/ | mock/production/ui/ directory | ✅ อัปเดตแล้ว (7 พ.ค. 2569) |
| Phase 3 Design เสร็จแล้ว | mock/prototype/ + mock/production/ui/ | ✅ อัปเดตแล้ว (7 พ.ค. 2569) |
| WP-03 SRS เพิ่มในรายการบังคับอ่าน | Doc/เอกสาร ISO/ | ✅ อัปเดตแล้ว (6 พ.ค. 2569) |
| M1-14 Curriculum Management (user story ใหม่, 2 SP) | Backlog Amendment v2.2 | ✅ อัปเดตแล้ว (7 พ.ค. 2569) |
| Prototype consistency fixes (rooms status, groups schema, maker form, lecturer stats) | mock/prototype/ ทุกไฟล์ | ✅ อัปเดตแล้ว (7 พ.ค. 2569) |
| Prototype feature additions (warning section, capacity_required, role_in_course pool, notification strip, teaching_quota bar, activity_types/course_offerings/practicum_series tabs, multi-role picker) | mock/prototype/ ทุกไฟล์ | ✅ อัปเดตแล้ว (8 พ.ค. 2569) |
| Role switcher UX (sidebar dropdown, maker ↔ lecturer) | mock/prototype/maker.html, lecturer.html | ✅ อัปเดตแล้ว (8 พ.ค. 2569) |
| Multi-role design decision (users.role enum → pending pivot table / JSON — ตัดสินใจ Sprint 1) | CLAUDE.md Database Enum section | ✅ อัปเดตแล้ว (8 พ.ค. 2569) |
| Schema: user_roles pivot table, username column, capacity_required, departments head/secretary FK | database/migrations/ | ✅ อัปเดตแล้ว (8 พ.ค. 2569) |
| staff.html เพิ่ม location_types tab + instructor_availability tab (สำคัญ: M4 conflict checking ต้องการข้อมูลนี้) | mock/prototype/staff.html | ✅ อัปเดตแล้ว (8 พ.ค. 2569) |
| Phase 2 schema เตรียมล่วงหน้า (course_offering_approvals, schedule_conflicts, indexes) | database/migrations/ | ✅ อัปเดตแล้ว (8 พ.ค. 2569) |
| ER_v1.jpg อัปเดตให้ตรงกับ schema ปัจจุบัน (รวม user_roles, Phase 2 tables) | mock/er_v1.jpg | ✅ อัปเดตแล้ว (8 พ.ค. 2569) |
| Enum values Phase 2 (approval action, conflict severity/type, warning type) | CLAUDE.md Database Enum section | ✅ อัปเดตแล้ว (8 พ.ค. 2569) |
| Glossary เพิ่ม instructor_availability, location_type, course_offering_approval, schedule_conflict, active_role | CLAUDE.md Glossary | ✅ อัปเดตแล้ว (8 พ.ค. 2569) |
| production/staff.html เสร็จแล้ว (Impeccable design, 6 sections, 11 master-data tabs, schedule grid, reports, inbox, 4 dialogs) | mock/production/staff.html | ✅ อัปเดตแล้ว (8 พ.ค. 2569) |

---

## Design Context: Impeccable Design Frontend (อัปเดต 8 พ.ค. 2569)

> **IMPORTANT**: โปรเจกต์นี้ใช้ระบบ **Impeccable Design** ในการควบคุมคุณภาพ Frontend
> อ้างอิงบริบทเต็มจาก `PRODUCT.md` และ `DESIGN.md` ใน Root ทุกครั้งที่เริ่ม Session ใหม่

### Source of Truth
- **UI Kit**: `mock/production/ui/ui_kits/tpss/` (Styles + Primitives)
- **Design Tokens**: `mock/production/ui/colors_and_type.css`
- **Metaphor**: "The Mahidol Navy Data Shell"

### Users & Purpose
บุคลากรคณะพยาบาลศาสตร์ 5 role ใช้งานบน PC/tablet ระหว่างทำงานจริง
- งานหลัก: จัดและตรวจสอบ block schedule ที่ซับซ้อน (300+ นักศึกษา, หลายกลุ่ม, หลายสถานที่)
- อารมณ์ที่ต้องการ: **ความมั่นใจ · ความชัดเจน · ประสิทธิภาพ** (ผู้ใช้เปิดนาน → ลด eye strain)

### Brand Personality
**มืออาชีพ · เชื่อถือได้ · เป็นทางการแต่ไม่เย็นชา**
- ใช้ตำแหน่งวิชาการเต็ม (รศ.ดร., อ.) ในข้อมูล
- ปุ่มเป็น imperative verbs ภาษาไทย (`บันทึกตาราง`, `ส่งขออนุมัติ`, `อนุมัติ`, `ตีกลับ`)
- ไม่ใช้ emoji ใน chrome/button/status — ใช้ได้แค่ใน approver queue list

### Aesthetic Direction
**Professional Institutional — Minimal Data Shell**
- Light mode เท่านั้น, sidebar ซ้ายสีเข้ม (navy) เป็น chrome หลัก
- สีอิ่มตัวสูง = semantic status เท่านั้น (แดง/เหลือง/เขียว) — ห้ามใช้ decorative
- ไม่มี gradient, pattern, หรือ decorative image ใดๆ
- Corner: 2–4 px สำหรับ button/input, 8–10 px สำหรับ card
- ไอคอน: Feather/Lucide outlined SVG, stroke 1.75–2 px, `currentColor`
- Anti-reference: ไม่ให้ดูเหมือน consumer app (Notion, Canva) หรือ patient-facing hospital app

### Typography (การใช้งานฟอนต์)
- **Kanit (`--font-display`)**: ใช้สำหรับ Header, Title, และส่วนหัวข้อหลักที่ต้องการความโดดเด่น
- **IBM Plex Sans Thai (`--font-sans`)**: ใช้สำหรับ Detail, เนื้อหาทั่วไป (Body text), เมนู, และข้อความ UI ที่ต้องการความอ่านง่ายสบายตา
- **TH Sarabun New (`--font-print`)**: ใช้สำหรับ Report, เอกสารส่งออก (PDF, Excel), และแบบฟอร์มเอกสารราชการ

### Design Principles
1. **ข้อมูลมาก่อนประดับตา** — ทุก px รับใช้การอ่านข้อมูล ไม่ใช่ความสวยงาม
2. **สีเป็นสัญญาณ ไม่ใช่ตกแต่ง** — แดง/เหลือง/เขียวมีความหมาย ห้ามใช้เป็น decoration
3. **Formal แต่ไม่เย็นชา** — ใช้ตำแหน่งวิชาการเต็ม, ปุ่มเป็น imperative verbs
4. **Precision over delight** — ไม่มี animation หรูหรา, press ไม่ scale, focus ring เสมอ
5. **Thai-first** — label/button/error เป็นภาษาไทย; English เฉพาะ technical term

### Key Assets
- **Design Framework**: Impeccable Design skill (PRODUCT.md / DESIGN.md)
- **Primary CSS**: `mock/production/ui/ui_kits/tpss/styles.css`
- **CSS Tokens**: `mock/production/ui/colors_and_type.css`
- **Component Previews**: `mock/production/ui/preview/`
- **Production Mockups**: `mock/production/`
- **Mahidol Logo**: `mock/production/picture/Mahidol_U_logo.png`
- Full spec: `.impeccable.md`
