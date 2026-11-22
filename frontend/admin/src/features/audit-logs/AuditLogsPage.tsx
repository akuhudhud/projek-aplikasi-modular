
import { useEffect, useRef, useState } from 'react';

import { getApiError, getAuditLogs } from '../../services/adminApi';
import type {
  AccountPagination,
  AuditLog
} from '../../types/admin';

const EMPTY_PAGINATION: AccountPagination = {
  current_page: 1,
  last_page: 1,
  per_page: 25,
  total: 0,
  from: null,
  to: null
};

export default function AuditLogsPage() {
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [pagination, setPagination] =
    useState<AccountPagination>(EMPTY_PAGINATION);

  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const requestId = useRef(0);

  async function loadLogs(nextPage = page) {
    const currentRequestId = ++requestId.current;

    setLoading(true);
    setError('');

    try {
      const result = await getAuditLogs({
        page: nextPage,
        per_page: 25
      });

      if (currentRequestId !== requestId.current) return;

      setLogs(result.audit_logs);
      setPagination(result.pagination);
      setPage(result.pagination.current_page);
    } catch (err) {
      if (currentRequestId === requestId.current) {
        setError(getApiError(err));
      }
    } finally {
      if (currentRequestId === requestId.current) {
        setLoading(false);
      }
    }
  }

  useEffect(() => {
    void loadLogs(1);

    return () => {
      requestId.current += 1;
    };
    // Initial page load.
    // eslint-disable-next-line react-hooks/exhaustive-deps
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
          type="button"
          className="admin-button"
          onClick={() => void loadLogs(page)}
          disabled={loading}
        >
          {loading ? 'Memuatkan...' : 'Muat Semula'}
        </button>
      </div>

      {error && (
        <div className="admin-alert admin-alert--error" role="alert">
          {error}
        </div>
      )}

      {loading ? (
        <div
          className="admin-state"
          role="status"
          aria-live="polite"
          aria-label="Memuatkan rekod audit"
        >
          Memuatkan rekod audit...
        </div>
      ) : logs.length === 0 ? (
        <div className="admin-state" role="status">
          Tiada rekod audit diterima.
        </div>
      ) : (
        <>
          <div
            className="admin-page__footnote"
            role="status"
            aria-live="polite"
          >
            Memaparkan {pagination.from ?? 0}–{pagination.to ?? 0}
            {' '}daripada {pagination.total} rekod audit.
          </div>

          <div className="admin-table-wrap">
            <table className="admin-table">
              <caption>
                Senarai rekod aktiviti pentadbiran
              </caption>

              <thead>
                <tr>
                  <th scope="col">Tarikh</th>
                  <th scope="col">Tindakan</th>
                  <th scope="col">ID Pentadbir</th>
                  <th scope="col">ID Sasaran</th>
                  <th scope="col">Alasan</th>
                </tr>
              </thead>

              <tbody>
                {logs.map((log) => (
                  <tr key={log.id}>
                    <td>
                      {new Date(log.created_at).toLocaleString('ms-MY')}
                    </td>
                    <td>{log.action}</td>
                    <td>{log.actor_account_id}</td>
                    <td>{log.target_account_id || '—'}</td>
                    <td>{log.reason || '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="admin-record__actions">
            <button
              type="button"
              className="admin-button"
              disabled={loading || pagination.current_page <= 1}
              onClick={() => void loadLogs(pagination.current_page - 1)}
            >
              Sebelum
            </button>

            <span
              className="admin-page__footnote"
              aria-live="polite"
            >
              Halaman {pagination.current_page} daripada {pagination.last_page}
            </span>

            <button
              type="button"
              className="admin-button"
              disabled={
                loading ||
                pagination.current_page >= pagination.last_page
              }
              onClick={() => void loadLogs(pagination.current_page + 1)}
            >
              Seterusnya
            </button>
          </div>
        </>
      )}
    </section>
  );
}
