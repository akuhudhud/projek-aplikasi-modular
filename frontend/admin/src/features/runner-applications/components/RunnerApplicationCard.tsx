import type {
  RunnerApplication,
  RunnerLicenseVerificationDetail
} from '../../../types/admin';

import {
  formatRunnerDate
} from '../utils/runnerApplicationUtils';

import RunnerLicenseDetails from './RunnerLicenseDetails';
import RunnerDocumentPreview from './RunnerDocumentPreview';

interface RunnerApplicationCardProps {
  application: RunnerApplication;
  licenseDetail?: RunnerLicenseVerificationDetail;
  licenseLoading: boolean;
  documentLoading: boolean;
  documentPreview: {
    url: string;
    mimeType: string;
  } | null;
  canApprove: boolean;
  canReject: boolean;
  canReviewLicense: boolean;
  working: boolean;
  reason: string;
  onReasonChange: (value: string) => void;
  onShowLicenseDetails: () => void;
  onShowLicenseDocument: () => void;
  onCloseDocumentPreview: () => void;
  onDecide: (decision: 'approve' | 'reject') => void;
}

export default function RunnerApplicationCard({
  application,
  licenseDetail,
  licenseLoading,
  documentLoading,
  documentPreview,
  canApprove,
  canReject,
  canReviewLicense,
  working,
  reason,
  onReasonChange,
  onShowLicenseDetails,
  onShowLicenseDocument,
  onCloseDocumentPreview,
  onDecide
}: RunnerApplicationCardProps) {
  const license = application.runner_license_application;

  return (
    <article className="admin-record">
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
          <strong>{formatRunnerDate(application.created_at)}</strong>
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
          <strong>{formatRunnerDate(license?.expires_at)}</strong>
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
            disabled={licenseLoading}
            onClick={onShowLicenseDetails}
          >
            {licenseLoading ? 'Memuatkan...' : 'Butiran Lesen'}
          </button>

          <button
            type="button"
            className="admin-button"
            disabled={documentLoading}
            onClick={onShowLicenseDocument}
          >
            {documentLoading ? 'Memaparkan...' : 'Paparkan Dokumen'}
          </button>
        </div>
      )}

      {licenseLoading && (
        <p role="status" aria-live="polite">
          Memuatkan butiran lesen...
        </p>
      )}

      {licenseDetail && (
        <RunnerLicenseDetails detail={licenseDetail} />
      )}

      {documentLoading && (
        <p role="status" aria-live="polite">
          Sedang menyediakan paparan dokumen lesen...
        </p>
      )}

      {documentPreview && !documentLoading && (
        <RunnerDocumentPreview
          url={documentPreview.url}
          mimeType={documentPreview.mimeType}
          onClose={onCloseDocumentPreview}
        />
      )}

      {(canApprove || canReject) && (
        <>
          <label htmlFor={`reason-${application.id}`}>
            Alasan keputusan
          </label>

          <textarea
            id={`reason-${application.id}`}
            rows={2}
            value={reason}
            onChange={(event) => onReasonChange(event.target.value)}
            placeholder="Masukkan alasan keputusan..."
          />

          <div className="admin-record__actions">
            {canApprove && (
              <button
                type="button"
                className="admin-button admin-button--primary"
                disabled={working || !license}
                onClick={() => onDecide('approve')}
              >
                {working ? 'Memproses...' : 'Luluskan'}
              </button>
            )}

            {canReject && (
              <button
                type="button"
                className="admin-button admin-button--danger"
                disabled={working || !license}
                onClick={() => onDecide('reject')}
              >
                {working ? 'Memproses...' : 'Tolak'}
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
}
