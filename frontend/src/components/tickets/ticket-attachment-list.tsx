import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { DownloadIcon, PaperclipIcon, TrashIcon, UploadIcon } from 'lucide-react'
import { toast } from 'sonner'

import { EmptyState } from '@/components/common/empty-state'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuth, useHasRole } from '@/hooks/use-auth'
import {
  useDeleteTicketAttachment,
  useTicketAttachments,
  useUploadTicketAttachment,
} from '@/hooks/use-ticket-attachments'
import { fetchAttachmentDownloadUrl } from '@/api/ticket-attachments'
import { toApiError } from '@/lib/api-client'
import AttachmentPreview from '@/components/ui/attachment-preview'
import AttachmentLightbox from '@/components/ui/attachment-lightbox'

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

export function TicketAttachmentList({ ticketId }: { ticketId: string }) {
  const { t } = useTranslation()
  const { user } = useAuth()
  const isStaff = useHasRole('Support Agent', 'Manager', 'Tenant Admin', 'Super Admin')
  const { data: attachments, isLoading } = useTicketAttachments(ticketId)
  const { mutateAsync: upload, isPending: uploading } = useUploadTicketAttachment(ticketId)
  const { mutateAsync: remove } = useDeleteTicketAttachment(ticketId)
  const [downloadingId, setDownloadingId] = useState<string | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const onFileSelected = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return

    try {
      await upload({ file })
      toast.success(t('attachments.uploadedToast'))
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  const onDownload = async (attachmentId: string) => {
    setDownloadingId(attachmentId)
    try {
      const url = await fetchAttachmentDownloadUrl(attachmentId)
      window.open(url, '_blank', 'noopener,noreferrer')
    } catch (error) {
      toast.error(toApiError(error).message)
    } finally {
      setDownloadingId(null)
    }
  }

  const onDelete = async (attachmentId: string) => {
    try {
      await remove(attachmentId)
      toast.success(t('attachments.deletedToast'))
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-medium">{t('attachments.title')}</h2>
        <Button variant="outline" size="sm" onClick={() => fileInputRef.current?.click()} disabled={uploading}>
          <UploadIcon className="size-4" />
          {uploading ? t('attachments.uploading') : t('attachments.uploadFile')}
        </Button>
        <input ref={fileInputRef} type="file" className="hidden" onChange={onFileSelected} />
      </div>

      {isLoading && <Skeleton className="h-10 w-full" />}

      {!isLoading && attachments?.length === 0 && (
        <EmptyState icon={PaperclipIcon} title={t('attachments.emptyTitle')} className="p-6" />
      )}

      {!isLoading && attachments && attachments.length > 0 && (
        <ul className="divide-border divide-y rounded-lg border">
          {attachments.map((attachment) => (
            <li key={attachment.id} className="flex items-center justify-between gap-3 px-3 py-2 text-sm">
              <div className="flex items-start gap-3 min-w-0">
                <div className="flex-shrink-0">
                  <AttachmentLightbox attachmentId={attachment.id} mimeType={attachment.mime_type} title={attachment.original_filename}>
                    <AttachmentPreview attachmentId={attachment.id} mimeType={attachment.mime_type} className="max-h-20 max-w-[120px] object-cover rounded" />
                  </AttachmentLightbox>
                </div>
                <div className="min-w-0">
                  <p className="truncate font-medium">{attachment.original_filename}</p>
                  <p className="text-muted-foreground text-xs">
                    {formatBytes(attachment.size_bytes)} ·{' '}
                    {t('attachments.uploadedBy', { name: attachment.uploader?.name ?? t('common.someone') })}
                  </p>
                </div>
              </div>
              <div className="flex shrink-0 gap-1">
                <Button
                  variant="ghost"
                  size="icon"
                  aria-label={t('attachments.download')}
                  disabled={downloadingId === attachment.id}
                  onClick={() => onDownload(attachment.id)}
                >
                  <DownloadIcon className="size-4" />
                </Button>
                {(user?.id === attachment.uploader?.id || isStaff) && (
                  <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('attachments.delete')}
                    onClick={() => onDelete(attachment.id)}
                  >
                    <TrashIcon className="size-4" />
                  </Button>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
