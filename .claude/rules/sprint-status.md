# Sprint Status — ณ 15 พ.ค. 2569

## Phase Overview

| Phase | ชื่อ | สถานะ |
|-------|------|-------|
| Phase 1–3 | Initiation → Design | ✅ เสร็จ |
| Phase 4–5 | Development | 🟢 Sprint 1+2 เสร็จ, Sprint 3 กำลังเตรียม |
| Phase 5 | Testing | 🟡 Internal Testing กำลังดำเนินการ |
| Phase 6–7 | Deployment → Closure | ยังไม่เริ่ม (4–7 มิ.ย. 2569) |

## Sprint Plan — Phase 1 (193 SP)

| Sprint | วันที่ | Module | สถานะ |
|--------|--------|--------|-------|
| Sprint 1 | 11–12 พ.ค. | M10 Login/RBAC | ✅ 100% |
| Sprint 2 | 12–15 พ.ค. | M1 Master Data | ✅ 100% |
| **Sprint 3** | **18–19 พ.ค.** | **M2 Course Management** | **🔜 เริ่มถัดไป** |
| Sprint 4 | 20–22 พ.ค. | M3 Schedule Management | — |
| Sprint 5 | 21–26 พ.ค. | M4 Conflict Checking | — |
| Sprint 6 | 22–26 พ.ค. | M8 Views & Calendar | — |
| Sprint 7 | 20–27 พ.ค. | M7 Search & Filter | — |

## Sprint 2 (M1) — สิ่งที่เสร็จแล้ว

- Shared Views: `views/shared/master_data/`, `views/shared/settings/`
- Staff\MasterDataController extends Admin\MasterDataController
- Staff\SettingController — จัดการ academic_year ได้ทั้งหมด, ไม่เห็น tab PA
- Lock icon บน tab ที่ Staff ดูอย่างเดียว
- Accordion drill-down: dept→อาจารย์, curriculum→วิชา, location_type→ห้อง
- Student Groups ย้ายออกจาก M1 → ไปสร้างใน M2 ตอน confirm offering
- `requires_capacity` boolean บน `location_types` — ห้องในประเภทที่ไม่ต้องการความจุ (เช่น ชุมชน) ไม่โดนแจ้งเตือน
- Admin Dashboard + role-based dashboards (executive, course_head, instructor, staff)
- Alerts system: `AlertController` + `/admin/alerts` page + dashboard widget
  - Critical: no active year, no dept, no curriculum, no activity_type, no location_type, PA violations
  - Warning: dept ขาด head/secretary, อาจารย์ขาดข้อมูล, ห้องขาด capacity, วิชาขาด coordinator/staff
  - Sidebar badge: แดง (critical), เหลือง (warning only)
- PA criteria schema เปลี่ยนจาก string (`"20-70%"`) → `{min: int, max: int}` ต่อแต่ละด้าน
- Settings tab PA: inputs คู่ min–max ต่อ field แทน text input เดิม

## ข้อค้นพบสำคัญสำหรับ M3 (Schedule Management)

> อ้างอิง: `Doc/ตัวอย่างตารางสอน/` (ปี 1-4, เทอม 1-2)

1. **Block-based** — ตารางไม่ซ้ำรายสัปดาห์ → `schedules` ต้องเก็บ `start_date`/`end_date` ไม่ใช่ `day_of_week`
2. **วันที่เฉพาะเจาะจง** — บางกิจกรรมระบุวันที่ตรงๆ เช่น "15-19 ก.ค. 2568"
3. **Parallel Groups** — วันเดียวกัน กลุ่ม A ward, กลุ่ม B ห้องเรียน → ทุก slot ต้อง link `student_group_id`
4. **Nested Groups** — ปี 3-4 แบ่ง A→A1/A2, B→B1/B2
5. **หลายอาจารย์ต่อกิจกรรม** — `schedule_instructors` pivot รองรับได้ ✅
6. **M2 ต้องเสร็จก่อน M3** — ทุก slot ต้อง FK → `course_offering_id` + `student_group_id`

## คำถามที่รอคำตอบจากลูกค้า (pending)

1. **ผู้ประสานรายวิชา** = login ด้วย `course_head` role เดียวกับหัวหน้าวิชาหรือเปล่า?
2. **เลขานุการวิชา** — จัดการใน M1 (ระดับวิชา) หรือ M2 (ระดับปีการศึกษา)?
3. **Course Offering** — ใครกด "ยืนยันเปิด" ได้ (Staff เท่านั้น หรือ Course Head ด้วย)?
4. **Notification** — Course Head รู้ว่าวิชาตัวเองถูกยืนยันเปิดยังไง?

## Definition of Done

- Code สมบูรณ์และผ่าน unit test
- ผ่าน code review
- ทดสอบ UI บน Chrome / Edge
- ไม่มี conflict ที่ยังไม่ได้แก้ไข
- บันทึกผลใน System Test Checklist
- เอกสาร (SRS / User Manual) อัปเดตแล้ว (ถ้าเกี่ยวข้อง)

## Git Branching

```
main ← production-ready
  └── develop ← integration
        ├── sprint/1-m10-login     ✅
        ├── sprint/2-m1-master-data ✅
        └── sprint/3-m2-course-management  ← เริ่มถัดไป
```
