import { expect, test, type Page } from '@playwright/test'

import { authFile, tokenFromState } from './support/auth-state'

const API_BASE = 'http://localhost:8080/api/v1'

// Deletes whatever ticket the test navigated to after creating it, via the
// API directly (as the Tenant Admin — a Client can't delete their own
// ticket, tickets.delete isn't granted to that role) — keeps the dev
// database from accumulating a new ticket on every run of this spec, the
// same cleanup discipline as ticket-assignment.spec.ts.
async function cleanUpCreatedTicket(page: Page) {
  const match = page.url().match(/\/tickets\/([0-9a-f-]+)$/)
  if (!match) return

  await page.request.delete(`${API_BASE}/tickets/${match[1]}`, {
    headers: { Authorization: `Bearer ${tokenFromState('acmeTenantAdmin')}` },
  })
}

test.describe('Tenant Admin', () => {
  test.use({ storageState: authFile('acmeTenantAdmin') })

  test('creates a ticket on behalf of a client', async ({ page }) => {
    await page.goto('/tickets/new')

    await page.getByRole('combobox', { name: /client/i }).click()
    await page.getByRole('option', { name: /Acme Client/i }).click()

    await page.getByRole('combobox', { name: /ticket type/i }).click()
    await page.getByRole('option', { name: 'IT Support' }).click()

    await page.getByLabel('Subject').fill('e2e: on-behalf-of ticket')
    await page.getByLabel('Description').fill('Created by the Tenant Admin on the client\'s behalf.')

    await page.getByRole('button', { name: 'Create ticket' }).click()

    // Proves navigation actually landed on the new ticket's own detail
    // page, not just that the URL briefly matched — getByText('Acme
    // Client') alone is ambiguous here: the create form's own (unmounting)
    // client combobox still renders a same-text hidden <option> for a
    // moment after submit.
    await expect(page).toHaveURL(/\/tickets\/[0-9a-f-]+$/)
    await expect(page.getByRole('heading', { name: 'e2e: on-behalf-of ticket' })).toBeVisible()
    await expect(page.getByText('Acme Client')).toBeVisible()

    await cleanUpCreatedTicket(page)
  })
})

test.describe('Client', () => {
  test.use({ storageState: authFile('acmeClient') })

  test('creates their own ticket directly, with no client picker shown', async ({ page }) => {
    await page.goto('/tickets/new')

    await expect(page.getByRole('combobox', { name: /client/i })).toHaveCount(0)

    await page.getByRole('combobox', { name: /ticket type/i }).click()
    await page.getByRole('option', { name: 'Billing' }).click()

    await page.getByLabel('Subject').fill('e2e: self-service ticket')
    await page.getByLabel('Description').fill('Created directly by the client.')

    await page.getByRole('button', { name: 'Create ticket' }).click()

    await expect(page).toHaveURL(/\/tickets\/[0-9a-f-]+$/)
    await expect(page.getByText('Acme Client')).toBeVisible()

    await cleanUpCreatedTicket(page)
  })
})
