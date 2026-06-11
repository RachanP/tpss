import { expect, test } from '@playwright/test';
import { login } from './support/auth';
import { findSchedulableOffering, fillCreateModal, selectTime } from './support/schedule';

/**
 * M3 — Schedule Management (CRUD + series)
 *
 *  - สร้าง slot วันเดียว → toast + modal ปิด
 *  - สร้าง slot (หัวข้อ unique) แล้วลบ → หายจากรายการ
 *  - สร้างชุดซ้ำรายสัปดาห์ (series) → สร้างหลาย slot
 *
 * ครอบ M3 (Schedule CRUD) + Priority 6 (series) · detail/edit modal UX อยู่ใน schedule-modal.spec.ts
 */

test.describe('M3 — Schedule CRUD', () => {
  test('course head can create a single-day slot', async ({ page }, testInfo) => {
    await login(page, 'head_med');
    const offeringUrl = await findSchedulableOffering(page);
    test.skip(!offeringUrl, 'No schedulable offering with a creatable student group.');

    await page.goto(offeringUrl, { waitUntil: 'domcontentloaded' });
    const startHour = testInfo.project.name === 'mobile-chrome' ? '14' : '10';
    const modal = await fillCreateModal(page, { date: '08/06/2569', startHour, topic: 'M3 create check' });

    const submit = modal.getByTestId('schedule-submit');
    await expect(submit).toBeEnabled({ timeout: 10_000 });
    const toast = page.locator('#tpss-toast').waitFor({ state: 'visible', timeout: 10_000 });
    await submit.click();

    await expect(page.getByTestId('schedule-create-modal')).toBeHidden({ timeout: 10_000 });
    await toast;
  });

  test('course head can create then delete a slot', async ({ page }, testInfo) => {
    await login(page, 'head_med');
    const offeringUrl = await findSchedulableOffering(page);
    test.skip(!offeringUrl, 'No schedulable offering with a creatable student group.');

    const uniqueTopic = 'M3 delete ' + Date.now();
    await page.goto(offeringUrl, { waitUntil: 'domcontentloaded' });
    const startHour = testInfo.project.name === 'mobile-chrome' ? '15' : '11';
    const modal = await fillCreateModal(page, { date: '09/06/2569', startHour, topic: uniqueTopic });

    const toast = page.locator('#tpss-toast').waitFor({ state: 'visible', timeout: 10_000 });
    await modal.getByTestId('schedule-submit').click();
    await expect(page.getByTestId('schedule-create-modal')).toBeHidden({ timeout: 10_000 });
    await toast;

    // เปิด list view แล้วหา slot จากหัวข้อ unique
    await page.goto(offeringUrl, { waitUntil: 'domcontentloaded' });
    await page.getByTestId('schedule-list-toggle').first().click();

    // กางทุก week group ที่มี เพื่อให้ trigger โผล่
    const groups = page.getByTestId('schedule-day-group-toggle');
    const groupCount = await groups.count();
    for (let i = 0; i < groupCount; i++) {
      await groups.nth(i).click().catch(() => {});
    }

    const trigger = page
      .locator('[data-testid="schedule-list-view"] [data-schedule-modal-trigger]:visible', { hasText: uniqueTopic })
      .first();
    await expect(trigger).toBeVisible({ timeout: 10_000 });
    await trigger.click();

    const detail = page.locator('[data-testid="schedule-detail-modal"]:visible');
    await expect(detail).toBeVisible({ timeout: 5_000 });
    await detail.getByTestId('schedule-delete-button').click();

    // tpssDelete → SweetAlert confirm
    await expect(page.locator('.swal2-popup')).toBeVisible({ timeout: 5_000 });
    await page.locator('.swal2-confirm').click();

    // หลังลบ — โหลดรายการใหม่ ไม่ควรพบหัวข้อนั้นอีก
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator(`text=${uniqueTopic}`)).toHaveCount(0, { timeout: 10_000 });
  });
});

