
import type { AdminProfile } from '../../../types/admin';

import { hasRunnerPermission } from '../utils/runnerApplicationUtils';

export default function useRunnerPermissions(account: AdminProfile) {
  const canViewApplications = hasRunnerPermission(
    account,
    'runner_applications.view'
  );

  return {
    canViewApplications,

    canApprove: hasRunnerPermission(
      account,
      'runner_applications.approve'
    ),

    canReject: hasRunnerPermission(
      account,
      'runner_applications.reject'
    ),

    // Semakan lesen berada dalam aliran permohonan Runner yang sama.
    canReviewLicense: canViewApplications,

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
