import type { AdminProfile } from '../../../types/admin';

import { hasRunnerPermission } from '../utils/runnerApplicationUtils';

export default function useRunnerPermissions(account: AdminProfile) {
  return {
    canViewApplications: hasRunnerPermission(
      account,
      'runner_applications.view'
    ),

    canApprove: hasRunnerPermission(
      account,
      'runner_applications.approve'
    ),

    canReject: hasRunnerPermission(
      account,
      'runner_applications.reject'
    ),

    canReviewLicense: hasRunnerPermission(
      account,
      'runner_license_verifications.review'
    ),

    canSuspend: hasRunnerPermission(
      account,
      'runners.suspend'
    ),

    canRestore: hasRunnerPermission(
      account,
      'runners.restore'
    )
  };
}
