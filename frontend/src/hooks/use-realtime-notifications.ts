import { useEffect } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import i18next from 'i18next'
import { toast } from 'sonner'

import { useAuth } from '@/hooks/use-auth'
import { getEcho } from '@/lib/echo'

import { notificationKeys } from './use-notifications'

interface BroadcastNotificationPayload {
  message?: string
  ticket_id?: string
}

/**
 * Subscribes to this user's private channel (see
 * User::receivesBroadcastNotificationsOn() on the backend) for the
 * lifetime of the mounted component — call once, near the app root
 * (AppShell), not per-page. Super Admins have no tenant, hence no channel
 * under the current tenant.{id}.user.{id} scheme, so this is a no-op for
 * them by design, not an oversight.
 */
export function useRealtimeNotifications() {
  const { user } = useAuth()
  const queryClient = useQueryClient()

  useEffect(() => {
    if (!user?.tenant_id) return

    const echo = getEcho()
    if (!echo) return

    const channelName = `tenant.${user.tenant_id}.user.${user.id}`

    echo.private(channelName).notification((notification: BroadcastNotificationPayload) => {
      toast.info(notification.message ?? i18next.t('notifications.newNotification'))
      queryClient.invalidateQueries({ queryKey: notificationKeys.all })
    })

    return () => {
      echo.leave(channelName)
    }
  }, [user?.id, user?.tenant_id, queryClient])
}
