
import { useEffect, useRef, useState } from 'react';

import {
  changeRunnerStatus,
  decideRunnerApplication,
  getApiError,
  getRunnerApplications,
  getRunnerDirectory,
  getRunnerLicenseDocument,
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

interface DirectoryFilters {
  search: string;
  status: string;
}

interface DocumentPreview {
  url: string;
  mimeType: string;
}

function hasPermission(account: AdminProfile, permission: string): boolean {
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
  const [applicationPage, setApplicationPage] = useState(1);
  const [applicationLastPage, setApplicationLastPage] = useState(1);
  const [applicationTotal, setApplicationTotal] = useState(0);
  const [applicationFrom, setApplicationFrom] = useState<number | null>(null);
  const [applicationTo, setApplicationTo] = useState<number | null>(null);

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
  const [documentPreview, setDocumentPreview] = useState<DocumentPreview | null>(null);

  const [reasons, setReasons] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [workingId, setWorkingId] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  const activeTabRef = useRef<RunnerTab>('applications');
  const applicationRequestId = useRef(0);
  const directoryRequestId = useRef(0);
  const licenseRequestId = useRef(0);
  const documentRequestId = useRef(0);
  const mountedRef = useRef(true);
  const previewUrlRef = useRef<string | null>(null);

  const canViewApplications = hasPermission(
    account,
    'runner_applications.view'
  );
  const canApprove = hasPermission(account, 'runner_applications.approve');
  const canReject = hasPermission(account, 'runner_applications.reject');
  const canReviewLicense = hasPermission(
    account,
    'runner_license_verifications.review'
  );
  const canSuspend = hasPermission(account, 'runners.suspend');
  const canRestore = hasPermission(account, 'runners.restore');

  function clearDocumentPreview() {
    if (previewUrlRef.current) {
      window.URL.revokeObjectURL(previewUrlRef.current);
      previewUrlRef.current = null;
    }

    setDocumentPreview(null);
  }

  useEffect(() => {
    mountedRef.current = true;

    return () => {
      mountedRef.current = false;
      applicationRequestId.current += 1;
      directoryRequestId.current += 1;
      licenseRequestId.current += 1;
      documentRequestId.current += 1;

      if (previewUrlRef.current) {
        window.URL.revokeObjectURL(previewUrlRef.current);
        previewUrlRef.current = null;
      }
    };
  }, []);

  async function loadApplications(page = applicationPage) {
    const requestId = ++applicationRequestId.current;

    setLoading(true);
    setError('');

    try {
      const result = await getRunnerApplications({
        page,
        per_page: 15
      });

      if (
        !mountedRef.current ||
        requestId !== applicationRequestId.current ||
        activeTabRef.current !== 'applications'
      ) {
        return;
      }

      setApplications(result.applications);
      setApplicationPage(result.pagination.current_page);
      setApplicationLastPage(result.pagination.last_page);
      setApplicationTotal(result.pagination.total);
      setApplicationFrom(result.pagination.from);
      setApplicationTo(result.pagination.to);
    } catch (err) {
      if (
        mountedRef.current &&
        requestId === applicationRequestId.current &&
        activeTabRef.current === 'applications'
      ) {
        setError(getApiError(err));
      }
    } finally {
      if (
        mountedRef.current &&
        requestId === applicationRequestId.current &&
        activeTabRef.current === 'applications'
      ) {
        setLoading(false);
      }
    }
  }

  async function loadRunners(
    page = runnerPage,
    filters: DirectoryFilters = {
      search: runnerSearch,
      status: runnerStatusFilter
    }
  ) {
    const requestId = ++directoryRequestId.current;

    setLoading(true);
    setError('');

    try {
      const result = await getRunnerDirectory({
        search: filters.search.trim() || undefined,
        status: filters.status || undefined,
        page,
        per_page: 15
      });

      if (
        !mountedRef.current ||
        requestId !== directoryRequestId.current ||
        activeTabRef.current !== 'directory'
      ) {
        return;
      }

      setRunners(result.runners);
      setRunnerPage(result.pagination.current_page);
      setRunnerLastPage(result.pagination.last_page);
      setRunnerTotal(result.pagination.total);
    } catch (err) {
      if (
        mountedRef.current &&
        requestId === directoryRequestId.current &&
        activeTabRef.current === 'directory'
      ) {
        setError(getApiError(err));
      }
    } finally {
      if (
        mountedRef.current &&
        requestId === directoryRequestId.current &&
        activeTabRef.current === 'directory'
      ) {
        setLoading(false);
      }
    }
  }

  useEffect(() => {
    if (activeTab === 'applications') {
      directoryRequestId.current += 1;
      licenseRequestId.current += 1;
      setLicenseLoadingId('');
      void loadApplications(1);
    } else {
      applicationRequestId.current += 1;
      licenseRequestId.current += 1;
      setLicenseLoadingId('');
      void loadRunners(1);
    }

    return () => {
      if (activeTab === 'applications') {
        applicationRequestId.current += 1;
      } else {
        directoryRequestId.current += 1;
      }

      licenseRequestId.current += 1;
    };

    // Tab changes initiate a fresh request for that tab.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTab]);

  function changeTab(tab: RunnerTab) {
    if (tab === activeTabRef.current) return;

    activeTabRef.current = tab;
    setActiveTab(tab);
    setError('');
    setMessage('');
    setLoading(true);

    applicationRequestId.current += 1;
    directoryRequestId.current += 1;
    licenseRequestId.current += 1;
    documentRequestId.current += 1;

    setLicenseLoadingId('');
    setDocumentLoadingId('');
    clearDocumentPreview();
  }

  function handleTabKeyDown(
    event: React.KeyboardEvent<HTMLButtonElement>
  ) {
    const tabs = Array.from(
      event.currentTarget
        .closest('[role="tablist"]')
        ?.querySelectorAll<HTMLButtonElement>('[role="tab"]') ?? []
    );

    if (tabs.length === 0) return;

    const currentIndex = tabs.indexOf(event.currentTarget);
    let nextIndex = currentIndex;

    if (event.key === 'ArrowRight') {
      nextIndex = (currentIndex + 1) % tabs.length;
    } else if (event.key === 'ArrowLeft') {
      nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
    } else if (event.key === 'Home') {
      nextIndex = 0;
    } else if (event.key === 'End') {
      nextIndex = tabs.length - 1;
    } else {
      return;
    }

    event.preventDefault();
    tabs[nextIndex]?.focus();
    tabs[nextIndex]?.click();
  }

  async function decide(
    accountId: string,
    decision: 'approve' | 'reject'
  ) {
    if (workingId) return;

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

      if (!mountedRef.current) return;

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

      const nextPage =
        applications.length === 1 && applicationPage > 1
          ? applicationPage - 1
          : applicationPage;

      await loadApplications(nextPage);
    } catch (err) {
      if (mountedRef.current) {
        setError(getApiError(err));
      }
    } finally {
      if (mountedRef.current) {
        setWorkingId('');
      }
    }
  }

  async function showLicenseDetails(accountId: string) {
    if (licenseLoadingId) return;

    if (!canReviewLicense) {
      setError('Anda tidak mempunyai kebenaran menyemak dokumen lesen.');
      return;
    }

    const requestId = ++licenseRequestId.current;

    setLicenseLoadingId(accountId);
    setError('');

    try {
      const detail = await getRunnerLicenseVerification(accountId);

      if (
        !mountedRef.current ||
        requestId !== licenseRequestId.current ||
        activeTabRef.current !== 'applications'
      ) {
        return;
      }

      setLicenseDetails((current) => ({
        ...current,
        [accountId]: detail
      }));
    } catch (err) {
      if (
        mountedRef.current &&
        requestId === licenseRequestId.current &&
        activeTabRef.current === 'applications'
      ) {
        setError(getApiError(err));
      }
    } finally {
      if (
        mountedRef.current &&
        requestId === licenseRequestId.current
      ) {
        setLicenseLoadingId('');
      }
    }
  }

  async function showLicenseDocument(accountId: string) {
    if (documentLoadingId) return;

    if (!canReviewLicense) {
      setError('Anda tidak mempunyai kebenaran melihat dokumen lesen.');
      return;
    }

    const requestId = ++documentRequestId.current;

    setDocumentLoadingId(accountId);
    setError('');
    clearDocumentPreview();

    try {
      const blob = await getRunnerLicenseDocument(accountId);

      if (
        !mountedRef.current ||
        requestId !== documentRequestId.current ||
        activeTabRef.current !== 'applications'
      ) {
        return;
      }

      const url = window.URL.createObjectURL(blob);
      previewUrlRef.current = url;

      setDocumentPreview({
        url,
        mimeType: blob.type || 'application/octet-stream'
      });
    } catch (err) {
      if (
        mountedRef.current &&
        requestId === documentRequestId.current
      ) {
        setError(getApiError(err));
      }
    } finally {
      if (
        mountedRef.current &&
        requestId === documentRequestId.current
      ) {
        setDocumentLoadingId('');
      }
    }
  }

  async function updateRunnerStatus(
    runner: RunnerDirectoryItem,
    action: 'suspend' | 'restore'
  ) {
    if (workingId) return;

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

      if (!mountedRef.current) return;

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
      if (mountedRef.current) {
        setError(getApiError(err));
      }
    } finally {
      if (mountedRef.current) {
        setWorkingId('');
      }
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
          type="button"
          className="admin-button"
          onClick={() => {
            if (activeTab === 'applications') {
              void loadApplications(applicationPage);
            } else {
              void loadRunners(runnerPage);
            }
          }}
          disabled={loading}
        >
          Muat Semula
        </button>
      </div>

      <div
        className="admin-tabs"
        role="tablist"
        aria-label="Bahagian Runner"
      >
        <button
          id="runner-tab-applications"
          type="button"
          role="tab"
          aria-controls="runner-panel-applications"
          aria-selected={activeTab === 'applications'}
          tabIndex={activeTab === 'applications' ? 0 : -1}
          className={`admin-tab ${
            activeTab === 'applications' ? 'admin-tab--active' : ''
          }`}
          onClick={() => changeTab('applications')}
          onKeyDown={handleTabKeyDown}
        >
          Permohonan
        </button>

        <button
          id="runner-tab-directory"
          type="button"
          role="tab"
          aria-controls="runner-panel-directory"
          aria-selected={activeTab === 'directory'}
          tabIndex={activeTab === 'directory' ? 0 : -1}
          className={`admin-tab ${
            activeTab === 'directory' ? 'admin-tab--active' : ''
          }`}
          onClick={() => changeTab('directory')}
          onKeyDown={handleTabKeyDown}
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
        <div
          className="admin-alert admin-alert--success"
          role="status"
          aria-live="polite"
        >
          {message}
        </div>
      )}

      {activeTab === 'applications' && (
        <div
          id="runner-panel-applications"
          role="tabpanel"
          aria-labelledby="runner-tab-applications"
          tabIndex={0}
        >
          {!canViewApplications ? (
            <div className="admin-state">
              Anda tidak mempunyai kebenaran melihat permohonan Runner.
            </div>
          ) : loading ? (
            <div className="admin-state" role="status" aria-live="polite">
              Memuatkan permohonan...
            </div>
          ) : applications.length === 0 ? (
            <div className="admin-state" role="status">
              Tiada permohonan Runner yang menunggu semakan.
            </div>
          ) : (
            <>
              <div className="admin-page__footnote" aria-live="polite">
                Memaparkan {applicationFrom ?? 0}–{applicationTo ?? 0} daripada{' '}
                {applicationTotal} permohonan
              </div>

              <div className="admin-record-list">
                {applications.map((application) => {
                  const license = application.runner_license_application;
                  const detail = licenseDetails[application.id];
                  const busy = Boolean(workingId);

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
                            disabled={Boolean(licenseLoadingId)}
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
                            disabled={Boolean(documentLoadingId)}
                            onClick={() =>
                              void showLicenseDocument(application.id)
                            }
                          >
                            {documentLoadingId === application.id
                              ? 'Memaparkan...'
                              : 'Paparkan Dokumen'}
                          </button>
                        </div>
                      )}

                      {licenseLoadingId === application.id && (
                        <p role="status" aria-live="polite">
                          Memuatkan butiran lesen...
                        </p>
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

                      {documentLoadingId === application.id && (
                        <p role="status" aria-live="polite">
                          Sedang menyediakan paparan dokumen lesen...
                        </p>
                      )}

                      {documentPreview && documentLoadingId === '' && (
                        <div className="admin-document-preview">
                          <div className="admin-record__heading">
                            <strong>Dokumen Lesen Runner</strong>
                            <button
                              type="button"
                              className="admin-button"
                              onClick={clearDocumentPreview}
                            >
                              Tutup Paparan
                            </button>
                          </div>

                          {documentPreview.mimeType.startsWith('image/') ? (
                            <img
                              src={documentPreview.url}
                              alt="Dokumen lesen Runner"
                              style={{
                                display: 'block',
                                maxWidth: '100%',
                                maxHeight: '640px',
                                objectFit: 'contain',
                                margin: '12px auto'
                              }}
                            />
                          ) : documentPreview.mimeType === 'application/pdf' ? (
                            <iframe
                              title="Paparan dokumen lesen Runner"
                              src={`${documentPreview.url}#toolbar=0&navpanes=0`}
                              style={{
                                display: 'block',
                                width: '100%',
                                minHeight: '560px',
                                border: '1px solid #d1d5db',
                                borderRadius: '8px'
                              }}
                            />
                          ) : (
                            <p role="alert">
                              Format dokumen ini tidak disokong untuk paparan.
                            </p>
                          )}
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
                                {busy ? 'Memproses...' : 'Tolak'}
                              </button>
                            )}
                          </div>
                        </>
                      )}

                      {!license && (
                        <p className="admin-page__footnote">
                          Rekod dokumen lesen PENDING tidak diterima daripada
                          API. Tindakan keputusan dinyahaktifkan.
                        </p>
                      )}
                    </article>
                  );
                })}
              </div>

              <div className="admin-record__actions">
                <button
                  type="button"
                  className="admin-button"
                  disabled={loading || applicationPage <= 1}
                  onClick={() => void loadApplications(applicationPage - 1)}
                >
                  Sebelum
                </button>

                <span aria-live="polite">
                  Halaman {applicationPage} daripada {applicationLastPage}
                </span>

                <button
                  type="button"
                  className="admin-button"
                  disabled={loading || applicationPage >= applicationLastPage}
                  onClick={() => void loadApplications(applicationPage + 1)}
                >
                  Seterusnya
                </button>
              </div>
            </>
          )}
        </div>
      )}

      {activeTab === 'directory' && (
        <div
          id="runner-panel-directory"
          role="tabpanel"
          aria-labelledby="runner-tab-directory"
          tabIndex={0}
        >
          <div className="admin-filters">
            <div>
              <label htmlFor="runner-directory-search">
                Cari Runner
              </label>
              <input
                id="runner-directory-search"
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
            </div>

            <div>
              <label htmlFor="runner-directory-status">
                Tapis status Runner
              </label>
              <select
                id="runner-directory-status"
                value={runnerStatusFilter}
                onChange={(event) => {
                  const status = event.target.value;
                  setRunnerStatusFilter(status);
                  setRunnerPage(1);

                  void loadRunners(1, {
                    search: runnerSearch,
                    status
                  });
                }}
              >
                <option value="">Semua status</option>
                <option value="APPROVED">APPROVED</option>
                <option value="SUSPENDED">SUSPENDED</option>
              </select>
            </div>

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
            <div className="admin-state" role="status" aria-live="polite">
              Memuatkan senarai Runner...
            </div>
          ) : runners.length === 0 ? (
            <div className="admin-state" role="status">
              Tiada Runner ditemui.
            </div>
          ) : (
            <>
              <div className="admin-page__footnote" aria-live="polite">
                Jumlah Runner: {runnerTotal}
              </div>

              <div className="admin-record-list">
                {runners.map((runner) => {
                  const busy = Boolean(workingId);
                  const canAct =
                    runner.runner_status === 'APPROVED'
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
                          <strong>
                            {runner.current_license?.license_type || '—'}
                          </strong>
                        </div>
                        <div>
                          <small>Kelas Lesen</small>
                          <strong>
                            {runner.current_license?.license_class || '—'}
                          </strong>
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
                            {runner.runner_status === 'APPROVED' &&
                              canSuspend && (
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

                            {runner.runner_status === 'SUSPENDED' &&
                              canRestore && (
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

                <span aria-live="polite">
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
        </div>
      )}
    </section>
  );
}
