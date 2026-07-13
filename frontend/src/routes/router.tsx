import { createBrowserRouter, Navigate } from 'react-router'

import { AppShell } from '@/components/layout/app-shell'
import { AuditLogsPage } from '@/pages/audit-logs/audit-logs-page'
import { ForgotPasswordPage } from '@/pages/auth/forgot-password-page'
import { LoginPage } from '@/pages/auth/login-page'
import { ResetPasswordPage } from '@/pages/auth/reset-password-page'
import { VerifyEmailPage } from '@/pages/auth/verify-email-page'
import { DashboardPage } from '@/pages/dashboard/dashboard-page'
import { ReportsPage } from '@/pages/reports/reports-page'
import { TenantsPage } from '@/pages/tenants/tenants-page'
import { TicketTypesPage } from '@/pages/ticket-types/ticket-types-page'
import { TicketCreatePage } from '@/pages/tickets/ticket-create-page'
import { TicketDetailPage } from '@/pages/tickets/ticket-detail-page'
import { TicketListPage } from '@/pages/tickets/ticket-list-page'
import { TicketPoolPage } from '@/pages/tickets/ticket-pool-page'
import { UsersPage } from '@/pages/users/users-page'

import { GuestRoute } from './guest-route'
import { ProtectedRoute } from './protected-route'

export const router = createBrowserRouter([
  // Not under GuestRoute or ProtectedRoute: a verification link can be
  // clicked whether or not the browser happens to still be logged in
  // (e.g. clicked from a saved email long after signing in elsewhere), and
  // it's a read-only, idempotent confirmation with nothing sensitive to
  // hide from either state.
  { path: '/verify-email', element: <VerifyEmailPage /> },
  {
    element: <GuestRoute />,
    children: [
      { path: '/login', element: <LoginPage /> },
      { path: '/forgot-password', element: <ForgotPasswordPage /> },
      { path: '/reset-password', element: <ResetPasswordPage /> },
    ],
  },
  {
    element: <ProtectedRoute />,
    children: [
      {
        element: <AppShell />,
        children: [
          { path: '/', element: <DashboardPage /> },
          { path: '/tickets', element: <TicketListPage /> },
          { path: '/tickets/new', element: <TicketCreatePage /> },
          { path: '/tickets/:ticketId', element: <TicketDetailPage /> },
          { path: '/ticket-pool', element: <TicketPoolPage /> },
          { path: '/reports', element: <ReportsPage /> },
          { path: '/ticket-types', element: <TicketTypesPage /> },
          { path: '/users', element: <UsersPage /> },
          { path: '/tenants', element: <TenantsPage /> },
          { path: '/audit-logs', element: <AuditLogsPage /> },
        ],
      },
    ],
  },
  { path: '*', element: <Navigate to="/" replace /> },
])
