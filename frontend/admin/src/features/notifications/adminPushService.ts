
import { adminApi } from '../../services/adminApiClient';

interface VapidKeyResponse {
  success: boolean;
  data: {
    public_key: string;
  };
}

interface PushSubscriptionResponse {
  success: boolean;
  message: string;
  data?: {
    id: string;
  };
}

interface PushSubscriptionPayload {
  endpoint: string;
  keys: {
    p256dh: string;
    auth: string;
  };
  content_encoding: string;
}

export interface AdminPushStatus {
  supported: boolean;
  permission: NotificationPermission | 'unsupported';
  subscribed: boolean;
}

function decodeBase64Url(value: string): Uint8Array {
  const padding = '='.repeat((4 - (value.length % 4)) % 4);
  const base64 = (value + padding)
    .replace(/-/g, '+')
    .replace(/_/g, '/');

  const raw = window.atob(base64);
  const output = new Uint8Array(raw.length);

  for (let index = 0; index < raw.length; index++) {
    output[index] = raw.charCodeAt(index);
  }

  return output;
}

function isPushSupported(): boolean {
  return (
    typeof window !== 'undefined' &&
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'Notification' in window
  );
}

export function getAdminPushStatus(): AdminPushStatus {
  if (!isPushSupported()) {
    return {
      supported: false,
      permission: 'unsupported',
      subscribed: false,
    };
  }

  return {
    supported: true,
    permission: Notification.permission,
    subscribed: false,
  };
}

async function getServiceWorkerRegistration():
Promise<ServiceWorkerRegistration> {
  if (!isPushSupported()) {
    throw new Error(
      'Pelayar ini tidak menyokong Web Push Notification.'
    );
  }

  if (!window.isSecureContext) {
    throw new Error(
      'Web Push memerlukan HTTPS atau localhost.'
    );
  }

  await navigator.serviceWorker.register('/sw.js', {
    scope: '/',
  });

  return navigator.serviceWorker.ready;
}

async function getVapidPublicKey(): Promise<string> {
  const response = await adminApi.get<VapidKeyResponse>(
    '/admin/push-public-key'
  );

  const publicKey = response.data.data?.public_key;

  if (
    !response.data.success ||
    typeof publicKey !== 'string' ||
    publicKey.length === 0
  ) {
    throw new Error(
      'VAPID Public Key tidak tersedia.'
    );
  }

  return publicKey;
}

function serializeSubscription(
  subscription: PushSubscription
): PushSubscriptionPayload {
  const json = subscription.toJSON();

  if (
    !subscription.endpoint ||
    !json.keys?.p256dh ||
    !json.keys?.auth
  ) {
    throw new Error(
      'Maklumat Push Subscription tidak lengkap.'
    );
  }

  return {
    endpoint: subscription.endpoint,
    keys: {
      p256dh: json.keys.p256dh,
      auth: json.keys.auth,
    },
    content_encoding: 'aes128gcm',
  };
}

export async function enableAdminPush():
Promise<PushSubscriptionResponse> {
  if (!isPushSupported()) {
    throw new Error(
      'Pelayar tidak menyokong notifikasi Push.'
    );
  }

  const registration = await getServiceWorkerRegistration();

  let permission = Notification.permission;

  if (permission === 'default') {
    permission = await Notification.requestPermission();
  }

  if (permission !== 'granted') {
    throw new Error(
      permission === 'denied'
        ? 'Kebenaran notifikasi telah disekat. Sila aktifkan melalui tetapan pelayar.'
        : 'Kebenaran notifikasi tidak diberikan.'
    );
  }

  const publicKey = await getVapidPublicKey();

  let subscription =
    await registration.pushManager.getSubscription();

  let createdSubscription = false;

  if (!subscription) {
    subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: decodeBase64Url(publicKey),
    });

    createdSubscription = true;
  }

  let payload: PushSubscriptionPayload;

  try {
    payload = serializeSubscription(subscription);
  } catch (error) {
    if (createdSubscription) {
      await subscription.unsubscribe().catch(() => false);
    }

    throw error;
  }

  try {
    const response = await adminApi.post<PushSubscriptionResponse>(
      '/admin/push-subscriptions',
      payload
    );

    return response.data;
  } catch (error) {
    // Batalkan hanya subscription yang baru dicipta.
    // Subscription sedia ada tidak boleh dibatalkan
    // hanya kerana permintaan API gagal.
    if (createdSubscription) {
      await subscription.unsubscribe().catch(() => false);
    }

    throw error;
  }
}

export async function disableAdminPush():
Promise<PushSubscriptionResponse | null> {
  if (!isPushSupported()) {
    return null;
  }

  const registration = await navigator.serviceWorker.getRegistration(
    '/'
  );

  if (!registration) {
    return null;
  }

  const subscription =
    await registration.pushManager.getSubscription();

  if (!subscription) {
    return null;
  }

  const endpoint = subscription.endpoint;

  // Padam rekod backend dahulu.
  const response = await adminApi.delete<PushSubscriptionResponse>(
    '/admin/push-subscriptions',
    {
      data: {
        endpoint,
      },
    }
  );

  const unsubscribed = await subscription.unsubscribe();

  if (!unsubscribed) {
    throw new Error(
      'Pendaftaran Push gagal dinyahaktifkan pada pelayar.'
    );
  }

  return response.data;
}

export async function isAdminPushSubscribed(): Promise<boolean> {
  if (!isPushSupported()) {
    return false;
  }

  const registration = await navigator.serviceWorker.getRegistration(
    '/'
  );

  if (!registration) {
    return false;
  }

  const subscription =
    await registration.pushManager.getSubscription();

  return subscription !== null;
}
