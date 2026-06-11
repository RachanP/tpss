import { expect, test } from '@playwright/test';
import { login } from './support/auth';
import { findSchedulableOffering } from './support/schedule';

/**
 * M7 (Search & Filter) + M8 (Views & Calendar)
 *
 *  - สลับมุมมอง list ↔ grid บนหน้าจัดตาราง offering
 *  - workspace ตารางสอน (/maker/schedules) รองรับ period day/week/month ผ่าน query
 *  - ตัวกรองเทอม (term filter) ปรากฏบนหน้าจัดตาราง
 *  - master data: ค้นหา → empty state (เสริม m1-master-data.spec.ts มีอยู่แล้ว)
 */

test.describe('M8 — Schedule views', () => {
  test('list and grid toggles switch the visible view on an offering page', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Grid view is desktop-first');
    await login(page, 'head_med');
    const offeringUrl = await findSchedulableOffering(page);
    test.skip(!offeringUrl, 'No schedulable offering available.');

    await page.goto(offeringUrl, { waitUntil: 'domcontentloaded' });

    await page.getByTestId('schedule-list-toggle').first().click();
    await expect(page.locator('[data-testid="schedule-list-view"]:visible').first()).toBeVisible({ timeout: 10_000 });

    await page.getByTestId('schedule-grid-toggle').first().click();
    await expect(
      page.locator('[data-testid="schedule-grid-view-co"]:visible, [data-testid="schedule-month-calendar-co"]:visible').first(),
    ).toBeVisible({ timeout: 10_000 });
  });

  test('workspace schedule loads and month calendar shows after switching to grid', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Month grid is desktop-first');
    await login(page, 'head_med');

    const response = await page.goto('/maker/schedules?period=month');
    expect(response?.status()).toBe(200);

    // workspace UI โหลด (toolbar สลับมุมมอง)
    const gridToggle = page.getByTestId('schedule-grid-toggle').first();
    await expect(gridToggle).toBeVisible({ timeout: 10_000 });
    await gridToggle.click();

    await expect(
      page.locator('[data-testid="schedule-month-calendar"]:visible, [data-testid="schedule-month-calendar-co"]:visible').first(),
    ).toBeVisible({ timeout: 10_000 });
  });
});

test.describe('M7 — Filters', () => {
  test('term filter is present on the schedule page', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Schedule toolbar is desktop-first');
    await login(page, 'head_med');
    const offeringUrl = await findSchedulableOffering(page);
    test.skip(!offeringUrl, 'No schedulable offering available.');

    await page.goto(offeringUrl, { waitUntil: 'domcontentloaded' });
    await expect(
      page.locator('[data-testid="schedule-term-filter"], [data-testid="schedule-term-filter-offering"]').first(),
    ).toBeVisible({ timeout: 10_000 });
  });

  test('master-data course search narrows to an empty state for nonsense query', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Master-data table is desktop-only');
    await login(page, 'admin_01');
    await page.goto('/admin/master-data?tab=courses');

    const search = page.getByTestId('courses-search-input');
    await expect(search).toBeVisible({ timeout: 15_000 });
    await search.fill('Z');
    await search.fill('');
    await search.fill('ไม่มีวิชานี้แน่ๆ-XYZ');

    await expect(page.getByTestId('courses-empty-state')).toBeVisible({ timeout: 10_000 });
  });
});
