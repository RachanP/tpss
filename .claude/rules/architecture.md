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

## Instructor & Conflict Logic

1. **Instructor Pool**: ไม่ผูกอาจารย์ถาวรกับกลุ่มใน M2 — ใช้วิธี Add จาก HR → สร้าง Pool ต่อวิชา
2. **Cross-Course Conflict (M4)**: ตรวจโดยอ้างอิง Global Instructor ID ข้ามทุกรายวิชาในคณะ
3. **Team Supervision**: เลือกอาจารย์ผู้สอนได้หลายท่านต่อ 1 กิจกรรม (via `schedule_instructors`)
4. **Workload Quota (M6)**: คำนวณจาก (สัปดาห์/ปี) × (ชม./สัปดาห์) → ชั่วโมงรวมต่อปี
5. **Name Display**: ไม่มีเว้นวรรคระหว่างตำแหน่ง/คำนำหน้ากับชื่อ (เช่น `อ.ดร.ราชันย์`)

## Curriculum & Course Offering Architecture

```
Curriculum (Master Plan)
└── Course → default_year_level, default_semester

Course Offerings (ต่อเทอม — ตัวกลาง Master ↔ Schedule)
├── สร้าง Draft Offerings อัตโนมัติเมื่อขึ้นเทอมใหม่
└── Staff กด: Confirm / Skip / เปิดวิชาพิเศษ
```

- Dashboard Course Head แสดงเฉพาะวิชาที่ offering status = confirmed
- Inactive Curriculum → Force Update รายวิชาทั้งหมดใน curriculum → `inactive`
- Clone curriculum สำหรับ versioning (2569 → 2574) ไม่กระทบประวัติเดิม

## Performance Agreement (PA) Criteria ปี 2569

**กลุ่มคณาจารย์** (อ., ผศ., รศ., ศ.):
- สอน 20–70%, วิจัย 20–70%, บริการ 5–20%, ศิลปะฯ 5–15%, มอบหมาย 0–20%

**กลุ่มผู้ช่วยอาจารย์** (4 ประเภท):

| ประเภท | สอน | วิจัย | บริการ |
|-------|-----|-------|-------|
| ปกติ (ป.โท/เอก) | ≤70% | 15–20% | 5–20% |
| วุฒิ ป.ตรี | 30–60% | 0% | 10–30% |
| คลินิก | ≤10% | 0–5% | 70–80% |
| สอนภาคปฏิบัติ | ≤70% | 0% | 5–20% |

**โลจิกพิเศษ**:
- ผช.อาจารย์ บรรจุ **ก่อน 1 ต.ค. 2559** + จบ ป.เอก → ใช้เกณฑ์ **อาจารย์**
- ผช.อาจารย์ บรรจุ **ตั้งแต่ 1 ต.ค. 2559** + จบ ป.เอก (ภาษาอังกฤษไม่ผ่าน) → ใช้เกณฑ์ **ผช.ปกติ**

## HR / Instructor Data Integration

- Phase 1: กรอก manual ผ่าน M1 — Admin/Staff เป็นผู้กรอก
- Phase 2 (Future): sync กับ FIMS/HR ของมหาวิทยาลัย
- ห้าม hardcode ข้อมูลอาจารย์ — ดึงจาก `users` + `instructor_profiles` เสมอ
- `instructor_profiles.employee_id` = Global Instructor ID สำหรับ Conflict Check (Sprint 4)
- System Settings URL: `?tab=pa` หรือ `?tab=academic` เพื่อ Active Tab Persistence
