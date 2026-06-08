# RBAC — Roles, Access Control, Role Switcher

## 5 Roles (ไม่มี Role นักศึกษา)

| Role | สิทธิ์หลัก |
|------|-----------|
| `admin` | จัดการผู้ใช้/ข้อมูลหลัก/ตั้งค่าระบบ; **ไม่มีสิทธิสร้างหรือแก้ไขตารางสอน**; ดูได้เฉพาะตารางสอนที่เผยแพร่แล้ว และอ่าน/นำออกรายงานภาระงาน |
| `staff` | CRUD rooms/location_types/courses; Read-only departments/instructors/curriculums/activity_types; จัดการ academic_year; บันทึกตารางร่วมกับ course_head |
| `course_head` | สร้าง/แก้ไขตาราง, ตรวจ conflict/warning, **ส่งขออนุมัติ** → executive |
| `executive` | Read-only ทุกรายวิชา + **Approve/Reject เท่านั้น** — ห้าม implement ปุ่ม edit · **(V4 ข้อ 3) กำหนดตำแหน่งหัวหน้าภาควิชาได้** (เฉพาะ executive/admin · instructor ทำไม่ได้ → gate) |
| `instructor` | Read-only ตาราง/ภาระงานของตัวเอง · **(V4 ข้อ 5) กรอกสัดส่วน PA ของตัวเองได้** (self-service ผ่าน `Instructor/PaController` + `pa_rounds`/`instructor_pa_allocations`) — ใช้ validation 100% + min/max เดิม |

> ⚠️ **Requirement V4 (merged `to-serve`)** เพิ่มสิทธิ์ 2 จุดในตารางข้างบน: executive แต่งตั้งหัวหน้าภาค (ข้อ 3) · instructor กรอก PA เอง (ข้อ 5). ดู `architecture.md` "Requirement V4 Update"

## Multi-Role RBAC

- `users` ไม่มี `role` column — query จาก `user_roles` pivot เสมอ
- `user_roles.is_primary = true` → role เริ่มต้นเมื่อ login ครั้งแรก
- `session('active_role')` → role ที่ใช้งานอยู่ปัจจุบัน
- RBAC middleware เช็ค `active_role` จาก session ไม่ใช่จาก DB โดยตรง

## Role Switcher (implement แล้ว — Sprint 1)

- Sidebar dropdown ▾ ใต้ชื่อ user — แสดงเฉพาะ user ที่มีหลาย role
- กดสลับ → เปลี่ยน `active_role` ใน session → redirect ไปยัง dashboard ที่ถูกต้องทันที

## Shared View Pattern (implement แล้ว — Sprint 2)

ไฟล์จริงอยู่ใน `views/shared/` — role-specific view เป็นแค่ `@include`:

```
views/shared/master_data/index.blade.php   ← รับ $isAdmin + $routePrefix
views/shared/settings/index.blade.php
views/admin/settings.blade.php             ← @include('shared.settings.index')
views/staff/settings.blade.php             ← @include('shared.settings.index')
```

Variables ที่ต้องส่งจาก Controller:

| Variable | Type | ความหมาย |
|----------|------|----------|
| `$isAdmin` | bool | ควบคุม visibility admin-only features (แท็บ PA, ปุ่มเพิ่ม/ลบใน lock tab) |
| `$routePrefix` | string | `'admin'` หรือ `'staff'` — ใช้ใน form action และ route() |

## Controller Inheritance Pattern

```php
// Staff extends Admin เสมอ — ไม่ duplicate logic
class Staff\MasterDataController extends Admin\MasterDataController { }

class Staff\SettingController extends AdminSettingController {
    public function index() { /* override: $isAdmin = false */ }
    // storeYear(), updateYear() — inherited โดยตรง
}
```

## Staff สิทธิ์ Master Data (implement แล้ว)

| Tab | Staff |
|-----|-------|
| ภาควิชา | Read-only 🔒 + accordion → อาจารย์ |
| หลักสูตร | Read-only 🔒 + accordion → รายวิชา |
| รายวิชา | CRUD ✅ |
| อาจารย์ | Read-only 🔒 |
| ห้องและสถานที่ | CRUD ✅ (รวม location_type + room เป็น tab เดียว) |
| ประเภทกิจกรรม | Read-only 🔒 |

## User Management (Admin)

- Multi-role checkbox picker, เลือก primary role, เปิด/ปิด Active
- จัดการ Prefix + Doctorate mapping แสดงชื่อตามระเบียบ
- ตัวอย่างชื่อ: **อ.ดร.ราชันย์**, **ผศ.สมศรี**, **ดร.สมบัติ**, **นายมานะ** (ไม่มีเว้นวรรค)
