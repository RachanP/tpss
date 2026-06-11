# Testing — Feature Tests + Playwright E2E

## ⚠️ กฎเหล็ก: แก้โค้ด → อัปเดต test เสมอ (Test Maintenance)

> ทุกครั้งที่ **แก้ไข / refactor / เปลี่ยน UI / เพิ่ม-ลบ field / เปลี่ยน route หรือ flow** ของฟีเจอร์ที่มี test คุมอยู่ ต้องอัปเดต test ในงานเดียวกัน — ห้ามปล่อยให้ test เพี้ยนแล้วค่อยตามแก้

- **เปลี่ยน UI/ลบ-ย้าย element** → ตรวจว่า `data-testid` ที่ test อ้างยังอยู่ ถ้าย้าย/เปลี่ยนชื่อให้ย้าย testid ตาม + แก้ selector ใน spec
- **เปลี่ยน flow/redirect/landing** → แก้ assertion ใน e2e (เช่น `m10-rbac` ผูกกับ `DashboardController::index` map ของแต่ละ role)
- **เพิ่ม/แก้ field, validation, enum** → อัปเดตทั้ง Feature test (PHPUnit) และ e2e ที่กรอกฟอร์มนั้น
- **เพิ่มฟีเจอร์/หน้าใหม่** → เพิ่ม `data-testid` ตอน implement (อย่ารอคนเขียน test มาขอ) + เพิ่ม spec ต่อ module
- **ก่อนถือว่าเสร็จ** → รัน suite ที่เกี่ยวข้องให้เขียว: `php artisan test` (PHPUnit) + `npx playwright test <spec> --project=chromium` (e2e)
- e2e แยกต่อ module ใน `tests/e2e/` (m10-rbac/settings, m1-master-data-crud/master-data, m2-*, m3-schedule, m4-conflict, m7-m8-views, pa-audit ฯลฯ) + helper รวม `tests/e2e/support/` — แก้ฟีเจอร์ไหนเปิดไฟล์ module นั้นดูก่อนเสมอ

## Stack

| Layer | Tool |
|-------|------|
| Feature/Unit | PHPUnit (`tests/Feature/`, `tests/Unit/`) |
| E2E | Playwright (`tests/e2e/`) |
| Test DB | MySQL `tpss_testing` (separate from `tpss`) |

## คำสั่งรัน

```bash
# Feature/Unit
php artisan test                                # ทุก suite
php artisan test --filter=AdminUserManagementTest
php artisan test --filter=test_password_is_hashed_on_create
php artisan test --testdox                      # อ่านชื่อ method ง่ายขึ้น

# Playwright E2E
npx playwright test                             # ทุก spec (chromium + mobile-chrome)
npx playwright test user-management.spec.ts
npx playwright test --project=chromium          # desktop เท่านั้น (เร็วกว่า)
npx playwright test --headed                    # เห็น browser
npx playwright test --ui                        # debug UI
npx playwright show-report                      # HTML report
```

## Playwright Setup (สำคัญ)

- **`workers: 1`** ใน `playwright.config.ts` — บังคับ serial เพราะทุก worker ใช้ `tpss_testing` ตัวเดียวกัน race condition แน่ถ้าไม่ serial
- **`globalSetup`** รัน `migrate:fresh --seed` กับ `tpss_testing` ทุกครั้งที่เริ่ม session ใหม่ — ใช้เวลา ~15-20s
- **`webServer`** boot `php artisan serve --port=8010` อัตโนมัติ (ถ้ายังไม่มี baseURL ใน env)
- ข้าม DB setup ได้: `E2E_SKIP_DB_SETUP=1 npx playwright test`

## Test ID Convention

- ทุก interactive element ที่ test ต้องอ้างถึง ใช้ `data-testid="<page>-<element>"`
- ตัวอย่าง: `users-add-button`, `user-form-username`, `users-row` (+ `data-username` สำหรับ filter ตาม user)
- **อย่าใช้ class หรือ text selector** — เปราะเมื่อ refactor

## Modal บน Mobile Viewport

