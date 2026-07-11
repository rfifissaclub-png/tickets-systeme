import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { fetchTicketPool, pullTicket } from '@/api/ticket-pool'

import { ticketKeys } from './use-tickets'

export const ticketPoolKeys = {
  all: ['ticket-pool'] as const,
  list: (page: number) => [...ticketPoolKeys.all, page] as const,
}

export function useTicketPool(page: number) {
  return useQuery({
    queryKey: ticketPoolKeys.list(page),
    queryFn: () => fetchTicketPool(page),
    placeholderData: (previousData) => previousData,
  })
}

export function usePullTicket() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => pullTicket(id),
    onSuccess: () => {
      // Both lists need to reflect the new assignment: the pool loses the
      // ticket, "my tickets" (a filtered tickets list) gains it.
      queryClient.invalidateQueries({ queryKey: ticketPoolKeys.all })
      queryClient.invalidateQueries({ queryKey: ticketKeys.lists() })
    },
  })
}
