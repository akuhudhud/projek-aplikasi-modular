
import { useEffect, useState } from 'react';

import {
  changeRunnerStatus,
  decideRunnerApplication,
  downloadRunnerLicenseDocument,
  getApiError,
  getRunnerApplications,
  getRunnerDirectory,
  getRunnerLicenseVerification
} from '../../services/adminApi';

import type {
  AdminProfile,
  RunnerApplication,
  RunnerDirectoryItem,
  RunnerLicenseVerificationDetail
} from '../../types/admin';

interface Props {
  account: AdminProfile;
}

type RunnerTab = 'applications' | 'directory';

function hasPermission(
  account: AdminProfile,
  permission: string
): boolean {
  return (
    account.role === 'ROOT_SUPER_ADMIN' ||
    account.permissions?.includes(permission) === true
  );
}

function formatDate(value: string | null | undefined): string {
  if (!value) return '—';

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat('ms-MY', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  }).format(date);
}

function formatBytes(value: number | null | undefined): string {
  if (!value) return '—';

  if (value < 1024) return `${value} B`;

  return `${(value / 1024).toFixed(1)} KB`;
}

export default function RunnerApplicationsPage({ account }: Props) {
  const [activeTab, setActiveTab] = useState<RunnerTab>('applications');

  const [applications, setApplications] = useState<RunnerApplication[]>([]);
  const [runners, setRunners] = useState<RunnerDirectoryItem[]>([]);

  const [runnerSearch, setRunnerSearch] = useState('');
  const [runnerStatusFilter, setRunnerStatusFilter] = useState('');
  const [runnerPage, setRunnerPage] = useState(1);
  const [runnerLastPage, setRunnerLastPage] = useState(1);
  const [runnerTotal, setRunnerTotal] = useState(0);

  const [licenseDetails, setLicenseDetails] = useState<
    Record<string, RunnerLicenseVerificationDetail>
  >({});
  const [licenseLoadingId, setLicenseLoadingId] = useState('');
  const [documentLoadingId, setDocumentLoadingId] = useState('');

  const [reasons, setReasons] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [workingId, setWorkingId] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  const canViewApplications = hasPermission(
    account,
    'runner_applications.view'
  );
  const canApprove = hasPermission(
    account,
    'runner_applications.approve'
  );
  const canReject = hasPermission(
    account,
    'runner_applications.reject'
  );
  const canReviewLicense = hasPermission(
    account,
    'runner_license_verifications.review'
  );
  const canSuspend = hasPermission(account, 'runners.suspend');
  const canRestore = hasPermission(account, 'runners.restore');

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

  async function loadRunners(page = runnerPage) {
    setLoading(true);
    setError('');

    try {
      const result = await getRunnerDirectory({
        search: runnerSearch.trim() || undefined,
        status: runnerStatusFilter || undefined,
        page,
        per_page: 15
      });

      setRunners(result.runners);
      setRunnerPage(result.pagination.current_page);
      setRunnerLastPage(result.pagination.last_page);
      setRunnerTotal(result.pagination.total);
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (activeTab === 'applications') {
      void loadApplications();
    } else {
      void loadRunners(1);
    }
    // Initial tab load only; filters are handled by their controls.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTab]);

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

  async function showLicenseDetails(accountId: string) {
    if (!canReviewLicense) {
      setError('Anda tidak mempunyai kebenaran menyemak dokumen lesen.');
      return;
    }

    setLicenseLoadingId(accountId);
    setError('');

    try {
      const detail = await getRunnerLicenseVerification(accountId);

      setLicenseDetails((current) => ({
        ...current,
        [accountId]: detail
      }));
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setLicenseLoadingId('');
    }
  }

  async function downloadDocument(accountId: string) {
    if (!canReviewLicense) {
      setError('Anda tidak mempunyai kebenaran memuat turun dokumen lesen.');
      return;
    }

    setDocumentLoadingId(accountId);
    setError('');

    try {
      const blob = await downloadRunnerLicenseDocument(accountId);
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');

      link.href = url;
      link.download = `lesen-runner-${accountId}`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setDocumentLoadingId('');
    }
  }

  async function updateRunnerStatus(
    runner: RunnerDirectoryItem,
    action: 'suspend' | 'restore'
  ) {
    const reason = (reasons[runner.id] || '').trim();

    if (!reason) {
      setError('Alasan tindakan wajib diisi.');
      return;
    }

    setWorkingId(runner.id);
    setError('');
    setMessage('');

    try {
      await changeRunnerStatus(runner.id, action, reason);

      setMessage(
        action === 'suspend'
          ? 'Kelayakan Runner berjaya digantung.'
          : 'Kelayakan Runner berjaya dipulihkan.'
      );

      setReasons((current) => {
        const next = { ...current };
        delete next[runner.id];
        return next;
      });

      await loadRunners(runnerPage);
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setWorkingId('');
    }
  }

  function updateReason(id: string, value: string) {
    setReasons((current) => ({
      ...current,
      [id]: value
    }));
  }

  return (
    <section className="admin-page">
      <div className="admin-page__heading">
        <div>
          <span className="dashboard-page__eyebrow">
            PENGURUSAN RUNNER
          </span>
          <h2>Pengurusan Runner</h2>
          <p>Semakan permohonan dan pengurusan kelayakan Runner.</p>
        </div>

        <button
          className="admin-button"
          onClick={() => {
            if (activeTab === 'applications') {
              void loadApplications();
            } else {
              void loadRunners(runnerPage);
            }
          }}
          disabled={loading}
        >
          Muat Semula
        </button>
      </div>

      <div className="admin-tabs" role="tablist" aria-label="Bahagian Runner">
        <button
          type="button"
          role="tab"
          aria-selected={activeTab === 'applications'}
          className={`admin-tab ${activeTab === 'applications' ? 'admin-tab--active' : ''}`}
          onClick={() => setActiveTab('applications')}
        >
          Permohonan
        </button>

        <button
          type="button"
          role="tab"
          aria-selected={activeTab === 'directory'}
          className={`admin-tab ${activeTab === 'directory' ? 'admin-tab--active' : ''}`}
          onClick={() => setActiveTab('directory')}
        >
          Senarai Runner
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

      {activeTab === 'applications' && (
        <>
          {!canViewApplications ? (
            <div className="admin-state">
              Anda tidak mempunyai kebenaran melihat permohonan Runner.
            </div>
          ) : loading ? (
            <div className="admin-state">Memuatkan permohonan...</div>
          ) : applications.length === 0 ? (
            <div className="admin-state">
              Tiada permohonan Runner yang menunggu semakan.
            </div>
          ) : (
            <div className="admin-record-list">
              {applications.map((application) => {
                const license = application.runner_license_application;
                const detail = licenseDetails[application.id];
                const busy = workingId === application.id;

                return (
                  <article className="admin-record" key={application.id}>
                    <div className="admin-record__heading">
                      <div>
                        <h3>{application.profile_name}</h3>
                        <span>
                          {application.phone || application.email || '—'}
                        </span>
                      </div>

                      <span className="admin-status">
                        {application.runner_status}
                      </span>
                    </div>

                    <div className="admin-record__details">
                      <div>
                        <small>Tarikh Permohonan</small>
                        <strong>{formatDate(application.created_at)}</strong>
                      </div>
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
                        <strong>{formatDate(license?.expires_at)}</strong>
                      </div>
                      <div>
                        <small>Status Dokumen</small>
                        <strong>{license?.status || 'Tiada rekod'}</strong>
                      </div>
                    </div>

                    {license && canReviewLicense && (
                      <div className="admin-record__actions">
                        <button
                          type="button"
                          className="admin-button"
                          disabled={licenseLoadingId === application.id}
                          onClick={() =>
                            void showLicenseDetails(application.id)
                          }
                        >
                          {licenseLoadingId === application.id
                            ? 'Memuatkan...'
                            : 'Butiran Lesen'}
                        </button>

                        <button
                          type="button"
                          className="admin-button"
                          disabled={documentLoadingId === application.id}
                          onClick={() =>
                            void downloadDocument(application.id)
                          }
                        >
                          {documentLoadingId === application.id
                            ? 'Memuat turun...'
                            : 'Muat Turun Dokumen'}
                        </button>
                      </div>
                    )}

                    {detail && (
                      <div className="admin-record__details">
                        <div>
                          <small>Nombor Lesen</small>
                          <strong>{detail.license_number_masked || '—'}</strong>
                        </div>
                        <div>
                          <small>Jenis Lesen</small>
                          <strong>{detail.license_type || '—'}</strong>
                        </div>
                        <div>
                          <small>Kelas Lesen</small>
                          <strong>{detail.license_class || '—'}</strong>
                        </div>
                        <div>
                          <small>Tarikh Hantar</small>
                          <strong>{formatDate(detail.submitted_at)}</strong>
                        </div>
                        <div>
                          <small>Saiz Dokumen</small>
                          <strong>{formatBytes(detail.size_bytes)}</strong>
                        </div>
                        <div>
                          <small>Tarikh Semakan</small>
                          <strong>{formatDate(detail.reviewed_at)}</strong>
                        </div>
                      </div>
                    )}

                    {(canApprove || canReject) && (
                      <>
                        <label htmlFor={`reason-${application.id}`}>
                          Alasan keputusan
                        </label>

                        <textarea
                          id={`reason-${application.id}`}
                          rows={2}
                          value={reasons[application.id] || ''}
                          onChange={(event) =>
                            updateReason(application.id, event.target.value)
                          }
                          placeholder="Masukkan alasan keputusan..."
                        />

                        <div className="admin-record__actions">
                          {canApprove && (
                            <button
                              type="button"
                              className="admin-button admin-button--primary"
                              disabled={busy || !license}
                              onClick={() =>
                                void decide(application.id, 'approve')
                              }
                            >
                              {busy ? 'Memproses...' : 'Luluskan'}
                            </button>
                          )}

                          {canReject && (
                            <button
                              type="button"
                              className="admin-button admin-button--danger"
                              disabled={busy || !license}
                              onClick={() =>
                                void decide(application.id, 'reject')
                              }
                            >
                              Tolak
                            </button>
                          )}
                        </div>
                      </>
                    )}

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
        </>
      )}

      {activeTab === 'directory' && (
        <>
          <div className="admin-filters">
            <input
              type="search"
              value={runnerSearch}
              placeholder="Cari nama, telefon atau e-mel Runner"
              onChange={(event) => setRunnerSearch(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === 'Enter') {
                  void loadRunners(1);
                }
              }}
            />

            <select
              value={runnerStatusFilter}
              onChange={(event) => {
                setRunnerStatusFilter(event.target.value);
                setRunnerPage(1);
                window.setTimeout(() => {
                  void getRunnerDirectory({
                    search: runnerSearch.trim() || undefined,
                    status: event.target.value || undefined,
                    page: 1,
                    per_page: 15
                  }).then((result) => {
                    setRunners(result.runners);
                    setRunnerPage(result.pagination.current_page);
                    setRunnerLastPage(result.pagination.last_page);
                    setRunnerTotal(result.pagination.total);
                  }).catch((err) => setError(getApiError(err)))
                    .finally(() => setLoading(false));
                }, 0);
              }}
            >
              <option value="">Semua status</option>
              <option value="APPROVED">APPROVED</option>
              <option value="SUSPENDED">SUSPENDED</option>
            </select>

            <button
              type="button"
              className="admin-button"
              onClick={() => void loadRunners(1)}
              disabled={loading}
            >
              Cari
            </button>
          </div>

          {loading ? (
            <div className="admin-state">Memuatkan senarai Runner...</div>
          ) : runners.length === 0 ? (
            <div className="admin-state">
              Tiada Runner ditemui.
            </div>
          ) : (
            <>
              <div className="admin-page__footnote">
                Jumlah Runner: {runnerTotal}
              </div>

              <div className="admin-record-list">
                {runners.map((runner) => {
                  const busy = workingId === runner.id;
                  const canAct = runner.runner_status === 'APPROVED'
                    ? canSuspend
                    : canRestore;

                  return (
                    <article className="admin-record" key={runner.id}>
                      <div className="admin-record__heading">
                        <div>
                          <h3>{runner.profile_name}</h3>
                          <span>{runner.phone || runner.email || '—'}</span>
                        </div>

                        <span className="admin-status">
                          {runner.runner_status}
                        </span>
                      </div>

                      <div className="admin-record__details">
                        <div>
                          <small>Status Akaun</small>
                          <strong>{runner.status}</strong>
                        </div>
                        <div>
                          <small>Tarikh Menjadi Runner</small>
                          <strong>{formatDate(runner.created_at)}</strong>
                        </div>
                        <div>
                          <small>Jenis Lesen</small>
                          <strong>{runner.current_license?.license_type || '—'}</strong>
                        </div>
                        <div>
                          <small>Kelas Lesen</small>
                          <strong>{runner.current_license?.license_class || '—'}</strong>
                        </div>
                        <div>
                          <small>Tarikh Luput Lesen</small>
                          <strong>
                            {formatDate(runner.current_license?.expires_at)}
                          </strong>
                        </div>
                      </div>

                      {canAct && (
                        <>
                          <label htmlFor={`runner-reason-${runner.id}`}>
                            Alasan tindakan
                          </label>

                          <textarea
                            id={`runner-reason-${runner.id}`}
                            rows={2}
                            value={reasons[runner.id] || ''}
                            onChange={(event) =>
                              updateReason(runner.id, event.target.value)
                            }
                            placeholder="Masukkan alasan tindakan..."
                          />

                          <div className="admin-record__actions">
                            {runner.runner_status === 'APPROVED' && canSuspend && (
                              <button
                                type="button"
                                className="admin-button admin-button--danger"
                                disabled={busy}
                                onClick={() =>
                                  void updateRunnerStatus(runner, 'suspend')
                                }
                              >
                                {busy ? 'Memproses...' : 'Gantung Runner'}
                              </button>
                            )}

                            {runner.runner_status === 'SUSPENDED' && canRestore && (
                              <button
                                type="button"
                                className="admin-button admin-button--primary"
                                disabled={busy}
                                onClick={() =>
                                  void updateRunnerStatus(runner, 'restore')
                                }
                              >
                                {busy ? 'Memproses...' : 'Pulihkan Runner'}
                              </button>
                            )}
                          </div>
                        </>
                      )}
                    </article>
                  );
                })}
              </div>

              <div className="admin-record__actions">
                <button
                  type="button"
                  className="admin-button"
                  disabled={loading || runnerPage <= 1}
                  onClick={() => void loadRunners(runnerPage - 1)}
                >
                  Sebelum
                </button>

                <span>
                  Halaman {runnerPage} daripada {runnerLastPage}
                </span>

                <button
                  type="button"
                  className="admin-button"
                  disabled={loading || runnerPage >= runnerLastPage}
                  onClick={() => void loadRunners(runnerPage + 1)}
                >
                  Seterusnya
                </button>
              </div>
            </>
          )}
        </>
      )}
    </section>
  );
}
