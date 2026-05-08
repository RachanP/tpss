# TPSS — Teaching & Practicum Scheduling System
**ระบบจัดตารางสอนและฝึกปฏิบัติพยาบาลศาสตร์ (Faculty of Nursing, Mahidol University)**

## 🚀 เกี่ยวกับโปรเจกต์
ระบบบริหารจัดการตารางเรียน (Block Schedule) และตารางฝึกปฏิบัติ (Rotation Schedule) ที่มีความซับซ้อนสูง สำหรับคณะพยาบาลศาสตร์ มหาวิทยาลัยมหิดล รองรับการตรวจสอบตารางชน (Conflict Detection) อัตโนมัติ และการบริหารภาระงานอาจารย์อย่างมีประสิทธิภาพ

## 🛠 Tech Stack
- **Backend**: Laravel 13 (PHP 8.3)
- **Frontend**: Blade Templates + Alpine.js
- **Design System**: Impeccable Design (Mahidol Navy Data Shell)
- **Database**: MySQL 8.0+

## 📦 การติดตั้ง (Setup)
1. Clone repository
2. ติดตั้ง dependencies:
   ```bash
   composer install
   npm install
   ```
3. ตั้งค่าสภาพแวดล้อม:
   - คัดลอก `.env.example` เป็น `.env`
   - ตั้งค่าฐานข้อมูล (Database) ใน `.env`
   - รัน `php artisan key:generate`
4. สร้างโครงสร้างฐานข้อมูล:
   ```bash
   php artisan migrate
   ```
5. รันเซิร์ฟเวอร์ทดสอบ:
   ```bash
   php artisan serve
   ```

## 📂 โครงสร้างที่สำคัญ
- `/mock/prototype`: ไฟล์ HTML ต้นแบบ (Prototypes) ครบทั้ง 5 บทบาท
- `/mock/production/ui`: Design System, UI Kits และ CSS หลักของโครงการ
- `/database/migrations`: ไฟล์ Migration ทั้งหมด (Phase 1 & 2) รวม 25 ไฟล์
- `CLAUDE.md`: คู่มือการพัฒนาสำหรับ AI Agent และการตั้งค่าโปรเจกต์ (สำคัญมาก)
- `PRODUCT.md` / `DESIGN.md`: เอกสารนิยามแบรนด์และคู่มือการออกแบบ (Impeccable Context)

## 🤖 สำหรับนักพัฒนา (Agentic Workflow)
โปรเจกต์นี้ได้รับการออกแบบมาให้รองรับการทำงานร่วมกับ AI Coding Agents (เช่น Claude Code, Antigravity, Cursor) โดยใช้กฎและแนวทางที่ระบุไว้ใน **`CLAUDE.md`**

**กรุณาอ่าน `CLAUDE.md` ทุกครั้งเมื่อเริ่ม Session ใหม่เพื่อรักษาความต่อเนื่องของงาน**
