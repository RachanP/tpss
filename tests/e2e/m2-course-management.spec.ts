import { expect, test, type Page } from '@playwright/test';
import { login } from './support/auth';

async function openBulkGroupsForm(page: Page) {
  const currentEditor = page.getByTestId('student-groups-editor');
  if ((await currentEditor.count()) > 0 && await currentEditor.isVisible()) {
    return true;
  }

  const form = page.getByTestId('bulk-groups-form');
  if ((await form.count()) > 0 && await form.isVisible()) {
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
  await login(page, 'head_med');

  await page.goto('/maker/course-offerings', { waitUntil: 'domcontentloaded' });
  const offeringLinks = await page.getByTestId('course-offering-show-link').evaluateAll((links) =>
    links.map((link) => (link as HTMLAnchorElement).href),
  );

  let foundEditableOffering = false;
  for (const href of offeringLinks) {
    await page.goto(href, { waitUntil: 'domcontentloaded' });

    if (await openBulkGroupsForm(page)) {
      foundEditableOffering = true;
      break;
    }
  }

  expect(foundEditableOffering, 'expected at least one course offering with ungrouped students').toBe(true);
  await expect(page.getByTestId('student-groups-editor')).toBeVisible();

  const groupRows = page.locator('input[data-testid^="student-group-code-"]');
  const initialGroupCount = await groupRows.count();

  await page.getByTestId('group-editor-source').selectOption({ index: 1 });
  await page.getByTestId('group-editor-add-count').fill('2');
  await expect.poll(async () => groupRows.count()).toBe(initialGroupCount + 2);

  const valuesBeforeSave = await groupRows.evaluateAll((inputs) =>
    inputs.map((input) => (input as HTMLInputElement).value).filter(Boolean),
  );
  expect(valuesBeforeSave.length, 'expected generated group codes before save').toBeGreaterThanOrEqual(initialGroupCount + 2);

  await page.getByTestId('student-groups-save').click();

  await expect
    .poll(async () => groupRows.evaluateAll((inputs) =>
      inputs.map((input) => (input as HTMLInputElement).value).filter(Boolean),
    ))
    .toEqual(expect.arrayContaining(valuesBeforeSave.slice(-2)));
});
