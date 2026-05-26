import { expect, test, type Page } from '@playwright/test';
import { login, switchRole } from './support/auth';

async function openBulkGroupsForm(page: Page) {
  const form = page.getByTestId('bulk-groups-form');
  if ((await form.count()) === 0) {
    return false;
  }

  if (await form.isVisible()) {
    return true;
  }

  const openButton = page.getByTestId('bulk-groups-open');
  if ((await openButton.count()) === 0 || !(await openButton.isVisible())) {
    return false;
  }

  await openButton.click();
  await expect(form).toBeVisible();

  return true;
}

test('course pool page is removed from admin workflow', async ({ page }) => {
  await login(page, 'admin_01');

  const response = await page.goto('/admin/course-pool', { waitUntil: 'domcontentloaded' });
  expect(response?.status()).toBe(404);
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
    await page.getByTestId('edit-mode-toggle').click();

    if (await openBulkGroupsForm(page)) {
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

  await expect
    .poll(async () => page.getByTestId('student-group-code').evaluateAll((inputs) =>
      inputs.map((input) => (input as HTMLInputElement).value),
    ))
    .toEqual(expect.arrayContaining([`${prefix}1`, `${prefix}2`]));
});
