import type { TicketPriority, TicketStatus } from '@/types/models'

export interface TicketReportOverview {
  total_tickets: number
  by_status: Partial<Record<TicketStatus, number>>
  by_priority: Partial<Record<TicketPriority, number>>
  avg_resolution_seconds: number | null
  avg_first_response_seconds: number | null
}

export interface AgentReportRow {
  agent_id: string
  agent_name: string
  ticket_count: number
  avg_resolution_seconds: number | null
  avg_first_response_seconds: number | null
}

export interface TenantReportRow {
  tenant_id: string
  tenant_name: string
  ticket_count: number
}

export interface TicketTrendPoint {
  date: string
  created: number
  resolved: number
}
