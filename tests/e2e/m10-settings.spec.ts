import { expect, test } from '@playwright/test';
import { login } from './support/auth';

/**
 * M10 — Admin Settings: ปีการศึกษา + วันหยุด
 *
 * Full CRUD ผ่าน modal:
 *  - เพิ่มปีการศึกษา (ชื่อ unique) → แถวขึ้นในตาราง
 *  - ชื่อปีซ้ำ → ถูก reject พร้อมข้อความ error
 *  - เพิ่มวันหยุด manual (วันที่ + ชื่อ) → แถวขึ้น แล้วลบได้
 *
 * หมายเหตุ: settings เป็น desktop-only (modal overlay มีปัญหาบน mobile-chrome)
 */

test.describe('M10 — Academic year CRUD', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Settings modal is desktop-only');
    await login(page, 'admin_01');
    await page.goto('/admin/settings?tab=academic');
    await expect(page.getByTestId('settings-add-year-button')).toBeVisible({ timeout: 15000 });
  });

  test('admin can add a new academic year', async ({ page }) => {
    const newYear = '2599';
    await page.getByTestId('settings-add-year-button').click();

    const nameInput = page.getByTestId('settings-year-name');
    await expect(nameInput).toBeVisible();
    await nameInput.fill(newYear);
    await page.getByTestId('settings-year-submit').click();

    await page.waitForURL(/settings/);
    await expect(page.locator(`[data-testid="settings-year-row"][data-year-name="${newYear}"]`))
      .toBeVisible({ timeout: 10000 });
  });

  test('duplicate academic year name is rejected', async ({ page }) => {
    // อ่านชื่อปีที่มีอยู่จากแถวแรก แล้วลองสร้างซ้ำ
    const firstRow = page.locator('[data-testid="settings-year-row"]').first();
    await expect(firstRow).toBeVisible();
    const existingName = (await firstRow.getAttribute('data-year-name')) ?? '';
    expect(existingName).not.toBe('');

    await page.getByTestId('settings-add-year-button').click();
    await page.getByTestId('settings-year-name').fill(existingName);
    await page.getByTestId('settings-year-submit').click();

    await page.waitForURL(/settings/);
    await expect(page.locator('body')).toContainText(/มีอยู่แล้วในระบบ/, { timeout: 10000 });
  });
});

test.describe('M10 — Holiday CRUD', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Settings modal is desktop-only');
    await login(page, 'admin_01');
    await page.goto('/admin/settings?tab=academic');
    // ไปแท็บวันหยุด
    await page.getByRole('button', { name: 'วันหยุด' }).click();
    await expect(page.getByTestId('settings-add-holiday-button')).toBeVisible({ timeout: 15000 });
  });

  test('admin can add a manual holiday then delete it', async ({ page }) => {
    const holidayName = 'E2E วันหยุดทดสอบ';

    await page.getByTestId('settings-add-holiday-button').click();
    // x-thai-date-input ส่งค่าเป็น พ.ศ. (DD/MM/YYYY) แล้ว controller แปลงเป็น ISO เอง
    await page.getByTestId('settings-holiday-date').fill('07/07/2599');
    await page.getByTestId('settings-holiday-name').fill(holidayName);
    await page.getByTestId('settings-holiday-submit').click();

    await page.waitForURL(/settings/);
    await page.getByRole('button', { name: 'วันหยุด' }).click();
    const row = page.locator(`[data-testid="settings-holiday-row"][data-holiday-name="${holidayName}"]`);
    await expect(row).toBeVisible({ timeout: 10000 });

    // ลบ: เปิด edit modal ของแถวนี้ → กดปุ่มลบ (submit deleteHolidayForm)
    await row.getByRole('button', { name: 'แก้ไข' }).click();
    await expect(page.getByTestId('settings-holiday-name')).toHaveValue(holidayName);
    await page.getByRole('button', { name: 'ลบ', exact: true }).click();

    await page.waitForURL(/settings/);
    await page.getByRole('button', { name: 'วันหยุด' }).click();
    await expect(page.locator(`[data-testid="settings-holiday-row"][data-holiday-name="${holidayName}"]`))
      .toHaveCount(0, { timeout: 10000 });
  });
});
