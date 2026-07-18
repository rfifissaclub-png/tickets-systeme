import type { Page } from '@playwright/test'

import { PASSWORD } from './accounts'

/**
 * Logs in through the real login form (not a shortcut against the API) —
 * exercises the actual auth flow the same way every manual verification
 * pass this suite replaces did. Waits for the redirect off /login rather
 * than a fixed timeout: `waitForURL` with a plain regex against the full
 * URL string is a trap here (a `/` earlier in `http://host/login` matches
 * `(?!login)` before the path does), so this checks `location.pathname`
 * directly instead.
 */
export async function login(page: Page, tenant: string, email: string, password = PASSWORD): Promise<void> {
  await page.goto('/login')
  await page.waitForSelector('#email')
  await page.fill('#tenant', tenant)
  await page.fill('#email', email)
  await page.fill('#password', password)
  await page.click('button[type="submit"]')
  await page.waitForFunction(() => !window.location.pathname.startsWith('/login'), null, { timeout: 15000 })
}