- modal admin (เพิ่ม/แก้ user, course, ฯลฯ) มี overlay issue บน mobile-chrome
- E2E ของ modal ให้ skip mobile: `test.skip(testInfo.project.name === 'mobile-chrome', '...')`
- admin เป้าหมายคือ desktop อยู่แล้ว ไม่จำเป็นต้อง cover mobile

## Alpine x-show race (Friend 1 quirk)

Alpine x-show expressions ที่อ่าน sibling DOM state จะ re-evaluate **ช้ากว่า** trigger เดิม — เช่น empty-state row ที่ check `$el.parentNode.children...style.display !== 'none'` จะเห็น stale display values รอบแรก แล้วถึงจะ pass รอบสอง

**Workaround ใน E2E test:**
```ts
await input.fill('A');       // กระตุ้นรอบแรก (sibling rows hide)
await input.fill('');        // reset เพื่อ force re-render
await input.fill('ZZZZ');    // รอบจริง — Alpine ผ่าน 3 cycles แล้ว predicate stable
await expect(emptyState).toBeVisible({ timeout: 10000 });
```

**ทำไม:** Alpine ไม่ tracking sibling DOM mutations เป็น reactive dep — ต้องมี trigger ของตัวเองหลาย ๆ ครั้ง ดูตัวอย่างใน `tests/e2e/m1-master-data.spec.ts` empty-state test

## Feature Test Pattern

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class XxxTest extends TestCase {
    use RefreshDatabase;

    protected $admin;
    protected function setUp(): void {
        parent::setUp();
        $this->admin = User::create([...]);
        UserRole::create(['user_id' => $this->admin->id, 'role' => 'admin', 'is_primary' => true]);
        session(['active_role' => 'admin']);
    }

    public function test_xxx(): void {
        $this->actingAs($this->admin);
        $response = $this->post('/admin/xxx', [...]);
        $response->assertRedirect('/admin/...');
    }
}
```

- ห้าม chain `withSession()->actingAs()` แล้วไม่ใช้ผลลัพธ์ — session จะไม่ถูก apply
- helper `makeUser($username, $role)` สำหรับสร้าง user แบบเร็ว (ดู `AdminUserManagementTest`)

## Test After Merge Flow (สำหรับ test ที่เขียนตามหลัง feature)

เมื่อ feature merge เข้า sprint แล้ว ผู้เขียน test (มัก = Lead) ทำตาม flow นี้:

```bash
# 1. รอ feature merge เข้า sprint ก่อน — อย่าเขียน test จาก branch feature โดยตรง
git checkout sprint
git pull origin sprint

# 2. แตก test branch
git checkout -b test/<feature-name>-coverage

# 3. เขียน test (Feature + E2E) — เพิ่ม data-testid ใน view ที่จำเป็น
#    ดู AdminUserManagementTest + user-management.spec.ts เป็น reference

# 4. ถ้ามี commit ใหม่เข้า sprint ระหว่างที่เขียน test → rebase
git fetch origin
git rebase origin/sprint
git push --force-with-lease   # safe — จะ refuse ถ้ามีคนอื่นทับ branch ตัวเอง

# 5. PR เข้า sprint
gh pr create --base sprint --title "test(<feature>): add coverage"
```

**กฎ:**
- **ห้าม** pull จาก feature branch ที่ยังไม่ merge เข้า sprint — จะลักลอบเอาโค้ดเข้า PR test
- **ห้าม** force push บน branch shared (sprint, main, feature ของเพื่อน) — ใช้ `--force-with-lease` บน branch ตัวเองเท่านั้น
- ทดสอบทยอย chunk-by-chunk ได้ — ไม่ต้องรอ feature เสร็จทั้งหมด แค่รอ chunk merge แล้วก็ test chunk นั้นได้เลย

## ไฟล์อ้างอิง

- `playwright.config.ts` — config หลัก
- `tests/e2e/support/auth.ts` — login helper
- `tests/e2e/support/global-setup.ts` — DB reset
- `tests/Feature/AdminUserManagementTest.php` — pattern reference (20 tests)
- `tests/e2e/user-management.spec.ts` — E2E pattern reference
- `docs/playwright-e2e.md` — extended notes
