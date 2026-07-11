import type { LucideIcon } from 'lucide-react'
import { InboxIcon } from 'lucide-react'

import { cn } from '@/lib/utils'

interface EmptyStateProps {
  icon?: LucideIcon
  title: string
  description?: string
  action?: React.ReactNode
  className?: string
}

export function EmptyState({ icon: Icon = InboxIcon, title, description, action, className }: EmptyStateProps) {
  return (
    <div
      className={cn(
        'flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed p-12 text-center',
        className,
      )}
    >
      <Icon className="text-muted-foreground size-10" aria-hidden="true" />
      <div className="space-y-1">
        <h3 className="text-sm font-medium">{title}</h3>
        {description && <p className="text-muted-foreground text-sm">{description}</p>}
      </div>
      {action}
    </div>
  )
}
