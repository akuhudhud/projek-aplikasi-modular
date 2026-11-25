import type { IdentityVerificationDetail as Detail } from '../../../types/admin';
import type { DocumentPreview } from '../utils/identityVerificationUtils';
import { formatBytes, formatDate, statusLabel } from '../utils/identityVerificationUtils';

interface Props {
  detail: Detail | null;
  loading: boolean;
  documentLoadingId: string;
  preview: DocumentPreview | null;
  rejectionReason: string;
  working: boolean;
  onClose: () => void;
  onShowDocument: (documentId: string, mimeType: string) => void;
  onClosePreview: () => void;
  onRejectionReasonChange: (value: string) => void;
  onSubmitDecision: (decision: 'VERIFIED' | 'REJECTED') => void;
}

export default function IdentityVerificationDetail({
  detail,
  loading,
  documentLoadingId,
  preview,
  rejectionReason,
  working,
  onClose,
  onShowDocument,
  onClosePreview,
  onRejectionReasonChange,
  onSubmitDecision
}: Props) {
  return (
    <section
      className="admin-record"
      aria-label="Butiran pengesahan identiti"
      aria-busy={loading}
    >
      <div className="admin-page__heading">
        <div>
          <span className="dashboard-page__eyebrow">BUTIRAN PERMOHONAN</span>
          <h2>Semakan Identiti</h2>
        </div>
        <button type="button" className="admin-button" onClick={onClose}>
          Tutup
        </button>
      </div>

      {loading ? (
        <div className="admin-state" role="status" aria-live="polite">
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
              <strong>Alasan penolakan:</strong> {detail.rejection_reason}
            </div>
          )}

          <div>
            <h3>Dokumen sokongan</h3>

            {detail.documents.length === 0 ? (
              <div className="admin-state">Tiada dokumen dilampirkan.</div>
            ) : (
              <div className="admin-record-list">
                {detail.documents.map((document) => (
                  <div className="admin-record" key={document.id}>
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
                        disabled={Boolean(documentLoadingId)}
                        onClick={() =>
                          onShowDocument(document.id, document.mime_type)
                        }
                      >
                        {documentLoadingId === document.id
                          ? 'Memaparkan...'
                          : 'Paparkan Dokumen'}
                      </button>
                    </div>

                    {preview?.id === document.id && (
                      <div className="admin-document-preview">
                        <div className="admin-record__heading">
                          <strong>Pratonton dokumen</strong>
                          <button
                            type="button"
                            className="admin-button"
                            onClick={onClosePreview}
                          >
                            Tutup Paparan
                          </button>
                        </div>

                        {preview.mimeType.startsWith('image/') ? (
                          <img
                            src={preview.url}
                            alt="Paparan dokumen identiti"
                            style={{
                              display: 'block',
                              maxWidth: '100%',
                              maxHeight: '70vh',
                              objectFit: 'contain',
                              margin: '16px auto'
                            }}
                          />
                        ) : preview.mimeType === 'application/pdf' ? (
                          <iframe
                            title="Paparan dokumen identiti PDF"
                            src={`${preview.url}#toolbar=0&navpanes=0`}
                            style={{
                              display: 'block',
                              width: '100%',
                              height: '70vh',
                              border: '1px solid var(--admin-border, #ddd)',
                              borderRadius: '8px'
                            }}
                          />
                        ) : (
                          <div className="admin-state" role="status">
                            Format dokumen ini tidak disokong untuk paparan.
                          </div>
                        )}
                      </div>
                    )}
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
                onChange={(event) => onRejectionReasonChange(event.target.value)}
                placeholder="Wajib diisi jika permohonan ditolak."
              />

              <div className="admin-page__footnote">
                Alasan ini hanya disimpan apabila keputusan REJECTED.
              </div>

              <div className="admin-record__actions">
                <button
                  type="button"
                  className="admin-button admin-button--primary"
                  disabled={working || detail.documents.length === 0}
                  onClick={() => onSubmitDecision('VERIFIED')}
                >
                  {working ? 'Memproses...' : 'Sahkan Identiti'}
                </button>

                <button
                  type="button"
                  className="admin-button admin-button--danger"
                  disabled={working || detail.documents.length === 0}
                  onClick={() => onSubmitDecision('REJECTED')}
                >
                  Tolak Permohonan
                </button>
              </div>
            </div>
          )}
        </>
      ) : (
        <div className="admin-state" role="alert">
          Butiran permohonan tidak dapat dimuatkan.
        </div>
      )}
    </section>
  );
}
