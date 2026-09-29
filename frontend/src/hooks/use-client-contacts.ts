import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchClientContacts, createClientContact, updateClientContact, deleteClientContact } from '@/api/client-contacts'

export const clientContactKeys = {
  list: (clientId: string) => ['client-contacts', clientId] as const,
}

export function useClientContacts(clientId: string | undefined) {
  return useQuery({ queryKey: clientContactKeys.list(clientId ?? ''), queryFn: () => fetchClientContacts(clientId as string), enabled: !!clientId })
}

export function useCreateClientContact(clientId: string) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: (payload: any) => createClientContact(clientId, payload), onSuccess: () => qc.invalidateQueries({ queryKey: clientContactKeys.list(clientId) }) })
}

export function useUpdateClientContact(clientId: string, contactId: string) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: (payload: any) => updateClientContact(clientId, contactId, payload), onSuccess: () => qc.invalidateQueries({ queryKey: clientContactKeys.list(clientId) }) })
}

export function useDeleteClientContact(clientId: string) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: (contactId: string) => deleteClientContact(clientId, contactId), onSuccess: () => qc.invalidateQueries({ queryKey: clientContactKeys.list(clientId) }) })
}
