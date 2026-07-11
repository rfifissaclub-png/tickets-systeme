import { LayoutDashboardIcon } from 'lucide-react'

import { EmptyState } from '@/components/common/empty-state'
import { useAuth } from '@/hooks/use-auth'

export function DashboardPage() {
  const { user } = useAuth()

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Welcome back{user ? `, ${user.name}` : ''}</h1>
        <p className="text-muted-foreground text-sm">Here&apos;s what&apos;s happening across your tickets.</p>
      </div>

      <EmptyState
        icon={LayoutDashboardIcon}
        title="Dashboard widgets are coming in the next phase"
        description="Ticket lists, the pool, and KPI reports land here next."
      />
    </div>
  )
}
