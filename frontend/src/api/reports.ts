import { apiClient } from '@/lib/api-client'
import type { ApiSuccessResponse } from '@/types/api'
import type { AgentReportRow, TenantReportRow, TicketReportOverview, TicketTrendPoint } from '@/types/reports'

export async function fetchReportOverview(): Promise<TicketReportOverview> {
  const { data } = await apiClient.get<ApiSuccessResponse<TicketReportOverview>>('/reports/overview')
  return data.data
}

export async function fetchTicketsByAgent(): Promise<AgentReportRow[]> {
  const { data } = await apiClient.get<ApiSuccessResponse<AgentReportRow[]>>('/reports/tickets-by-agent')
  return data.data
}

/** Super Admin only — the backend checks the role directly, not a grantable permission. */
export async function fetchTicketsByTenant(): Promise<TenantReportRow[]> {
  const { data } = await apiClient.get<ApiSuccessResponse<TenantReportRow[]>>('/reports/tickets-by-tenant')
  return data.data
}

export async function fetchTicketTrends(params: { from?: string; to?: string } = {}): Promise<TicketTrendPoint[]> {
  const { data } = await apiClient.get<ApiSuccessResponse<TicketTrendPoint[]>>('/reports/ticket-trends', {
    params,
  })
  return data.data
}
