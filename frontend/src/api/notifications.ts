import { apiClient } from '@/lib/api-client'
import type { ApiSuccessResponse, PaginationMeta } from '@/types/api'
import type { AppNotification } from '@/types/models'

export interface NotificationListMeta extends PaginationMeta {
  unread_count: number
}

export interface NotificationListResult {
  notifications: AppNotification[]
  meta: NotificationListMeta
}

export async function fetchNotifications(
  params: { unread_only?: boolean; page?: number } = {},
): Promise<NotificationListResult> {
  const { data } = await apiClient.get<ApiSuccessResponse<AppNotification[]>>('/notifications', {
    params: {
      unread_only: params.unread_only ? '1' : undefined,
      page: params.page,
    },
  })

  return { notifications: data.data, meta: data.meta as unknown as NotificationListMeta }
}

export async function markNotificationRead(id: string): Promise<AppNotification> {
  const { data } = await apiClient.patch<ApiSuccessResponse<AppNotification>>(`/notifications/${id}/read`)
  return data.data
}

export async function markAllNotificationsRead(): Promise<void> {
  await apiClient.patch('/notifications/read-all')
}
