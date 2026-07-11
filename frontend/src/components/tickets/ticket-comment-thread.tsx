import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { LockIcon, MessageSquareIcon } from 'lucide-react'
import { toast } from 'sonner'

import { EmptyState } from '@/components/common/empty-state'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { useAuth, useHasRole } from '@/hooks/use-auth'
import { useCreateTicketComment, useTicketComments } from '@/hooks/use-ticket-comments'
import { toApiError } from '@/lib/api-client'
import { cn } from '@/lib/utils'

function initials(name: string): string {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
}

export function TicketCommentThread({ ticketId }: { ticketId: string }) {
  const { t } = useTranslation()
  const { user } = useAuth()
  const canPostInternal = useHasRole('Support Agent', 'Manager', 'Tenant Admin', 'Super Admin')
  const { data: comments, isLoading } = useTicketComments(ticketId)
  const { mutateAsync, isPending } = useCreateTicketComment(ticketId)

  const [body, setBody] = useState('')
  const [isInternal, setIsInternal] = useState(false)

  const onSubmit = async (event: React.FormEvent) => {
    event.preventDefault()
    if (!body.trim()) return

    try {
      await mutateAsync({ body: body.trim(), is_internal: isInternal })
      setBody('')
      setIsInternal(false)
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  return (
    <div className="space-y-4">
      <h2 className="text-sm font-medium">{t('comments.title')}</h2>

      {isLoading && (
        <div className="space-y-3">
          <Skeleton className="h-16 w-full" />
          <Skeleton className="h-16 w-full" />
        </div>
      )}

      {!isLoading && comments?.length === 0 && (
        <EmptyState icon={MessageSquareIcon} title={t('comments.emptyTitle')} description={t('comments.emptyDescription')} />
      )}

      {!isLoading && comments && comments.length > 0 && (
        <ul className="space-y-4">
          {comments.map((comment) => (
            <li
              key={comment.id}
              className={cn(
                'flex gap-3 rounded-lg border p-3',
                comment.is_internal && 'border-warning/30 bg-warning/5',
              )}
            >
              <Avatar className="mt-0.5">
                <AvatarFallback>{comment.author ? initials(comment.author.name) : '?'}</AvatarFallback>
              </Avatar>
              <div className="min-w-0 flex-1 space-y-1">
                <div className="flex items-center gap-2 text-sm">
                  <span className="font-medium">{comment.author?.name ?? t('common.unknown')}</span>
                  <span className="text-muted-foreground">
                    {new Date(comment.created_at).toLocaleString()}
                  </span>
                  {comment.is_internal && (
                    <span className="text-warning-foreground inline-flex items-center gap-1 text-xs font-medium">
                      <LockIcon className="size-3" /> {t('comments.internalNote')}
                    </span>
                  )}
                </div>
                <p className="text-sm whitespace-pre-wrap">{comment.body}</p>
              </div>
            </li>
          ))}
        </ul>
      )}

      {user && (
        <form onSubmit={onSubmit} className="space-y-2 border-t pt-4">
          <Textarea
            value={body}
            onChange={(event) => setBody(event.target.value)}
            placeholder={t('comments.replyPlaceholder')}
            rows={3}
            aria-label={t('comments.replyLabel')}
          />
          <div className="flex items-center justify-between">
            {canPostInternal ? (
              <label className="text-muted-foreground flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  className="border-input size-4 rounded"
                  checked={isInternal}
                  onChange={(event) => setIsInternal(event.target.checked)}
                />
                {t('comments.internalNoteCheckbox')}
              </label>
            ) : (
              <span />
            )}
            <Button type="submit" size="sm" disabled={isPending || !body.trim()}>
              {isPending ? t('comments.posting') : t('comments.postReply')}
            </Button>
          </div>
        </form>
      )}
    </div>
  )
}
