import { expect, test } from '@playwright/test'

import { authFile, tokenFromState } from './support/auth-state'

// The backend's own origin — separate from playwright.config.ts's baseURL
// (the frontend's origin), since this file talks to the API directly to
// set up/tear down fixtures instead of driving the UI for it.
const API_BASE = 'http://localhost:8080/api/v1'

test.use({ storageState: authFile('acmeTenantAdmin') })

let adminToken: string
let billingTicketId: string
let itSupportTicketId: string

test.beforeAll(async ({ request }) => {
  adminToken = tokenFromState('acmeTenantAdmin')

  const typesResponse = await request.get(`${API_BASE}/ticket-types`, {
    headers: { Authorization: `Bearer ${adminToken}` },
  })
  const types = (await typesResponse.json()).data as Array<{ id: string; name: string }>
  const billingTypeId = types.find((type) => type.name === 'Billing')!.id
  const itSupportTypeId = types.find((type) => type.name === 'IT Support')!.id

  // The seeded "Acme Support Agent" (see DatabaseSeeder) is qualified only
  // for IT Support — a Billing ticket makes them the "unqualified" target
  // without needing a throwaway agent fixture; an IT Support ticket makes
  // them the "qualified" target for the accept case, reusing the exact
  // same seeded account both times.
  for (const [typeId, setId] of [
    [billingTypeId, 'billing'],
    [itSupportTypeId, 'itSupport'],
  ] as const) {
    const created = await request.post(`${API_BASE}/tickets`, {
      headers: { Authorization: `Bearer ${adminToken}` },
      data: {
        ticket_type_id: typeId,
        subject: `e2e reassignment fixture (${setId})`,
        description: 'Created by the e2e suite; deleted in afterAll.',
        priority: 'low',
      },
    })
    const ticketId = (await created.json()).data.id as string
    if (setId === 'billing') billingTicketId = ticketId
    else itSupportTicketId = ticketId
  }
})

test.afterAll(async ({ request }) => {
  for (const ticketId of [billingTicketId, itSupportTicketId]) {
    await request.delete(`${API_BASE}/tickets/${ticketId}`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
  }
})

test('rejects reassigning to an agent who lacks the ticket type\'s skill, with the specific reason shown', async ({ page }) => {
  await page.goto(`/tickets/${billingTicketId}`)

  await page.getByRole('combobox', { name: /agent/i }).click()
  await page.getByRole('option', { name: 'Acme Support Agent' }).click()

  await expect(page.getByText(/not qualified for this ticket's type/i)).toBeVisible()
  // No silent success: the agent select must not have taken on the value.
  await expect(page.getByRole('combobox', { name: /agent/i })).not.toHaveText('Acme Support Agent')
})

test('accepts reassigning to an agent who holds the matching skill', async ({ page }) => {
  await page.goto(`/tickets/${itSupportTicketId}`)

  await page.getByRole('combobox', { name: /agent/i }).click()
  await page.getByRole('option', { name: 'Acme Support Agent' }).click()

  await expect(page.getByText(/assigned/i).first()).toBeVisible()
  await expect(page.getByRole('combobox', { name: /agent/i })).toContainText('Acme Support Agent')
})
