import { expect, type Page } from '@playwright/test';

export async function login(page: Page, username = 'admin_01', password = 'password') {
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await page.getByTestId('login-username').fill(username);
  await page.getByTestId('login-password').fill(password);
  await page.getByTestId('login-submit').click();
  // staff ลงที่ /staff/settings (ไม่มี dashboard) — รับ landing ของทุก role
  await expect(page).toHaveURL(/\/dashboard|\/admin\/dashboard|\/staff\/(dashboard|settings)|\/maker\/dashboard|\/lecturer\/dashboard/);
}

export async function switchRole(page: Page, role: 'admin' | 'staff' | 'course_head' | 'instructor' | 'executive') {
  await page
    .locator(`form:has(input[name="role"][value="${role}"])`)
    .evaluate((form) => (form as HTMLFormElement).submit());
  await expect(page).toHaveURL(/\/dashboard/);
}
