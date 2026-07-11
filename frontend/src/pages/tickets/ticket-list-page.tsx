import { useState } from 'react'
import { Link } from 'react-router'
import { useTranslation } from 'react-i18next'
import { PlusIcon, SearchIcon, TicketIcon } from 'lucide-react'

import { EmptyState } from '@/components/common/empty-state'
import { Pagination } from '@/components/common/pagination'
import { TicketPriorityBadge } from '@/components/tickets/priority-badge'
import { TicketStatusBadge } from '@/components/tickets/status-badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { useTickets } from '@/hooks/use-tickets'
import type { TicketPriority, TicketStatus } from '@/types/models'

const STATUS_VALUES: (TicketStatus | 'all')[] = ['all', 'pending', 'in_progress', 'on_hold', 'resolved', 'closed']
const PRIORITY_VALUES: (TicketPriority | 'all')[] = ['all', 'low', 'medium', 'high', 'urgent']

export function TicketListPage() {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState<TicketStatus | 'all'>('all')
  const [priority, setPriority] = useState<TicketPriority | 'all'>('all')
  const [page, setPage] = useState(1)

  const debouncedSearch = useDebouncedValue(search)

  const { data, isLoading, isError } = useTickets({
    search: debouncedSearch || undefined,
    status: status === 'all' ? undefined : [status],
    priority: priority === 'all' ? undefined : [priority],
    page,
  })

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{t('tickets.list.title')}</h1>
          <p className="text-muted-foreground text-sm">{t('tickets.list.subtitle')}</p>
        </div>
        <Button asChild>
          <Link to="/tickets/new">
            <PlusIcon className="size-4" />
            {t('tickets.list.newTicket')}
          </Link>
        </Button>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <div className="relative min-w-[240px] flex-1">
          <SearchIcon className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
          <Input
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              setPage(1)
            }}
            placeholder={t('tickets.list.searchPlaceholder')}
            className="pl-9"
            aria-label={t('tickets.list.searchLabel')}
          />
        </div>

        <Select
          value={status}
          onValueChange={(value) => {
            setStatus(value as TicketStatus | 'all')
            setPage(1)
          }}
        >
          <SelectTrigger className="w-[160px]" aria-label={t('tickets.list.statusFilterLabel')}>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {STATUS_VALUES.map((value) => (
              <SelectItem key={value} value={value}>
                {value === 'all' ? t('tickets.list.allStatuses') : t(`tickets.status.${value}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select
          value={priority}
          onValueChange={(value) => {
            setPriority(value as TicketPriority | 'all')
            setPage(1)
          }}
        >
          <SelectTrigger className="w-[160px]" aria-label={t('tickets.list.priorityFilterLabel')}>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {PRIORITY_VALUES.map((value) => (
              <SelectItem key={value} value={value}>
                {value === 'all' ? t('tickets.list.allPriorities') : t(`tickets.priority.${value}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {isLoading && (
        <div className="space-y-2">
          {Array.from({ length: 6 }).map((_, index) => (
            <Skeleton key={index} className="h-14 w-full" />
          ))}
        </div>
      )}

      {isError && (
        <EmptyState
          icon={TicketIcon}
          title={t('tickets.list.loadErrorTitle')}
          description={t('tickets.list.loadErrorDescription')}
        />
      )}

      {!isLoading && !isError && data && data.tickets.length === 0 && (
        <EmptyState
          icon={TicketIcon}
          title={t('tickets.list.emptyTitle')}
          description={t('tickets.list.emptyDescription')}
          action={
            <Button asChild variant="outline">
              <Link to="/tickets/new">{t('tickets.list.newTicket')}</Link>
            </Button>
          }
        />
      )}

      {!isLoading && !isError && data && data.tickets.length > 0 && (
        <>
          <div className="overflow-hidden rounded-lg border">
            <table className="w-full text-sm">
              <thead className="bg-muted/50 text-muted-foreground text-left">
                <tr>
                  <th className="px-4 py-3 font-medium">{t('tickets.list.columnSubject')}</th>
                  <th className="px-4 py-3 font-medium">{t('tickets.list.columnClient')}</th>
                  <th className="px-4 py-3 font-medium">{t('tickets.list.columnAgent')}</th>
                  <th className="px-4 py-3 font-medium">{t('tickets.list.columnStatus')}</th>
                  <th className="px-4 py-3 font-medium">{t('tickets.list.columnPriority')}</th>
                  <th className="px-4 py-3 font-medium">{t('tickets.list.columnCreated')}</th>
                </tr>
              </thead>
              <tbody className="divide-border divide-y">
                {data.tickets.map((ticket) => (
                  <tr key={ticket.id} className="hover:bg-muted/30">
                    <td className="max-w-[320px] px-4 py-3">
                      <Link to={`/tickets/${ticket.id}`} className="line-clamp-1 font-medium hover:underline">
                        {ticket.subject}
                      </Link>
                    </td>
                    <td className="text-muted-foreground px-4 py-3">{ticket.client?.name ?? t('common.dash')}</td>
                    <td className="text-muted-foreground px-4 py-3">{ticket.agent?.name ?? t('common.unassigned')}</td>
                    <td className="px-4 py-3">
                      <TicketStatusBadge status={ticket.status} />
                    </td>
                    <td className="px-4 py-3">
                      <TicketPriorityBadge priority={ticket.priority} />
                    </td>
                    <td className="text-muted-foreground px-4 py-3">
                      {new Date(ticket.created_at).toLocaleDateString()}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <Pagination meta={data.meta} onPageChange={setPage} />
        </>
      )}
    </div>
  )
}
