# คำสั่ง Git ที่ใช้บ่อย

เอกสารนี้สรุปคำสั่ง Git ที่ใช้บ่อยในการทำงานร่วมกับเพื่อนในโปรเจกต์ เรียงตามลำดับ workflow ที่ควรใช้จริง

---

## 1. เช็กสถานะก่อนเริ่มงาน

```bash
git status
```

ใช้ดูว่าเราอยู่ branch ไหน และมีไฟล์ที่แก้ไข/เพิ่ม/ลบค้างอยู่หรือไม่  
ควรรันก่อน `pull`, `commit`, `push` ทุกครั้ง

```bash
git branch
```

ใช้ดู branch ทั้งหมดในเครื่อง และ branch ปัจจุบันจะมีเครื่องหมาย `*`

```bash
git log --oneline -5
```

ใช้ดู commit ล่าสุด 5 รายการแบบสั้น ๆ เพื่อเช็กว่าโค้ดอยู่ถึง commit ไหนแล้ว

---

## 2. ดึงโค้ดล่าสุดจากเพื่อน

```bash
git fetch
```

ใช้ดึงข้อมูลล่าสุดจาก remote เช่น GitHub แต่ยังไม่รวมเข้ากับไฟล์ในเครื่อง  
เหมาะสำหรับเช็กก่อนว่า remote มีอะไรใหม่บ้าง

```bash
git pull
```

ใช้ดึงโค้ดล่าสุดจาก remote แล้วรวมเข้ากับ branch ปัจจุบันทันที  
โดยทั่วไปใช้ก่อนเริ่มเขียนโค้ด เพื่อให้เครื่องเราอัปเดตตามของเพื่อน

```bash
git pull --ff-only
```

ใช้ดึงโค้ดแบบปลอดภัยขึ้น ถ้า Git รวมแบบ fast-forward ไม่ได้ คำสั่งจะหยุดแทนการสร้าง merge commit อัตโนมัติ

---

## 3. สร้าง branch สำหรับงานใหม่

```bash
git switch -c feature/example-name
```

ใช้สร้าง branch ใหม่และย้ายเข้า branch นั้นทันที  
ควรใช้เมื่อต้องทำงานใหม่ เช่น feature, fix, database update

ตัวอย่างชื่อ branch:

```bash
git switch -c feature/user-management
git switch -c fix/login-error
git switch -c database/update-seeders
```

```bash
git switch main
```

ใช้ย้ายกลับไป branch `main`

```bash
git switch 2-m1-master_data
```

ใช้ย้ายไป branch ที่มีอยู่แล้ว เช่น branch งานปัจจุบันของโปรเจกต์

---

## 4. ดูไฟล์ที่เปลี่ยนแปลง

```bash
git diff
```

ใช้ดูรายละเอียดว่าเราแก้ไฟล์อะไรไปบ้างก่อน add/commit

```bash
git diff --staged
```

ใช้ดูไฟล์ที่ `git add` ไปแล้ว และกำลังจะเข้า commit

---

## 5. เพิ่มไฟล์เข้า staging area

```bash
git add ชื่อไฟล์
```

ใช้เลือกไฟล์ที่ต้องการเอาเข้า commit

ตัวอย่าง:

```bash
git add database/seeders/UserSeeder.php
```

```bash
git add .
```

ใช้เพิ่มไฟล์ที่เปลี่ยนทั้งหมดในโฟลเดอร์ปัจจุบัน  
ควรใช้หลังจากเช็ก `git status` และ `git diff` แล้วว่าไม่มีไฟล์แปลกปน

---

## 6. บันทึกงานเป็น commit

```bash
git commit -m "ข้อความ commit"
```

ใช้บันทึกชุดการเปลี่ยนแปลงลง Git

ตัวอย่าง:

```bash
git commit -m "feat: add user seed data"
git commit -m "fix: restore missing database seed records"
git commit -m "docs: add git command guide"
```

แนวทางเขียน commit message:

- `feat:` เพิ่ม feature ใหม่
- `fix:` แก้ bug
- `docs:` แก้เอกสาร
- `refactor:` ปรับโค้ดโดยไม่เปลี่ยน behavior
- `test:` เพิ่ม/แก้ test
- `chore:` งานทั่วไป เช่น config หรือ cleanup

---

## 7. ส่งโค้ดขึ้น GitHub

```bash
git push
```

ใช้ส่ง commit จากเครื่องเราขึ้น remote branch ที่ผูกไว้แล้ว

ถ้าเป็น branch ใหม่ที่ยังไม่เคย push:

```bash
git push -u origin ชื่อ-branch
```

ตัวอย่าง:

```bash
git push -u origin feature/user-management
```

หลังจากใช้ `-u` ครั้งแรก รอบต่อไปใช้แค่:

```bash
git push
```

---

## 8. Workflow ที่แนะนำในแต่ละวัน

### ก่อนเริ่มทำงาน

```bash
git status
git pull --ff-only
```

เช็กก่อนว่าไม่มีไฟล์ค้าง แล้วดึงโค้ดล่าสุดจากเพื่อน

### เริ่มงานใหม่

```bash
git switch -c feature/my-task
```

สร้าง branch แยกสำหรับงานนั้น

### ระหว่างทำงาน

```bash
git status
git diff
```

ดูว่าแก้อะไรไปแล้วบ้าง

### ก่อน commit

```bash
git status
git diff
git add .
git diff --staged
git commit -m "feat: describe your change"
```

ตรวจงานก่อน add จากนั้นตรวจอีกครั้งก่อน commit

### ส่งงานให้เพื่อน

```bash
git push -u origin feature/my-task
```

