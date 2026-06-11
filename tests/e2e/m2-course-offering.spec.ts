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
