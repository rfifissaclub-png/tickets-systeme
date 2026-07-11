import { apiClient } from '@/lib/api-client'
import type { ApiSuccessResponse } from '@/types/api'
import type { TicketComment } from '@/types/models'

export async function fetchTicketComments(ticketId: string): Promise<TicketComment[]> {
  const { data } = await apiClient.get<ApiSuccessResponse<TicketComment[]>>(`/tickets/${ticketId}/comments`)
  return data.data
}

export interface CreateTicketCommentPayload {
  body: string
  is_internal?: boolean
}

export async function createTicketComment(
  ticketId: string,
  payload: CreateTicketCommentPayload,
): Promise<TicketComment> {
  const { data } = await apiClient.post<ApiSuccessResponse<TicketComment>>(
    `/tickets/${ticketId}/comments`,
    payload,
  )
  return data.data
}
