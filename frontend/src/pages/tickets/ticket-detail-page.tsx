import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { ArrowLeftIcon, PencilIcon, TicketIcon, TrashIcon } from 'lucide-react'
import { toast } from 'sonner'

import { EmptyState } from '@/components/common/empty-state'
import { TicketAttachmentList } from '@/components/tickets/ticket-attachment-list'
import { TicketCommentThread } from '@/components/tickets/ticket-comment-thread'
import { TicketForm, type TicketFormValues } from '@/components/tickets/ticket-form'
import { TicketPriorityBadge } from '@/components/tickets/priority-badge'
import { TicketStatusBadge } from '@/components/tickets/status-badge'
import { TicketStatusSelect } from '@/components/tickets/ticket-status-select'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { Skeleton } from '@/components/ui/skeleton'
import { toApiError } from '@/lib/api-client'
import { useDeleteTicket, useTicket, useUpdateTicket } from '@/hooks/use-tickets'

export function TicketDetailPage() {
  const { ticketId } = useParams<{ ticketId: string }>()
  const navigate = useNavigate()
  const { data: ticket, isLoading, isError } = useTicket(ticketId)
  const { mutateAsync: update } = useUpdateTicket(ticketId ?? '')
  const { mutateAsync: remove } = useDeleteTicket()
  const [editing, setEditing] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-1/3" />
        <Skeleton className="h-40 w-full" />
      </div>
    )
  }

  if (isError || !ticket) {
    return (
      <EmptyState
        icon={TicketIcon}
        title="Ticket not found"
        description="It may have been deleted, or you may not have access to it."
        action={
          <Button asChild variant="outline">
            <Link to="/tickets">Back to tickets</Link>
          </Button>
        }
      />
    )
  }

  const isReadOnly = ticket.status === 'resolved' || ticket.status === 'closed'

  const onSubmitEdit = async (values: TicketFormValues) => {
    setFormError(null)
    try {
      await update(values)
      setEditing(false)
      toast.success('Ticket updated.')
    } catch (error) {
      setFormError(toApiError(error).message)
    }
  }

  const onDelete = async () => {
    if (!ticketId) return
    if (!window.confirm('Delete this ticket? This cannot be undone.')) return

    try {
      await remove(ticketId)
      toast.success('Ticket deleted.')
      navigate('/tickets')
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div>
        <Button variant="ghost" size="sm" asChild className="mb-2 -ml-2">
          <Link to="/tickets">
            <ArrowLeftIcon className="size-4" />
            Back to tickets
          </Link>
        </Button>
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight">{ticket.subject}</h1>
            <div className="mt-2 flex items-center gap-2">
              <TicketStatusBadge status={ticket.status} />
              <TicketPriorityBadge priority={ticket.priority} />
              {ticket.ticket_type && (
                <span className="text-muted-foreground text-xs">{ticket.ticket_type.name}</span>
              )}
            </div>
          </div>
          <div className="flex gap-2">
            {!isReadOnly && !editing && (
              <Button variant="outline" size="sm" onClick={() => setEditing(true)}>
                <PencilIcon className="size-4" />
                Edit
              </Button>
            )}
            <Button variant="outline" size="sm" onClick={onDelete}>
              <TrashIcon className="size-4" />
              Delete
            </Button>
          </div>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-sm">Details</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {editing ? (
            <TicketForm
              defaultValues={{
                ticket_type_id: ticket.ticket_type?.id,
                subject: ticket.subject,
                description: ticket.description,
                priority: ticket.priority,
              }}
              onSubmit={onSubmitEdit}
              submitLabel="Save changes"
              formError={formError}
              onCancel={() => setEditing(false)}
            />
          ) : (
            <>
              <p className="text-sm whitespace-pre-wrap">{ticket.description}</p>

              <Separator />

              <dl className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <dt className="text-muted-foreground">Client</dt>
                  <dd>{ticket.client?.name ?? '—'}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">Agent</dt>
                  <dd>{ticket.agent?.name ?? 'Unassigned'}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">Created</dt>
                  <dd>{new Date(ticket.created_at).toLocaleString()}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">Updated</dt>
                  <dd>{new Date(ticket.updated_at).toLocaleString()}</dd>
                </div>
              </dl>

              <Separator />

              <div className="flex items-center gap-3">
                <span className="text-muted-foreground text-sm">Status</span>
                <TicketStatusSelect ticketId={ticket.id} status={ticket.status} />
              </div>
            </>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardContent className="pt-6">
          <TicketAttachmentList ticketId={ticket.id} />
        </CardContent>
      </Card>

      <Card>
        <CardContent className="pt-6">
          <TicketCommentThread ticketId={ticket.id} />
        </CardContent>
      </Card>
    </div>
  )
}
