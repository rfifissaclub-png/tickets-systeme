import { Badge, type badgeVariants } from '@/components/ui/badge'
import type { TicketPriority } from '@/types/models'
import type { VariantProps } from 'class-variance-authority'

type BadgeVariant = VariantProps<typeof badgeVariants>['variant']

const PRIORITY_CONFIG: Record<TicketPriority, { label: string; variant: BadgeVariant }> = {
  low: { label: 'Low', variant: 'outline' },
  medium: { label: 'Medium', variant: 'secondary' },
  high: { label: 'High', variant: 'warning' },
  urgent: { label: 'Urgent', variant: 'destructive' },
}

export function TicketPriorityBadge({ priority }: { priority: TicketPriority }) {
  const config = PRIORITY_CONFIG[priority]
  return <Badge variant={config.variant}>{config.label}</Badge>
}
