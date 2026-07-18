import { describe, expect, it } from 'vitest'

import { NAV_ITEMS } from './nav-config'

/**
 * A config-sanity check, not a UI test — role-navigation.spec.ts (the
 * Playwright e2e suite) already proves the *rendered* sidebar matches this
 * file for every seeded role; this instead guards the data itself, so a
 * typo here (a role added or dropped from one item's list) fails in
 * milliseconds instead of only surfacing in a multi-minute browser run.
 */
function rolesFor(labelKey: string): string[] | undefined {
  return NAV_ITEMS.find((item) => item.labelKey === labelKey)?.roles
}

describe('NAV_ITEMS role matrix', () => {
  it('shows Dashboard and Tickets to every authenticated role (no roles restriction)', () => {
    expect(rolesFor('nav.dashboard')).toBeUndefined()
    expect(rolesFor('nav.tickets')).toBeUndefined()
  })

  it('restricts Ticket Pool to staff who can work tickets', () => {
    expect(rolesFor('nav.ticketPool')).toEqual(['Support Agent', 'Manager', 'Tenant Admin', 'Super Admin'])
  })

  it('restricts Reports to management roles', () => {
    expect(rolesFor('nav.reports')).toEqual(['Manager', 'Tenant Admin', 'Super Admin'])
  })

  it('restricts Users to management roles', () => {
    expect(rolesFor('nav.users')).toEqual(['Manager', 'Tenant Admin', 'Super Admin'])
  })

  it('restricts Audit Log to Tenant Admin and Super Admin only', () => {
    expect(rolesFor('nav.auditLogs')).toEqual(['Tenant Admin', 'Super Admin'])
  })

  it('restricts Ticket Types and Entities to Super Admin only', () => {
    expect(rolesFor('nav.ticketTypes')).toEqual(['Super Admin'])
    expect(rolesFor('nav.tenants')).toEqual(['Super Admin'])
  })
})
