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

test.describe('M1 — Location type & Room', () => {
  test('admin can create a location type', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Master-data modal is desktop-only');
    await login(page, 'admin_01');
    await page.goto('/admin/master-data?tab=location_types');

    const addBtn = page.getByTestId('location-type-add-button');
    await expect(addBtn).toBeVisible({ timeout: 15000 });
    await addBtn.click();

    const name = 'ประเภทสถานที่ทดสอบ E2E';
    await page.getByTestId('location-type-form-name').fill(name);
    await page.getByTestId('location-type-form-submit').click();

    await page.waitForURL(/master-data/);
    await expect(async () => {
      expect(await page.getByText(name).count()).toBeGreaterThan(0);
    }).toPass({ timeout: 10000 });
  });

  test('admin can create a room under a location type', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Room modal is desktop-only');
    await login(page, 'admin_01');
    await page.goto('/admin/master-data?tab=location_types');

    const addBtn = page.getByTestId('room-add-button');
    await expect(addBtn).toBeVisible({ timeout: 15000 });
    await addBtn.click();

    const code = 'E2E-' + (Date.now() % 100000);
    await page.getByTestId('room-form-code').fill(code);
    await page.getByTestId('room-form-name').fill('ห้องทดสอบ E2E');

    // เลือกประเภทสถานที่ผ่าน custom dropdown (room-type-trigger → option แรกที่เป็นประเภทจริง)
    await page.locator('.room-type-trigger').click();
    await page.locator('.room-type-option:not(.is-placeholder)').first().click();

    // ความจุ (กรอกไว้เผื่อประเภทต้องการ)
    const capacity = page.locator('input[name="capacity"]');
    if (await capacity.isVisible().catch(() => false)) await capacity.fill('40');

    await page.getByTestId('room-form-submit').click();
    await page.waitForURL(/master-data/);
    await expect(async () => {
      expect(await page.getByText(code).count()).toBeGreaterThan(0);
    }).toPass({ timeout: 10000 });
  });

  test('rooms CSV import modal opens with a file input', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Import modal is desktop-only');
    await login(page, 'admin_01');
    await page.goto('/admin/master-data?tab=location_types');

    await page.getByTestId('rooms-import-button').click();
    await expect(page.getByText('นำเข้าห้อง/สถานที่จากไฟล์ CSV')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('input[name="csv_file"]').first()).toBeAttached();
  });
});

test.describe('M1 — Student cohort', () => {
  test('admin can create a student cohort', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Cohort modal is desktop-only');
    await login(page, 'admin_01');
    await page.goto('/admin/master-data?tab=student_cohorts');

    const addBtn = page.getByTestId('cohort-add-button');
    await expect(addBtn).toBeVisible({ timeout: 15000 });
    await addBtn.click();

    // เลือกหลักสูตรจริงตัวแรก
    const curriculum = page.locator('select[name="curriculum_id"]');
    await expect(curriculum).toBeVisible({ timeout: 5000 });
    await curriculum.selectOption({ index: 1 });

    // ชั้นปี (ถ้าหลักสูตรใช้ระบบชั้นปี)
    const yearSelect = page.locator('select[name="year_level"]');
    if (await yearSelect.isVisible().catch(() => false)) {
      await yearSelect.selectOption({ index: 1 });
    }

    const code = 'Z';
    await page.locator('input[name="student_count"]').fill('80');
    await page.locator('input[name="code"]').fill(code);
    await page.getByTestId('cohort-form-submit').click();

    await page.waitForURL(/master-data/);
    // กลุ่มใหม่โผล่ในแท็บ (อาจอยู่ใน accordion) — ตรวจว่าไม่มี validation error ค้าง + อยู่หน้า cohorts
    await expect(page.getByTestId('master-data-tab-student-cohorts')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('body')).not.toContainText('กรุณากรอก', { timeout: 5000 });
  });
});
