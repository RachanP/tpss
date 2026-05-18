# Playwright E2E Tests

Automated UI tests live in `tests/e2e`.

## Commands

```bash
npm run test:e2e
npm run test:e2e:headed
npm run test:e2e:ui
npm run test:e2e:report
```

The default config starts Laravel with `php artisan serve` on `http://127.0.0.1:8010`.
Set `PLAYWRIGHT_BASE_URL` to test an already-running app instead.

## Database

Before the suite runs, `tests/e2e/support/global-setup.ts` runs:

```bash
php artisan migrate:fresh --seed --force
```

By default it targets `DB_DATABASE=tpss_testing` and refuses to run against a
database name that does not contain `test`, `testing`, or `e2e`.

Useful overrides:

```bash
E2E_SKIP_DB_SETUP=1 npm run test:e2e
DB_DATABASE=tpss_e2e npm run test:e2e
PLAYWRIGHT_BASE_URL=http://tpss.test npm run test:e2e
```

Use `E2E_ALLOW_DESTRUCTIVE_DB=1` only when you intentionally want
`migrate:fresh` against the configured database.

For the most deterministic local run, do not set `PLAYWRIGHT_BASE_URL`; let
Playwright start Laravel with the testing environment. If you do point at an
already-running app, make sure that app is also using the same test database.