ส่ง branch ขึ้น GitHub เพื่อให้เพื่อน review หรือเปิด Pull Request

---

## 9. คำสั่งแก้ปัญหาที่ใช้บ่อย

```bash
git restore ชื่อไฟล์
```

ใช้ยกเลิกการแก้ไขไฟล์ที่ยังไม่ได้ `git add`

```bash
git restore --staged ชื่อไฟล์
```

ใช้เอาไฟล์ออกจาก staging area แต่ยังเก็บการแก้ไขไว้

```bash
git commit --amend
```

ใช้แก้ commit ล่าสุด เช่น แก้ข้อความ commit หรือเพิ่มไฟล์ที่ลืม add  
ควรใช้เฉพาะ commit ที่ยังไม่ได้ push หรือคุยกับทีมก่อนถ้า push ไปแล้ว

```bash
git stash
```

ใช้เก็บงานที่ยังไม่อยาก commit ชั่วคราว เพื่อให้ working tree กลับมาสะอาด

```bash
git stash pop
```

ใช้ดึงงานที่ stash ไว้กลับมา

---

## 10. คำสั่งดูข้อมูล remote

```bash
git remote -v
```

ใช้ดูว่า repo นี้เชื่อมกับ remote URL ไหน

```bash
git branch -vv
```

ใช้ดูว่า branch local ผูกกับ remote branch ไหน และ ahead/behind กี่ commit

---

## 11. ข้อควรระวัง

- ก่อน `pull` ควร `git status` ทุกครั้ง
- ก่อน `commit` ควร `git diff` ทุกครั้ง
- อย่าใช้ `git add .` ถ้ายังไม่ได้เช็กว่าไม่มีไฟล์ลับ เช่น `.env`
- อย่าใช้ `git reset --hard` ถ้ายังไม่แน่ใจ เพราะจะลบงานที่ยังไม่ได้ commit
- ถ้าเกิด conflict ให้หยุดอ่านไฟล์ก่อน อย่ารีบลบโค้ดของเพื่อน

---

## 12. คำสั่ง Database หลังดึงโค้ดล่าสุด

หลังจากดึงโค้ดล่าสุดจากเพื่อนด้วย Git แล้ว บางครั้ง database ในเครื่องเรายังไม่ตรงกับโค้ดล่าสุด เช่น มี migration ใหม่ หรือมี seeder ใหม่ จึงควรรันคำสั่ง database เพิ่มตามสถานการณ์

### เช็กสถานะ migration ก่อน

```bash
php artisan migrate:status
```

ใช้ดูว่า migration ไหนรันแล้ว และ migration ไหนยังไม่ได้รัน

### กรณีทั่วไป: มี migration ใหม่ แต่ไม่อยากลบข้อมูลเดิม

```bash
php artisan migrate
```

ใช้รันเฉพาะ migration ใหม่ที่ยังไม่ได้รัน  
คำสั่งนี้ไม่ล้าง database เดิม เหมาะกับการอัปเดต database ในงานประจำวัน

### กรณีมีข้อมูล seed ใหม่ และต้องการเติมข้อมูลเพิ่ม

```bash
php artisan db:seed
```

ใช้รัน seeder เพื่อเติมข้อมูลเริ่มต้นหรือข้อมูลทดสอบ  
ควรรันหลัง `php artisan migrate` เพื่อให้ table/column พร้อมก่อน

ลำดับที่แนะนำ:

```bash
git pull --ff-only
php artisan migrate
php artisan db:seed
```

### กรณีต้องการสร้าง database ใหม่ทั้งหมด

```bash
php artisan migrate:fresh --seed
```

ใช้ลบตารางทั้งหมด สร้างใหม่จาก migration ทั้งหมด แล้ว seed ข้อมูลใหม่ทันที

คำสั่งนี้เหมาะเมื่อ:

- เป็น database local สำหรับทดสอบเท่านั้น
- ต้องการ reset ข้อมูลทั้งหมด
- ยอมรับได้ว่าข้อมูลเดิมจะหาย

### ห้ามรันลำดับนี้ถ้าไม่ตั้งใจลบข้อมูล

```bash
php artisan db:seed
php artisan migrate:fresh
```

ลำดับนี้ผิดสำหรับการกู้ข้อมูล เพราะ `db:seed` จะใส่ข้อมูลก่อน แล้ว `migrate:fresh` จะลบตารางทั้งหมดทิ้ง ทำให้ข้อมูลที่เพิ่ง seed หาย

ถ้าจะ fresh database และ seed ด้วย ให้ใช้คำสั่งเดียวนี้แทน:

```bash
php artisan migrate:fresh --seed
```

### Workflow แนะนำหลังดึงโค้ดเพื่อน

ถ้าต้องการอัปเดตแบบไม่ลบข้อมูล:

```bash
git status
git pull --ff-only
php artisan migrate
php artisan db:seed
```

ถ้าต้องการ reset database local ใหม่ทั้งหมด:

```bash
git status
git pull --ff-only
php artisan migrate:fresh --seed
```

### ข้อควรระวังเรื่อง database

- `php artisan migrate` ปลอดภัยกว่าสำหรับงานประจำวัน เพราะไม่ล้างข้อมูลเดิม
- `php artisan migrate:fresh` จะ drop table ทั้งหมด ข้อมูลเดิมหาย
- `php artisan migrate:fresh --seed` ใช้ได้เมื่อแน่ใจว่าต้องการ reset database local
- ก่อนรันคำสั่งที่ลบข้อมูล ควรเช็กว่า `.env` ต่อ database local ไม่ใช่ database จริงหรือ database ของทีม
- ถ้าข้อมูลสำคัญ ให้ backup ก่อนรัน `migrate:fresh`
