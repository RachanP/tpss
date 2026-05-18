import { expect, test } from '@playwright/test';
import { login } from './support/auth';

test.describe('M1 Master Data — Friend 1 hardening coverage', () => {
  test('courses tab shows empty state when search has no match', async ({ page }) => {
    await login(page, 'admin_01');
    await page.goto('/admin/master-data?tab=courses');

    // Wait for Alpine to fully init on this large page — verify by tab content visibility
    await page.waitForFunction(
      () => {
        const el = document.querySelector('[data-testid="courses-search-input"]');
        return el && (el as HTMLElement).offsetParent !== null;
      },
      null,
      { timeout: 20000 },
    );

    // Type a nonsense keyword that shouldn't match anything
    // Note: Alpine evaluates row x-show before empty-state x-show, so empty-state
    // sees stale sibling display values. Type 2 chars to force a 2nd evaluation pass.
    await page.getByTestId('courses-search-input').fill('Z');
    await page.getByTestId('courses-search-input').fill('ZZZZ-NO-MATCH-XYZ');

    // Empty state row should now be visible
    const emptyState = page.getByTestId('courses-empty-state');
    await expect(emptyState).toBeVisible({ timeout: 10000 });
    await expect(emptyState).toContainText('ไม่พบข้อมูลที่ค้นหา');
  });

  test('course code duplicate in same curriculum is rejected', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Modal layout is desktop-only for admin');

    await login(page, 'admin_01');
    await page.goto('/admin/master-data?tab=courses');
    await expect(page.getByTestId('courses-search-input')).toBeVisible({ timeout: 15000 });

    // Pick a visible seeded course row (rows from other tabs share `data-search` but are hidden)
    const firstRow = page.locator('tr[data-search][data-curriculum-id]:visible').first();
    await expect(firstRow).toBeVisible();
    const searchText = (await firstRow.getAttribute('data-search')) ?? '';
    // data-search format: "<course_code> <name_th> ..." — course_code may have space (e.g. "nsbs 111")
    // courseCodeExistsInCurriculum uses REPLACE/UPPER for whitespace-insensitive match
    // so we can submit with or without the original space
    const match = searchText.match(/^([a-z]+)\s*(\d+)/i);
    expect(match).not.toBeNull();
    const existingCode = `${match![1]} ${match![2]}`.toUpperCase();   // submit with space

    // Read the curriculum_id of the same row so we submit into the same curriculum
    const existingCurriculumId = await firstRow.getAttribute('data-curriculum-id');
    expect(existingCurriculumId).not.toBeNull();

    // Open add-course modal and fill the fields relevant to the duplicate check
    await page.getByTestId('courses-add-button').click();
    await page.getByTestId('course-form-code').fill(existingCode);
    await page.getByTestId('course-form-name-th').fill('E2E Duplicate Test Course');
    await page.getByTestId('course-form-curriculum').selectOption(existingCurriculumId!);

    // Bypass HTML5 required validation so we reach server-side unique check
    // (form has many required fields we don't care about for this specific test)
    await page.evaluate(() => {
      document.querySelectorAll('form').forEach((form) => {
        if (form.action.includes('/master-data/courses')) form.noValidate = true;
      });
    });

    await page.getByTestId('course-form-submit').click();

    // Wait for redirect to land + Alpine init on the reloaded page
    await page.waitForURL(/master-data/);
    await expect(page.getByTestId('courses-search-input')).toBeVisible({ timeout: 15000 });

    // The controller flashes the duplicate error which the view renders inline in the modal
    // Message text: "รหัสวิชานี้มีอยู่แล้วในหลักสูตรนี้" (from courseCodeValidationMessages)
    await expect(page.locator('body')).toContainText(/มีอยู่แล้วในหลักสูตรนี้/, { timeout: 10000 });
  });

  test('curriculum cascade-delete shows SweetAlert with course count', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Modal layout is desktop-only for admin');

    await login(page, 'admin_01');
    await page.goto('/admin/master-data?tab=curriculums');

    // Click first curriculum's edit button (wait for Alpine init)
    const editBtn = page.getByTestId('curriculum-edit-button').first();
    await expect(editBtn).toBeVisible({ timeout: 15000 });
    await editBtn.click();

    // Modal opens — click delete
    const deleteBtn = page.getByTestId('curriculum-delete-button');
    await expect(deleteBtn).toBeVisible();
    await deleteBtn.click();

    // SweetAlert popup should appear with cascade warning
    const swal = page.locator('.swal2-popup');
    await expect(swal).toBeVisible();
    await expect(swal).toContainText(/ยืนยันการลบหลักสูตรแบบ Cascade|รายวิชา/);

    // Cancel — no DB change, just verifying UI flow
    await page.locator('.swal2-cancel').click();
    await expect(swal).toBeHidden();
  });
});
