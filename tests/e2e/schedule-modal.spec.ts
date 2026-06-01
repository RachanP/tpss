import { test, expect } from '@playwright/test';
import { login, switchRole } from './support/auth';

test('schedule modal shows muted "ไม่มีผู้สอน" and datepicker popover appears', async ({ page }) => {
  await login(page, 'admin_01');
  await switchRole(page, 'course_head');

  // Go to course offerings and open first offering's schedules
  await page.goto('/maker/course-offerings', { waitUntil: 'domcontentloaded' });
  const links = await page.getByTestId('course-offering-schedule-link').evaluateAll((els) => els.map((a: HTMLAnchorElement) => a.href));
  test.expect(links.length, 'expected at least one offering with schedules').toBeGreaterThan(0);

  await page.goto(links[0], { waitUntil: 'domcontentloaded' });

  // Try to open an existing schedule detail modal; if none, open create modal
  const triggerCount = await page.locator('[data-schedule-modal-trigger]').count();
  let modalLocator = page.getByTestId('schedule-detail-modal');
  if (triggerCount > 0) {
    await page.locator('[data-schedule-modal-trigger]').first().click();
    await expect(modalLocator).toBeVisible({ timeout: 5000 });
  } else {
    // open create modal
    await page.getByTestId('schedule-create-link').first().click();
    modalLocator = page.getByTestId('schedule-create-modal');
    await expect(modalLocator).toBeVisible({ timeout: 5000 });
  }

  // If a 'ไม่มีผู้สอน' label exists in the modal, ensure it uses muted styling
  const noInstructor = modalLocator.locator('text=ไม่มีผู้สอน');
  if (await noInstructor.count()) {
    await expect(noInstructor.first()).toHaveClass(/sched-muted/);
  }

  // Open datepicker inside modal (if present) and assert popover appears
  const dateControl = modalLocator.locator('input[placeholder="วว/ดด/พ.ศ."]');
  if (await dateControl.count()) {
    const calBtn = modalLocator.locator('.tdi-cal-btn').first();
    await calBtn.waitFor({ state: 'visible', timeout: 5000 });
    await calBtn.click();
    const pop = page.locator('.tdi-pop');
    await expect(pop).toBeVisible({ timeout: 5000 });
  }
});

test('schedule detail modal opens from lazy list rows and grid cards', async ({ page }) => {
  await login(page, 'admin_01');
  await switchRole(page, 'course_head');

  await page.goto('/maker/course-offerings', { waitUntil: 'domcontentloaded' });
  const links = await page.getByTestId('course-offering-schedule-link').evaluateAll((els) => els.map((a: HTMLAnchorElement) => a.href));
  test.expect(links.length, 'expected at least one offering with schedules').toBeGreaterThan(0);

  await page.goto(links[0], { waitUntil: 'domcontentloaded' });
  await page.getByTestId('schedule-list-toggle').first().click();

  const firstHeader = page.getByTestId('schedule-day-group-toggle').first();
  await expect(firstHeader).toBeVisible({ timeout: 10_000 });
  const firstWeekStart = await firstHeader.getAttribute('data-schedule-week-start');
  await firstHeader.click();

  const listTrigger = page.locator('[data-testid="schedule-list-view"] [data-schedule-modal-trigger]:visible').first();
  await expect(listTrigger).toBeVisible({ timeout: 10_000 });
  await listTrigger.click();
  await expect(page.getByTestId('schedule-detail-modal')).toBeVisible({ timeout: 5_000 });
  await page.keyboard.press('Escape');
  await expect(page.getByTestId('schedule-detail-modal')).toBeHidden({ timeout: 5_000 });

  test.expect(firstWeekStart, 'expected a list header with a week start').toBeTruthy();
  const gridUrl = new URL(links[0]);
  gridUrl.searchParams.set('week_start', firstWeekStart || '');
  gridUrl.searchParams.set('date', firstWeekStart || '');
  gridUrl.searchParams.set('period', 'week');
  await page.goto(gridUrl.toString(), { waitUntil: 'domcontentloaded' });
  await page.getByTestId('schedule-grid-toggle').first().click();

  const gridTrigger = page.locator('[data-testid="schedule-grid-view-co"] [data-schedule-modal-trigger]:visible').first();
  await expect(gridTrigger).toBeVisible({ timeout: 10_000 });
  await gridTrigger.click();
  await expect(page.getByTestId('schedule-detail-modal')).toBeVisible({ timeout: 5_000 });
});
