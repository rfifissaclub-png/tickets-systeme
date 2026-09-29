import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchClients, fetchClient, createClient, updateClient, deleteClient } from '@/api/clients'

export const clientKeys = {
  list: () => ['clients'] as const,
  page: (params: any) => [...clientKeys.list(), params] as const,
  detail: (id: string) => ['clients', id] as const,
}

export function useClients(params: { page?: number; q?: string; is_linked?: boolean } = { page: 1 }) {
  return useQuery({ queryKey: clientKeys.page(params), queryFn: () => fetchClients(params) })
}

export function useClient(id?: string) {
  return useQuery({ queryKey: clientKeys.detail(id ?? ''), queryFn: () => fetchClient(id as string), enabled: !!id })
}

export function useCreateClient() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: (payload: any) => createClient(payload), onSuccess: () => qc.invalidateQueries({ queryKey: clientKeys.list() }) })
}

export function useUpdateClient(id: string) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: (payload: any) => updateClient(id, payload), onSuccess: () => qc.invalidateQueries({ queryKey: clientKeys.list() }) })
}

export function useDeleteClient() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: (id: string) => deleteClient(id), onSuccess: () => qc.invalidateQueries({ queryKey: clientKeys.list() }) })
}
