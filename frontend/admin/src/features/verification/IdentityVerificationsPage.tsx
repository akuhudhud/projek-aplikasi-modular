import { useEffect, useRef, useState } from 'react';

import {
  decideIdentityVerification,
  getIdentityVerificationDocument,
  getApiError,
  getIdentityVerificationDetail,
  getIdentityVerifications
} from '../../services/adminApi';

import type {
  AdminProfile,
  IdentityVerificationDetail as IdentityDetail,
  IdentityVerificationQueueItem
} from '../../types/admin';

import IdentityVerificationQueue from './components/IdentityVerificationQueue';
import IdentityVerificationDetail from './components/IdentityVerificationDetail';

import {
  hasReviewPermission,
  STATUS_TABS,
  type DocumentPreview,
  type VerificationStatus
} from './utils/identityVerificationUtils';

interface Props {
  account: AdminProfile;
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
  const [detail, setDetail] = useState<IdentityDetail | null>(null);

  const [rejectionReason, setRejectionReason] = useState('');
  const [loading, setLoading] = useState(true);
  const [detailLoading, setDetailLoading] = useState(false);
  const [documentLoadingId, setDocumentLoadingId] = useState('');
  const [preview, setPreview] = useState<DocumentPreview | null>(null);
  const [working, setWorking] = useState(false);

  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  const queueRequestId = useRef(0);
  const detailRequestId = useRef(0);
  const documentRequestId = useRef(0);
  const mounted = useRef(false);

  const canReview = hasReviewPermission(account);

  useEffect(() => {
    if (!preview?.url) return;

    return () => {
      window.URL.revokeObjectURL(preview.url);
    };
  }, [preview?.url]);

  async function loadQueue(
    nextPage = page,
    nextStatus = activeStatus,
    nextSearch = search
  ) {
    const requestId = ++queueRequestId.current;

    setLoading(true);
    setError('');

    try {
      const result = await getIdentityVerifications({
        status: nextStatus,
        search: nextSearch.trim() || undefined,
        page: nextPage,
        per_page: 15
      });

      if (!mounted.current || requestId !== queueRequestId.current) return;

      setItems(result.verifications);
      setPage(result.pagination.current_page);
      setLastPage(result.pagination.last_page);
      setTotal(result.pagination.total);

      setSelectedId((currentId) => {
        if (
          currentId &&
          !result.verifications.some((item) => item.account_id === currentId)
        ) {
          detailRequestId.current += 1;
          documentRequestId.current += 1;
          setDetail(null);
          setDetailLoading(false);
          setPreview(null);
          return '';
        }

        return currentId;
      });
    } catch (err) {
      if (mounted.current && requestId === queueRequestId.current) {
        setError(getApiError(err));
      }
    } finally {
      if (mounted.current && requestId === queueRequestId.current) {
        setLoading(false);
      }
    }
  }

