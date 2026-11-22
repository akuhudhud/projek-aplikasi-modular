import { useEffect, useState } from 'react';

import {
  decideIdentityVerification,
  downloadIdentityVerificationDocument,
  getApiError,
  getIdentityVerificationDetail,
  getIdentityVerifications
} from '../../services/adminApi';

import type {
  AdminProfile,
  IdentityVerificationDetail,
  IdentityVerificationQueueItem
} from '../../types/admin';

interface Props {
  account: AdminProfile;
}

type VerificationStatus = 'PENDING' | 'VERIFIED' | 'REJECTED' | 'ALL';

const STATUS_TABS: Array<{
  value: VerificationStatus;
  label: string;
}> = [
  { value: 'PENDING', label: 'Menunggu' },
  { value: 'VERIFIED', label: 'Disahkan' },
  { value: 'REJECTED', label: 'Ditolak' },
  { value: 'ALL', label: 'Semua' }
];

function hasReviewPermission(account: AdminProfile): boolean {
  return (
    account.role === 'ROOT_SUPER_ADMIN' ||
    account.permissions?.includes('identity_verifications.review') === true
  );
}

function formatDate(value: string | null | undefined): string {
  if (!value) return '—';

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat('ms-MY', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date);
}

function formatBytes(value: number): string {
  if (value < 1024) return `${value} B`;

  if (value < 1024 * 1024) {
    return `${(value / 1024).toFixed(1)} KB`;
  }

  return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

function statusLabel(status: string): string {
  switch (status) {
    case 'PENDING':
      return 'MENUNGGU';
    case 'VERIFIED':
      return 'DISAHKAN';
    case 'REJECTED':
      return 'DITOLAK';
    default:
      return status;
  }
}

export default function IdentityVerificationsPage({ account }: Props) {
  const [items, setItems] = useState<IdentityVerificationQueueItem[]>([]);
  const [activeStatus, setActiveStatus] =
    useState<VerificationStatus>('PENDING');

  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');

  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);

  const [selectedId, setSelectedId] = useState('');
  const [detail, setDetail] =
    useState<IdentityVerificationDetail | null>(null);

  const [rejectionReason, setRejectionReason] = useState('');
  const [loading, setLoading] = useState(true);
  const [detailLoading, setDetailLoading] = useState(false);
  const [documentLoadingId, setDocumentLoadingId] = useState('');
  const [working, setWorking] = useState(false);

  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  const canReview = hasReviewPermission(account);

  async function loadQueue(
    nextPage = page,
    nextStatus = activeStatus,
    nextSearch = search
  ) {
    setLoading(true);
    setError('');

    try {
      const result = await getIdentityVerifications({
        status: nextStatus,
        search: nextSearch.trim() || undefined,
        page: nextPage,
        per_page: 15
      });

      setItems(result.verifications);
      setPage(result.pagination.current_page);
      setLastPage(result.pagination.last_page);
      setTotal(result.pagination.total);

      if (
        selectedId &&
        !result.verifications.some(
          (item) => item.account_id === selectedId
        )
      ) {
        setSelectedId('');
        setDetail(null);
      }
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (canReview) {
      void loadQueue(1, 'PENDING', '');
    } else {
      setLoading(false);
    }
    // Load the initial queue once when the page opens.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function changeStatus(status: VerificationStatus) {
    setActiveStatus(status);
    setPage(1);
    setSelectedId('');
    setDetail(null);
    setMessage('');
    await loadQueue(1, status, search);
  }

  async function submitSearch(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const nextSearch = searchInput.trim();

    setSearch(nextSearch);
    setPage(1);
    setSelectedId('');
    setDetail(null);
    setMessage('');

    await loadQueue(1, activeStatus, nextSearch);
  }

  async function openDetail(item: IdentityVerificationQueueItem) {
    setSelectedId(item.account_id);
    setDetail(null);
    setRejectionReason('');
    setError('');
    setMessage('');
    setDetailLoading(true);

    try {
      const result = await getIdentityVerificationDetail(item.account_id);
      setDetail(result);
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setDetailLoading(false);
    }
  }

  async function downloadDocument(documentId: string) {
    if (!detail || !canReview) return;

    setDocumentLoadingId(documentId);
    setError('');

    try {
      const blob = await downloadIdentityVerificationDocument(
        detail.account_id,
        documentId
      );

      const objectUrl = window.URL.createObjectURL(blob);
      const link = document.createElement('a');

      link.href = objectUrl;
      link.download = `dokumen-identiti-${documentId}`;
      document.body.appendChild(link);
      link.click();
      link.remove();

      window.setTimeout(() => {
        window.URL.revokeObjectURL(objectUrl);
      }, 1000);
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setDocumentLoadingId('');
    }
  }

  async function submitDecision(decision: 'VERIFIED' | 'REJECTED') {
    if (!detail || detail.status !== 'PENDING' || !canReview) {
      return;
    }

    const reason = rejectionReason.trim();

    if (decision === 'REJECTED' && !reason) {
      setError('Alasan penolakan wajib diisi.');
      return;
    }

    if (detail.documents.length === 0) {
      setError('Permohonan tiada dokumen untuk disemak.');
      return;
    }

    setWorking(true);
    setError('');
    setMessage('');

    try {
      await decideIdentityVerification(
        detail.account_id,
        decision,
        decision === 'REJECTED' ? reason : undefined
      );

      setMessage(
        decision === 'VERIFIED'
          ? 'Pengesahan identiti berjaya diluluskan.'
          : 'Permohonan pengesahan identiti telah ditolak.'
      );

      setDetail((current) =>
        current
          ? {
              ...current,
              status: decision,
              rejection_reason:
                decision === 'REJECTED' ? reason : null
            }
          : current
      );

      await loadQueue(page, activeStatus, search);
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setWorking(false);
    }
  }

  function closeDetail() {
    setSelectedId('');
    setDetail(null);
    setRejectionReason('');
    setError('');
  }

  if (!canReview) {
    return (
      <section className="admin-page">
        <div className="admin-page__heading">
          <div>
            <span className="dashboard-page__eyebrow">
              MODUL PENGESAHAN
            </span>
            <h2>Pengesahan Identiti</h2>
          </div>
        </div>

        <div className="admin-state">
          Anda tidak mempunyai kebenaran menyemak pengesahan identiti.
        </div>
      </section>
    );
  }

  return (
    <section className="admin-page">
      <div className="admin-page__heading">
        <div>
          <span className="dashboard-page__eyebrow">
            PENGURUSAN PENGESAHAN
          </span>
          <h2>Pengesahan Identiti</h2>
          <p>
            Semak maklumat identiti dan dokumen pengguna.
          </p>
        </div>

        <button
          type="button"
          className="admin-button"
          disabled={loading}
          onClick={() => void loadQueue(page)}
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

      <div
        className="admin-tabs"
        role="tablist"
        aria-label="Tapisan status pengesahan identiti"
      >
        {STATUS_TABS.map((tab) => (
          <button
            key={tab.value}
            type="button"
            role="tab"
            aria-selected={activeStatus === tab.value}
            className={`admin-tab ${
              activeStatus === tab.value ? 'admin-tab--active' : ''
            }`}
            onClick={() => void changeStatus(tab.value)}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <form className="admin-filters" onSubmit={submitSearch}>
        <input
          type="search"
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
          placeholder="Cari nama, nombor telefon atau e-mel"
          aria-label="Carian permohonan identiti"
        />

        <button
          type="submit"
          className="admin-button admin-button--primary"
          disabled={loading}
        >
          Cari
        </button>

        <button
          type="button"
          className="admin-button"
          disabled={loading && !search}
          onClick={() => {
            setSearchInput('');
            setSearch('');
            setPage(1);
            setSelectedId('');
            setDetail(null);
            void loadQueue(1, activeStatus, '');
          }}
        >
          Kosongkan
        </button>
      </form>

      <div className="admin-page__footnote">
        Jumlah rekod: {total}
      </div>

      {loading ? (
        <div className="admin-state">
          Memuatkan senarai permohonan...
        </div>
      ) : items.length === 0 ? (
        <div className="admin-state">
          Tiada permohonan ditemui untuk tapisan ini.
        </div>
      ) : (
        <div className="admin-record-list">
          {items.map((item) => (
            <article
              className="admin-record"
              key={item.account_id}
            >
              <div className="admin-record__heading">
                <div>
                  <h3>{item.profile_name}</h3>
                  <span>
                    {item.phone || item.email || 'Tiada maklumat hubungan'}
                  </span>
                </div>

                <span className="admin-status">
                  {statusLabel(item.status)}
                </span>
              </div>

              <div className="admin-record__details">
                <div>
                  <small>Nama pada identiti</small>
                  <strong>{item.verified_name}</strong>
                </div>

                <div>
                  <small>Nombor MyKad</small>
                  <strong>{item.mykad_number_masked}</strong>
                </div>

                <div>
                  <small>Tarikh permohonan</small>
                  <strong>{formatDate(item.submitted_at)}</strong>
                </div>

                <div>
                  <small>Dokumen</small>
                  <strong>{item.documents_count} fail</strong>
                </div>
              </div>

              <div className="admin-record__actions">
                <button
                  type="button"
                  className="admin-button admin-button--primary"
                  disabled={
                    detailLoading ||
                    selectedId === item.account_id
                  }
                  onClick={() => void openDetail(item)}
                >
                  {selectedId === item.account_id
                    ? 'Dipilih'
                    : 'Lihat Butiran'}
                </button>
              </div>
            </article>
          ))}
        </div>
      )}

      {!loading && lastPage > 1 && (
        <div className="admin-record__actions">
          <button
            type="button"
            className="admin-button"
            disabled={page <= 1}
            onClick={() => void loadQueue(page - 1)}
          >
            Sebelum
          </button>

          <span className="admin-page__footnote">
            Halaman {page} daripada {lastPage}
          </span>

          <button
            type="button"
            className="admin-button"
            disabled={page >= lastPage}
            onClick={() => void loadQueue(page + 1)}
          >
            Seterusnya
          </button>
        </div>
      )}

      {selectedId && (
        <section className="admin-record" aria-label="Butiran pengesahan identiti">
          <div className="admin-page__heading">
            <div>
              <span className="dashboard-page__eyebrow">
                BUTIRAN PERMOHONAN
              </span>
              <h2>Semakan Identiti</h2>
            </div>

            <button
              type="button"
              className="admin-button"
              onClick={closeDetail}
            >
              Tutup
            </button>
          </div>

          {detailLoading ? (
            <div className="admin-state">
              Memuatkan butiran permohonan...
            </div>
          ) : detail ? (
            <>
              <div className="admin-record__details">
                <div>
                  <small>Nama profil</small>
                  <strong>{detail.profile_name}</strong>
                </div>

                <div>
                  <small>Nama sah</small>
                  <strong>{detail.verified_name}</strong>
                </div>

                <div>
                  <small>Nombor MyKad</small>
                  <strong>{detail.mykad_number_masked}</strong>
                </div>

                <div>
                  <small>Status</small>
                  <strong>{statusLabel(detail.status)}</strong>
                </div>

                <div>
                  <small>Tarikh dihantar</small>
                  <strong>{formatDate(detail.submitted_at)}</strong>
                </div>

                <div>
                  <small>Tarikh disemak</small>
                  <strong>{formatDate(detail.reviewed_at)}</strong>
                </div>
              </div>

              {detail.rejection_reason && (
                <div className="admin-alert admin-alert--error">
                  <strong>Alasan penolakan:</strong>{' '}
                  {detail.rejection_reason}
                </div>
              )}

              <div>
                <h3>Dokumen sokongan</h3>

                {detail.documents.length === 0 ? (
                  <div className="admin-state">
                    Tiada dokumen dilampirkan.
                  </div>
                ) : (
                  <div className="admin-record-list">
                    {detail.documents.map((document) => (
                      <div
                        className="admin-record"
                        key={document.id}
                      >
                        <div className="admin-record__heading">
                          <div>
                            <h3>Dokumen identiti</h3>
                            <span>{document.mime_type}</span>
                          </div>

                          <span className="admin-status">
                            {formatBytes(document.size_bytes)}
                          </span>
                        </div>

                        <div className="admin-page__footnote">
                          Tarikh dimuat naik: {formatDate(document.created_at)}
                        </div>

                        <div className="admin-record__actions">
                          <button
                            type="button"
                            className="admin-button"
                            disabled={documentLoadingId === document.id}
                            onClick={() =>
                              void downloadDocument(document.id)
                            }
                          >
                            {documentLoadingId === document.id
                              ? 'Memuat turun...'
                              : 'Muat Turun Dokumen'}
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {detail.status === 'PENDING' && (
                <div className="admin-record">
                  <h3>Keputusan semakan</h3>

                  <label htmlFor="identity-rejection-reason">
                    Alasan penolakan
                  </label>

                  <textarea
                    id="identity-rejection-reason"
                    rows={3}
                    maxLength={500}
                    value={rejectionReason}
                    onChange={(event) =>
                      setRejectionReason(event.target.value)
                    }
                    placeholder="Wajib diisi jika permohonan ditolak."
                  />

                  <div className="admin-page__footnote">
                    Alasan ini hanya disimpan apabila keputusan REJECTED.
                  </div>

                  <div className="admin-record__actions">
                    <button
                      type="button"
                      className="admin-button admin-button--primary"
                      disabled={
                        working ||
                        detail.documents.length === 0
                      }
                      onClick={() => void submitDecision('VERIFIED')}
                    >
                      {working ? 'Memproses...' : 'Sahkan Identiti'}
                    </button>

                    <button
                      type="button"
                      className="admin-button admin-button--danger"
                      disabled={
                        working ||
                        detail.documents.length === 0
                      }
                      onClick={() => void submitDecision('REJECTED')}
                    >
                      Tolak Permohonan
                    </button>
                  </div>
                </div>
              )}
            </>
          ) : (
            <div className="admin-state">
              Butiran permohonan tidak dapat dimuatkan.
            </div>
          )}
        </section>
      )}
    </section>
  );
}
