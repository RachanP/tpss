# TASKS — Branch C: `feat/v4-rbac-pa-ops`

> แผนรวม: `Doc/requirement/v4_work_split.md` · requirement: `requirement_v4.md`
> ขอบเขต: V4 ข้อ 3 (executive gate — S) + ข้อ 5 (PA self-service — M) + ข้อ 6 (สลับเวร + timestamp — S/M)

## งานที่ต้องทำ

### ข้อ 3 — ผู้บริหาร = หัวหน้าภาควิชา + gate สิทธิ์ (S)
- [ ] gate การกำหนด/แต่งตั้งตำแหน่งหัวหน้าภาควิชา ให้เฉพาะ role `executive` (และ `admin`)
- [ ] `instructor` ทั่วไปทำไม่ได้ — ซ่อน/บล็อกฟังก์ชันนี้

### ข้อ 5 — อาจารย์กรอกสัดส่วน PA เอง (M)
- [ ] หน้า self-service ให้ instructor ล็อกอินกรอกสัดส่วนภาระงานตนเอง
- [ ] validation เดิมคงไว้: ผลรวม = 100% + แต่ละด้านอยู่ใน min/max ของกลุ่มตำแหน่ง (ดู `AlertController::getPaViolations`)
- [ ] เก็บรอบการประเมิน (PA round) เพื่อให้แต่ละรอบมีสัดส่วนของตัวเอง
- [ ] ลดภาระ admin ที่เคยกรอกรวมให้ทุกคน

### ข้อ 6 — สลับเวร/แลกคลาส ผ่านหัวหน้าวิชา + timestamp (S/M)
- [ ] action "เปลี่ยนผู้สอน" ใน slot (หัวหน้าวิชาทำให้ — ไม่ตกลงกันเองนอกระบบ) เพื่อโอนชั่วโมง/PA ถูกคน
- [ ] หน้าตารางแสดง last-updated timestamp (หัวหน้าวิชา + ผู้บริหารเห็น)
- [ ] บันทึก audit trail การแก้ไข
- [ ] **ประสานกับ Branch A**: ปุ่ม/แถบในหน้า schedule = ของ A → rebase บน to-serve **หลัง A merge** แล้วค่อยเสียบ

## ไฟล์ที่เป็นเจ้าของ
- `app/Http/Controllers/Admin/UserController.php` / RBAC middleware
- หน้า self-service PA (instructor) + `AlertController` PA logic + `instructor_profiles`
- audit log (ข้อ 6)

## หมายเหตุ merge
- merge ลำดับสุดท้าย (หลัง A และ B)
- เฉพาะส่วนที่แตะหน้า schedule (timestamp/ปุ่มเปลี่ยนผู้สอน) ทำหลัง A merge แล้ว rebase
- ก่อน PR: unit test ผ่าน · `data-testid` ครบ · responsive ≤390px
