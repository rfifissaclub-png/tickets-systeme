import { create } from 'zustand'
import { persist } from 'zustand/middleware'

import type { User } from '@/types/models'

interface AuthState {
  token: string | null
  user: User | null
  setSession: (token: string, user: User) => void
  setUser: (user: User) => void
  logout: () => void
}

/**
 * Persisted to localStorage under "tms-auth" so a page refresh doesn't log
 * the user out. The token is a long-lived Sanctum bearer token (or
 * non-expiring, if "remember" was set at login — see LoginController on the
 * backend), not a short-lived session cookie, so this is the correct place
 * for it to live.
 */
export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      token: null,
      user: null,
      setSession: (token, user) => set({ token, user }),
      setUser: (user) => set({ user }),
      logout: () => set({ token: null, user: null }),
    }),
    {
      name: 'tms-auth',
      partialize: (state) => ({ token: state.token, user: state.user }),
    },
  ),
)

export function isAuthenticated(): boolean {
  return useAuthStore.getState().token !== null
}
