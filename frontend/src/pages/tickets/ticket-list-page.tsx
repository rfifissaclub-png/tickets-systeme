import { useState } from 'react'
import { Link } from 'react-router'
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

const STATUS_OPTIONS: { value: TicketStatus | 'all'; label: string }[] = [
  { value: 'all', label: 'All statuses' },
  { value: 'pending', label: 'Pending' },
  { value: 'in_progress', label: 'In Progress' },
  { value: 'on_hold', label: 'On Hold' },
  { value: 'resolved', label: 'Resolved' },
  { value: 'closed', label: 'Closed' },
]

const PRIORITY_OPTIONS: { value: TicketPriority | 'all'; label: string }[] = [
  { value: 'all', label: 'All priorities' },
  { value: 'low', label: 'Low' },
  { value: 'medium', label: 'Medium' },
  { value: 'high', label: 'High' },
  { value: 'urgent', label: 'Urgent' },
]

export function TicketListPage() {
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
          <h1 className="text-2xl font-semibold tracking-tight">Tickets</h1>
          <p className="text-muted-foreground text-sm">Search, filter, and triage your tickets.</p>
        </div>
        <Button asChild>
          <Link to="/tickets/new">
            <PlusIcon className="size-4" />
            New ticket
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
            placeholder="Search subject and description…"
            className="pl-9"
            aria-label="Search tickets"
          />
        </div>

        <Select
          value={status}
          onValueChange={(value) => {
            setStatus(value as TicketStatus | 'all')
            setPage(1)
          }}
        >
          <SelectTrigger className="w-[160px]" aria-label="Filter by status">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {STATUS_OPTIONS.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
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
          <SelectTrigger className="w-[160px]" aria-label="Filter by priority">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {PRIORITY_OPTIONS.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
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
          title="Couldn't load tickets"
          description="Something went wrong fetching the ticket list. Try refreshing the page."
        />
      )}

      {!isLoading && !isError && data && data.tickets.length === 0 && (
        <EmptyState
          icon={TicketIcon}
          title="No tickets match your filters"
          description="Try adjusting your search or filters, or create a new ticket."
          action={
            <Button asChild variant="outline">
              <Link to="/tickets/new">New ticket</Link>
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
                  <th className="px-4 py-3 font-medium">Subject</th>
                  <th className="px-4 py-3 font-medium">Client</th>
                  <th className="px-4 py-3 font-medium">Agent</th>
                  <th className="px-4 py-3 font-medium">Status</th>
                  <th className="px-4 py-3 font-medium">Priority</th>
                  <th className="px-4 py-3 font-medium">Created</th>
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
                    <td className="text-muted-foreground px-4 py-3">{ticket.client?.name ?? '—'}</td>
                    <td className="text-muted-foreground px-4 py-3">{ticket.agent?.name ?? 'Unassigned'}</td>
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
