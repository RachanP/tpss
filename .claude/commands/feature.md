# Implement Feature

**Arguments:** `$ARGUMENTS` — Module ID (เช่น M2) หรือ User Story ID (เช่น M2-03)

## ขั้นตอน

1. **อ่าน context** — โหลด `.claude/rules/sprint-status.md` + rules ที่เกี่ยวข้องกับ Module นี้
2. **ค้น codebase** — ดูโครงสร้างที่มีอยู่ (models, controllers, migrations, views) ที่เกี่ยวข้อง
3. **วางแผนก่อน implement** — แสดง:
   - Files ที่ต้องสร้าง/แก้ไข
   - Migrations ที่ต้องการ
   - Routes + Controller methods
   - Views (shared/ pattern ถ้าเกี่ยวกับหลาย role)
   - RBAC: แต่ละ role เห็น/ทำอะไรได้บ้าง
4. **รอยืนยัน** — ถามว่า "แผนนี้โอเคไหม? มีข้อสงสัยก่อน implement?"
5. **Implement** — ตาม rules ใน `.claude/rules/` ทุกข้อ โดยเฉพาะ:
   - Database: enum values ตาม `database.md`, เก็บวันที่เป็น ค.ศ.
   - RBAC: query role จาก `user_roles` เสมอ, ใช้ `active_role` จาก session
   - UI: Blade + Alpine.js, Impeccable Design, ห้าม gradient/emoji ใน chrome

## ข้อบังคับ
- ห้าม implement ปุ่ม edit ให้ `executive` role
- ถ้ามี view ที่ admin + staff ใช้ร่วมกัน → ใช้ `shared/` pattern + `$isAdmin` + `$routePrefix`
- ชื่ออาจารย์: ดึงจาก `users` + `instructor_profiles` เสมอ ห้าม hardcode
