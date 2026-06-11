import { expect, test } from '@playwright/test';
import { login } from './support/auth';
import { findSchedulableOffering, fillCreateModal, selectTime } from './support/schedule';

/**
 * M4 — Conflict Checking & Warnings
 *
 *  - Realtime: เปิด slot ที่ผู้สอน/เวลาทับกับ slot เดิม → ระบบบล็อก (schedule-live-block) + ปุ่มบันทึก disabled
 *  - หน้าแจ้งเตือนการชน (course_head) โหลดได้
 *  - หน้า alerts ของ admin โหลดได้
 *
 * Logic การชน: instructor/room/group overlap → severity conflict (บล็อกบันทึก)
 */

test.describe('M4 — Realtime conflict block', () => {
  test('overlapping instructor/time is blocked in the create modal', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Conflict modal flow is desktop-first');
    await login(page, 'head_med');
    const offeringUrl = await findSchedulableOffering(page);
    test.skip(!offeringUrl, 'No schedulable offering with a creatable student group.');

    // ── slot A: สร้างจริงที่ 13:17-13:47 วันที่ 10/06/2569 ──
    await page.goto(offeringUrl, { waitUntil: 'domcontentloaded' });
    const modalA = await fillCreateModal(page, { date: '10/06/2569', startHour: '13', topic: 'M4 base slot' });
    const toast = page.locator('#tpss-toast').waitFor({ state: 'visible', timeout: 10_000 });
    await modalA.getByTestId('schedule-submit').click();
    await expect(page.getByTestId('schedule-create-modal')).toBeHidden({ timeout: 10_000 });
    await toast;

    // ── slot B: วัน/เวลา/ผู้สอนเดียวกัน → ต้องถูกบล็อก ──
    await page.goto(offeringUrl, { waitUntil: 'domcontentloaded' });
    const createButton = page.locator('[data-testid="schedule-create-link"]:visible').first();
    await createButton.click();
    const modalB = page.getByTestId('schedule-create-modal');
    await expect(modalB).toBeVisible({ timeout: 5_000 });

    await modalB.locator('input[name="start_date"]').fill('10/06/2569');
    await selectTime(page, 'tp_start', '13', '17');
    await selectTime(page, 'tp_end', '13', '47');
    await modalB.locator('select[name="activity_type_id"]').selectOption({ index: 1 });
    await modalB.getByTestId('schedule-topic-input').fill('M4 conflicting slot');
    await modalB.locator('input[data-testid="schedule-instructor"]:not(:disabled)').first().check();
    await modalB.locator('input[data-testid="schedule-group-option"]:not(:disabled)').first().check();

    // debounced realtime check → block ขึ้น + submit disabled
    await expect(modalB.getByTestId('schedule-live-block')).toBeVisible({ timeout: 10_000 });
    await expect(modalB.getByTestId('schedule-submit')).toBeDisabled({ timeout: 10_000 });
  });
});

test.describe('M4 — Conflict / alert pages', () => {
  test('course head conflict/alerts page loads', async ({ page }) => {
    await login(page, 'head_med');
    const response = await page.goto('/maker/alerts');
    expect(response?.status()).toBe(200);
    await expect(page.locator('body')).toContainText(/การชน|แจ้งเตือน|conflict|ไม่พบ/i, { timeout: 10_000 });
  });

  test('admin alerts page loads', async ({ page }) => {
    await login(page, 'admin_01');
    const response = await page.goto('/admin/alerts');
    expect(response?.status()).toBe(200);
  });
});
