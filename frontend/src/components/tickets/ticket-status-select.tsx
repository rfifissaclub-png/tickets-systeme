import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { toApiError } from '@/lib/api-client'
import { useTransitionTicketStatus } from '@/hooks/use-tickets'
import type { TicketStatus } from '@/types/models'

/**
 * Mirrors App\Services\Tickets\TicketStatusTransitions on the backend — kept
 * in sync by hand, not generated. Only used to decide which options to
 * *show*; the backend is the actual authority and will reject anything not
 * in its own graph regardless of what this renders.
 */
const ALLOWED_TRANSITIONS: Record<TicketStatus, TicketStatus[]> = {
  pending: ['in_progress', 'on_hold'],
  in_progress: ['on_hold', 'resolved'],
  on_hold: ['in_progress'],
  resolved: ['in_progress', 'closed'],
  closed: [],
}

export function TicketStatusSelect({ ticketId, status }: { ticketId: string; status: TicketStatus }) {
  const { t } = useTranslation()
  const { mutate, isPending } = useTransitionTicketStatus(ticketId)
  const [pendingValue, setPendingValue] = useState<TicketStatus | null>(null)

  const options = ALLOWED_TRANSITIONS[status]

  if (options.length === 0) {
    return <span className="text-muted-foreground text-sm">{t('tickets.detail.noFurtherTransitions')}</span>
  }

  return (
    <Select
      value={pendingValue ?? status}
      disabled={isPending}
      onValueChange={(value) => {
        const nextStatus = value as TicketStatus
        setPendingValue(nextStatus)
        mutate(nextStatus, {
          onSuccess: () =>
            toast.success(t('tickets.detail.statusChangedToast', { status: t(`tickets.status.${nextStatus}`) })),
          onError: (error) => {
            toast.error(toApiError(error).message)
            setPendingValue(null)
          },
        })
      }}
    >
      <SelectTrigger className="w-[180px]" aria-label={t('tickets.detail.status')}>
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={status}>
          {t(`tickets.status.${status}`)} {t('tickets.detail.current')}
        </SelectItem>
        {options.map((option) => (
          <SelectItem key={option} value={option}>
            {t('tickets.detail.moveTo', { status: t(`tickets.status.${option}`) })}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
