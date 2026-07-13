import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ScrollTextIcon, SearchIcon } from 'lucide-react'

import { EmptyState } from '@/components/common/empty-state'
import { Pagination } from '@/components/common/pagination'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuditLogs } from '@/hooks/use-audit-logs'
import { useDebouncedValue } from '@/hooks/use-debounced-value'

export function AuditLogsPage() {
  const { t } = useTranslation()
  const [action, setAction] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [page, setPage] = useState(1)

  const debouncedAction = useDebouncedValue(action)

  const { data, isLoading } = useAuditLogs({
    action: debouncedAction || undefined,
    from: from || undefined,
    to: to || undefined,
    page,
  })

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t('auditLogs.title')}</h1>
        <p className="text-muted-foreground text-sm">{t('auditLogs.subtitle')}</p>
      </div>

      <div className="flex flex-wrap items-end gap-3">
        <div className="min-w-[240px] flex-1">
          <Label className="mb-1.5 block text-xs">{t('auditLogs.filterAction')}</Label>
          <div className="relative">
            <SearchIcon className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
            <Input
              value={action}
              onChange={(event) => {
                setAction(event.target.value)
                setPage(1)
              }}
              placeholder={t('auditLogs.filterActionPlaceholder')}
              className="pl-9"
            />
          </div>
        </div>
        <div>
          <Label className="mb-1.5 block text-xs">{t('auditLogs.filterFrom')}</Label>
          <Input
            type="date"
            value={from}
            onChange={(event) => {
              setFrom(event.target.value)
              setPage(1)
            }}
          />
        </div>
        <div>
          <Label className="mb-1.5 block text-xs">{t('auditLogs.filterTo')}</Label>
          <Input
            type="date"
            value={to}
            onChange={(event) => {
              setTo(event.target.value)
              setPage(1)
            }}
          />
        </div>
      </div>

      {isLoading && (
        <div className="space-y-2">
          {Array.from({ length: 6 }).map((_, index) => (
            <Skeleton key={index} className="h-12 w-full" />
          ))}
        </div>
      )}

      {!isLoading && data && data.logs.length === 0 && (
        <EmptyState icon={ScrollTextIcon} title={t('auditLogs.emptyTitle')} />
      )}

      {!isLoading && data && data.logs.length > 0 && (
        <>
          <div className="overflow-hidden rounded-lg border">
            <table className="w-full text-sm">
              <thead className="bg-muted/50 text-muted-foreground text-left">
                <tr>
                  <th className="px-4 py-3 font-medium">{t('auditLogs.columnDate')}</th>
                  <th className="px-4 py-3 font-medium">{t('auditLogs.columnActor')}</th>
                  <th className="px-4 py-3 font-medium">{t('auditLogs.columnAction')}</th>
                  <th className="px-4 py-3 font-medium">{t('auditLogs.columnTarget')}</th>
                  <th className="px-4 py-3 font-medium">{t('auditLogs.columnIp')}</th>
                </tr>
              </thead>
              <tbody className="divide-border divide-y">
                {data.logs.map((log) => (
                  <tr key={log.id}>
                    <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">
                      {new Date(log.created_at).toLocaleString()}
                    </td>
                    <td className="px-4 py-3">
                      {log.actor ? (
                        <div>
                          <div className="font-medium">{log.actor.name}</div>
                          <div className="text-muted-foreground text-xs">{log.actor.email}</div>
                        </div>
                      ) : (
                        <span className="text-muted-foreground">{t('common.someone')}</span>
                      )}
                    </td>
                    <td className="px-4 py-3 font-mono text-xs">{log.action}</td>
                    <td className="text-muted-foreground px-4 py-3 font-mono text-xs">
                      {log.auditable_type ? `${log.auditable_type.split('\\').pop()} · ${log.auditable_id}` : t('common.dash')}
                    </td>
                    <td className="text-muted-foreground px-4 py-3">{log.ip_address ?? t('common.dash')}</td>
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
