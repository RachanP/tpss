import { test, expect } from '@playwright/test';
import { login } from './support/auth';

/**
 * ข้อ 1 — ปฏิทินการศึกษายุบจาก modal ซ้อน 2 ชั้น เหลือ modal เดียว + dropdown สลับปฏิทิน
 */
test.describe('Academic calendar modal (dropdown switcher)', () => {
  test('opens single modal with dropdown and switches to new calendar', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Settings modal is desktop-only');

    // เก็บเฉพาะ JS error จริง (ข้าม resource 404 ของ static asset)
    const jsErrors: string[] = [];
    page.on('pageerror', (e) => jsErrors.push(String(e)));

    await login(page, 'admin_01');
    await page.goto('/admin/settings?tab=academic', { waitUntil: 'domcontentloaded' });

    // เปิด modal ปฏิทินจากปุ่มที่แถวปีการศึกษา
    await page.locator('[title^="ปฏิทินการศึกษาตามกลุ่ม"]').first().click();

    // modal เดียว: มี dropdown "เลือกปฏิทิน" + term fields อยู่ในตัวเดียวกัน (ไม่ใช่ list แยก)
    await expect(page.getByText('เลือกปฏิทิน')).toBeVisible({ timeout: 3000 });
    await expect(page.locator('.modal-center select').first()).toBeVisible();
    await expect(page.getByText('ภาคการศึกษา (เทอม)')).toBeVisible();

    // กด "+ เพิ่ม" → ฟอร์มล้างเป็นปฏิทินใหม่ (ชื่อว่าง)
    await page.locator('.modal-center').getByRole('button', { name: '+ เพิ่ม', exact: true }).click();
    await expect(page.locator('.modal-center input[name="name"]')).toHaveValue('');

    // ต้องไม่มี JS error (เช่น x-data พังจาก quote)
    expect(jsErrors).toEqual([]);
  });
});
