import { expect, test } from '@playwright/test'

import { authFile } from './support/auth-state'

/**
 * Mirrors frontend/src/components/layout/nav-config.ts's role matrix. Direct
 * URL navigation to a page whose nav link is hidden must never render a
 * silent blank content area — this is the regression test for exactly that
 * bug (Users/Tenants/Audit Log/Reports never checked `isError`, so a 403
 * rendered the page title with nothing underneath).
 */
const PLANS = {
  acmeTenantAdmin: {
    allowedNav: ['Dashboard', 'Tickets', 'Ticket Pool', 'Reports', 'Users', 'Audit Log'],
    deniedNav: ['Ticket Types', 'Entities'],
    deniedErrors: [{ path: '/tenants', text: "Couldn't load entities" }],
  },
  acmeAgent: {
    allowedNav: ['Dashboard', 'Tickets', 'Ticket Pool'],
    deniedNav: ['Reports', 'Ticket Types', 'Users', 'Entities', 'Audit Log'],
    deniedErrors: [
      { path: '/reports', text: "Couldn't load reports" },
      { path: '/users', text: "Couldn't load users" },
      { path: '/audit-logs', text: "Couldn't load the audit log" },
      { path: '/tenants', text: "Couldn't load entities" },
    ],
  },
  acmeClient: {
    allowedNav: ['Dashboard', 'Tickets'],
    deniedNav: ['Ticket Pool', 'Reports', 'Ticket Types', 'Users', 'Entities', 'Audit Log'],
    deniedErrors: [
      { path: '/reports', text: "Couldn't load reports" },
      { path: '/users', text: "Couldn't load users" },
      { path: '/audit-logs', text: "Couldn't load the audit log" },
      { path: '/tenants', text: "Couldn't load entities" },
    ],
    // Not a 403 at all — a Client is simply skill-gated to zero rows, same
    // as a front-line agent with no qualifications yet (see
    // TicketPoolService::isSkillGated()) — so this renders the pool's own
    // *empty*, not error, state.
    deniedEmpty: [{ path: '/ticket-pool', text: 'The pool is empty' }],
  },
} as const

for (const [role, plan] of Object.entries(PLANS)) {
  test.describe(role, () => {
    test.use({ storageState: authFile(role as keyof typeof PLANS) })

    test('sidebar shows only the permitted sections', async ({ page }) => {
      await page.goto('/')
      const sidebar = page.locator('aside')
      for (const label of plan.allowedNav) {
        await expect(sidebar.getByRole('link', { name: label })).toBeVisible()
      }
      for (const label of plan.deniedNav) {
        await expect(sidebar.getByRole('link', { name: label })).toHaveCount(0)
      }
    })

    test('direct URL to a denied page shows an error, never a silent blank', async ({ page }) => {
      for (const { path, text } of plan.deniedErrors) {
        await page.goto(path)
        await expect(page.getByText(text)).toBeVisible()
      }

      for (const { path, text } of 'deniedEmpty' in plan ? plan.deniedEmpty : []) {
        await page.goto(path)
        await expect(page.getByText(text)).toBeVisible()
      }
    })
  })
}

test.describe('superAdmin', () => {
  test.use({ storageState: authFile('superAdmin') })

  test('sees every section, including platform-only ones', async ({ page }) => {
    await page.goto('/')
    const sidebar = page.locator('aside')
    for (const label of ['Dashboard', 'Tickets', 'Ticket Pool', 'Reports', 'Ticket Types', 'Users', 'Entities', 'Audit Log']) {
      await expect(sidebar.getByRole('link', { name: label })).toBeVisible()
    }
  })
})
