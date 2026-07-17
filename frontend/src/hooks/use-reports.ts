import { useQuery } from '@tanstack/react-query'

import { fetchReportOverview, fetchTicketsByAgent, fetchTicketsByTenant, fetchTicketTrends } from '@/api/reports'

export function useReportOverview(enabled = true) {
  return useQuery({
    queryKey: ['reports', 'overview'],
    queryFn: fetchReportOverview,
    staleTime: 60_000,
    enabled,
  })
}

export function useTicketsByAgentReport() {
  return useQuery({
    queryKey: ['reports', 'by-agent'],
    queryFn: fetchTicketsByAgent,
    staleTime: 60_000,
  })
}

export function useTicketsByTenantReport(enabled: boolean) {
  return useQuery({
    queryKey: ['reports', 'by-tenant'],
    queryFn: fetchTicketsByTenant,
    staleTime: 60_000,
    enabled,
  })
}

export function useTicketTrends(from?: string, to?: string) {
  return useQuery({
    queryKey: ['reports', 'trends', from, to],
    queryFn: () => fetchTicketTrends({ from, to }),
    staleTime: 60_000,
  })
}
