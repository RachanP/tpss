import { test, expect, type Page } from '@playwright/test';
import { login } from './support/auth';

async function selectTime(page, pickerId: string, hour: string, minute: string) {
  await page.locator(`#${pickerId}`).click();
  await page.locator(`.tp-drop.tp-open .tp-hour-item[data-val="${hour}"][data-cycle="1"]`).click();
  await page.locator(`.tp-drop.tp-open .tp-min-item[data-val="${minute}"][data-cycle="1"]`).click();
}

async function ensureStudentGroup(page: Page, scheduleUrl: string) {
  const detailUrl = new URL(scheduleUrl);
  detailUrl.pathname = detailUrl.pathname.replace(/\/schedules\/?$/, '');
  detailUrl.search = '';
  detailUrl.hash = 'student-groups';

  await page.goto(detailUrl.toString(), { waitUntil: 'domcontentloaded' });
  const source = page.getByTestId('group-editor-source');
  if (!(await source.count())) return false;

  const cohortId = await source.locator('option').nth(1).getAttribute('value').catch(() => null);
  if (!cohortId) return false;

  const token = await page.locator('input[name="_token"]').first().getAttribute('value');
  const saveUrl = new URL(detailUrl.toString());
  saveUrl.hash = '';
  saveUrl.pathname = `${saveUrl.pathname}/student-groups/save`;

  const response = await page.request.post(saveUrl.toString(), {
    form: {
      'rows[0][cohort_group_id]': cohortId,
      'rows[0][group_code]': `E2E-${Date.now()}`,
      'rows[0][student_count]': '1',
      'rows[0][color_code]': '#2563eb',
    },
    headers: token ? { 'X-CSRF-TOKEN': token, Accept: 'application/json' } : { Accept: 'application/json' },
  });

  return response.ok();
}

test('schedule modal shows muted "ไม่มีผู้สอน" and datepicker popover appears', async ({ page }) => {
  await login(page, 'head_med');

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
  const calBtn = modalLocator.locator('.tdi-cal-btn:visible').first();
  if (await calBtn.count()) {
    await calBtn.click();
    const pop = page.locator('.tdi-pop:visible').first();
    await expect(pop).toBeVisible({ timeout: 5000 });
  }
});

test('schedule create modal accepts a filled single-day schedule and closes after save', async ({ page }, testInfo) => {
  await login(page, 'head_med');

  await page.goto('/maker/course-offerings', { waitUntil: 'domcontentloaded' });
  const links = await page.getByTestId('course-offering-schedule-link').evaluateAll((els) => els.map((a: HTMLAnchorElement) => a.href));
  test.expect(links.length, 'expected at least one offering with schedules').toBeGreaterThan(0);

  let selectedLink = '';

  for (const link of links) {
    if (await ensureStudentGroup(page, link)) {
      selectedLink = link;
      break;
    }
  }

  test.skip(!selectedLink, 'No seeded course offering can create a student group for create-modal save coverage.');

  await page.goto(selectedLink, { waitUntil: 'domcontentloaded' });
  const createButton = page.locator('[data-testid="schedule-create-link"]:visible').first();
  await expect(createButton).toBeVisible({ timeout: 10_000 });
  await createButton.click();

  const modal = page.getByTestId('schedule-create-modal');
  await expect(modal).toBeVisible({ timeout: 5_000 });
  await expect(modal.locator('input[type="time"]')).toHaveCount(0);

  const startHour = testInfo.project.name === 'mobile-chrome' ? '14' : '13';
  await modal.locator('input[name="start_date"]').fill('08/12/2569');
  await selectTime(page, 'tp_start', startHour, '17');
  await selectTime(page, 'tp_end', startHour, '47');

  await modal.locator('select[name="activity_type_id"]').selectOption({ index: 1 });
  await modal.getByTestId('schedule-topic-input').fill('E2E modal save check');

  const instructor = modal.locator('input[data-testid="schedule-instructor"]:not(:disabled)').first();
  await expect(instructor).toBeVisible({ timeout: 10_000 });
  await instructor.check();

  const group = modal.locator('input[data-testid="schedule-group-option"]:not(:disabled)').first();
  await expect(group).toBeVisible({ timeout: 10_000 });
  await group.check();

  const submit = modal.getByTestId('schedule-submit');
  await expect(submit).toBeEnabled({ timeout: 10_000 });
  await submit.click();

  await expect(page.getByTestId('schedule-create-modal')).toBeHidden({ timeout: 10_000 });
  await expect(page.locator('#tpss-toast')).toBeVisible({ timeout: 10_000 });
});

test('schedule detail modal opens from lazy list rows and grid cards', async ({ page }) => {
  await login(page, 'head_med');

  await page.goto('/maker/course-offerings', { waitUntil: 'domcontentloaded' });
  const links = await page.getByTestId('course-offering-schedule-link').evaluateAll((els) => els.map((a: HTMLAnchorElement) => a.href));
  test.expect(links.length, 'expected at least one offering with schedules').toBeGreaterThan(0);

  let selectedLink = '';
  let firstWeekStart: string | null = null;

  for (const link of links) {
    await page.goto(link, { waitUntil: 'domcontentloaded' });
    await page.getByTestId('schedule-list-toggle').first().click();

    const header = page.getByTestId('schedule-day-group-toggle').first();
    if (await header.count()) {
      await expect(header).toBeVisible({ timeout: 10_000 });
      firstWeekStart = await header.getAttribute('data-schedule-week-start');
      await header.click();
      selectedLink = link;
      break;
    }
  }

  test.skip(!selectedLink, 'No seeded schedule rows are available for detail-modal coverage.');

  const listTrigger = page.locator('[data-testid="schedule-list-view"] [data-schedule-modal-trigger]:visible').first();
  await expect(listTrigger).toBeVisible({ timeout: 10_000 });
  await listTrigger.click();
  await expect(page.getByTestId('schedule-detail-modal')).toBeVisible({ timeout: 5_000 });
  await page.keyboard.press('Escape');
  await expect(page.getByTestId('schedule-detail-modal')).toBeHidden({ timeout: 5_000 });

  test.expect(firstWeekStart, 'expected a list header with a week start').toBeTruthy();
  const gridUrl = new URL(selectedLink);
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
