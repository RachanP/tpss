import { test, expect, type Locator, type Page } from '@playwright/test';
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

async function expectCustomSelectOpensBelow(modal: Locator, selectName: string) {
  const select = modal.locator(`select[name="${selectName}"]`);
  const wrapper = select.locator('xpath=..');
  await expect(wrapper).toHaveClass(/tpss-select/, { timeout: 5000 });

  const trigger = wrapper.locator('.tpss-select-trigger');
  await trigger.scrollIntoViewIfNeeded();
  await trigger.click();

  const menu = wrapper.locator('.tpss-select-menu:not([hidden])');
  await expect(menu).toBeVisible({ timeout: 5000 });

  const triggerBox = await trigger.boundingBox();
  const menuBox = await menu.boundingBox();
  expect(triggerBox, `${selectName} trigger has a layout box`).not.toBeNull();
  expect(menuBox, `${selectName} menu has a layout box`).not.toBeNull();
  expect(menuBox!.y, `${selectName} dropdown opens below the trigger`).toBeGreaterThanOrEqual(
    triggerBox!.y + triggerBox!.height - 1,
  );

  await trigger.click();
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

test('schedule create modal dropdowns open below fields without covering date inputs', async ({ page }) => {
  await login(page, 'head_med');

  await page.goto('/maker/course-offerings', { waitUntil: 'domcontentloaded' });
  const links = await page.getByTestId('course-offering-schedule-link').evaluateAll((els) => els.map((a: HTMLAnchorElement) => a.href));
  test.expect(links.length, 'expected at least one offering with schedules').toBeGreaterThan(0);

  await page.goto(links[0], { waitUntil: 'domcontentloaded' });
  const createButton = page.locator('[data-testid="schedule-create-link"]:visible').first();
  await expect(createButton).toBeVisible({ timeout: 10_000 });
  await createButton.click();

  const modal = page.getByTestId('schedule-create-modal');
  await expect(modal).toBeVisible({ timeout: 5_000 });
  await modal.getByTestId('schedule-type-block').click();

  await expectCustomSelectOpensBelow(modal, 'activity_type_id');
  await expectCustomSelectOpensBelow(modal, 'room_id');
});

test('schedule copy modal supports day and custom range layout without page-level overflow', async ({ page }) => {
  await login(page, 'head_med');

  await page.goto('/maker/course-offerings', { waitUntil: 'domcontentloaded' });
  const links = await page.getByTestId('course-offering-schedule-link').evaluateAll((els) => els.map((a: HTMLAnchorElement) => a.href));
  test.expect(links.length, 'expected at least one offering with schedules').toBeGreaterThan(0);

  await page.goto(links[0], { waitUntil: 'domcontentloaded' });

  const copyButton = page.getByTestId('schedule-copy-week-button');
  await expect(copyButton).toBeVisible({ timeout: 10_000 });
  await copyButton.click();

  const modal = page.getByTestId('schedule-copy-week-modal');
  await expect(modal).toBeVisible({ timeout: 5_000 });
  await expect(modal.locator('.copy-mode-option')).toHaveCount(3);

  const weeklyUnexpectedScrollables = await modal.evaluate((root) => {
    return Array.from(root.querySelectorAll<HTMLElement>('.modal-form-body *'))
      .filter((el) => {
        if (el.closest('.copy-week-preview-scroll') || el.closest('.tdi-pop-menu')) return false;

        const style = window.getComputedStyle(el);
        const rect = el.getBoundingClientRect();
        const canScroll = /(auto|scroll)/.test(style.overflowY) && el.scrollHeight > el.clientHeight + 2;

        return canScroll && rect.width > 0 && rect.height > 0;
      })
      .map((el) => el.className || el.tagName.toLowerCase());
  });
  expect(weeklyUnexpectedScrollables, 'weekly source/target controls do not create their own scrollbar').toEqual([]);

  const weekStepMetrics = await modal.locator('.copy-week-target-control').evaluateAll((controls) => {
    return controls.map((control) => {
      const buttons = Array.from(control.querySelectorAll<HTMLElement>('.copy-week-step'));
      const boxes = buttons.map((button) => button.getBoundingClientRect());

      return {
        topDelta: Math.max(...boxes.map((box) => box.top)) - Math.min(...boxes.map((box) => box.top)),
        positions: buttons.map((button) => window.getComputedStyle(button).position),
        heights: boxes.map((box) => box.height),
      };
    });
  });
  expect(
    weekStepMetrics.every((metrics) => metrics.topDelta <= 2 && metrics.positions.every((position) => position === 'static')),
    'week step buttons stay inline instead of being treated as modal close buttons',
  ).toBe(true);

  const rangeMode = modal.getByRole('button', { name: 'ช่วงวันที่' });
  await rangeMode.click();
  await expect(rangeMode).toHaveClass(/is-active/);
  const modeMetrics = await modal.locator('.copy-mode-option').evaluateAll((buttons) => {
    const boxes = buttons.map((button) => button.getBoundingClientRect());
    return {
      topDelta: Math.max(...boxes.map((box) => box.top)) - Math.min(...boxes.map((box) => box.top)),
      heights: boxes.map((box) => box.height),
      whiteSpaces: buttons.map((button) => window.getComputedStyle(button).whiteSpace),
    };
  });
  expect(modeMetrics.topDelta, 'copy mode segmented buttons stay in one row').toBeLessThanOrEqual(2);
  expect(modeMetrics.whiteSpaces.every((value) => value === 'nowrap'), 'copy mode labels stay on one line').toBe(true);
  const rangeModeBox = await rangeMode.boundingBox();
  expect(rangeModeBox, 'range copy mode button has a layout box').not.toBeNull();
  expect(rangeModeBox!.height, 'range copy mode button remains one compact segment').toBeLessThanOrEqual(44);

  await expect(modal.locator('input[type="date"]')).toHaveCount(0);

  const targetStart = modal.getByTestId('copy-range-target-start-date');
  await expect(targetStart).toBeVisible();
  const targetControl = targetStart.locator('xpath=ancestor::div[contains(@class,"tdi-control")]');
  await targetControl.locator('.tdi-cal-btn').click();

  const calendar = modal.locator('.tdi-pop:visible').first();
  await expect(calendar).toBeVisible({ timeout: 5_000 });
  await expect(calendar).toHaveCSS('position', 'fixed');
  const controlBox = await targetControl.boundingBox();
  const calendarBox = await calendar.boundingBox();
  expect(controlBox, 'target date control has a layout box').not.toBeNull();
  expect(calendarBox, 'calendar popover has a layout box').not.toBeNull();
  const horizontalOverlap = Math.min(calendarBox!.x + calendarBox!.width, controlBox!.x + controlBox!.width)
    - Math.max(calendarBox!.x, controlBox!.x);
  expect(horizontalOverlap, 'calendar popover stays anchored to the active date field').toBeGreaterThan(80);

  if ((page.viewportSize()?.width || 0) >= 700) {
    expect(
      Math.abs((calendarBox!.x + calendarBox!.width) - (controlBox!.x + controlBox!.width)),
      'desktop calendar popover aligns near the calendar icon instead of the modal left edge',
    ).toBeLessThanOrEqual(18);
  }

  const unexpectedTopScrollables = await modal.evaluate((root) => {
    return Array.from(root.querySelectorAll<HTMLElement>('.modal-form-body *'))
      .filter((el) => {
        if (el.closest('.copy-week-preview-scroll') || el.closest('.tdi-pop-menu')) return false;

        const style = window.getComputedStyle(el);
        const rect = el.getBoundingClientRect();
        const canScroll = /(auto|scroll)/.test(style.overflowY) && el.scrollHeight > el.clientHeight + 2;

        return canScroll && rect.width > 0 && rect.height > 0;
      })
      .map((el) => el.className || el.tagName.toLowerCase());
  });
  expect(unexpectedTopScrollables, 'date controls and calendar area do not create their own scrollbar').toEqual([]);

  await page.locator('.schedule-shell').evaluate((el: Element) => {
    const shell = (el as HTMLElement & { __tpssScheduleShell?: any }).__tpssScheduleShell;
    shell.copyWeekLoading = false;
    shell.copyWeekPreview = {
      total: 6,
      ready: Array.from({ length: 5 }, (_, index) => ({
        topic: `Ready ${index + 1}`,
        target_date: `2026-08-${String(index + 10).padStart(2, '0')}`,
        time: '09:00-11:30',
        room: 'R-301',
      })),
      blocked: [{
        topic: 'Blocked 1',
        target_date: '2026-08-20',
        time: '09:00-11:30',
        reasons: ['ห้อง/สถานที่ชนกับรายการเดิม'],
      }],
    };
  });

  const previewScroll = modal.locator('.copy-week-preview-scroll');
  await expect(previewScroll).toBeVisible({ timeout: 5_000 });
  await expect(previewScroll.locator('.copy-week-item')).toHaveCount(6);
  const bodyOverflowY = await modal.locator('.modal-form-body').evaluate((el) => window.getComputedStyle(el).overflowY);
  expect(bodyOverflowY, 'copy modal body does not own the preview scrollbar').toBe('hidden');
  const previewMetrics = await previewScroll.evaluate((el) => ({
    clientHeight: el.clientHeight,
    scrollHeight: el.scrollHeight,
    overflowY: window.getComputedStyle(el).overflowY,
  }));

  expect(previewMetrics.overflowY).toBe('auto');
  expect(previewMetrics.clientHeight, 'preview area is capped at about four rows').toBeLessThanOrEqual(316);
  expect(previewMetrics.scrollHeight, 'overflow stays inside the preview area').toBeGreaterThan(previewMetrics.clientHeight);
  const previewBox = await previewScroll.boundingBox();
  const actionBox = await modal.locator('.schedule-copy-week-actions').boundingBox();
  expect(previewBox, 'preview scroll area has a layout box').not.toBeNull();
  expect(actionBox, 'copy modal footer has a layout box').not.toBeNull();
  expect(previewBox!.y + previewBox!.height, 'preview list stays above the fixed action footer').toBeLessThanOrEqual(actionBox!.y + 1);
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
