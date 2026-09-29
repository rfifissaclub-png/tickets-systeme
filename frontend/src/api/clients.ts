import { apiClient } from '@/lib/api-client'
import type { ApiSuccessResponse, PaginationMeta } from '@/types/api'
import type { Client } from '@/types/models'

interface ClientPaginator {
  data: Client[]
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ClientListParams {
  page?: number
  per_page?: number
  q?: string
  is_linked?: boolean
}

export interface ClientListResult {
  clients: Client[]
  meta: PaginationMeta
}

export async function fetchClients(params: ClientListParams = {}): Promise<ClientListResult> {
  const { data } = await apiClient.get<ApiSuccessResponse<ClientPaginator>>('/clients', { params })
  const paginator = data.data

  return {
    clients: paginator.data,
    meta: {
      current_page: paginator.current_page,
      per_page: paginator.per_page,
      total: paginator.total,
      last_page: paginator.last_page,
    },
  }
}

export async function fetchClient(id: string): Promise<Client> {
  const { data } = await apiClient.get<ApiSuccessResponse<Client>>(`/clients/${id}`)
  return data.data
}

export interface CreateClientPayload {
  name: string
  email: string
  tenant_id?: string
}

export async function createClient(payload: CreateClientPayload): Promise<Client> {
  const { data } = await apiClient.post<ApiSuccessResponse<Client>>('/clients', payload)
  return data.data
}

export interface UpdateClientPayload {
  name?: string
  email?: string
}

export async function updateClient(id: string, payload: UpdateClientPayload): Promise<Client> {
  const { data } = await apiClient.patch<ApiSuccessResponse<Client>>(`/clients/${id}`, payload)
  return data.data
}

export async function deleteClient(id: string): Promise<void> {
  await apiClient.delete(`/clients/${id}`)
}
