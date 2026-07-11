import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  assignTicket,
  createTicket,
  deleteTicket,
  fetchTicket,
  fetchTickets,
  transitionTicketStatus,
  updateTicket,
  type CreateTicketPayload,
  type TicketListParams,
  type UpdateTicketPayload,
} from '@/api/tickets'
import type { TicketStatus } from '@/types/models'

export const ticketKeys = {
  all: ['tickets'] as const,
  lists: () => [...ticketKeys.all, 'list'] as const,
  list: (params: TicketListParams) => [...ticketKeys.lists(), params] as const,
  details: () => [...ticketKeys.all, 'detail'] as const,
  detail: (id: string) => [...ticketKeys.details(), id] as const,
}

export function useTickets(params: TicketListParams) {
  return useQuery({
    queryKey: ticketKeys.list(params),
    queryFn: () => fetchTickets(params),
    placeholderData: (previousData) => previousData,
  })
}

export function useTicket(id: string | undefined) {
  return useQuery({
    queryKey: ticketKeys.detail(id ?? ''),
    queryFn: () => fetchTicket(id as string),
    enabled: !!id,
  })
}

export function useCreateTicket() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateTicketPayload) => createTicket(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ticketKeys.lists() })
    },
  })
}

export function useUpdateTicket(id: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: UpdateTicketPayload) => updateTicket(id, payload),
    onSuccess: (ticket) => {
      queryClient.setQueryData(ticketKeys.detail(id), ticket)
      queryClient.invalidateQueries({ queryKey: ticketKeys.lists() })
    },
  })
}

export function useDeleteTicket() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => deleteTicket(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ticketKeys.lists() })
    },
  })
}

export function useTransitionTicketStatus(id: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (status: TicketStatus) => transitionTicketStatus(id, status),
    onSuccess: (ticket) => {
      queryClient.setQueryData(ticketKeys.detail(id), ticket)
      queryClient.invalidateQueries({ queryKey: ticketKeys.lists() })
    },
  })
}

export function useAssignTicket(id: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (agentId: string) => assignTicket(id, agentId),
    onSuccess: (ticket) => {
      queryClient.setQueryData(ticketKeys.detail(id), ticket)
      queryClient.invalidateQueries({ queryKey: ticketKeys.lists() })
    },
  })
}
