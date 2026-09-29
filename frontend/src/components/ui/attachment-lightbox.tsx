import * as React from 'react'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from './dialog'
import AttachmentPreview from './attachment-preview'

interface Props {
  file?: File
  attachmentId?: string
  mimeType?: string
  title?: string
  children?: React.ReactNode
}

export function AttachmentLightbox({ file, attachmentId, mimeType, title, children }: Props) {
  return (
    <Dialog>
      <DialogTrigger asChild>{children ?? <button type="button">View</button>}</DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{title ?? 'Preview'}</DialogTitle>
        </DialogHeader>
        <div className="mt-2">
          {'file' in (file ? { file } : {}) && file ? (
            <AttachmentPreview file={file} className="max-h-[70vh] w-full object-contain" />
          ) : (
            <AttachmentPreview attachmentId={attachmentId as string} mimeType={mimeType as string} className="max-h-[70vh] w-full object-contain" />
          )}
      </div>
      </DialogContent>
    </Dialog>
  )
}

export default AttachmentLightbox
