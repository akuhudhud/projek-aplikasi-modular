
import { useEffect, useState } from 'react';

import { getApiError, getAuditLogs } from '../../services/adminApi';
import type { AuditLog } from '../../types/admin';

export default function AuditLogsPage() {
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  async function loadLogs() {
    setLoading(true);
    setError('');

    try {
      setLogs(await getAuditLogs());
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadLogs();
  }, []);

  return (
    <section className="admin-page">
      <div className="admin-page__heading">
        <div>
          <span className="dashboard-page__eyebrow">
            REKOD KESELAMATAN
          </span>
          <h2>Log Audit</h2>
          <p>Rekod aktiviti pentadbiran daripada sistem utama.</p>
        </div>

        <button
          className="admin-button"
          onClick={() => void loadLogs()}
          disabled={loading}
        >
          Muat Semula
        </button>
      </div>

      {error && (
        <div className="admin-alert admin-alert--error" role="alert">
          {error}
        </div>
      )}

      {loading ? (
        <div className="admin-state">Memuatkan rekod audit...</div>
      ) : logs.length === 0 ? (
        <div className="admin-state">Tiada rekod audit diterima.</div>
      ) : (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <thead>
              <tr>
                <th>Tarikh</th>
                <th>Tindakan</th>
                <th>ID Pentadbir</th>
                <th>ID Sasaran</th>
                <th>Alasan</th>
              </tr>
            </thead>
            <tbody>
              {logs.map((log) => (
                <tr key={log.id}>
                  <td>{new Date(log.created_at).toLocaleString('ms-MY')}</td>
                  <td>{log.action}</td>
                  <td>{log.actor_account_id}</td>
                  <td>{log.target_account_id || '—'}</td>
                  <td>{log.reason || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
