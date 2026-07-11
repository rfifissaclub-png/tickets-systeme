import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  createTicketComment,
  fetchTicketComments,
  type CreateTicketCommentPayload,
} from '@/api/ticket-comments'

export const ticketCommentKeys = {
  list: (ticketId: string) => ['ticket-comments', ticketId] as const,
}

export function useTicketComments(ticketId: string) {
  return useQuery({
    queryKey: ticketCommentKeys.list(ticketId),
    queryFn: () => fetchTicketComments(ticketId),
  })
}

export function useCreateTicketComment(ticketId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateTicketCommentPayload) => createTicketComment(ticketId, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ticketCommentKeys.list(ticketId) }),
  })
}
