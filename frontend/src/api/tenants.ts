import { apiClient } from '@/lib/api-client'
import type { ApiSuccessResponse, PaginationMeta } from '@/types/api'
import type { Tenant, TenantStatus } from '@/types/models'

export interface TenantListParams {
  per_page?: number
  page?: number
}

export interface TenantListResult {
  tenants: Tenant[]
  meta: PaginationMeta
}

export async function fetchTenants(params: TenantListParams = {}): Promise<TenantListResult> {
  const { data } = await apiClient.get<ApiSuccessResponse<Tenant[]>>('/tenants', { params })
  return { tenants: data.data, meta: data.meta as unknown as PaginationMeta }
}

export async function fetchTenant(id: string): Promise<Tenant> {
  const { data } = await apiClient.get<ApiSuccessResponse<Tenant>>(`/tenants/${id}`)
  return data.data
}

export interface CreateTenantPayload {
  name: string
  slug: string
  status?: TenantStatus
}

export async function createTenant(payload: CreateTenantPayload): Promise<Tenant> {
  const { data } = await apiClient.post<ApiSuccessResponse<Tenant>>('/tenants', payload)
  return data.data
}

export interface UpdateTenantPayload {
  name?: string
  status?: TenantStatus
}

export async function updateTenant(id: string, payload: UpdateTenantPayload): Promise<Tenant> {
  const { data } = await apiClient.patch<ApiSuccessResponse<Tenant>>(`/tenants/${id}`, payload)
  return data.data
}

export async function deleteTenant(id: string): Promise<void> {
  await apiClient.delete(`/tenants/${id}`)
}
