import { renderHook } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'

import { useAuthStore } from '@/stores/auth-store'
import type { User } from '@/types/models'

import { useAuth, useHasRole } from './use-auth'

function fakeUser(roles: User['roles']): User {
  return {
    id: 'user-1',
    tenant_id: 'tenant-1',
    name: 'Test User',
    email: 'test@example.com',
    email_verified_at: null,
    roles,
  }
}

afterEach(() => {
  useAuthStore.setState({ token: null, user: null })
})

describe('useAuth', () => {
  it('reports unauthenticated with no session', () => {
    const { result } = renderHook(() => useAuth())
    expect(result.current.isAuthenticated).toBe(false)
    expect(result.current.user).toBeNull()
  })

  it('reflects the store once a session is set', () => {
    useAuthStore.setState({ token: 'a-token', user: fakeUser(['Tenant Admin']) })
    const { result } = renderHook(() => useAuth())

    expect(result.current.isAuthenticated).toBe(true)
    expect(result.current.user?.email).toBe('test@example.com')
  })
})

describe('useHasRole', () => {
  it('is always false with no user', () => {
    const { result } = renderHook(() => useHasRole('Tenant Admin', 'Super Admin'))
    expect(result.current).toBe(false)
  })

  it('is true when the user holds one of the listed roles', () => {
    useAuthStore.setState({ token: 'a-token', user: fakeUser(['Support Agent']) })
    const { result } = renderHook(() => useHasRole('Manager', 'Tenant Admin', 'Support Agent'))
    expect(result.current).toBe(true)
  })

  it('is false when the user holds a role, but not one of the listed ones', () => {
    useAuthStore.setState({ token: 'a-token', user: fakeUser(['Client']) })
    const { result } = renderHook(() => useHasRole('Manager', 'Tenant Admin'))
    expect(result.current).toBe(false)
  })
})
