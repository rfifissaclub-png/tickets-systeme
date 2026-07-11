import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { fetchNotifications, markAllNotificationsRead, markNotificationRead } from '@/api/notifications'

export const notificationKeys = {
  all: ['notifications'] as const,
  list: (unreadOnly: boolean) => [...notificationKeys.all, unreadOnly] as const,
}

export function useNotifications(unreadOnly = false) {
  return useQuery({
    queryKey: notificationKeys.list(unreadOnly),
    queryFn: () => fetchNotifications({ unread_only: unreadOnly }),
    // Realtime (see use-realtime-notifications.ts) is the primary update
    // path; this poll is just a fallback for missed/dropped WebSocket
    // events, so it can be slow.
    refetchInterval: 60_000,
  })
}

export function useMarkNotificationRead() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => markNotificationRead(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: notificationKeys.all }),
  })
}

export function useMarkAllNotificationsRead() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: markAllNotificationsRead,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: notificationKeys.all }),
  })
}
