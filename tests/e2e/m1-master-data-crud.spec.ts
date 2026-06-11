import { expect, test } from '@playwright/test';
import { login } from './support/auth';

/**
 * M1 — Master Data CRUD (เสริมจาก m1-master-data.spec.ts ที่คุม courses/curriculums)
 *
 *  - ภาควิชา (departments): สร้าง → แถวขึ้น
 *  - ประเภทกิจกรรม (activity_types): สร้าง → แถวขึ้น → ลบ (SweetAlert confirm)
 *
 * หมายเหตุ: tab/modal ของ master data เป็น desktop-only
 * curriculums/courses + cohort/rooms ครอบคลุมใน m1-master-data.spec.ts + PHPUnit
 */

test.describe('M1 — Department CRUD', () => {
  test('admin can create a department', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Master-data modal is desktop-only');
    await login(page, 'admin_01');
    await page.goto('/admin/master-data?tab=departments');

    const addBtn = page.getByTestId('department-add-button');
    await expect(addBtn).toBeVisible({ timeout: 15000 });
    await addBtn.click();

    const name = 'ภาควิชาทดสอบ E2E';
    await page.getByTestId('department-form-name').fill(name);
    await page.getByTestId('department-form-submit').click();

    await page.waitForURL(/master-data/);
    await expect(page.locator(`[data-testid="department-row"][data-dept-name="${name}"]`))
      .toBeVisible({ timeout: 10000 });
  });
});

test.describe('M1 — Activity Type CRUD', () => {
  test('admin can create then delete an activity type', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Master-data modal is desktop-only');
    await login(page, 'admin_01');
    await page.goto('/admin/master-data?tab=activity_types');

    // ── Create ──
    const addBtn = page.getByTestId('activity-type-add-button');
    await expect(addBtn).toBeVisible({ timeout: 15000 });
    await addBtn.click();

    const name = 'กิจกรรมทดสอบ E2E';
    await page.getByTestId('activity-type-form-name').fill(name);
    await page.getByTestId('activity-type-form-submit').click();

    await page.waitForURL(/master-data/);
    const row = page.locator(`[data-testid="activity-type-row"][data-name="${name}"]`);
    await expect(row).toBeVisible({ timeout: 10000 });

    // ── Delete ── เปิด edit modal ของแถวที่เพิ่งสร้าง → ลบ → ยืนยันใน SweetAlert
    await row.getByTestId('activity-type-edit-button').click();
    await expect(page.getByTestId('activity-type-form-name')).toHaveValue(name);
    await page.getByRole('button', { name: /ลบข้อมูล/ }).click();

    const swal = page.locator('.swal2-popup');
    await expect(swal).toBeVisible();
    await page.locator('.swal2-confirm').click();

    await page.waitForURL(/master-data/);
    await expect(page.locator(`[data-testid="activity-type-row"][data-name="${name}"]`))
      .toHaveCount(0, { timeout: 10000 });
  });
});
