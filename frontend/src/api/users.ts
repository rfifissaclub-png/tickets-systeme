import { apiClient } from '@/lib/api-client'
import type { ApiSuccessResponse, PaginationMeta } from '@/types/api'
import type { RoleName, User } from '@/types/models'

export interface UserListParams {
  tenant_id?: string
  per_page?: number
  page?: number
}

export interface UserListResult {
  users: User[]
  meta: PaginationMeta
}

export async function fetchUsers(params: UserListParams = {}): Promise<UserListResult> {
  const { data } = await apiClient.get<ApiSuccessResponse<User[]>>('/users', { params })
  return { users: data.data, meta: data.meta as unknown as PaginationMeta }
}

export async function fetchUser(id: string): Promise<User> {
  const { data } = await apiClient.get<ApiSuccessResponse<User>>(`/users/${id}`)
  return data.data
}

export interface CreateUserPayload {
  tenant_id?: string
  name: string
  email: string
  password: string
  roles?: RoleName[]
}

export async function createUser(payload: CreateUserPayload): Promise<User> {
  const { data } = await apiClient.post<ApiSuccessResponse<User>>('/users', payload)
  return data.data
}

export interface UpdateUserPayload {
  name?: string
  email?: string
}

export async function updateUser(id: string, payload: UpdateUserPayload): Promise<User> {
  const { data } = await apiClient.patch<ApiSuccessResponse<User>>(`/users/${id}`, payload)
  return data.data
}

export async function deleteUser(id: string): Promise<void> {
  await apiClient.delete(`/users/${id}`)
}

export async function syncUserRoles(id: string, roles: RoleName[]): Promise<User> {
  const { data } = await apiClient.patch<ApiSuccessResponse<User>>(`/users/${id}/roles`, { roles })
  return data.data
}

export async function syncUserSkills(id: string, ticketTypeIds: string[]): Promise<User> {
  const { data } = await apiClient.patch<ApiSuccessResponse<User>>(`/users/${id}/skills`, {
    ticket_type_ids: ticketTypeIds,
  })
  return data.data
}
