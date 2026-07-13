import { useQuery } from '@tanstack/react-query'

import { fetchAuditLogs, type AuditLogListParams } from '@/api/audit-logs'

export const auditLogKeys = {
  all: ['audit-logs'] as const,
  list: (params: AuditLogListParams) => [...auditLogKeys.all, 'list', params] as const,
}

export function useAuditLogs(params: AuditLogListParams = {}) {
  return useQuery({
    queryKey: auditLogKeys.list(params),
    queryFn: () => fetchAuditLogs(params),
    placeholderData: (previousData) => previousData,
  })
}
