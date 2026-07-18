import { expect, test } from '@playwright/test'

import { ACCOUNTS, PASSWORD } from './support/accounts'
import { login } from './support/login'

// Only two representative roles get a real login here (global-setup.ts
// already proves all six can log in, or the whole suite would never have
// gotten this far) — every real hit against the rate-limited login
// endpoint (nginx's `limit_req zone=api_auth burst=5 nodelay`, refilling
// at one slot per 12s — see infra/docker/nginx/tms.conf) counts against
// the same budget, and EVERY test in this file hits it once, including
// the two "rejects ..." cases (wrong credentials still reach the
// endpoint). Global setup already used up most of the burst logging in
// six roles, so each of this file's own attempts needs its own pause
// too, or the second-or-third one in a row gets the same opaque
// "Network Error" (a 503 with no CORS headers, which the browser reports
// as a CORS failure) this file exists to actually test past.
test.beforeEach(async () => {
  await new Promise((resolve) => setTimeout(resolve, 13000))
})

test('logs in as a platform Super Admin and lands on the dashboard', async ({ page }) => {
  await login(page, ACCOUNTS.superAdmin.tenant, ACCOUNTS.superAdmin.email)

  await expect(page).toHaveURL('/')
  await expect(page.getByRole('heading', { level: 1 })).toContainText(/welcome back/i)
})

test('logs in as a tenant-scoped Client and lands on the dashboard', async ({ page }) => {
  await login(page, ACCOUNTS.acmeClient.tenant, ACCOUNTS.acmeClient.email)

  await expect(page).toHaveURL('/')
  await expect(page.getByRole('heading', { level: 1 })).toContainText(/welcome back/i)
})

test('rejects a wrong password with a visible error, no redirect', async ({ page }) => {
  await page.goto('/login')
  await page.fill('#tenant', ACCOUNTS.acmeTenantAdmin.tenant)
  await page.fill('#email', ACCOUNTS.acmeTenantAdmin.email)
  await page.fill('#password', 'not-the-password')
  await page.click('button[type="submit"]')

  await expect(page.getByRole('alert')).toBeVisible()
  await expect(page).toHaveURL('/login')
})

test('rejects a valid email under the wrong tenant slug', async ({ page }) => {
  await page.goto('/login')
  await page.fill('#tenant', 'not-a-real-tenant')
  await page.fill('#email', ACCOUNTS.acmeTenantAdmin.email)
  await page.fill('#password', PASSWORD)
  await page.click('button[type="submit"]')

  await expect(page.getByRole('alert')).toBeVisible()
  await expect(page).toHaveURL('/login')
})
