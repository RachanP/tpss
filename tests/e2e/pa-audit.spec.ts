import { expect, test } from '@playwright/test';
import { login } from './support/auth';

/**
 * PA self-service (V4 ข้อ 5) + Audit Log (M12)
 *
 *  - instructor กรอกสัดส่วน PA ของตัวเอง (รวม 100% + อยู่ในเกณฑ์) → สำเร็จ
 *  - admin เปิดหน้า Audit Log + ตัวกรองทำงาน
 *
 * หมายเหตุ: Approval flow (executive approve/reject) เป็น Phase 2 — v1.0 executive = read-only
 * (coming-soon landing) ครอบใน m10-rbac.spec.ts จึงไม่มี e2e approve/reject ที่นี่
 */

test.describe('PA — instructor self-service', () => {
  test('instructor can submit PA proportions that sum to 100%', async ({ page }) => {
    await login(page, 'instructor_01');
    await page.goto('/lecturer/dashboard');

    const form = page.getByTestId('pa-form');
    // ถ้ายังไม่มีรอบ PA (ไม่มีปีการศึกษา) → ข้าม
    if ((await page.getByTestId('pa-round-empty').count()) > 0) {
      test.skip(true, 'No PA round available in this seed.');
    }
    await expect(form).toBeVisible({ timeout: 10_000 });

    // แผง PA เปิดอยู่แล้วโดย default (paOpen:true) — กรอกได้เลย ไม่ต้องกด toggle

    // กรอกสัดส่วนรวม 100% และอยู่ในเกณฑ์กลุ่ม "อาจารย์" (t20-70/r20-70/s5-20/c5-15/o0-20)
    await page.getByTestId('pa-teaching-pct').fill('40');
    await page.getByTestId('pa-research-pct').fill('30');
    await page.getByTestId('pa-service-pct').fill('15');
    await page.getByTestId('pa-culture-pct').fill('10');
    await page.getByTestId('pa-other-pct').fill('5');

    await expect(page.getByTestId('pa-total')).toContainText('100');

    await page.getByTestId('pa-submit').click();

    await page.waitForURL(/lecturer\/dashboard/);
    await expect(page.getByTestId('pa-success-message')).toBeVisible({ timeout: 10_000 });
  });

  test('PA rejects proportions that do not sum to 100%', async ({ page }) => {
    await login(page, 'instructor_01');
    await page.goto('/lecturer/dashboard');
    if ((await page.getByTestId('pa-round-empty').count()) > 0) {
      test.skip(true, 'No PA round available in this seed.');
    }
    await expect(page.getByTestId('pa-form')).toBeVisible({ timeout: 10_000 });
    // แผง PA เปิดอยู่แล้วโดย default — กรอกได้เลย

    await page.getByTestId('pa-teaching-pct').fill('10');
    await page.getByTestId('pa-research-pct').fill('10');
    await page.getByTestId('pa-service-pct').fill('10');
    await page.getByTestId('pa-culture-pct').fill('10');
    await page.getByTestId('pa-other-pct').fill('10'); // รวม 50%

    await page.getByTestId('pa-submit').click();

    await page.waitForURL(/lecturer\/dashboard/);
    await expect(page.getByTestId('pa-error-message')).toBeVisible({ timeout: 10_000 });
  });
});

test.describe('Audit Log (admin)', () => {
  test('admin can open the audit log page with filters', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Audit table is desktop-first');
    await login(page, 'admin_01');
    const response = await page.goto('/admin/audit-logs');
    expect(response?.status()).toBe(200);

    await expect(page.getByTestId('audit-logs-table')).toBeVisible({ timeout: 10_000 });
    await expect(page.getByTestId('audit-logs-filter-category')).toBeVisible();
    await expect(page.getByTestId('audit-logs-filter-action')).toBeVisible();
  });
});
