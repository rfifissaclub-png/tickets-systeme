import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  createTenant,
  deleteTenant,
  fetchTenants,
  updateTenant,
  type CreateTenantPayload,
  type TenantListParams,
  type UpdateTenantPayload,
} from '@/api/tenants'

export const tenantKeys = {
  all: ['tenants'] as const,
  lists: () => [...tenantKeys.all, 'list'] as const,
  list: (params: TenantListParams) => [...tenantKeys.lists(), params] as const,
}

export function useTenants(params: TenantListParams = {}, options: { enabled?: boolean } = {}) {
  return useQuery({
    queryKey: tenantKeys.list(params),
    queryFn: () => fetchTenants(params),
    placeholderData: (previousData) => previousData,
    enabled: options.enabled ?? true,
  })
}

export function useCreateTenant() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateTenantPayload) => createTenant(payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: tenantKeys.lists() }),
  })
}

export function useUpdateTenant(id: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: UpdateTenantPayload) => updateTenant(id, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: tenantKeys.lists() }),
  })
}

export function useDeleteTenant() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => deleteTenant(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: tenantKeys.lists() }),
  })
}
