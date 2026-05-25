# UI — Blade + Alpine.js + Impeccable Design

## Stack (ห้ามเสนอ React, Vue, Inertia.js)

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | Blade templates + Alpine.js v3.x (CDN) |
| CSS | Impeccable Design + `mock/production/ui/ui_kits/tpss/styles.css` |
| DB | MySQL 8.0+ |

## Alpine.js — แนวทาง

- โหลดผ่าน CDN ใน layout หลัก — ไม่ต้อง Vite/npm build
- `x-data`, `x-show`, `x-on:click` แทน React state/hooks
- ตัวแปรใช้ camelCase: `showModal`, `selectedInstructor`

**Gotcha สำคัญ**: `:style` กับ `style="display:flex"` บน element เดียวกันทำให้ flex หาย
→ ต้องแยกเป็น 2 div (outer: `@click` + `:style` background / inner: `display:flex`)

## Impeccable Design — กฎหลัก

> เมื่อเริ่มงาน UI รัน: `node .agents/skills/impeccable/scripts/load-context.mjs`
> อ้างอิงคลาสจาก `mock/production/ui/ui_kits/tpss/styles.css` เสมอ

**Metaphor**: "The Mahidol Navy Data Shell"
**Style**: Professional Institutional — ขอบคม (2–4px), Flat (ไม่มี shadow), Data Density

### สิ่งที่ห้าม
- ห้ามใช้ gradient, pattern, decorative image
- ห้ามใช้สีอิ่มตัวสูงเป็น decoration — แดง/เหลือง/เขียวสงวนไว้สำหรับ semantic status
- ห้ามใช้ emoji ใน chrome/button/status (ใช้ได้แค่ใน approver queue)
- ห้ามมี animation หรูหรา, press ไม่ scale

### Typography
- **Kanit (`--font-display`)**: Header, Title, หัวข้อหลัก
- **IBM Plex Sans Thai (`--font-sans`)**: Body, เมนู, UI text ทั่วไป
- **TH Sarabun New (`--font-print`)**: Report, PDF, เอกสารราชการ

### ไอคอน
Feather/Lucide outlined SVG — stroke 1.75–2 px, `currentColor`

### ปุ่ม
Imperative verbs ภาษาไทย: `บันทึกตาราง`, `ส่งขออนุมัติ`, `อนุมัติ`, `ตีกลับ`

## Mock Files (ใช้เป็น UI Spec)

```
mock/prototype/         ← HTML prototype ครบ 6 role (spec — แก้ได้)
mock/production/
├── login.html          ✅ เสร็จ
├── admin.html          ✅ implement เป็น Blade แล้ว
├── maker/staff/approver/lecturer.html  🔲 ยังไม่เริ่ม
└── ui/
    ├── colors_and_type.css   ← copy → resources/css/app.css
    ├── ui_kits/tpss/styles.css  ← component classes
    └── preview/comp-*.html   ← เปิดใน browser ก่อน implement
```

## Design System Files

| ไฟล์ | ใช้ทำอะไร |
|------|----------|
| `mock/production/ui/colors_and_type.css` | CSS variables สี + typography ทั้งระบบ |
| `mock/production/ui/ui_kits/tpss/styles.css` | sidebar, topbar, btn, pill, tag classes |
| `mock/production/ui/preview/comp-*.html` | Component spec — เปิดดูก่อน implement |
| `*.jsx` ใน ui_kits | React UI Kit — ดูเป็น spec เท่านั้น ไม่ใช้ใน production |
| `PRODUCT.md` / `DESIGN.md` | Brand/Users context และ Design tokens/rules |
| `.impeccable.md` | Full Impeccable Design spec |

## Shared Components

### `<x-thai-date-input>` (Schedule Suite — 24 พ.ค.)
ช่องวันที่ระบบทั้งหมดต้องใช้คอมโพเนนต์นี้ — popup ปฏิทิน พ.ศ., เก็บค่าเป็น ค.ศ. ใน hidden input. Year range สำหรับหน้า schedule คำนวณจาก `AcademicYear.start_date/end_date` (`ScheduleController::scheduleDatePickerYearRange`) — ส่งผ่าน `$scheduleDatePickerYearStart/End` ใน view data

### Schedule Calendar (M8 — 22 พ.ค.)
- Period filter: `?period=day|week|month&date=YYYY-MM-DD`
- ป้าย `นอกช่วงปีการศึกษา` เมื่อวันที่ตกนอก `start_date/end_date` ของปีการศึกษา active
- Month grid testid: `schedule-month-calendar-co`
- Conflict errors render เป็น bullet list (error bag `schedule` เป็น array)

## Accordion Drill-Down Pattern (implement แล้ว M1)

- ภาควิชา → อาจารย์ (title, employment_type, academic_degree)
- หลักสูตร → รายวิชา (course_code, name_th/en, credits, year_level, semester)
- ประเภทสถานที่ → ห้อง (+ ปุ่มแก้ไขแต่ละห้อง)
- Cascade delete: ลบ location_type → ลบห้องทั้งหมดใน type นั้น (พร้อม warning)

## Dashboard Widget Contract (สำคัญเมื่อทำงานขนาน)

- Dashboard ของแต่ละ role (`views/admin/dashboard.blade.php`, `staff/dashboard.blade.php`, `course_head/dashboard.blade.php`) มี **owner คนเดียว** (ผู้ทำ dashboard polish)
- ใครจะเพิ่ม widget ใหม่ลง dashboard → ต้องส่งเป็น **partial แยกไฟล์** ใน `views/shared/dashboard/<widget-name>.blade.php`
- Owner เป็นคนเดียวที่ `@include('shared.dashboard.xxx')` ใน dashboard blade
- **ห้ามคนอื่นแก้ dashboard.blade.php โดยตรง** — ลด merge conflict ระหว่าง dev หลายคน
- Precedent ที่มีอยู่: `shared/dashboard/instructors_workload.blade.php`, `master_data_alerts.blade.php`

## Test ID Convention (สำหรับ Playwright E2E)

- ทุก interactive element ที่ E2E จะอ้างถึง ใส่ `data-testid="<page>-<element>"`
- Format: kebab-case, ขึ้นต้นด้วยชื่อหน้า/feature
- ตัวอย่าง: `users-add-button`, `user-form-username`, `users-row` (พร้อม `data-username="..."` สำหรับหา row)
- เพิ่ม testid พร้อมตอน implement feature — อย่ารอคนเขียน test มาขอ
- รายละเอียดเพิ่ม: `.claude/rules/testing.md`
