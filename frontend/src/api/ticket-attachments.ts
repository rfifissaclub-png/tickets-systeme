import { apiClient } from '@/lib/api-client'
import type { ApiSuccessResponse } from '@/types/api'
import type { TicketAttachment } from '@/types/models'

export async function fetchTicketAttachments(ticketId: string): Promise<TicketAttachment[]> {
  const { data } = await apiClient.get<ApiSuccessResponse<TicketAttachment[]>>(
    `/tickets/${ticketId}/attachments`,
  )
  return data.data
}

export async function uploadTicketAttachment(
  ticketId: string,
  file: File,
  onProgress?: (percent: number) => void,
): Promise<TicketAttachment> {
  const form = new FormData()
  form.append('file', file)

  const { data } = await apiClient.post<ApiSuccessResponse<TicketAttachment>>(
    `/tickets/${ticketId}/attachments`,
    form,
    {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (event) => {
        if (onProgress && event.total) {
          onProgress(Math.round((event.loaded / event.total) * 100))
        }
      },
    },
  )
  return data.data
}

/** Returns a fresh, short-lived presigned URL — never cache this across renders. */
export async function fetchAttachmentDownloadUrl(attachmentId: string): Promise<string> {
  const { data } = await apiClient.get<ApiSuccessResponse<{ url: string }>>(
    `/attachments/${attachmentId}/download`,
  )
  return data.data.url
}

export async function deleteTicketAttachment(attachmentId: string): Promise<void> {
  await apiClient.delete(`/attachments/${attachmentId}`)
}
