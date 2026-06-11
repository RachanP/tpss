import { expect, type Page } from '@playwright/test';

/** เลือกเวลาในตัวเลือกเวลา custom (tp_start / tp_end) */
export async function selectTime(page: Page, pickerId: string, hour: string, minute: string) {
  await page.locator(`#${pickerId}`).click();
  await page.locator(`.tp-drop.tp-open .tp-hour-item[data-val="${hour}"][data-cycle="1"]`).click();
  await page.locator(`.tp-drop.tp-open .tp-min-item[data-val="${minute}"][data-cycle="1"]`).click();
}

/**
 * สร้างกลุ่มนักศึกษาให้ offering (จำเป็นก่อนบันทึก slot เพราะ field กลุ่ม required)
 * คืน true ถ้าสร้างได้ — ใช้ POST ตรงไปที่ student-groups/save ผ่าน request context
 */
export async function ensureStudentGroup(page: Page, scheduleUrl: string): Promise<boolean> {
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

/**
 * หา offering ที่จัดตารางได้ + สร้างกลุ่มให้พร้อม → คืน URL หน้าจัดตารางของ offering นั้น
 * (ถ้าไม่มี offering ที่ใช้ได้ คืน '')
 */
export async function findSchedulableOffering(page: Page): Promise<string> {
  await page.goto('/maker/course-offerings', { waitUntil: 'domcontentloaded' });
  const links = await page
    .getByTestId('course-offering-schedule-link')
    .evaluateAll((els) => els.map((a) => (a as HTMLAnchorElement).href));

  for (const link of links) {
    if (await ensureStudentGroup(page, link)) return link;
  }
  return '';
}

/** เปิด modal สร้าง slot + กรอกข้อมูลพื้นฐานครบ (วันที่/เวลา/ประเภท/หัวข้อ/ผู้สอน/กลุ่ม) */
export async function fillCreateModal(
  page: Page,
  opts: { date: string; startHour: string; topic: string },
) {
  const createButton = page.locator('[data-testid="schedule-create-link"]:visible').first();
  await expect(createButton).toBeVisible({ timeout: 10_000 });
  await createButton.click();

  const modal = page.getByTestId('schedule-create-modal');
  await expect(modal).toBeVisible({ timeout: 5_000 });

  await modal.locator('input[name="start_date"]').fill(opts.date);
  await selectTime(page, 'tp_start', opts.startHour, '17');
  await selectTime(page, 'tp_end', opts.startHour, '47');

  await modal.locator('select[name="activity_type_id"]').selectOption({ index: 1 });
  await modal.getByTestId('schedule-topic-input').fill(opts.topic);

  const instructor = modal.locator('input[data-testid="schedule-instructor"]:not(:disabled)').first();
  await expect(instructor).toBeVisible({ timeout: 10_000 });
  await instructor.check();

  const group = modal.locator('input[data-testid="schedule-group-option"]:not(:disabled)').first();
  await expect(group).toBeVisible({ timeout: 10_000 });
  await group.check();

  return modal;
}
