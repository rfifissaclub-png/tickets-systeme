import { defineConfig, devices } from '@playwright/test'

// Targets the already-running docker-compose stack (backend + Postgres +
// Redis + frontend), the same one this suite's own scenarios were verified
// against by hand before being committed here — not a `vite dev` instance,
// which has no seeded backend of its own to talk to. `docker compose up -d`
// (and, once, `php artisan migrate:fresh --seed` for the fixtures this suite
// relies on — see e2e/support/accounts.ts) is a precondition, not something
// this config manages itself.
export default defineConfig({
  testDir: './e2e',
  globalSetup: './e2e/global-setup.ts',
  // The login endpoint this suite depends on (both directly in auth.spec.ts
  // and once per role in global-setup.ts) is rate-limited at 5/min with a
  // burst of 5 (nginx's `limit_req zone=api_auth`, doubled up by Laravel's
  // own `throttle:login`) — full parallelism reliably blows through that
  // the moment more than a handful of tests happen to log in around the
  // same moment. Every spec but auth.spec.ts itself reuses a role's
  // storageState (see e2e/support/auth-state.ts) instead of logging in, so
  // serial execution here costs little in practice.
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: 'list',
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8081',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
})
