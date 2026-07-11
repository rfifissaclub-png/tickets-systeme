import { useAuthStore } from '@/stores/auth-store'
import type { RoleName } from '@/types/models'

export function useAuth() {
  const token = useAuthStore((state) => state.token)
  const user = useAuthStore((state) => state.user)

  return {
    token,
    user,
    isAuthenticated: token !== null,
  }
}

/** UX gating only (hide links a user can't use) — the API enforces the real boundary regardless. */
export function useHasRole(...roles: RoleName[]): boolean {
  const user = useAuthStore((state) => state.user)
  if (!user?.roles) return false
  return roles.some((role) => user.roles?.includes(role))
}
