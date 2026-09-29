import { apiClient } from '@/lib/api-client'
import type { ApiSuccessResponse } from '@/types/api'

export interface ClientContact {
  id: string
  name: string
  email: string
  phone?: string | null
  title?: string | null
  created_at: string
}

export async function fetchClientContacts(clientId: string): Promise<ClientContact[]> {
  const { data } = await apiClient.get<ApiSuccessResponse<ClientContact[]>>(`/clients/${clientId}/contacts`)
  return data.data
}

export async function createClientContact(clientId: string, payload: { name: string; email: string; phone?: string; title?: string }) {
  const { data } = await apiClient.post<ApiSuccessResponse<ClientContact>>(`/clients/${clientId}/contacts`, payload)
  return data.data
}

export async function updateClientContact(clientId: string, contactId: string, payload: { name?: string; email?: string; phone?: string; title?: string }) {
  const { data } = await apiClient.patch<ApiSuccessResponse<ClientContact>>(`/clients/${clientId}/contacts/${contactId}`, payload)
  return data.data
}

export async function deleteClientContact(clientId: string, contactId: string) {
  await apiClient.delete(`/clients/${clientId}/contacts/${contactId}`)
}
