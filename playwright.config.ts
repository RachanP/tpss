import { defineConfig, devices } from '@playwright/test';

const host = process.env.PLAYWRIGHT_HOST ?? '127.0.0.1';
const port = Number(process.env.PLAYWRIGHT_PORT ?? 8010);
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? `http://${host}:${port}`;
const shouldStartServer = !process.env.PLAYWRIGHT_BASE_URL;

export const laravelE2EEnv = {
  ...process.env,
  APP_ENV: process.env.APP_ENV ?? 'testing',
  APP_DEBUG: process.env.APP_DEBUG ?? 'true',
  BCRYPT_ROUNDS: process.env.BCRYPT_ROUNDS ?? '4',
  BROADCAST_CONNECTION: process.env.BROADCAST_CONNECTION ?? 'null',
  CACHE_STORE: process.env.CACHE_STORE ?? 'array',
  DB_CONNECTION: process.env.DB_CONNECTION ?? 'mysql',
  DB_DATABASE: process.env.DB_DATABASE ?? 'tpss_testing',
  MAIL_MAILER: process.env.MAIL_MAILER ?? 'array',
  QUEUE_CONNECTION: process.env.QUEUE_CONNECTION ?? 'sync',
  SESSION_DRIVER: process.env.SESSION_DRIVER ?? 'database',
};

export default defineConfig({
  testDir: './tests/e2e',
  globalSetup: './tests/e2e/support/global-setup.ts',
  timeout: 30_000,
  expect: {
    timeout: 7_000,
  },
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['github'], ['html']] : [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  webServer: shouldStartServer
    ? {
        command: `php artisan serve --host=${host} --port=${port}`,
        url: baseURL,
        reuseExistingServer: false,
        timeout: 120_000,
        env: laravelE2EEnv,
      }
    : undefined,
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'mobile-chrome',
      use: { ...devices['Pixel 7'] },
    },
  ],
});
