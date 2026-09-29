import { useEffect, useState } from 'react'
import { fetchAttachmentDownloadUrl } from '@/api/ticket-attachments'

interface LocalPreviewProps {
  file: File
  className?: string
}

interface RemotePreviewProps {
  attachmentId: string
  mimeType: string
  className?: string
}

type Props = ({ file: File } & LocalPreviewProps) | ({ attachmentId: string; mimeType: string } & RemotePreviewProps)

export function AttachmentPreview(props: Props) {
  const [url, setUrl] = useState<string | null>(null)

  useEffect(() => {
    let mounted = true
    let objUrl: string | null = null

    async function setup() {
      if ('file' in props) {
        objUrl = URL.createObjectURL(props.file)
        if (mounted) setUrl(objUrl)
      } else {
        try {
          const downloadUrl = await fetchAttachmentDownloadUrl(props.attachmentId)
          if (mounted) setUrl(downloadUrl)
        } catch {
          // ignore — caller can still show filename
        }
      }
    }

    setup()

    return () => {
      mounted = false
      if (objUrl) URL.revokeObjectURL(objUrl)
    }
  }, [props])

  const mime = 'file' in props ? props.file.type : props.mimeType

  if (!url) {
    return null
  }

  if (mime.startsWith('image/')) {
    return <img src={url} alt="attachment preview" className={props.className ?? 'max-h-24 rounded'} />
  }

  if (mime === 'application/pdf') {
    return (
      <object data={url} type="application/pdf" className={props.className ?? 'h-40 w-full'}>
        <a href={url} target="_blank" rel="noopener noreferrer">
          Open PDF
        </a>
      </object>
    )
  }

  return null
}

export default AttachmentPreview
