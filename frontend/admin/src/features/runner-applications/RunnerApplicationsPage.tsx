
import type { AdminProfile } from '../../types/admin';

import RunnerApplicationsHeader from './components/RunnerApplicationsHeader';
import RunnerApplicationsTabs from './components/RunnerApplicationsTabs';
import RunnerApplicationsPanel from './components/RunnerApplicationsPanel';
import RunnerDirectoryPanel from './components/RunnerDirectoryPanel';

import useRunnerManagement from './hooks/useRunnerManagement';

interface RunnerApplicationsPageProps {
  account: AdminProfile;
}

export default function RunnerApplicationsPage({
  account,
}: RunnerApplicationsPageProps) {
  const management = useRunnerManagement({ account });

  const {
    activeTab,
    changeTab,
    refreshActiveTab,
    permissions,
    applications,
    directory,
    license,
    actions,
  } = management;

  const error =
    activeTab === 'applications'
      ? applications.error || license.error || actions.error
      : directory.error || actions.error;

  const loading =
    activeTab === 'applications'
      ? applications.loading
      : directory.loading;

  return (
    <section className="admin-page">
      <RunnerApplicationsHeader
        loading={loading}
        onRefresh={refreshActiveTab}
      />

      <RunnerApplicationsTabs
        activeTab={activeTab}
        onTabChange={changeTab}
      />

      {error && (
        <div
          className="admin-alert admin-alert--error"
          role="alert"
        >
          {error}
        </div>
      )}

      {actions.message && (
        <div
          className="admin-alert admin-alert--success"
          role="status"
          aria-live="polite"
        >
          {actions.message}
        </div>
      )}

      {activeTab === 'applications' ? (
        <RunnerApplicationsPanel
          canView={permissions.canViewApplications}
          applications={applications.applications}
          page={applications.page}
          lastPage={applications.lastPage}
          total={applications.total}
          from={applications.from}
          to={applications.to}
          loading={applications.loading}
          licenseDetails={license.licenseDetails}
          licenseLoadingId={license.licenseLoadingId}
          documentLoadingId={license.documentLoadingId}
          documentPreview={license.documentPreview}
          canApprove={permissions.canApprove}
          canReject={permissions.canReject}
          canReviewLicense={permissions.canReviewLicense}
          workingId={actions.workingId}
          reasons={actions.reasons}
          onPageChange={applications.loadApplications}
          onReasonChange={actions.updateReason}
          onShowLicenseDetails={license.showLicenseDetails}
          onShowLicenseDocument={license.showLicenseDocument}
          onCloseDocumentPreview={license.clearDocumentPreview}
          onDecide={actions.decide}
        />
      ) : (
        <RunnerDirectoryPanel
          runners={directory.runners}
          search={directory.search}
          status={directory.status}
          page={directory.page}
          lastPage={directory.lastPage}
          total={directory.total}
          loading={directory.loading}
          canSuspend={permissions.canSuspend}
          canRestore={permissions.canRestore}
          workingId={actions.workingId}
          reasons={actions.reasons}
          onSearchChange={directory.setSearch}
          onStatusChange={directory.filterByStatus}
          onSearch={directory.searchRunners}
          onPageChange={directory.loadRunners}
          onReasonChange={actions.updateReason}
          onRunnerStatusChange={actions.updateRunnerStatus}
        />
      )}
    </section>
  );
}
