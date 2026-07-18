import fs from 'node:fs'
import path from 'node:path'

import type { ACCOUNTS } from './accounts'

/** Path to a role's saved storageState from global-setup.ts. */
export function authFile(role: keyof typeof ACCOUNTS): string {
  return path.join(import.meta.dirname, '..', '.auth', `${role}.json`)
}

/**
 * Pulls the bearer token straight out of a role's saved storageState
 * (persisted by useAuthStore under localStorage key "tms-auth" — see
 * src/stores/auth-store.ts) instead of logging in again just to get one for
 * a direct API fixture-setup call. One real login per role already happened
 * once, in global-setup.ts; this reuses it, keeping every spec file's own
 * hit count against the rate-limited login endpoint at zero.
 */
export function tokenFromState(role: keyof typeof ACCOUNTS): string {
  const state = JSON.parse(fs.readFileSync(authFile(role), 'utf-8'))
  const origin = state.origins?.[0]
  const entry = origin?.localStorage?.find((item: { name: string }) => item.name === 'tms-auth')
  const parsed = JSON.parse(entry.value)
  return parsed.state.token as string
}
