import type {
  RunnerApplication,
  RunnerLicenseVerificationDetail
} from '../../../types/admin';

import RunnerApplicationsList from './RunnerApplicationsList';

interface RunnerApplicationsPanelProps {
  canView: boolean;
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

export default function RunnerApplicationsPanel({
  canView,
  ...listProps
}: RunnerApplicationsPanelProps) {
  return (
    <div
      id="runner-panel-applications"
      role="tabpanel"
      aria-labelledby="runner-tab-applications"
      tabIndex={0}
    >
      {!canView ? (
        <div className="admin-state">
          Anda tidak mempunyai kebenaran melihat permohonan Runner.
        </div>
      ) : (
        <RunnerApplicationsList {...listProps} />
      )}
    </div>
  );
}
