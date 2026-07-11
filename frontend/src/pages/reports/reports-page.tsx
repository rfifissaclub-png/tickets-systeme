import { HorizontalBarChart, type BarDatum } from '@/components/charts/horizontal-bar-chart'
import { StatTile } from '@/components/charts/stat-tile'
import { TrendLineChart } from '@/components/charts/trend-line-chart'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useHasRole } from '@/hooks/use-auth'
import { useReportOverview, useTicketsByAgentReport, useTicketsByTenantReport, useTicketTrends } from '@/hooks/use-reports'
import { formatDuration } from '@/lib/format'
import type { TicketPriority, TicketStatus } from '@/types/models'

const STATUS_LABELS: Record<TicketStatus, string> = {
  pending: 'Pending',
  in_progress: 'In Progress',
  on_hold: 'On Hold',
  resolved: 'Resolved',
  closed: 'Closed',
}

const STATUS_ORDER: TicketStatus[] = ['pending', 'in_progress', 'on_hold', 'resolved', 'closed']

const PRIORITY_LABELS: Record<TicketPriority, string> = {
  low: 'Low',
  medium: 'Medium',
  high: 'High',
  urgent: 'Urgent',
}

const PRIORITY_ORDER: TicketPriority[] = ['low', 'medium', 'high', 'urgent']

export function ReportsPage() {
  const isSuperAdmin = useHasRole('Super Admin')

  const { data: overview, isLoading: overviewLoading } = useReportOverview()
  const { data: byAgent, isLoading: byAgentLoading } = useTicketsByAgentReport()
  const { data: byTenant, isLoading: byTenantLoading } = useTicketsByTenantReport(isSuperAdmin)
  const { data: trends, isLoading: trendsLoading } = useTicketTrends()

  const statusData: BarDatum[] = STATUS_ORDER.map((status) => ({
    label: STATUS_LABELS[status],
    value: overview?.by_status[status] ?? 0,
  }))

  const priorityData: BarDatum[] = PRIORITY_ORDER.map((priority) => ({
    label: PRIORITY_LABELS[priority],
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
        <h1 className="text-2xl font-semibold tracking-tight">Reports</h1>
        <p className="text-muted-foreground text-sm">KPIs for your tenant over all time, and trends for the last 30 days.</p>
      </div>

      {overviewLoading ? (
        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-24 w-full" />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
          <StatTile label="Total tickets" value={String(overview?.total_tickets ?? 0)} />
          <StatTile label="Avg first response" value={formatDuration(overview?.avg_first_response_seconds ?? null)} />
          <StatTile label="Avg resolution time" value={formatDuration(overview?.avg_resolution_seconds ?? null)} />
          <StatTile label="Open tickets" value={String((overview?.total_tickets ?? 0) - (overview?.by_status.closed ?? 0))} />
        </div>
      )}

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Card>
          <CardContent className="pt-6">
            {overviewLoading ? (
              <Skeleton className="h-40 w-full" />
            ) : (
              <HorizontalBarChart title="Tickets by status" data={statusData} colors={categoricalColors} />
            )}
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6">
            {overviewLoading ? (
              <Skeleton className="h-40 w-full" />
            ) : (
              <HorizontalBarChart title="Tickets by priority" data={priorityData} colors={sequentialColors} />
            )}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardContent className="pt-6">
          {trendsLoading ? (
            <Skeleton className="h-64 w-full" />
          ) : (
            <TrendLineChart title="Created vs. resolved (last 30 days)" data={trends ?? []} />
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-sm">Tickets by agent</CardTitle>
        </CardHeader>
        <CardContent>
          {byAgentLoading ? (
            <Skeleton className="h-32 w-full" />
          ) : !byAgent || byAgent.length === 0 ? (
            <p className="text-muted-foreground text-sm">No assigned tickets yet.</p>
          ) : (
            <div className="overflow-hidden rounded-lg border">
              <table className="w-full text-sm">
                <thead className="bg-muted/50 text-muted-foreground text-left">
                  <tr>
                    <th className="px-4 py-2 font-medium">Agent</th>
                    <th className="px-4 py-2 font-medium">Tickets</th>
                    <th className="px-4 py-2 font-medium">Avg first response</th>
                    <th className="px-4 py-2 font-medium">Avg resolution</th>
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
            <CardTitle className="text-sm">Tickets by tenant (platform-wide)</CardTitle>
          </CardHeader>
          <CardContent>
            {byTenantLoading ? (
              <Skeleton className="h-32 w-full" />
            ) : !byTenant || byTenant.length === 0 ? (
              <p className="text-muted-foreground text-sm">No tickets across any tenant yet.</p>
            ) : (
              <div className="overflow-hidden rounded-lg border">
                <table className="w-full text-sm">
                  <thead className="bg-muted/50 text-muted-foreground text-left">
                    <tr>
                      <th className="px-4 py-2 font-medium">Tenant</th>
                      <th className="px-4 py-2 font-medium">Tickets</th>
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
    </div>
  )
}
