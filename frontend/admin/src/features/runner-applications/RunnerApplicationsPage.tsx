
import { useEffect, useState } from 'react';

import {
  decideRunnerApplication,
  getApiError,
  getRunnerApplications
} from '../../services/adminApi';

import type { RunnerApplication } from '../../types/admin';

export default function RunnerApplicationsPage() {
  const [applications, setApplications] = useState<RunnerApplication[]>([]);
  const [reasons, setReasons] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [workingId, setWorkingId] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  async function loadApplications() {
    setLoading(true);
    setError('');

    try {
      setApplications(await getRunnerApplications());
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadApplications();
  }, []);

  async function decide(
    accountId: string,
    decision: 'approve' | 'reject'
  ) {
    const reason = (reasons[accountId] || '').trim();

    if (!reason) {
      setError('Alasan keputusan wajib diisi.');
      return;
    }

    setWorkingId(accountId);
    setError('');
    setMessage('');

    try {
      await decideRunnerApplication(accountId, decision, reason);

      setMessage(
        decision === 'approve'
          ? 'Permohonan Runner berjaya diluluskan.'
          : 'Permohonan Runner berjaya ditolak.'
      );

      setReasons((current) => {
        const next = { ...current };
        delete next[accountId];
        return next;
      });

      await loadApplications();
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setWorkingId('');
    }
  }

  return (
    <section className="admin-page">
      <div className="admin-page__heading">
        <div>
          <span className="dashboard-page__eyebrow">
            SEMAKAN PERMOHONAN
          </span>
          <h2>Permohonan Runner</h2>
          <p>Permohonan yang menunggu keputusan pentadbir.</p>
        </div>

        <button
          className="admin-button"
          onClick={() => void loadApplications()}
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

      {message && (
        <div className="admin-alert admin-alert--success" role="status">
          {message}
        </div>
      )}

      {loading ? (
        <div className="admin-state">Memuatkan permohonan...</div>
      ) : applications.length === 0 ? (
        <div className="admin-state">
          Tiada permohonan Runner yang menunggu semakan.
        </div>
      ) : (
        <div className="admin-record-list">
          {applications.map((application) => {
            const license = application.runner_license_application;
            const busy = workingId === application.id;

            return (
              <article className="admin-record" key={application.id}>
                <div className="admin-record__heading">
                  <div>
                    <h3>{application.profile_name}</h3>
                    <span>{application.phone || application.email || '—'}</span>
                  </div>

                  <span className="admin-status">
                    {application.runner_status}
                  </span>
                </div>

                <div className="admin-record__details">
                  <div>
                    <small>Jenis Lesen</small>
                    <strong>{license?.license_type || '—'}</strong>
                  </div>
                  <div>
                    <small>Kelas Lesen</small>
                    <strong>{license?.license_class || '—'}</strong>
                  </div>
                  <div>
                    <small>Tarikh Luput</small>
                    <strong>{license?.expires_at || '—'}</strong>
                  </div>
                  <div>
                    <small>Status Dokumen</small>
                    <strong>{license?.status || 'Tiada rekod'}</strong>
                  </div>
                </div>

                <label htmlFor={`reason-${application.id}`}>
                  Alasan keputusan
                </label>

                <textarea
                  id={`reason-${application.id}`}
                  rows={2}
                  value={reasons[application.id] || ''}
                  onChange={(event) =>
                    setReasons((current) => ({
                      ...current,
                      [application.id]: event.target.value
                    }))
                  }
                  placeholder="Masukkan alasan keputusan..."
                />

                <div className="admin-record__actions">
                  <button
                    className="admin-button admin-button--primary"
                    disabled={busy || !license}
                    onClick={() =>
                      void decide(application.id, 'approve')
                    }
                  >
                    {busy ? 'Memproses...' : 'Luluskan'}
                  </button>

                  <button
                    className="admin-button admin-button--danger"
                    disabled={busy || !license}
                    onClick={() =>
                      void decide(application.id, 'reject')
                    }
                  >
                    Tolak
                  </button>
                </div>

                {!license && (
                  <p className="admin-page__footnote">
                    Rekod dokumen lesen PENDING tidak diterima daripada API.
                    Tindakan keputusan dinyahaktifkan.
                  </p>
                )}
              </article>
            );
          })}
        </div>
      )}
    </section>
  );
}
