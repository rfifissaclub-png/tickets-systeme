import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { InboxIcon, TicketIcon } from 'lucide-react'

import { StatTile } from '@/components/charts/stat-tile'
import { EmptyState } from '@/components/common/empty-state'
import { TicketPriorityBadge } from '@/components/tickets/priority-badge'
import { TicketStatusBadge } from '@/components/tickets/status-badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuth, useHasRole } from '@/hooks/use-auth'
import { useReportOverview } from '@/hooks/use-reports'
import { useTicketPool } from '@/hooks/use-ticket-pool'
import { useTickets } from '@/hooks/use-tickets'
import { formatDuration } from '@/lib/format'

const OPEN_STATUSES = ['pending', 'in_progress', 'on_hold'] as const

export function DashboardPage() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const isStaff = useHasRole('Support Agent', 'Manager', 'Tenant Admin', 'Super Admin')
  const canSeeReports = useHasRole('Manager', 'Tenant Admin', 'Super Admin')
  const canSeePool = useHasRole('Support Agent', 'Manager', 'Tenant Admin', 'Super Admin')

  // Not fetched at all when the KPI tiles below are hidden anyway (a plain
  // Client/Support Agent can't view reports — see nav-config.ts) — an
  // unconditional call here would 403 on every single dashboard load for a
  // result the JSX never uses.
  const { data: overview, isLoading: overviewLoading } = useReportOverview(canSeeReports)

  const { data: myTickets, isLoading: myTicketsLoading } = useTickets(
    isStaff
      ? { agent_id: user?.id, status: [...OPEN_STATUSES], per_page: 5, sort: '-updated_at' }
      : { client_id: user?.id, status: [...OPEN_STATUSES], per_page: 5, sort: '-updated_at' },
  )

  const { data: pool, isLoading: poolLoading } = useTicketPool(1)

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">
          {user ? t('dashboard.welcomeBackNamed', { name: user.name }) : t('dashboard.welcomeBack')}
        </h1>
        <p className="text-muted-foreground text-sm">{t('dashboard.subtitle')}</p>
      </div>

      {canSeeReports && (
        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
          {overviewLoading ? (
            Array.from({ length: 4 }).map((_, index) => <Skeleton key={index} className="h-24 w-full" />)
          ) : (
            <>
              <StatTile label={t('dashboard.totalTickets')} value={String(overview?.total_tickets ?? 0)} />
              <StatTile
                label={t('dashboard.openTickets')}
                value={String((overview?.total_tickets ?? 0) - (overview?.by_status.closed ?? 0))}
              />
              <StatTile
                label={t('dashboard.avgFirstResponse')}
                value={formatDuration(overview?.avg_first_response_seconds ?? null)}
              />
              <StatTile
                label={t('dashboard.avgResolution')}
                value={formatDuration(overview?.avg_resolution_seconds ?? null)}
              />
            </>
          )}
        </div>
      )}

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle className="text-sm">{isStaff ? t('dashboard.myTickets') : t('dashboard.myOpenTickets')}</CardTitle>
            <Button variant="ghost" size="sm" asChild>
              <Link to="/tickets">{t('dashboard.viewAll')}</Link>
            </Button>
          </CardHeader>
          <CardContent>
            {myTicketsLoading && (
              <div className="space-y-2">
                <Skeleton className="h-12 w-full" />
                <Skeleton className="h-12 w-full" />
              </div>
            )}

            {!myTicketsLoading && myTickets && myTickets.tickets.length === 0 && (
              <EmptyState icon={TicketIcon} title={t('dashboard.noTicketsTitle')} className="border-none p-6" />
            )}

            {!myTicketsLoading && myTickets && myTickets.tickets.length > 0 && (
              <ul className="divide-border divide-y">
                {myTickets.tickets.map((ticket) => (
                  <li key={ticket.id} className="py-2 first:pt-0 last:pb-0">
                    <Link to={`/tickets/${ticket.id}`} className="flex items-center justify-between gap-3">
                      <span className="line-clamp-1 text-sm font-medium hover:underline">{ticket.subject}</span>
                      <span className="flex shrink-0 items-center gap-2">
                        <TicketPriorityBadge priority={ticket.priority} />
                        <TicketStatusBadge status={ticket.status} />
                      </span>
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>

        <Card>
          {isStaff && canSeePool ? (
            <>
              <CardHeader>
                <CardTitle className="text-sm">{t('dashboard.ticketPool')}</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                {poolLoading ? (
                  <Skeleton className="h-16 w-full" />
                ) : (
                  <div className="flex items-center gap-4">
                    <InboxIcon className="text-muted-foreground size-8" aria-hidden="true" />
                    <div>
                      <p className="text-2xl font-semibold">{pool?.meta.total ?? 0}</p>
                      <p className="text-muted-foreground text-sm">{t('dashboard.availableToPull')}</p>
                    </div>
                  </div>
                )}
                <Button asChild variant="outline">
                  <Link to="/ticket-pool">{t('dashboard.goToPool')}</Link>
                </Button>
              </CardContent>
            </>
          ) : (
            <>
              <CardHeader>
                <CardTitle className="text-sm">{t('dashboard.needHelp')}</CardTitle>
              </CardHeader>
              <CardContent>
                <Button asChild>
                  <Link to="/tickets/new">{t('dashboard.newTicket')}</Link>
                </Button>
              </CardContent>
            </>
          )}
        </Card>
      </div>
    </div>
  )
}
