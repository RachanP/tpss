# Sync Context Files

อัปเดตไฟล์ context ของ Claude ให้ตรงกับสถานะปัจจุบันของโปรเจกต์

## ขั้นตอน

### 1. วิเคราะห์สิ่งที่เปลี่ยนไป
- รัน `git log --oneline -20` ดู commits ล่าสุด
- รัน `git diff main...HEAD --stat` ดู files ที่เปลี่ยน
- อ่าน `.claude/rules/sprint-status.md` เปรียบเทียบกับ commits

### 2. ตรวจแต่ละไฟล์ context

**sprint-status.md** — ต้องอัปเดตถ้า:
- Sprint เสร็จแล้วแต่ยังไม่ mark ✅
- Sprint ใหม่เริ่มแล้วแต่ยังไม่อัปเดต "Sprint ปัจจุบัน"
- มี User Stories เสร็จแล้วที่ยังไม่ได้บันทึก
- มีคำถามที่ได้คำตอบแล้วจากลูกค้า

**database.md** — ต้องอัปเดตถ้า:
- มี migration ใหม่ที่เพิ่ม enum หรือเปลี่ยน schema
- รัน `php artisan migrate:status` ตรวจดู

**rbac.md / architecture.md** — ต้องอัปเดตถ้า:
- มีการตัดสินใจด้าน architecture ใหม่
- มีการเปลี่ยน role permissions จาก client feedback

**Memory files** (`~/.claude/projects/c--MAMP-htdocs-tpss/memory/`) — ต้องอัปเดตถ้า:
- มี pattern ใหม่ที่ validated แล้ว
- มี constraint ใหม่จากลูกค้า
- มีข้อค้นพบสำคัญสำหรับ Sprint ถัดไป

### 3. แสดง diff proposal

สำหรับแต่ละไฟล์ที่ต้องเปลี่ยน แสดง:
```
📄 [ชื่อไฟล์]
  เดิม: ...
  ใหม่: ...
  เหตุผล: ...
```

### 4. ถามยืนยัน

"ต้องการอัปเดตไฟล์ไหนบ้าง?" — รอยืนยันก่อน write ทุกครั้ง

---
**หมายเหตุ**: ถ้าต้องการอัปเดตเฉพาะไฟล์ใดไฟล์หนึ่ง ระบุได้ใน arguments เช่น `/sync-context sprint` หรือ `/sync-context memory`
