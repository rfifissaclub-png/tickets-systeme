import { useTranslation } from 'react-i18next'

import { Badge, type badgeVariants } from '@/components/ui/badge'
import type { TicketPriority } from '@/types/models'
import type { VariantProps } from 'class-variance-authority'

type BadgeVariant = VariantProps<typeof badgeVariants>['variant']

const PRIORITY_VARIANT: Record<TicketPriority, BadgeVariant> = {
  low: 'outline',
  medium: 'secondary',
  high: 'warning',
  urgent: 'destructive',
}

export function TicketPriorityBadge({ priority }: { priority: TicketPriority }) {
  const { t } = useTranslation()
  return <Badge variant={PRIORITY_VARIANT[priority]}>{t(`tickets.priority.${priority}`)}</Badge>
}
