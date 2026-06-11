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

/* ───────────────────────────────────────────────────────────────────────────
 * M2 — Course Offering navigation + Activity Topics (V4) + Instructor pool
 * เดิมแยกอยู่ m2-course-offering.spec.ts
 * ─────────────────────────────────────────────────────────────────────────── */

test.describe('M2 — Course offering navigation (course head)', () => {
  test('course head sees offerings and can open an offering detail', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Offering tables are desktop-first');
    await login(page, 'head_med');
    await page.goto('/maker/course-offerings');

    await expect(page.getByTestId('offering-summary').first()).toBeVisible({ timeout: 15000 });

    const showLink = page.getByTestId('course-offering-show-link').first();
    await expect(showLink).toBeVisible();
    await showLink.click();

    // หน้ารายละเอียด offering — ปุ่มย้อนกลับเป็น marker ที่เสถียร
    await expect(page.getByTestId('back-to-offerings')).toBeVisible({ timeout: 10000 });
  });
});

test.describe('M2 — Activity topics per course (V4 dropdown)', () => {
  test('admin can add an activity topic and it persists on reopen', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Topics modal is desktop-only');
    await login(page, 'admin_01');
    await page.goto('/admin/master-data?tab=courses');

    const topicsBtn = page.getByTestId('courses-topics-button').first();
    await expect(topicsBtn).toBeVisible({ timeout: 15000 });
    await topicsBtn.click();

    // modal โหลดหัวข้อเดิมผ่าน AJAX — เพิ่มแถวใหม่
    const topicName = 'หัวข้อทดสอบ E2E ' + Date.now();
    await page.getByRole('button', { name: '+ เพิ่มหัวข้อ' }).click();
    const lastInput = page.locator('input[data-topic-input]').last();
    await lastInput.fill(topicName);
    await page.getByTestId('course-topics-submit').click();

    await page.waitForURL(/master-data/);

    // เปิดซ้ำ → ต้องเห็นหัวข้อที่เพิ่ง save (โหลดจาก DB ผ่าน AJAX, value เป็น property จาก x-model)
    await page.getByTestId('courses-topics-button').first().click();
    const inputs = page.locator('input[data-topic-input]');
    await expect(async () => {
      const values = await inputs.evaluateAll((els) => els.map((e) => (e as HTMLInputElement).value));
      expect(values).toContain(topicName);
    }).toPass({ timeout: 10000 });
  });
});

test.describe('M2 — Instructor pool (delegation V4)', () => {
  test('course head can add then remove an instructor in the pool', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Instructor pool combobox is desktop-first');
    await login(page, 'head_med');
    await page.goto('/maker/course-offerings');
    await page.getByTestId('course-offering-show-link').first().click();
    await expect(page.getByTestId('back-to-offerings')).toBeVisible({ timeout: 10_000 });

    // เข้าโหมดแก้ไขส่วนชุดผู้สอน
    await page.getByTestId('section-edit-quick-toggle-instructors').click();

    const cards = page.locator('.instructor-pool-card');
    const before = await cards.count();
    // ปุ่มลบของหัวหน้าวิชาถูกซ่อน (x-show=false) → เลือกเฉพาะตัวที่ visible
    const removeBtns = page.locator('[data-testid="instructor-pool-remove"]:visible');

    // ต้องมีผู้สอนที่ลบได้ (non-coordinator) อย่างน้อย 1 — ไม่งั้นข้าม
    test.skip((await removeBtns.count()) === 0, 'No removable instructor in this offering pool.');

    async function fillNoteIfPrompted(text: string) {
      // โมดัลเหตุผลโผล่แบบ async หลัง predictor — รอสักครู่ก่อนตัดสินใจ
      const noteSubmit = page.getByTestId('instructor-pool-note-submit');
      const appeared = await noteSubmit.waitFor({ state: 'visible', timeout: 4000 }).then(() => true).catch(() => false);
      if (appeared) {
        await page.getByTestId('instructor-pool-note-text').fill(text);
        await noteSubmit.click();
        await expect(noteSubmit).toBeHidden({ timeout: 5000 });
      }
    }

    // ── ลบผู้สอน 1 คน → pool ลดลง (คนนี้กลับมา available ใน dropdown ภาควิชาเดียวกัน) ──
    await removeBtns.first().click();
    await fillNoteIfPrompted('E2E ลบผู้สอนชั่วคราว');
    await expect(cards).toHaveCount(before - 1, { timeout: 10_000 });

    // ── เพิ่มกลับผ่าน combobox (default เฉพาะภาควิชานี้ → ผ่าน department gate) ──
    const search = page.getByTestId('instructor-pool-search');
    await search.click();
    await search.fill('');

    const option = page.getByTestId('instructor-pool-option').first();
    await expect(option).toBeVisible({ timeout: 10_000 });
    await option.click();
    await fillNoteIfPrompted('E2E เพิ่มผู้สอนกลับ');

    await expect(cards).toHaveCount(before, { timeout: 10_000 });
  });
});
