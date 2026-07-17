import { BarChart3Icon } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { HorizontalBarChart, type BarDatum } from '@/components/charts/horizontal-bar-chart'
import { StatTile } from '@/components/charts/stat-tile'
import { TrendLineChart } from '@/components/charts/trend-line-chart'
import { EmptyState } from '@/components/common/empty-state'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useHasRole } from '@/hooks/use-auth'
import { useReportOverview, useTicketsByAgentReport, useTicketsByTenantReport, useTicketTrends } from '@/hooks/use-reports'
import { formatDuration } from '@/lib/format'
import type { TicketPriority, TicketStatus } from '@/types/models'

const STATUS_ORDER: TicketStatus[] = ['pending', 'in_progress', 'on_hold', 'resolved', 'closed']
const PRIORITY_ORDER: TicketPriority[] = ['low', 'medium', 'high', 'urgent']

export function ReportsPage() {
  const { t } = useTranslation()
  const isSuperAdmin = useHasRole('Super Admin')

  const { data: overview, isLoading: overviewLoading, isError: overviewError } = useReportOverview()
  const { data: byAgent, isLoading: byAgentLoading, isError: byAgentError } = useTicketsByAgentReport()
  const { data: byTenant, isLoading: byTenantLoading, isError: byTenantError } = useTicketsByTenantReport(isSuperAdmin)
  const { data: trends, isLoading: trendsLoading, isError: trendsError } = useTicketTrends()

  // All four endpoints are gated by the same reports.view permission (or
  // Super Admin, for by-tenant) — if any of them 403s, the rest are
  // meaningless too, so one shared guard replaces the page's whole content
  // rather than every card separately.
  const isError = overviewError || byAgentError || byTenantError || trendsError

  const statusData: BarDatum[] = STATUS_ORDER.map((status) => ({
    label: t(`tickets.status.${status}`),
    value: overview?.by_status[status] ?? 0,
  }))

  const priorityData: BarDatum[] = PRIORITY_ORDER.map((priority) => ({
    label: t(`tickets.priority.${priority}`),
    value: overview?.by_priority[priority] ?? 0,
  }))

  const categoricalColors = [
    'var(--viz-series-1)',
    'var(--viz-series-2)',
    'var(--viz-series-3)',
    'var(--viz-series-4)',
    'var(--viz-series-5)',
  ]
  const sequentialColors = [
    'var(--viz-sequential-1)',
    'var(--viz-sequential-2)',
    'var(--viz-sequential-3)',
    'var(--viz-sequential-4)',
  ]

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t('reports.title')}</h1>
        <p className="text-muted-foreground text-sm">{t('reports.subtitle')}</p>
      </div>

      {isError ? (
        <EmptyState
          icon={BarChart3Icon}
          title={t('reports.loadErrorTitle')}
          description={t('reports.loadErrorDescription')}
        />
      ) : (
        <>
          {overviewLoading ? (
            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
              {Array.from({ length: 4 }).map((_, index) => (
                <Skeleton key={index} className="h-24 w-full" />
              ))}
            </div>
          ) : (
            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
              <StatTile label={t('reports.totalTickets')} value={String(overview?.total_tickets ?? 0)} />
              <StatTile
                label={t('reports.avgFirstResponse')}
                value={formatDuration(overview?.avg_first_response_seconds ?? null)}
              />
              <StatTile
                label={t('reports.avgResolutionTime')}
                value={formatDuration(overview?.avg_resolution_seconds ?? null)}
              />
              <StatTile
                label={t('reports.openTickets')}
                value={String((overview?.total_tickets ?? 0) - (overview?.by_status.closed ?? 0))}
              />
            </div>
          )}

          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <Card>
              <CardContent className="pt-6">
                {overviewLoading ? (
                  <Skeleton className="h-40 w-full" />
                ) : (
                  <HorizontalBarChart title={t('reports.ticketsByStatus')} data={statusData} colors={categoricalColors} />
                )}
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-6">
                {overviewLoading ? (
                  <Skeleton className="h-40 w-full" />
                ) : (
                  <HorizontalBarChart title={t('reports.ticketsByPriority')} data={priorityData} colors={sequentialColors} />
                )}
              </CardContent>
            </Card>
          </div>

          <Card>
            <CardContent className="pt-6">
              {trendsLoading ? (
                <Skeleton className="h-64 w-full" />
              ) : (
                <TrendLineChart title={t('reports.trendsTitle')} data={trends ?? []} />
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-sm">{t('reports.ticketsByAgent')}</CardTitle>
            </CardHeader>
            <CardContent>
              {byAgentLoading ? (
                <Skeleton className="h-32 w-full" />
              ) : !byAgent || byAgent.length === 0 ? (
                <p className="text-muted-foreground text-sm">{t('reports.noAssignedTickets')}</p>
              ) : (
                <div className="overflow-hidden rounded-lg border">
                  <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-muted-foreground text-left">
                      <tr>
                        <th className="px-4 py-2 font-medium">{t('reports.columnAgent')}</th>
                        <th className="px-4 py-2 font-medium">{t('reports.columnTickets')}</th>
                        <th className="px-4 py-2 font-medium">{t('reports.columnAvgFirstResponse')}</th>
                        <th className="px-4 py-2 font-medium">{t('reports.columnAvgResolution')}</th>
                      </tr>
                    </thead>
                    <tbody className="divide-border divide-y">
                      {byAgent.map((row) => (
                        <tr key={row.agent_id}>
                          <td className="px-4 py-2">{row.agent_name}</td>
                          <td className="px-4 py-2 tabular-nums">{row.ticket_count}</td>
                          <td className="px-4 py-2 tabular-nums">{formatDuration(row.avg_first_response_seconds)}</td>
                          <td className="px-4 py-2 tabular-nums">{formatDuration(row.avg_resolution_seconds)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CardContent>
          </Card>

          {isSuperAdmin && (
            <Card>
              <CardHeader>
                <CardTitle className="text-sm">{t('reports.ticketsByTenant')}</CardTitle>
              </CardHeader>
              <CardContent>
                {byTenantLoading ? (
                  <Skeleton className="h-32 w-full" />
                ) : !byTenant || byTenant.length === 0 ? (
                  <p className="text-muted-foreground text-sm">{t('reports.noTenantTickets')}</p>
                ) : (
                  <div className="overflow-hidden rounded-lg border">
                    <table className="w-full text-sm">
                      <thead className="bg-muted/50 text-muted-foreground text-left">
                        <tr>
                          <th className="px-4 py-2 font-medium">{t('reports.columnTenant')}</th>
                          <th className="px-4 py-2 font-medium">{t('reports.columnTickets')}</th>
                        </tr>
                      </thead>
                      <tbody className="divide-border divide-y">
                        {byTenant.map((row) => (
                          <tr key={row.tenant_id}>
                            <td className="px-4 py-2">{row.tenant_name}</td>
                            <td className="px-4 py-2 tabular-nums">{row.ticket_count}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </CardContent>
            </Card>
          )}
        </>
      )}
    </div>
  )
}