test.describe('M3 — Weekly series', () => {
  test('course head can generate a weekly series', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Series modal is desktop-first');
    await login(page, 'head_med');
    const offeringUrl = await findSchedulableOffering(page);
    test.skip(!offeringUrl, 'No schedulable offering with a creatable student group.');

    await page.goto(offeringUrl, { waitUntil: 'domcontentloaded' });
    const createButton = page.locator('[data-testid="schedule-create-link"]:visible').first();
    await expect(createButton).toBeVisible({ timeout: 10_000 });
    await createButton.click();

    const modal = page.getByTestId('schedule-create-modal');
    await expect(modal).toBeVisible({ timeout: 5_000 });

    // เลือกโหมด series
    await modal.getByTestId('schedule-series-toggle').click();

    // เวลาแยกจากเทสอื่นเพื่อเลี่ยง conflict
    await selectTime(page, 'tp_start', '08', '17');
    await selectTime(page, 'tp_end', '08', '47');
    await modal.locator('select[name="activity_type_id"]').selectOption({ index: 1 });
    await modal.getByTestId('schedule-topic-input').fill('M3 series check');

    const instructor = modal.locator('input[data-testid="schedule-instructor"]:not(:disabled)').first();
    await expect(instructor).toBeVisible({ timeout: 10_000 });
    await instructor.check();
    const group = modal.locator('input[data-testid="schedule-group-option"]:not(:disabled)').first();
    await expect(group).toBeVisible({ timeout: 10_000 });
    await group.check();

    const toast = page.locator('#tpss-toast').waitFor({ state: 'visible', timeout: 15_000 });
    await modal.getByTestId('schedule-submit').click();
    await expect(page.getByTestId('schedule-create-modal')).toBeHidden({ timeout: 10_000 });
    await toast;
  });
});

/** เปิด list view + กางทุก week group + คลิก slot จากหัวข้อ → เปิด detail modal
 *  isoDate (ค.ศ. YYYY-MM-DD) = โฟกัสสัปดาห์ของ slot นั้นก่อน (กันกรณี list lazy ไม่โหลดสัปดาห์) */
async function openDetailByTopic(page: import('@playwright/test').Page, offeringUrl: string, topic: string, isoDate?: string) {
  const url = new URL(offeringUrl);
  if (isoDate) {
    url.searchParams.set('date', isoDate);
    url.searchParams.set('week_start', isoDate);
  }
  await page.goto(url.toString(), { waitUntil: 'domcontentloaded' });
  await page.getByTestId('schedule-list-toggle').first().click();
  const groups = page.getByTestId('schedule-day-group-toggle');
  const groupCount = await groups.count();
  for (let i = 0; i < groupCount; i++) await groups.nth(i).click().catch(() => {});

  const trigger = page
    .locator('[data-testid="schedule-list-view"] [data-schedule-modal-trigger]:visible', { hasText: topic })
    .first();
  await expect(trigger).toBeVisible({ timeout: 10_000 });
  await trigger.click();
  const detail = page.locator('[data-testid="schedule-detail-modal"]:visible');
  await expect(detail).toBeVisible({ timeout: 5_000 });
  return detail;
}

