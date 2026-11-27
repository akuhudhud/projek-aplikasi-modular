
import { useCallback, useEffect, useState } from 'react';

import {
  getAdminNotifications,
  markAdminNotificationAsRead,
  markAllAdminNotificationsAsRead,
} from './adminNotificationService';

import type {
  AdminNotification,
} from './adminNotificationService';

import AdminPushToggle from './AdminPushToggle';

export default function AdminNotificationBell() {
  const [notifications, setNotifications] = useState<AdminNotification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');

  const loadNotifications = useCallback(async () => {
    if (document.visibilityState === 'hidden') return;

    try {
      const result = await getAdminNotifications();

      setNotifications(result.notifications);
      setUnreadCount(result.unread_count);
      setError('');
    } catch {
      setError('Notifikasi gagal dimuatkan.');
    }
  }, []);

  useEffect(() => {
    void loadNotifications();

    const interval = window.setInterval(() => {
      void loadNotifications();
    }, 15000);

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        void loadNotifications();
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);

    return () => {
      window.clearInterval(interval);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    };
  }, [loadNotifications]);

  async function handleMarkAsRead(notification: AdminNotification) {
    if (notification.read_at) return;

    try {
      const result = await markAdminNotificationAsRead(notification.id);

      setNotifications((current) =>
        current.map((item) =>
          item.id === notification.id
            ? { ...item, read_at: new Date().toISOString() }
            : item,
        ),
      );

      setUnreadCount(result.unread_count);
    } catch {
      setError('Status notifikasi gagal dikemaskini.');
    }
  }

  async function handleMarkAllAsRead() {
    setIsLoading(true);
    setError('');

    try {
      await markAllAdminNotificationsAsRead();

      setNotifications((current) =>
        current.map((item) => ({
          ...item,
          read_at: item.read_at ?? new Date().toISOString(),
        })),
      );

      setUnreadCount(0);
    } catch {
      setError('Notifikasi gagal ditandakan sebagai dibaca.');
    } finally {
      setIsLoading(false);
    }
  }

  function formatDate(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return '';

    return date.toLocaleString('ms-MY', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  return (
    <div
      className="admin-notification"
      style={{ position: 'relative' }}
    >
      <button
        type="button"
        aria-label={`Notifikasi, ${unreadCount} belum dibaca`}
        aria-expanded={isOpen}
        onClick={() => {
          setIsOpen((current) => !current);

          if (!isOpen) {
            void loadNotifications();
          }
        }}
        style={{
          position: 'relative',
          width: 40,
          height: 40,
          border: '1px solid var(--admin-border)',
          borderRadius: 8,
          background: 'var(--admin-panel-soft)',
          color: 'var(--admin-text)',
          cursor: 'pointer',
          fontSize: 20,
        }}
      >
        <span aria-hidden="true">🔔</span>

        {unreadCount > 0 && (
          <span
            style={{
              position: 'absolute',
              top: -5,
              right: -5,
              minWidth: 18,
              height: 18,
              padding: '0 4px',
              borderRadius: 20,
              background: '#dc2626',
              color: '#fff',
              fontSize: 10,
              fontWeight: 700,
              lineHeight: '18px',
            }}
          >
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </button>

      {isOpen && (
        <div
          role="dialog"
          aria-label="Pusat notifikasi Admin"
          style={{
            position: 'absolute',
            top: 48,
            right: 0,
            zIndex: 100,
            width: 'min(360px, calc(100vw - 24px))',
            maxHeight: 560,
            overflowY: 'auto',
            border: '1px solid var(--admin-border)',
            borderRadius: 10,
            background: 'var(--admin-panel)',
            color: 'var(--admin-text)',
            boxShadow: '0 12px 32px rgba(0,0,0,.25)',
          }}
        >
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              gap: 8,
              padding: 14,
              borderBottom: '1px solid var(--admin-border)',
            }}
          >
            <strong>Notifikasi Operasi</strong>

            <button
              type="button"
              onClick={() => void handleMarkAllAsRead()}
              disabled={unreadCount === 0 || isLoading}
              style={{
                border: 0,
                background: 'transparent',
                color: 'var(--admin-yellow)',
                cursor: unreadCount === 0 ? 'default' : 'pointer',
                fontSize: 12,
                opacity: unreadCount === 0 ? 0.5 : 1,
              }}
            >
              Baca semua
            </button>
          </div>

          <div
            style={{
              padding: 12,
              borderBottom: '1px solid var(--admin-border)',
            }}
          >
            <AdminPushToggle />
          </div>

          {error && (
            <p
              role="alert"
              style={{
                padding: '8px 14px',
                margin: 0,
                color: '#ef4444',
                fontSize: 12,
              }}
            >
              {error}
            </p>
          )}

          {notifications.length === 0 && !error && (
            <p
              style={{
                padding: 24,
                margin: 0,
                textAlign: 'center',
                opacity: 0.7,
                fontSize: 13,
              }}
            >
              Tiada notifikasi buat masa ini.
            </p>
          )}

          {notifications.map((notification) => {
            const data = notification.data;
            const isUnread = notification.read_at === null;

            return (
              <button
                type="button"
                key={notification.id}
                onClick={() => void handleMarkAsRead(notification)}
                style={{
                  display: 'block',
                  width: '100%',
                  padding: 14,
                  border: 0,
                  borderBottom: '1px solid var(--admin-border)',
                  background: isUnread
                    ? 'var(--admin-panel-soft)'
                    : 'transparent',
                  color: 'var(--admin-text)',
                  textAlign: 'left',
                  cursor: 'pointer',
                }}
              >
                <div
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 8,
                  }}
                >
                  <strong style={{ fontSize: 13 }}>
                    {data.title || 'Notifikasi operasi'}
                  </strong>

                  {isUnread && (
                    <span
                      aria-label="Belum dibaca"
                      style={{
                        width: 8,
                        height: 8,
                        flexShrink: 0,
                        borderRadius: '50%',
                        background: '#eab308',
                      }}
                    />
                  )}
                </div>

                <p
                  style={{
                    margin: '6px 0',
                    fontSize: 12,
                    lineHeight: 1.5,
                    opacity: 0.8,
                  }}
                >
                  {data.message || 'Maklumat operasi baharu.'}
                </p>

                <span
                  style={{
                    fontSize: 11,
                    opacity: 0.6,
                  }}
                >
                  {formatDate(notification.created_at)}
                </span>
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}
