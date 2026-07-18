import { chromium, request } from '@playwright/test'
import fs from 'node:fs'
import path from 'node:path'

import { ACCOUNTS, PASSWORD } from './support/accounts'

const API_BASE = 'http://localhost:8080/api/v1'

/**
 * Logs in once per seeded role — via the API directly, not the UI — and
 * saves each session's storageState; every other spec reuses these
 * instead of calling login() itself. Not just a speed optimization: a
 * real UI login triggers a CORS preflight (OPTIONS) ahead of the actual
 * POST, and both count against nginx's `limit_req zone=api_auth burst=5
 * nodelay` (see infra/docker/nginx/tms.conf) — six real logins in a row
 * blow through that burst well before reaching the last couple of roles,
 * and the resulting 503 has no CORS headers, which the browser reports as
 * an opaque "Network Error"/CORS failure rather than the real status.
 * A server-side POST here has no browser, so no preflight, and needs only
 * one request per role — six total, comfortably inside the burst.
 *
 * useAuthStore (src/stores/auth-store.ts) persists to localStorage under
 * "tms-auth" via zustand's persist middleware — the exact shape written
 * below matches what a real browser login produces (confirmed against a
 * real captured storageState), so the app can't tell the difference.
 */
export default async function globalSetup(): Promise<void> {
  const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost:8081'
  const authDir = path.join(import.meta.dirname, '.auth')
  fs.mkdirSync(authDir, { recursive: true })

  const api = await request.newContext()
  const browser = await chromium.launch()

  for (const [role, account] of Object.entries(ACCOUNTS)) {
    const response = await api.post(`${API_BASE}/auth/login`, {
      data: { tenant: account.tenant, email: account.email, password: PASSWORD },
    })
    const body = await response.json()
    const { token, user } = body.data as { token: string; user: unknown }

    const context = await browser.newContext({ baseURL })
    const page = await context.newPage()
    await page.goto('/login')
    await page.evaluate(
      ([storedToken, storedUser]) => {
        window.localStorage.setItem('tms-auth', JSON.stringify({ state: { token: storedToken, user: storedUser }, version: 0 }))
      },
      [token, user],
    )

    await context.storageState({ path: path.join(authDir, `${role}.json`) })
    await context.close()
  }

  await api.dispose()
  await browser.close()

  // auth.spec.ts still logs in for real (that's what it tests) — the six
  // requests just made above consume most/all of the burst allowance, so
  // without a pause its very first test hits the same rejection this
  // whole file exists to avoid. `rate=5r/m` refills at one slot per 12s;
  // this buys back enough for that test's own preflight+POST pair.
  await new Promise((resolve) => setTimeout(resolve, 20000))
}
