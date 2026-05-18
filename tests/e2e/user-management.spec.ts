import { expect, test } from '@playwright/test';
import { login } from './support/auth';

test.describe('Admin User Management', () => {
  test('admin sees seeded users on the index page', async ({ page }) => {
    await login(page, 'admin_01');
    await page.goto('/admin/users');

    await expect(page.locator('[data-testid="users-row"]').first()).toBeVisible();
    const rowCount = await page.locator('[data-testid="users-row"]').count();
    expect(rowCount).toBeGreaterThan(0);

    // admin_01 should appear in the table
    await expect(page.locator('[data-testid="users-row"][data-username="admin_01"]')).toHaveCount(1);
  });

  test('admin can create a new staff user via modal', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Modal layout is desktop-only for admin');
    await login(page, 'admin_01');
    await page.goto('/admin/users');

    const username = `e2e_staff_${Date.now()}`;

    await page.getByTestId('users-add-button').click();
    await page.getByTestId('user-form-name').fill('E2E Staff Tester');
    await page.getByTestId('user-form-username').fill(username);
    await page.getByTestId('user-form-email').fill(`${username}@example.com`);
    await page.getByTestId('user-form-password').fill('password123');
    await page.getByTestId('user-form-submit').click();

    await expect(page).toHaveURL(/\/admin\/users/);
    await expect(page.locator(`[data-testid="users-row"][data-username="${username}"]`)).toHaveCount(1);
  });

  test('admin can deactivate a user via edit modal and see status change', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Modal layout is desktop-only for admin');
    await login(page, 'admin_01');
    await page.goto('/admin/users');

    // Create a disposable user first so we don't affect seeded fixtures
    const username = `e2e_toggle_${Date.now()}`;
    await page.getByTestId('users-add-button').click();
    await page.getByTestId('user-form-name').fill('E2E Toggle Target');
    await page.getByTestId('user-form-username').fill(username);
    await page.getByTestId('user-form-email').fill(`${username}@example.com`);
    await page.getByTestId('user-form-password').fill('password123');
    await page.getByTestId('user-form-submit').click();

    const row = page.locator(`[data-testid="users-row"][data-username="${username}"]`);
    await expect(row).toHaveCount(1);
    await expect(row).toContainText('กำลังใช้งาน');

    // Open edit modal and set inactive
    await row.getByTestId('users-edit-button').click();
    await page.getByTestId('user-form-is-active').selectOption('0');
    await page.getByTestId('user-form-submit').click();

    await expect(page).toHaveURL(/\/admin\/users/);
    await expect(page.locator(`[data-testid="users-row"][data-username="${username}"]`)).toContainText('ระงับการใช้งาน');
  });

  test('non-admin cannot access user management page', async ({ page }) => {
    // Seeder creates staff_01 (or similar) — try staff role
    await login(page, 'staff_01');
    const response = await page.goto('/admin/users');

    // Should not see the user table; either 403 or redirected away
    expect(response?.status()).not.toBe(200);
  });
});
