import { apiClient } from '@/lib/api-client'
import type { ApiSuccessResponse } from '@/types/api'
import type { TicketType } from '@/types/models'

export async function fetchTicketTypes(): Promise<TicketType[]> {
  const { data } = await apiClient.get<ApiSuccessResponse<TicketType[]>>('/ticket-types')
  return data.data
}

export interface CreateTicketTypePayload {
  name: string
  description?: string
  is_active?: boolean
}

export async function createTicketType(payload: CreateTicketTypePayload): Promise<TicketType> {
  const { data } = await apiClient.post<ApiSuccessResponse<TicketType>>('/ticket-types', payload)
  return data.data
}

export async function updateTicketType(
  id: string,
  payload: Partial<CreateTicketTypePayload>,
): Promise<TicketType> {
  const { data } = await apiClient.patch<ApiSuccessResponse<TicketType>>(`/ticket-types/${id}`, payload)
  return data.data
}

export async function deleteTicketType(id: string): Promise<void> {
  await apiClient.delete(`/ticket-types/${id}`)
}
