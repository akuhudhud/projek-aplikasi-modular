import { useState } from 'react';

import type { AdminProfile } from '../../../types/admin';

import useRunnerPermissions from './useRunnerPermissions';
import useRunnerApplications from './useRunnerApplications';
import useRunnerDirectory from './useRunnerDirectory';
import useRunnerLicense from './useRunnerLicense';
import useRunnerActions from './useRunnerActions';

export type RunnerManagementTab = 'applications' | 'directory';

interface UseRunnerManagementOptions {
  account: AdminProfile;
}

export default function useRunnerManagement({
  account
}: UseRunnerManagementOptions) {
  const [activeTab, setActiveTab] =
    useState<RunnerManagementTab>('applications');

  const permissions = useRunnerPermissions(account);

  const applications = useRunnerApplications({
    enabled: activeTab === 'applications',
    perPage: 15
  });

  const directory = useRunnerDirectory({
    enabled: activeTab === 'directory',
    perPage: 15
  });

  const license = useRunnerLicense({
    canReview: permissions.canReviewLicense
  });

  const actions = useRunnerActions({
    applicationPage: applications.page,
    applicationCount: applications.applications.length,
    runnerPage: directory.page,
    refreshApplications: applications.loadApplications,
    refreshRunners: directory.loadRunners
  });

  function changeTab(tab: RunnerManagementTab) {
    if (tab === activeTab) return;

    setActiveTab(tab);
    applications.setError('');
    directory.setError('');
    actions.setError('');
    actions.setMessage('');
    license.setError('');
    license.resetLicenseRequests();
  }

  function refreshActiveTab() {
    if (activeTab === 'applications') {
      void applications.loadApplications(applications.page);
    } else {
      void directory.loadRunners(directory.page);
    }
  }

  return {
    activeTab,
    changeTab,
    refreshActiveTab,
    permissions,
    applications,
    directory,
    license,
    actions
  };
}
