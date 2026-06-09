import { test, expect } from '@playwright/test';
import { login } from './support/auth';

/**
 * ข้อ 1 — จัดหน้าตั้งค่าปฏิทินใหม่:
 *  - การ์ด "ปฏิทินกลางของคณะ" (บน) + dropdown เลือกปี + ปุ่มกำหนดเทอม
 *  - การ์ด "ปฏิทินแยกตามหลักสูตร/ชั้นปี" (ล่าง) — accordion + ปุ่มแก้ไข → modal
 *  - วันหยุด แยกเป็น tab
 */
test.describe('Settings: academic calendar layout', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Settings is desktop-only');
  });

  test('central calendar card on top, per-curriculum overrides below', async ({ page }) => {
    const jsErrors: string[] = [];
    page.on('pageerror', (e) => jsErrors.push(String(e)));

    await login(page, 'admin_01');
    await page.goto('/admin/settings?tab=academic', { waitUntil: 'domcontentloaded' });

    // การ์ดปฏิทินกลาง (บน): อิงปีปัจจุบัน (label + badge) ไม่มี dropdown เลือกปี + ปุ่มแก้/กำหนดเทอม
    await expect(page.getByText('ปฏิทินกลางของคณะ').first()).toBeVisible({ timeout: 3000 });
    await expect(page.getByText('ปัจจุบัน').first()).toBeVisible();
    await page.getByRole('button', { name: /^(แก้ไขปฏิทินกลาง|กำหนดเทอม)$/ }).click();
    await expect(page.locator('.modal-center').getByText('ปฏิทินกลางของคณะ')).toBeVisible();
    await expect(page.locator('.modal-center').getByText('วันเริ่มเทอม').first()).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByText('วันเริ่มเทอม').first()).toBeHidden(); // modal ปิดแล้ว

    // การ์ดปฏิทินแยก (ล่าง) + ปุ่มแก้ไข/ตั้งค่า → เปิด modal ของขอบเขตนั้น
    const section = page.locator('#cal-override-section');
    await expect(section.getByText('ปฏิทินแยกตามหลักสูตร/ชั้นปี')).toBeVisible();
    await expect(section.getByText(/ตั้งปฏิทินแยกแล้ว \d+ รายการ/)).toBeVisible();
    const editBtn = section.getByRole('button', { name: /^(แก้ไข|ตั้งค่า)$/ }).first();
    if (await editBtn.count()) {
      await editBtn.click();
      await expect(page.locator('.modal-center').getByText('วันเริ่มเทอม').first()).toBeVisible();
    }

    expect(jsErrors).toEqual([]);
  });

  test('holidays moved to its own tab', async ({ page }) => {
    await login(page, 'admin_01');
    await page.goto('/admin/settings?tab=academic', { waitUntil: 'domcontentloaded' });

    // แท็บปีการศึกษา: ไม่เห็นตารางวันหยุด
    await expect(page.getByText('วันหยุดราชการ', { exact: false })).toBeHidden();

    // กดแท็บ "วันหยุด" → เห็นตารางวันหยุด · section ปฏิทินแยกถูกซ่อน
    await page.getByRole('button', { name: 'วันหยุด' }).click();
    await expect(page.getByText('วันหยุดราชการ', { exact: false })).toBeVisible();
    await expect(page.locator('#cal-override-section')).toBeHidden();
  });
});
