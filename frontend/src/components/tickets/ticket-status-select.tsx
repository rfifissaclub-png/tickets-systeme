import { useState } from 'react'
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

const STATUS_LABELS: Record<TicketStatus, string> = {
  pending: 'Pending',
  in_progress: 'In Progress',
  on_hold: 'On Hold',
  resolved: 'Resolved',
  closed: 'Closed',
}

export function TicketStatusSelect({ ticketId, status }: { ticketId: string; status: TicketStatus }) {
  const { mutate, isPending } = useTransitionTicketStatus(ticketId)
  const [pendingValue, setPendingValue] = useState<TicketStatus | null>(null)

  const options = ALLOWED_TRANSITIONS[status]

  if (options.length === 0) {
    return <span className="text-muted-foreground text-sm">No further transitions — this ticket is closed.</span>
  }

  return (
    <Select
      value={pendingValue ?? status}
      disabled={isPending}
      onValueChange={(value) => {
        const nextStatus = value as TicketStatus
        setPendingValue(nextStatus)
        mutate(nextStatus, {
          onSuccess: () => toast.success(`Status changed to ${STATUS_LABELS[nextStatus]}.`),
          onError: (error) => {
            toast.error(toApiError(error).message)
            setPendingValue(null)
          },
        })
      }}
    >
      <SelectTrigger className="w-[180px]" aria-label="Change status">
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={status}>{STATUS_LABELS[status]} (current)</SelectItem>
        {options.map((option) => (
          <SelectItem key={option} value={option}>
            Move to {STATUS_LABELS[option]}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
