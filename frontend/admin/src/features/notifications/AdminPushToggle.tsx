
import { useCallback, useEffect, useState } from 'react';
import {
  disableAdminPush,
  enableAdminPush,
  getAdminPushStatus,
  isAdminPushSubscribed,
} from './adminPushService';

type PushState = {
  supported: boolean;
  permission: NotificationPermission | 'unsupported';
  subscribed: boolean;
};

export default function AdminPushToggle() {
  const [state, setState] = useState<PushState>({
    supported: false,
    permission: 'unsupported',
    subscribed: false,
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const refreshStatus = useCallback(async () => {
    const status = getAdminPushStatus();

    if (!status.supported) {
      setState(status);
      setLoading(false);
      return;
    }

    try {
      const subscribed = await isAdminPushSubscribed();

      setState({
        supported: status.supported,
        permission: status.permission,
        subscribed,
      });

      setError('');
    } catch {
      setError('Tidak berjaya menyemak status notifikasi.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let active = true;

    const checkStatus = async () => {
      const status = getAdminPushStatus();

      if (!status.supported) {
        if (active) {
          setState(status);
          setLoading(false);
        }
        return;
      }

      try {
        const subscribed = await isAdminPushSubscribed();

        if (active) {
          setState({
            supported: status.supported,
            permission: status.permission,
            subscribed,
          });
          setError('');
        }
      } catch {
        if (active) {
          setError('Tidak berjaya menyemak status notifikasi.');
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    };

    void checkStatus();

    return () => {
      active = false;
    };
  }, []);

  const handleEnable = async () => {
    setLoading(true);
    setError('');

    try {
      await enableAdminPush();
      await refreshStatus();
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : 'Tidak berjaya mengaktifkan notifikasi.',
      );
    } finally {
      setLoading(false);
    }
  };

  const handleDisable = async () => {
    setLoading(true);
    setError('');

    try {
      await disableAdminPush();
      await refreshStatus();
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : 'Tidak berjaya menyahaktifkan notifikasi.',
      );
    } finally {
      setLoading(false);
    }
  };

  const permissionLabel = {
    granted: 'Dibenarkan',
    denied: 'Disekat oleh browser',
    default: 'Belum diberi kebenaran',
    unsupported: 'Tidak disokong',
  }[state.permission];

  return (
    <section
      aria-label="Tetapan notifikasi push"
      style={{
        padding: '14px',
        border: '1px solid #e5e7eb',
        borderRadius: '10px',
        background: '#ffffff',
        color: '#111827',
      }}
    >
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          gap: '12px',
        }}
      >
        <div>
          <div style={{ fontWeight: 600, fontSize: '14px' }}>
            Notifikasi Push Admin
          </div>
          <div
            style={{
              fontSize: '12px',
              color: '#6b7280',
              marginTop: '4px',
            }}
          >
            {loading
              ? 'Menyemak status...'
              : `Kebenaran: ${permissionLabel}`}
          </div>
        </div>

        <span
          style={{
            fontSize: '12px',
            fontWeight: 600,
            color: state.subscribed ? '#15803d' : '#6b7280',
          }}
        >
          {state.subscribed ? 'AKTIF' : 'TIDAK AKTIF'}
        </span>
      </div>

      {error && (
        <p
          role="alert"
          style={{
            color: '#dc2626',
            fontSize: '12px',
            margin: '10px 0 0',
          }}
        >
          {error}
        </p>
      )}

      {!state.supported && !loading && (
        <p style={{ color: '#b45309', fontSize: '12px' }}>
          Browser atau peranti ini tidak menyokong Web Push.
        </p>
      )}

      {state.supported && (
        <button
          type="button"
          disabled={
            loading ||
            (!state.subscribed && state.permission === 'denied')
          }
          onClick={() =>
            state.subscribed
              ? void handleDisable()
              : void handleEnable()
          }
          style={{
            width: '100%',
            marginTop: '12px',
            padding: '9px 12px',
            border: 'none',
            borderRadius: '7px',
            background: state.subscribed ? '#374151' : '#eab308',
            color: state.subscribed ? '#ffffff' : '#111827',
            fontWeight: 600,
            cursor: loading ? 'wait' : 'pointer',
            opacity: loading ? 0.6 : 1,
          }}
        >
          {loading
            ? 'Sila tunggu...'
            : state.subscribed
              ? 'Nyahaktifkan Notifikasi'
              : 'Aktifkan Notifikasi'}
        </button>
      )}

      {state.permission === 'denied' && (
        <p style={{ color: '#b45309', fontSize: '12px', marginBottom: 0 }}>
          Kebenaran notifikasi telah disekat. Tukar tetapan kebenaran melalui
          tetapan browser untuk mengaktifkannya semula.
        </p>
      )}
    </section>
  );
}
