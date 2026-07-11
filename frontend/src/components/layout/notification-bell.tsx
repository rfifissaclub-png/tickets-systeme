import { Link } from 'react-router'
import { BellIcon, CheckCheckIcon } from 'lucide-react'

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Button } from '@/components/ui/button'
import { EmptyState } from '@/components/common/empty-state'
import { useMarkAllNotificationsRead, useMarkNotificationRead, useNotifications } from '@/hooks/use-notifications'
import { cn } from '@/lib/utils'
import type { AppNotification } from '@/types/models'

function notificationMessage(notification: AppNotification): string {
  return (notification.data.message as string | undefined) ?? 'New notification'
}

function notificationTicketId(notification: AppNotification): string | undefined {
  return notification.data.ticket_id as string | undefined
}

export function NotificationBell() {
  const { data, isLoading } = useNotifications()
  const { mutate: markRead } = useMarkNotificationRead()
  const { mutate: markAllRead, isPending: markingAll } = useMarkAllNotificationsRead()

  const unreadCount = data?.meta.unread_count ?? 0

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" className="relative" aria-label="Notifications">
          <BellIcon className="size-4" />
          {unreadCount > 0 && (
            <span className="bg-destructive absolute top-1 right-1 flex size-4 items-center justify-center rounded-full text-[10px] font-medium text-white">
              {unreadCount > 9 ? '9+' : unreadCount}
            </span>
          )}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80">
        <div className="flex items-center justify-between px-2 py-1.5">
          <DropdownMenuLabel className="p-0">Notifications</DropdownMenuLabel>
          {unreadCount > 0 && (
            <Button
              variant="ghost"
              size="sm"
              className="h-auto p-1 text-xs"
              disabled={markingAll}
              onClick={() => markAllRead()}
            >
              <CheckCheckIcon className="size-3" />
              Mark all read
            </Button>
          )}
        </div>
        <DropdownMenuSeparator />

        {isLoading && <p className="text-muted-foreground px-2 py-4 text-center text-sm">Loading…</p>}

        {!isLoading && data?.notifications.length === 0 && (
          <EmptyState icon={BellIcon} title="No notifications" className="border-none p-4" />
        )}

        {!isLoading &&
          data?.notifications.slice(0, 8).map((notification) => {
            const ticketId = notificationTicketId(notification)
            const isUnread = notification.read_at === null

            const content = (
              <div className="flex items-start gap-2">
                <span
                  className={cn('mt-1.5 size-1.5 shrink-0 rounded-full', isUnread ? 'bg-primary' : 'bg-transparent')}
                  aria-hidden="true"
                />
                <div className="min-w-0 flex-1">
                  <p className="line-clamp-2 text-sm">{notificationMessage(notification)}</p>
                  <p className="text-muted-foreground text-xs">
                    {new Date(notification.created_at).toLocaleString()}
                  </p>
                </div>
              </div>
            )

            return (
              <DropdownMenuItem
                key={notification.id}
                onClick={() => isUnread && markRead(notification.id)}
                asChild={!!ticketId}
              >
                {ticketId ? <Link to={`/tickets/${ticketId}`}>{content}</Link> : content}
              </DropdownMenuItem>
            )
          })}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
