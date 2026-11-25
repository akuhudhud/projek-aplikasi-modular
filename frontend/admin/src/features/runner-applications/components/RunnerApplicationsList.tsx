import type {
  RunnerApplication,
  RunnerLicenseVerificationDetail
} from '../../../types/admin';

import RunnerApplicationCard from './RunnerApplicationCard';
import RunnerPagination from './RunnerPagination';

interface RunnerApplicationsListProps {
  applications: RunnerApplication[];
  page: number;
  lastPage: number;
  total: number;
  from: number | null;
  to: number | null;
  loading: boolean;
  licenseDetails: Record<string, RunnerLicenseVerificationDetail>;
  licenseLoadingId: string;
  documentLoadingId: string;
  documentPreview: {
    accountId: string;
    url: string;
    mimeType: string;
  } | null;
  canApprove: boolean;
  canReject: boolean;
  canReviewLicense: boolean;
  workingId: string;
  reasons: Record<string, string>;
  onPageChange: (page: number) => void;
  onReasonChange: (id: string, value: string) => void;
  onShowLicenseDetails: (id: string) => void;
  onShowLicenseDocument: (id: string) => void;
  onCloseDocumentPreview: () => void;
  onDecide: (
    id: string,
    decision: 'approve' | 'reject'
  ) => void;
}

export default function RunnerApplicationsList({
  applications,
  page,
  lastPage,
  total,
  from,
  to,
  loading,
  licenseDetails,
  licenseLoadingId,
  documentLoadingId,
  documentPreview,
  canApprove,
  canReject,
  canReviewLicense,
  workingId,
  reasons,
  onPageChange,
  onReasonChange,
  onShowLicenseDetails,
  onShowLicenseDocument,
  onCloseDocumentPreview,
  onDecide
}: RunnerApplicationsListProps) {
  if (loading) {
    return (
      <div className="admin-state" role="status" aria-live="polite">
        Memuatkan permohonan...
      </div>
    );
  }

  if (applications.length === 0) {
    return (
      <div className="admin-state" role="status">
        Tiada permohonan Runner yang menunggu semakan.
      </div>
    );
  }

  return (
    <>
      <div className="admin-page__footnote" aria-live="polite">
        Memaparkan {from ?? 0}–{to ?? 0} daripada {total} permohonan
      </div>

      <div className="admin-record-list">
        {applications.map((application) => (
          <RunnerApplicationCard
            key={application.id}
            application={application}
            licenseDetail={licenseDetails[application.id]}
            licenseLoading={licenseLoadingId === application.id}
            documentLoading={documentLoadingId === application.id}
            documentPreview={
              documentPreview?.accountId === application.id
                ? {
                    url: documentPreview.url,
                    mimeType: documentPreview.mimeType
                  }
                : null
            }
            canApprove={canApprove}
            canReject={canReject}
            canReviewLicense={canReviewLicense}
            working={Boolean(workingId)}
            reason={reasons[application.id] || ''}
            onReasonChange={(value) =>
              onReasonChange(application.id, value)
            }
            onShowLicenseDetails={() =>
              onShowLicenseDetails(application.id)
            }
            onShowLicenseDocument={() =>
              onShowLicenseDocument(application.id)
            }
            onCloseDocumentPreview={onCloseDocumentPreview}
            onDecide={(decision) =>
              onDecide(application.id, decision)
            }
          />
        ))}
      </div>

      <RunnerPagination
        currentPage={page}
        lastPage={lastPage}
        loading={loading}
        onPageChange={onPageChange}
      />
    </>
  );
}
