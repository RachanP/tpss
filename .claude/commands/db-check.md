# Database Consistency Check

ตรวจ database ให้ครบ 3 ส่วน:

1. **Migration Status** — รัน `php artisan migrate:status` แล้วรายงาน pending migrations
2. **Enum Consistency** — ใช้ MySQL MCP query ตรวจ enum values ของ columns สำคัญ:
   - `user_roles.role`
   - `schedules.status`
   - `course_offerings.approval_status`
   - `course_offerings.course_type`
   - `rooms.status`
   เปรียบเทียบกับ `.claude/rules/database.md` — แจ้งถ้าไม่ตรง
3. **FK Integrity** — ตรวจว่า `course_offering_id` ใน `student_groups` ยังคง valid (ไม่มี orphan rows)

จบด้วย summary: ✅ ปัญหาที่พบ / ✅ ผ่านทั้งหมด
