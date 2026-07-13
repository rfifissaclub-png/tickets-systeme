import { useState } from 'react'
import { Outlet } from 'react-router'
import { useTranslation } from 'react-i18next'
import { MenuIcon, TicketIcon } from 'lucide-react'

import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { ErrorBoundary } from '@/components/common/error-boundary'
import { useRealtimeNotifications } from '@/hooks/use-realtime-notifications'

import { EmailVerificationBanner } from './email-verification-banner'
import { LanguageToggle } from './language-toggle'
import { NotificationBell } from './notification-bell'
import { SidebarNav } from './sidebar-nav'
import { ThemeToggle } from './theme-toggle'
import { UserMenu } from './user-menu'

export function AppShell() {
  const { t } = useTranslation()
  const [mobileNavOpen, setMobileNavOpen] = useState(false)

  // Mounted once here (not per-page) so the WebSocket subscription persists
  // across client-side navigation instead of reconnecting on every route.
  useRealtimeNotifications()

  return (
    <div className="bg-background flex min-h-svh">
      {/* Desktop sidebar */}
      <aside className="bg-sidebar text-sidebar-foreground border-sidebar-border hidden w-64 shrink-0 border-r md:flex md:flex-col">
        <div className="flex h-14 items-center gap-2 border-b px-4">
          <TicketIcon className="text-primary size-5" aria-hidden="true" />
          <span className="font-semibold">{t('common.appName')}</span>
        </div>
        <SidebarNav />
      </aside>

      {/* Mobile sidebar */}
      <Dialog open={mobileNavOpen} onOpenChange={setMobileNavOpen}>
        <DialogContent className="left-0 top-0 h-full w-64 max-w-[80vw] translate-x-0 translate-y-0 rounded-none p-0 sm:max-w-[80vw]">
          <DialogTitle className="sr-only">{t('nav.navigation')}</DialogTitle>
          <div className="flex h-14 items-center gap-2 border-b px-4">
            <TicketIcon className="text-primary size-5" aria-hidden="true" />
            <span className="font-semibold">TMS</span>
          </div>
          <SidebarNav onNavigate={() => setMobileNavOpen(false)} />
        </DialogContent>
      </Dialog>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="bg-background/95 supports-[backdrop-filter]:bg-background/60 sticky top-0 z-30 flex h-14 items-center gap-3 border-b px-4 backdrop-blur">
          <Button
            variant="ghost"
            size="icon"
            className="md:hidden"
            aria-label={t('nav.openNavigation')}
            onClick={() => setMobileNavOpen(true)}
          >
            <MenuIcon className="size-5" />
          </Button>
          <div className="flex-1" />
          <NotificationBell />
          <LanguageToggle />
          <ThemeToggle />
          <UserMenu />
        </header>

        <EmailVerificationBanner />

        <main className="flex-1 p-4 md:p-6">
          <ErrorBoundary>
            <Outlet />
          </ErrorBoundary>
        </main>
      </div>
    </div>
  )
}
