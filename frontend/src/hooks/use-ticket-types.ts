import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  createTicketType,
  deleteTicketType,
  fetchTicketTypes,
  updateTicketType,
  type CreateTicketTypePayload,
} from '@/api/ticket-types'

export const ticketTypeKeys = {
  all: ['ticket-types'] as const,
}

export function useTicketTypes() {
  return useQuery({
    queryKey: ticketTypeKeys.all,
    queryFn: fetchTicketTypes,
    staleTime: 5 * 60_000,
  })
}

export function useCreateTicketType() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateTicketTypePayload) => createTicketType(payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ticketTypeKeys.all }),
  })
}

export function useUpdateTicketType(id: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: Partial<CreateTicketTypePayload>) => updateTicketType(id, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ticketTypeKeys.all }),
  })
}

export function useDeleteTicketType() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => deleteTicketType(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ticketTypeKeys.all }),
  })
}