  useEffect(() => {
    mounted.current = true;

    if (canReview) {
      void loadQueue(1, 'PENDING', '');
    } else {
      setLoading(false);
    }

    return () => {
      mounted.current = false;
      queueRequestId.current += 1;
      detailRequestId.current += 1;
      documentRequestId.current += 1;
    };
    // Initial queue is loaded once when the page opens.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  function resetDetail() {
    detailRequestId.current += 1;
    documentRequestId.current += 1;
    setSelectedId('');
    setDetail(null);
    setDetailLoading(false);
    setDocumentLoadingId('');
    setPreview(null);
    setRejectionReason('');
  }

  async function changeStatus(status: VerificationStatus) {
    detailRequestId.current += 1;
    documentRequestId.current += 1;
    setActiveStatus(status);
    setPage(1);
    setSelectedId('');
    setDetail(null);
    setDetailLoading(false);
    setDocumentLoadingId('');
    setPreview(null);
    setMessage('');
    setError('');

    await loadQueue(1, status, search);
  }

  async function submitSearch(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const nextSearch = searchInput.trim();

    detailRequestId.current += 1;
    documentRequestId.current += 1;
    setSearch(nextSearch);
    setPage(1);
    setSelectedId('');
    setDetail(null);
    setDetailLoading(false);
    setDocumentLoadingId('');
    setPreview(null);
    setMessage('');
    setError('');

    await loadQueue(1, activeStatus, nextSearch);
  }

  async function openDetail(item: IdentityVerificationQueueItem) {
    const requestId = ++detailRequestId.current;
    documentRequestId.current += 1;

    setSelectedId(item.account_id);
    setDetail(null);
    setPreview(null);
    setDocumentLoadingId('');
    setRejectionReason('');
    setError('');
    setMessage('');
    setDetailLoading(true);

    try {
      const result = await getIdentityVerificationDetail(item.account_id);

      if (!mounted.current || requestId !== detailRequestId.current) return;

      setDetail(result);
    } catch (err) {
      if (mounted.current && requestId === detailRequestId.current) {
        setError(getApiError(err));
      }
    } finally {
      if (mounted.current && requestId === detailRequestId.current) {
        setDetailLoading(false);
      }
    }
  }

  async function showDocument(documentId: string, mimeType: string) {
    if (!detail || !canReview || documentLoadingId) return;

    const accountId = detail.account_id;
    const requestId = ++documentRequestId.current;

    setDocumentLoadingId(documentId);
    setPreview(null);
    setError('');

    try {
      const blob = await getIdentityVerificationDocument(accountId, documentId);

      if (!mounted.current || requestId !== documentRequestId.current) return;

      const objectUrl = window.URL.createObjectURL(blob);

      setPreview({
        id: documentId,
        url: objectUrl,
        mimeType: mimeType.toLowerCase()
      });
    } catch (err) {
      if (mounted.current && requestId === documentRequestId.current) {
        setError(getApiError(err));
      }
    } finally {
      if (mounted.current && requestId === documentRequestId.current) {
        setDocumentLoadingId('');
      }
    }
  }

  async function submitDecision(decision: 'VERIFIED' | 'REJECTED') {
    if (!detail || detail.status !== 'PENDING' || !canReview || working) return;

    const reason = rejectionReason.trim();

    if (decision === 'REJECTED' && !reason) {
      setError('Alasan penolakan wajib diisi.');
      return;
    }

    if (detail.documents.length === 0) {
      setError('Permohonan tiada dokumen untuk disemak.');
      return;
    }

    const accountId = detail.account_id;

    setWorking(true);
    setError('');
    setMessage('');

    try {
      await decideIdentityVerification(
        accountId,
        decision,
        decision === 'REJECTED' ? reason : undefined
      );

      if (!mounted.current) return;

      setMessage(
        decision === 'VERIFIED'
          ? 'Pengesahan identiti berjaya diluluskan.'
          : 'Permohonan pengesahan identiti telah ditolak.'
      );

      setDetail((current) =>
        current && current.account_id === accountId
          ? {
              ...current,
              status: decision,
              rejection_reason: decision === 'REJECTED' ? reason : null
            }
          : current
      );

      await loadQueue(page, activeStatus, search);
    } catch (err) {
      if (mounted.current) setError(getApiError(err));
    } finally {
      if (mounted.current) setWorking(false);
    }
  }

  function closeDetail() {
    resetDetail();
    setError('');
  }

  if (!canReview) {
    return (
      <section className="admin-page">
        <div className="admin-page__heading">
          <div>
            <span className="dashboard-page__eyebrow">MODUL PENGESAHAN</span>
            <h2>Pengesahan Identiti</h2>
          </div>
        </div>

        <div className="admin-state" role="status">
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
          <p>Semak maklumat identiti dan dokumen pengguna.</p>
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
        aria-label="Tapisan status pengesahan identiti"
      >
        {STATUS_TABS.map((tab) => (
          <button
            key={tab.value}
            type="button"
            aria-pressed={activeStatus === tab.value}
            className={`admin-tab ${
              activeStatus === tab.value ? 'admin-tab--active' : ''
            }`}
            disabled={loading}
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
            resetDetail();
            setMessage('');
            void loadQueue(1, activeStatus, '');
          }}
        >
          Kosongkan
        </button>
      </form>

      <div className="admin-page__footnote" aria-live="polite">
        Jumlah rekod: {total}
      </div>

      <IdentityVerificationQueue
        items={items}
        loading={loading}
        detailLoading={detailLoading}
        selectedId={selectedId}
        page={page}
        lastPage={lastPage}
        onOpenDetail={(item) => void openDetail(item)}
        onPageChange={(nextPage) => void loadQueue(nextPage)}
      />

      {selectedId && (
        <IdentityVerificationDetail
          detail={detail}
          loading={detailLoading}
          documentLoadingId={documentLoadingId}
          preview={preview}
          rejectionReason={rejectionReason}
          working={working}
          onClose={closeDetail}
          onShowDocument={(documentId, mimeType) =>
            void showDocument(documentId, mimeType)
          }
          onClosePreview={() => setPreview(null)}
          onRejectionReasonChange={setRejectionReason}
          onSubmitDecision={(decision) => void submitDecision(decision)}
        />
      )}
    </section>
  );
}
