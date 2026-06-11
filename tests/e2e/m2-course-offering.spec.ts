import { expect, test } from '@playwright/test';
import { login } from './support/auth';

/**
 * M2 — Course Management: Course Offering + Activity Topics (V4)
 *
 *  - course_head: รายการ offering แสดง + เปิดหน้ารายละเอียดได้
 *  - admin: เพิ่มหัวข้อกิจกรรม (activity_topics) ของวิชา แล้วค่าคงอยู่เมื่อเปิดซ้ำ
 *
 * student groups (V4 cohort) ครอบคลุมใน m2-course-management.spec.ts
 */

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
