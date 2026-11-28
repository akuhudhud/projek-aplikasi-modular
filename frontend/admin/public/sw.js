
self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
  let payload = {};

  try {
    payload = event.data ? event.data.json() : {};
  } catch {
    payload = {
      title: 'SUPER RUNNER',
      message: event.data
        ? event.data.text()
        : 'Notifikasi baharu',
    };
  }

  const title =
    typeof payload.title === 'string'
      ? payload.title
      : 'SUPER RUNNER';

  const options = {
    body:
      typeof payload.message === 'string'
        ? payload.message
        : 'Anda mempunyai notifikasi operasi baharu.',
    icon: '/icons/admin-icon-192.png',
    badge: '/icons/admin-badge-72.png',
    tag:
      typeof payload.event === 'string'
        ? payload.event
        : 'admin-operational',
    renotify: true,
    requireInteraction: payload.severity === 'CRITICAL',
    data: {
      actionPath:
        typeof payload.action_path === 'string'
          ? payload.action_path
          : '/',
      event:
        typeof payload.event === 'string'
          ? payload.event
          : null,
    },
  };

  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const actionPath =
    event.notification.data?.actionPath || '/';

  let targetUrl;

  try {
    targetUrl = new URL(actionPath, self.location.origin);

    if (targetUrl.origin !== self.location.origin) {
      targetUrl = new URL('/', self.location.origin);
    }
  } catch {
    targetUrl = new URL('/', self.location.origin);
  }

  event.waitUntil(
    (async () => {
      const windows = await self.clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
      });

      for (const client of windows) {
        if ('focus' in client) {
          await client.focus();

          if ('navigate' in client) {
            await client.navigate(targetUrl.href);
          }

          return;
        }
      }

      await self.clients.openWindow(targetUrl.href);
    })()
  );
});
