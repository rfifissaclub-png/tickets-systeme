import { NavLink } from 'react-router'
import { useTranslation } from 'react-i18next'

import { useAuthStore } from '@/stores/auth-store'
import { cn } from '@/lib/utils'

import { NAV_ITEMS } from './nav-config'

export function SidebarNav({ onNavigate }: { onNavigate?: () => void }) {
  const { t } = useTranslation()
  const user = useAuthStore((state) => state.user)

  const items = NAV_ITEMS.filter((item) => {
    if (!item.roles) return true
    return item.roles.some((role) => user?.roles?.includes(role))
  })

  return (
    <nav className="flex flex-col gap-1 p-2" aria-label={t('nav.primary')}>
      {items.map((item) => (
        <NavLink
          key={item.to}
          to={item.to}
          onClick={onNavigate}
          end={item.to === '/'}
          className={({ isActive }) =>
            cn(
              'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
              isActive
                ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                : 'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
            )
          }
        >
          <item.icon className="size-4" aria-hidden="true" />
          {t(item.labelKey)}
        </NavLink>
      ))}
    </nav>
  )
}
