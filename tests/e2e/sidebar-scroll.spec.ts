import { expect, test } from '@playwright/test';
import { login } from './support/auth';

test.describe('Sidebar scroll restore (Bug #10)', () => {
  test('sidebar scrollTop is restored after navigating to a new page', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'Sidebar is collapsed on mobile');

    await login(page, 'admin_01');

    const sidebar = page.getByTestId('sidebar-scroll');
    await expect(sidebar).toBeVisible();

    // Make sure the sidebar has scrollable overflow — admin role has the most menu items.
    // If the viewport is tall enough that scrolling isn't needed, skip the test gracefully.
    const scrollable = await sidebar.evaluate((el) => el.scrollHeight > el.clientHeight + 20);
    test.skip(!scrollable, 'Sidebar fits viewport on this resolution — no scroll to restore');

    // Scroll the sidebar to a recognisable position and wait for the debounced save to commit.
    await sidebar.evaluate((el) => { el.scrollTop = 120; });
    await page.waitForTimeout(250);   // debounce window is 100ms; allow margin

    const savedScroll = await page.evaluate(() => Number(window.localStorage.getItem('tpss.sidebar.scrollTop.admin') ?? '0'));
    expect(savedScroll).toBeGreaterThanOrEqual(100);

    // Navigate to another admin page — sidebar re-renders during the request
    await page.goto('/admin/master-data');
    await expect(page.getByTestId('sidebar-scroll')).toBeVisible();

    const restoredScroll = await page.getByTestId('sidebar-scroll').evaluate((el) => el.scrollTop);
    expect(restoredScroll).toBeGreaterThanOrEqual(100);
  });
});
