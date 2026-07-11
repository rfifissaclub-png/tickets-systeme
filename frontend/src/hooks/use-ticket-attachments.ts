import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  deleteTicketAttachment,
  fetchTicketAttachments,
  uploadTicketAttachment,
} from '@/api/ticket-attachments'

export const ticketAttachmentKeys = {
  list: (ticketId: string) => ['ticket-attachments', ticketId] as const,
}

export function useTicketAttachments(ticketId: string) {
  return useQuery({
    queryKey: ticketAttachmentKeys.list(ticketId),
    queryFn: () => fetchTicketAttachments(ticketId),
  })
}

export function useUploadTicketAttachment(ticketId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ file, onProgress }: { file: File; onProgress?: (percent: number) => void }) =>
      uploadTicketAttachment(ticketId, file, onProgress),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ticketAttachmentKeys.list(ticketId) }),
  })
}

export function useDeleteTicketAttachment(ticketId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (attachmentId: string) => deleteTicketAttachment(attachmentId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ticketAttachmentKeys.list(ticketId) }),
  })
}
