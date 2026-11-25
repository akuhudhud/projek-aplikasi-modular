import type { IdentityVerificationQueueItem } from '../../../types/admin';
import {
  formatDate,
  statusLabel
} from '../utils/identityVerificationUtils';

interface Props {
  items: IdentityVerificationQueueItem[];
  loading: boolean;
  detailLoading: boolean;
  selectedId: string;
  page: number;
  lastPage: number;
  onOpenDetail: (item: IdentityVerificationQueueItem) => void;
  onPageChange: (page: number) => void;
}

export default function IdentityVerificationQueue({
  items,
  loading,
  detailLoading,
  selectedId,
  page,
  lastPage,
  onOpenDetail,
  onPageChange
}: Props) {
  if (loading) {
    return (
      <div className="admin-state" role="status" aria-live="polite">
        Memuatkan senarai permohonan...
      </div>
    );
  }

  if (items.length === 0) {
    return (
      <div className="admin-state" role="status">
        Tiada permohonan ditemui untuk tapisan ini.
      </div>
    );
  }

  return (
    <>
      <div className="admin-record-list">
        {items.map((item) => (
          <article className="admin-record" key={item.account_id}>
            <div className="admin-record__heading">
              <div>
                <h3>{item.profile_name}</h3>
                <span>
                  {item.phone || item.email || 'Tiada maklumat hubungan'}
                </span>
              </div>
              <span className="admin-status">{statusLabel(item.status)}</span>
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
                  detailLoading || loading || selectedId === item.account_id
                }
                onClick={() => onOpenDetail(item)}
              >
                {selectedId === item.account_id ? 'Dipilih' : 'Lihat Butiran'}
              </button>
            </div>
          </article>
        ))}
      </div>

      {lastPage > 1 && (
        <div className="admin-record__actions">
          <button
            type="button"
            className="admin-button"
            disabled={page <= 1}
            onClick={() => onPageChange(page - 1)}
          >
            Sebelum
          </button>

          <span className="admin-page__footnote" aria-live="polite">
            Halaman {page} daripada {lastPage}
          </span>

          <button
            type="button"
            className="admin-button"
            disabled={page >= lastPage}
            onClick={() => onPageChange(page + 1)}
          >
            Seterusnya
          </button>
        </div>
      )}
    </>
  );
}
