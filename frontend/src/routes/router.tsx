import { createBrowserRouter, Navigate } from 'react-router'

import { AppShell } from '@/components/layout/app-shell'
import { ForgotPasswordPage } from '@/pages/auth/forgot-password-page'
import { LoginPage } from '@/pages/auth/login-page'
import { ResetPasswordPage } from '@/pages/auth/reset-password-page'
import { DashboardPage } from '@/pages/dashboard/dashboard-page'
import { ReportsPage } from '@/pages/reports/reports-page'
import { TicketCreatePage } from '@/pages/tickets/ticket-create-page'
import { TicketDetailPage } from '@/pages/tickets/ticket-detail-page'
import { TicketListPage } from '@/pages/tickets/ticket-list-page'
import { TicketPoolPage } from '@/pages/tickets/ticket-pool-page'

import { GuestRoute } from './guest-route'
import { ProtectedRoute } from './protected-route'

export const router = createBrowserRouter([
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
        ],
      },
    ],
  },
  { path: '*', element: <Navigate to="/" replace /> },
])
