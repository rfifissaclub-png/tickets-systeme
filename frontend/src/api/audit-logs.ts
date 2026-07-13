import { apiClient } from '@/lib/api-client'
import type { ApiSuccessResponse, PaginationMeta } from '@/types/api'
import type { AuditLog } from '@/types/models'

export interface AuditLogListParams {
  per_page?: number
  page?: number
  tenant_id?: string
  action?: string
  actor_id?: string
  from?: string
  to?: string
}

export interface AuditLogListResult {
  logs: AuditLog[]
  meta: PaginationMeta
}

export async function fetchAuditLogs(params: AuditLogListParams = {}): Promise<AuditLogListResult> {
  const { data } = await apiClient.get<ApiSuccessResponse<AuditLog[]>>('/audit-logs', { params })
  return { logs: data.data, meta: data.meta as unknown as PaginationMeta }
}
