import { expect, test } from '@playwright/test';
import { login, switchRole } from './support/auth';

test('admin can see locked course pool templates after scheduling opens', async ({ page }) => {
  await login(page, 'admin_01');

  await page.goto('/admin/course-pool', { waitUntil: 'domcontentloaded' });
  await page.getByTestId('course-pool-template-button').first().click();

  await expect(page.getByTestId('course-pool-template-modal')).toBeVisible();
  await expect(page.getByText('แม่แบบนี้ถูกล็อกแล้ว')).toBeVisible();
  await expect(page.getByRole('link', { name: 'เปิดหน้ารายละเอียด' })).toHaveCount(0);
  await expect(page).toHaveURL(/\/admin\/course-pool$/);
});

test('course head can bulk-create student groups from an offering', async ({ page }) => {
  await login(page, 'admin_01');
  await switchRole(page, 'course_head');

  await page.goto('/maker/course-offerings', { waitUntil: 'domcontentloaded' });
  const offeringLinks = await page.getByTestId('course-offering-show-link').evaluateAll((links) =>
    links.map((link) => (link as HTMLAnchorElement).href),
  );

  let foundEditableOffering = false;
  for (const href of offeringLinks) {
    await page.goto(href, { waitUntil: 'domcontentloaded' });
    if ((await page.getByTestId('bulk-groups-form').count()) > 0) {
      foundEditableOffering = true;
      break;
    }
  }

  const prefix = `E2E${Date.now().toString().slice(-6)}`;
  expect(foundEditableOffering, 'expected at least one course offering with ungrouped students').toBe(true);
  await expect(page.getByTestId('bulk-groups-form')).toBeVisible();
  await page.getByTestId('bulk-group-prefix').fill(prefix);
  await page.getByTestId('bulk-group-start').fill('1');
  await page.getByTestId('bulk-group-count').fill('2');
  await page.getByTestId('bulk-groups-submit').click();

  await expect(page.locator(`input[data-testid="student-group-code"][value="${prefix}1"]`)).toHaveCount(1);
  await expect(page.locator(`input[data-testid="student-group-code"][value="${prefix}2"]`)).toHaveCount(1);
});
