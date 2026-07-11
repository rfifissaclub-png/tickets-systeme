import { useTranslation } from 'react-i18next'

import { Badge, type badgeVariants } from '@/components/ui/badge'
import type { TicketStatus } from '@/types/models'
import type { VariantProps } from 'class-variance-authority'

type BadgeVariant = VariantProps<typeof badgeVariants>['variant']

const STATUS_VARIANT: Record<TicketStatus, BadgeVariant> = {
  pending: 'outline',
  in_progress: 'default',
  on_hold: 'warning',
  resolved: 'success',
  closed: 'secondary',
}

export function TicketStatusBadge({ status }: { status: TicketStatus }) {
  const { t } = useTranslation()
  return <Badge variant={STATUS_VARIANT[status]}>{t(`tickets.status.${status}`)}</Badge>
}