test.describe('M3 — Edit & block & copy', () => {
  test('course head can edit a slot topic', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Edit modal is desktop-first');
    test.setTimeout(90_000); // หน้าจัดตารางหนัก (หลาย slot/สัปดาห์) + หลาย navigation
    await login(page, 'head_med');
    const offeringUrl = await findSchedulableOffering(page);
    test.skip(!offeringUrl, 'No schedulable offering available.');

    const topic1 = 'M3 edit before ' + Date.now();
    const topic2 = topic1.replace('before', 'after');

    await page.goto(offeringUrl, { waitUntil: 'domcontentloaded' });
    const modal = await fillCreateModal(page, { date: '12/06/2569', startHour: '10', topic: topic1 });
    let toast = page.locator('#tpss-toast').waitFor({ state: 'visible', timeout: 10_000 });
    await modal.getByTestId('schedule-submit').click();
    await expect(page.getByTestId('schedule-create-modal')).toBeHidden({ timeout: 10_000 });
    await toast;

    // เปิด detail → edit → เปลี่ยนหัวข้อ → บันทึก
    const detail = await openDetailByTopic(page, offeringUrl, topic1, '2026-06-12'); // 12/06/2569
    await detail.getByTestId('schedule-edit-modal-trigger').click();

    const editModal = page.locator('[data-testid="schedule-edit-modal"]:visible');
    await expect(editModal).toBeVisible({ timeout: 5_000 });
    await editModal.locator('input[name="topic"]').fill(topic2);

    // edit เป็น full POST + redirect (ไม่ใช่ AJAX toast แบบ create)
    await Promise.all([
      page.waitForLoadState('domcontentloaded'),
      editModal.getByTestId('schedule-submit').click(),
    ]);
    // toast ยืนยันบันทึกสำเร็จหลัง redirect
    await expect(page.locator('#tpss-toast')).toContainText('บันทึกสำเร็จ', { timeout: 10_000 });

    // ยืนยัน rename: เปิด detail จากหัวข้อใหม่ได้ (ถ้า rename ไม่สำเร็จจะหาไม่เจอ)
    const detail2 = await openDetailByTopic(page, offeringUrl, topic2, '2026-06-12');
    await expect(detail2).toContainText(topic2);
  });

  test('course head can create a multi-day block slot', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Block create is desktop-first');
    await login(page, 'head_med');
    const offeringUrl = await findSchedulableOffering(page);
    test.skip(!offeringUrl, 'No schedulable offering available.');

    await page.goto(offeringUrl, { waitUntil: 'domcontentloaded' });
    const createButton = page.locator('[data-testid="schedule-create-link"]:visible').first();
    await createButton.click();
    const modal = page.getByTestId('schedule-create-modal');
    await expect(modal).toBeVisible({ timeout: 5_000 });

    // โหมดต่อเนื่องหลายวัน
    await modal.getByTestId('schedule-type-block').click();
    await modal.locator('input[name="start_date"]').fill('16/06/2569');
    await modal.locator('input[name="end_date"]').fill('18/06/2569');
    await selectTime(page, 'tp_start', '09', '17');
    await selectTime(page, 'tp_end', '09', '47');
    await modal.locator('select[name="activity_type_id"]').selectOption({ index: 1 });
    await modal.getByTestId('schedule-topic-input').fill('M3 block check');
    await modal.locator('input[data-testid="schedule-instructor"]:not(:disabled)').first().check();
    await modal.locator('input[data-testid="schedule-group-option"]:not(:disabled)').first().check();

    const toast = page.locator('#tpss-toast').waitFor({ state: 'visible', timeout: 10_000 });
    await modal.getByTestId('schedule-submit').click();
    await expect(page.getByTestId('schedule-create-modal')).toBeHidden({ timeout: 10_000 });
    await toast;
  });

  test('course head can copy a day to another date', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Copy modal is desktop-first');
    await login(page, 'head_med');
    const offeringUrl = await findSchedulableOffering(page);
    test.skip(!offeringUrl, 'No schedulable offering available.');

    // สร้าง slot ต้นทางที่ 19/06
    await page.goto(offeringUrl, { waitUntil: 'domcontentloaded' });
    const modal = await fillCreateModal(page, { date: '19/06/2569', startHour: '10', topic: 'M3 copy source' });
    const toast = page.locator('#tpss-toast').waitFor({ state: 'visible', timeout: 10_000 });
    await modal.getByTestId('schedule-submit').click();
    await expect(page.getByTestId('schedule-create-modal')).toBeHidden({ timeout: 10_000 });
    await toast;

    // เปิด copy modal → โหมดรายวัน → ต้นทาง 19/06 ปลายทาง 26/06
    await page.goto(offeringUrl, { waitUntil: 'domcontentloaded' });
    await page.getByTestId('schedule-copy-week-button').first().click();
    const copyModal = page.getByTestId('schedule-copy-week-modal');
    await expect(copyModal).toBeVisible({ timeout: 5_000 });
    await copyModal.getByRole('button', { name: 'รายวัน' }).click();
    await copyModal.getByTestId('copy-day-source-date').fill('19/06/2569');
    await copyModal.getByTestId('copy-day-target-date').fill('26/06/2569');

    // รอ preview ตรวจ conflict → ปุ่มยืนยันเปิดเมื่อมีรายการคัดลอกได้
    const confirm = copyModal.getByTestId('schedule-copy-week-confirm');
    await expect(confirm).toBeEnabled({ timeout: 15_000 });
    const copyToast = page.locator('#tpss-toast').waitFor({ state: 'visible', timeout: 15_000 });
    await confirm.click();
    await copyToast;
  });
});
