import { useTranslation } from 'react-i18next'
import { LayoutDashboardIcon } from 'lucide-react'

import { EmptyState } from '@/components/common/empty-state'
import { useAuth } from '@/hooks/use-auth'

export function DashboardPage() {
  const { t } = useTranslation()
  const { user } = useAuth()

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">
          {user ? t('dashboard.welcomeBackNamed', { name: user.name }) : t('dashboard.welcomeBack')}
        </h1>
        <p className="text-muted-foreground text-sm">{t('dashboard.subtitle')}</p>
      </div>

      <EmptyState
        icon={LayoutDashboardIcon}
        title={t('dashboard.widgetsTitle')}
        description={t('dashboard.widgetsDescription')}
      />
    </div>
  )
}
