# TPSS Medium Demo Seeder

Seeder นี้ใช้สำหรับ Demo/Test ภาพรวมระบบ Phase 1 หลังรวมแผน UX Refactor + Bug Fix + Important Features แล้ว

## วิธีรัน

```bash
php artisan db:seed --class=TpssMediumDemoSeeder
```

Seeder นี้ไม่ถูกเรียกจาก `DatabaseSeeder` โดยอัตโนมัติ เพื่อไม่ให้ seed หลักบวมเกินจำเป็น

## ข้อมูลที่สร้าง

- ปีการศึกษา `2569` เป็นปี active และอยู่ phase `scheduling`
- รายวิชา demo 7 วิชา ครอบคลุมสถานะ `draft`, `pending`, `published`, `rejected`
- ตารางสอนตัวอย่างสำหรับ schedule modal, conflict, warning, copy activity และ practical block หลายวัน
- กลุ่มนักศึกษา `A1` ถึง `A12` ในแต่ละ offering เพื่อทดสอบ selector แบบ scroll
- ผู้สอน demo เพิ่ม `demo_instructor_01` ถึง `demo_instructor_10` รหัสผ่าน `password`
- อาจารย์ต่างภาค `demo_outside_01` สำหรับทดสอบ warning cross-department
- บัญชี inactive `demo_inactive_01` สำหรับทดสอบ hard block เฉพาะ user ไม่พร้อมใช้งาน
- ห้อง/สถานที่หลัก เช่น `R-301`, `R-302`, `LAB-401`, `WARD-A`, `HOSP-RAMA`, `DEMO-SMALL`
- วันหยุดสำคัญปี 2569/2570 สำหรับทดสอบ holiday warning
- PA allocation ตัวอย่างและ audit log สำหรับหน้า dashboard/settings/audit

## จุดทดสอบที่ตั้งใจเตรียมไว้

- `NSBS 212`: ตัวอย่างข้อมูลไม่ครบ, วันหยุด, ห้องชน, อาจารย์ชน, กลุ่มเกินความจุ และอาจารย์ต่างภาค
- `NSBS 213`: ตัวอย่างรายการรออนุมัติ
- `NSBS 221`: practical block ต่อเนื่องหลายวัน เพื่อยืนยัน `populate_resources = all`
- `NSBS 231`: copy activity source week มี 5 รายการ เพื่อทดสอบ list scroll เมื่อรายการเกิน 4
- `NSBS 222`: offering ถูก reject เพื่อทดสอบประวัติ/สถานะส่งกลับแก้ไข

## หมายเหตุ

- Seeder ใช้ tag `[tpss-medium-demo]` กับ schedule/audit records ที่สร้างเอง
- รันซ้ำได้ โดย schedule demo เดิมจะถูกล้างแล้วสร้างใหม่ ไม่ทำให้จำนวนรายการซ้ำ
- Seeder ไม่แก้ migration และไม่ผูกกับ production workflow
