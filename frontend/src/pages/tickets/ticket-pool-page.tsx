import { useState } from 'react'
import { Link } from 'react-router'
import { useTranslation } from 'react-i18next'
import { InboxIcon } from 'lucide-react'
import { toast } from 'sonner'

import { EmptyState } from '@/components/common/empty-state'
import { Pagination } from '@/components/common/pagination'
import { TicketPriorityBadge } from '@/components/tickets/priority-badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { toApiError } from '@/lib/api-client'
import { usePullTicket, useTicketPool } from '@/hooks/use-ticket-pool'

export function TicketPoolPage() {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)
  const { data, isLoading, isError } = useTicketPool(page)
  const { mutate: pull, isPending, variables: pullingId } = usePullTicket()

  const onPull = (ticketId: string) => {
    pull(ticketId, {
      onSuccess: () => toast.success(t('ticketPool.pulledToast')),
      onError: (error) => {
        const apiError = toApiError(error)
        toast.error(apiError.code === 'ticket_already_assigned' ? t('ticketPool.alreadyAssignedToast') : apiError.message)
      },
    })
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t('ticketPool.title')}</h1>
        <p className="text-muted-foreground text-sm">{t('ticketPool.subtitle')}</p>
      </div>

      {isLoading && (
        <div className="space-y-2">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-16 w-full" />
          ))}
        </div>
      )}

      {isError && (
        <EmptyState icon={InboxIcon} title={t('ticketPool.loadErrorTitle')} description={t('ticketPool.loadErrorDescription')} />
      )}

      {!isLoading && !isError && data && data.tickets.length === 0 && (
        <EmptyState icon={InboxIcon} title={t('ticketPool.emptyTitle')} description={t('ticketPool.emptyDescription')} />
      )}

      {!isLoading && !isError && data && data.tickets.length > 0 && (
        <>
          <ul className="space-y-2">
            {data.tickets.map((ticket) => (
              <li key={ticket.id} className="flex items-center justify-between gap-4 rounded-lg border p-4">
                <div className="min-w-0">
                  <Link to={`/tickets/${ticket.id}`} className="line-clamp-1 font-medium hover:underline">
                    {ticket.subject}
                  </Link>
                  <div className="text-muted-foreground mt-1 flex items-center gap-2 text-xs">
                    <span>{ticket.client?.name ?? t('ticketPool.unknownClient')}</span>
                    <span>·</span>
                    <span>{ticket.ticket_type?.name}</span>
                    <TicketPriorityBadge priority={ticket.priority} />
                  </div>
                </div>
                <Button size="sm" disabled={isPending && pullingId === ticket.id} onClick={() => onPull(ticket.id)}>
                  {isPending && pullingId === ticket.id ? t('ticketPool.claiming') : t('ticketPool.pull')}
                </Button>
              </li>
            ))}
          </ul>

          <Pagination meta={data.meta} onPageChange={setPage} />
        </>
      )}
    </div>
  )
}
