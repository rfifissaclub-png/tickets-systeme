import { apiClient } from '@/lib/api-client'
import type { ApiSuccessResponse, PaginationMeta } from '@/types/api'
import type { Ticket, TicketPriority, TicketStatus } from '@/types/models'

export interface TicketListParams {
  search?: string
  status?: TicketStatus[]
  priority?: TicketPriority[]
  agent_id?: string
  client_id?: string
  ticket_type_id?: string
  unassigned?: boolean
  created_from?: string
  created_to?: string
  sort?: string
  per_page?: number
  page?: number
}

export interface TicketListResult {
  tickets: Ticket[]
  meta: PaginationMeta
}

function serializeParams(params: TicketListParams): Record<string, string | number | boolean> {
  const out: Record<string, string | number | boolean> = {}

  if (params.search) out.search = params.search
  if (params.status?.length) out.status = params.status.join(',')
  if (params.priority?.length) out.priority = params.priority.join(',')
  if (params.agent_id) out.agent_id = params.agent_id
  if (params.client_id) out.client_id = params.client_id
  if (params.ticket_type_id) out.ticket_type_id = params.ticket_type_id
  if (params.unassigned) out.unassigned = '1'
  if (params.created_from) out.created_from = params.created_from
  if (params.created_to) out.created_to = params.created_to
  if (params.sort) out.sort = params.sort
  if (params.per_page) out.per_page = params.per_page
  if (params.page) out.page = params.page

  return out
}

export async function fetchTickets(params: TicketListParams = {}): Promise<TicketListResult> {
  const { data } = await apiClient.get<ApiSuccessResponse<Ticket[]>>('/tickets', {
    params: serializeParams(params),
  })

  return { tickets: data.data, meta: data.meta as unknown as PaginationMeta }
}

export async function fetchTicket(id: string): Promise<Ticket> {
  const { data } = await apiClient.get<ApiSuccessResponse<Ticket>>(`/tickets/${id}`)
  return data.data
}

export interface CreateTicketPayload {
  ticket_type_id: string
  tenant_id?: string
  client_id?: string
  subject: string
  description: string
  priority?: TicketPriority
}

export async function createTicket(payload: CreateTicketPayload): Promise<Ticket> {
  const { data } = await apiClient.post<ApiSuccessResponse<Ticket>>('/tickets', payload)
  return data.data
}

export interface UpdateTicketPayload {
  ticket_type_id?: string
  subject?: string
  description?: string
  priority?: TicketPriority
}

export async function updateTicket(id: string, payload: UpdateTicketPayload): Promise<Ticket> {
  const { data } = await apiClient.patch<ApiSuccessResponse<Ticket>>(`/tickets/${id}`, payload)
  return data.data
}

export async function deleteTicket(id: string): Promise<void> {
  await apiClient.delete(`/tickets/${id}`)
}

export async function transitionTicketStatus(id: string, status: TicketStatus): Promise<Ticket> {
  const { data } = await apiClient.patch<ApiSuccessResponse<Ticket>>(`/tickets/${id}/status`, { status })
  return data.data
}

export async function assignTicket(id: string, agentId: string): Promise<Ticket> {
  const { data } = await apiClient.patch<ApiSuccessResponse<Ticket>>(`/tickets/${id}/assign`, {
    agent_id: agentId,
  })
  return data.data
}
