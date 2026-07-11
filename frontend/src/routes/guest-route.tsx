import { Navigate, Outlet } from 'react-router'

import { useAuth } from '@/hooks/use-auth'

/** Redirects an already-authenticated user away from login/forgot/reset pages. */
export function GuestRoute() {
  const { isAuthenticated } = useAuth()

  if (isAuthenticated) {
    return <Navigate to="/" replace />
  }

  return <Outlet />
}
