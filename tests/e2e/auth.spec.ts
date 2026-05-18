import { expect, test } from '@playwright/test';
import { login } from './support/auth';

test('seeded admin can sign in and sign out', async ({ page }) => {
  await login(page, 'admin_01');

  await expect(page).toHaveURL(/\/admin\/dashboard/);
  await page.locator('form[action$="/logout"]').last().evaluate((form) => (form as HTMLFormElement).submit());
  await expect(page).toHaveURL(/\/login/);
});

test('invalid credentials stay on login with an error', async ({ page }) => {
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await page.getByTestId('login-username').fill('admin_01');
  await page.getByTestId('login-password').fill('wrong-password');
  await page.getByTestId('login-submit').click();

  await expect(page).toHaveURL(/\/login/);
  await expect(page.locator('#alert-msg')).toBeVisible();
});
