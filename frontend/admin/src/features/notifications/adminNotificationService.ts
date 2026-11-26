
import { adminApi } from '../../../services/adminApiClient';

export interface AdminNotification {
  id: string;
  type: string;
  notifiable_type: string;
  notifiable_id: string;
  data: {
    event?: string;
    category?: string;
    title?: string;
    message?: string;
    severity?: 'INFO' | 'WARNING' | 'CRITICAL';
    resource_type?: string;
    resource_id?: string;
    action_path?: string;
  };
  read_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface AdminNotificationPagination {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface AdminNotificationListResponse {
  success: boolean;
  data: {
    unread_count: number;
    notifications: AdminNotification[];
    pagination: AdminNotificationPagination;
  };
}

export interface AdminNotificationReadResponse {
  success: boolean;
  message: string;
  data: {
    id?: string;
    read_at?: string;
    unread_count: number;
  };
}

export async function getAdminNotifications(
  page = 1
): Promise<AdminNotificationListResponse['data']> {
  const response = await adminApi.get<AdminNotificationListResponse>(
    '/admin/notifications',
    {
      params: { page }
    }
  );

  return response.data.data;
}

export async function markAdminNotificationAsRead(
  notificationId: string
): Promise<AdminNotificationReadResponse['data']> {
  const response = await adminApi.patch<AdminNotificationReadResponse>(
    `/admin/notifications/${encodeURIComponent(notificationId)}/read`
  );

  return response.data.data;
}

export async function markAllAdminNotificationsAsRead():
Promise<AdminNotificationReadResponse['data']> {
  const response = await adminApi.patch<AdminNotificationReadResponse>(
    '/admin/notifications/read-all'
  );

  return response.data.data;
}
