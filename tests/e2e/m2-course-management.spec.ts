import { expect, test } from '@playwright/test';
import { login } from './support/auth';

test('course pool page is removed from admin workflow', async ({ page }) => {
  await login(page, 'admin_01');

  const response = await page.goto('/admin/course-pool', { waitUntil: 'domcontentloaded' });
  expect(response?.status()).toBe(404);
});

test('course head can add student groups from an offering (V4 cohort-based)', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name === 'mobile-chrome', 'Offering group editor is desktop-only');
  // V4: หัวหน้าวิชาจัดกลุ่มเป็นงานหลัก — editor แก้ได้ทันที (ไม่มีปุ่ม "แก้ไข") · จัดกลุ่มจาก cohort ชั้นปี
  await login(page, 'head_med');

  await page.goto('/maker/course-offerings', { waitUntil: 'domcontentloaded' });
  const offeringLinks = await page.getByTestId('course-offering-show-link').evaluateAll((links) =>
    links.map((link) => (link as HTMLAnchorElement).href),
  );
  expect(offeringLinks.length, 'expected at least one course offering').toBeGreaterThan(0);

  // หา offering ที่มี group editor + แหล่งกลุ่มชั้นปี (cohort) ให้เลือก
  let added = false;
  for (const href of offeringLinks) {
    await page.goto(href, { waitUntil: 'domcontentloaded' });
    const editor = page.getByTestId('student-groups-editor');
    if (!(await editor.count()) || !(await editor.isVisible())) continue;

    const source = page.getByTestId('group-editor-source');
    if (!(await source.count()) || (await source.locator('option').count()) <= 1) continue;

    await source.selectOption({ index: 1 });
    await page.getByTestId('group-editor-add-count').fill('2');
    // กรอกจำนวน → autoAddGroups เพิ่มแถวกลุ่มอัตโนมัติ (debounce 600ms)
    await expect(page.getByTestId('student-group-code-0')).toBeVisible({ timeout: 7_000 });
    await expect(page.getByTestId('student-group-code-1')).toBeVisible();
    added = true;
    break;
  }

  expect(added, 'expected an offering with a cohort source to group students').toBe(true);
});
