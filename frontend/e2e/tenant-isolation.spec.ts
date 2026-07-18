import { expect, test } from '@playwright/test'

import { authFile, tokenFromState } from './support/auth-state'

const API_BASE = 'http://localhost:8080/api/v1'

let acmeToken: string
let acmeTicketId: string

test.beforeAll(async ({ request }) => {
  acmeToken = tokenFromState('acmeTenantAdmin')

  const types = (await (await request.get(`${API_BASE}/ticket-types`, {
    headers: { Authorization: `Bearer ${acmeToken}` },
  })).json()).data as Array<{ id: string; name: string }>

  const created = await request.post(`${API_BASE}/tickets`, {
    headers: { Authorization: `Bearer ${acmeToken}` },
    data: {
      ticket_type_id: types[0].id,
      subject: 'e2e isolation fixture — Acme-only',
      description: 'Must never be visible to Globex.',
      priority: 'low',
    },
  })
  acmeTicketId = (await created.json()).data.id as string
})

test.afterAll(async ({ request }) => {
  await request.delete(`${API_BASE}/tickets/${acmeTicketId}`, {
    headers: { Authorization: `Bearer ${acmeToken}` },
  })
})

test.describe('Globex Tenant Admin', () => {
  test.use({ storageState: authFile('globexTenantAdmin') })

  test("never sees Acme's tickets in their list", async ({ page }) => {
    await page.goto('/tickets')
    await expect(page.getByText('e2e isolation fixture')).toHaveCount(0)
  })

  test("gets 404 navigating straight to Acme's ticket by id", async ({ request }) => {
    const response = await request.get(`${API_BASE}/tickets/${acmeTicketId}`, {
      headers: { Authorization: `Bearer ${tokenFromState('globexTenantAdmin')}` },
    })
    expect(response.status()).toBe(404)
  })

  test("never sees Acme's users in their user list", async ({ page }) => {
    await page.goto('/users')
    await expect(page.getByText('Acme Tenant Admin')).toHaveCount(0)
    await expect(page.getByText('Acme Client')).toHaveCount(0)
    await expect(page.getByText('Globex Tenant Admin')).toBeVisible()
  })
})
