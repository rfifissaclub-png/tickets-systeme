import { apiClient } from '@/lib/api-client'
import type { ApiSuccessResponse, PaginationMeta } from '@/types/api'
import type { Ticket } from '@/types/models'

export interface TicketPoolResult {
  tickets: Ticket[]
  meta: PaginationMeta
}

export async function fetchTicketPool(page = 1): Promise<TicketPoolResult> {
  const { data } = await apiClient.get<ApiSuccessResponse<Ticket[]>>('/ticket-pool', { params: { page } })
  return { tickets: data.data, meta: data.meta as unknown as PaginationMeta }
}

/**
 * May reject with a 409 (ApiRequestError, code "ticket_already_assigned") if
 * another agent claimed it first — see TicketPoolService::pull() on the
 * backend. Callers should show that as a clean "someone else got it" toast,
 * not a generic error.
 */
export async function pullTicket(id: string): Promise<Ticket> {
  const { data } = await apiClient.post<ApiSuccessResponse<Ticket>>(`/ticket-pool/${id}/pull`)
  return data.data
}
