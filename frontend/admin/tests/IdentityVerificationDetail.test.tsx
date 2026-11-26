
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  cleanup,
  render,
  screen
} from '@testing-library/react';

import IdentityVerificationDetail from '../src/features/verification/components/IdentityVerificationDetail';

import type { IdentityVerificationDetail as Detail } from '../src/types/admin';

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const baseDetail: Detail = {
  account_id: 'account-001',
  profile_name: 'Pengguna Ujian',
  verified_name: 'Nama Sebenar',
  status: 'VERIFIED',
  mykad_number_masked: '********5678',
  submitted_at: '2026-11-21T10:00:00.000Z',
  reviewed_at: '2026-11-22T10:00:00.000Z',
  rejection_reason: null,
  account_role: 'USER',
  runner_status: null,
  documents: []
};

function renderDetail(
  detail: Detail,
  options?: {
    canDeleteData?: boolean;
    canDeleteAnyRole?: boolean;
  }
) {
  return render(
    <IdentityVerificationDetail
      detail={detail}
      loading={false}
      documentLoadingId=""
      preview={null}
      rejectionReason=""
      working={false}
      canDeleteData={options?.canDeleteData ?? true}
      canDeleteAnyRole={options?.canDeleteAnyRole ?? false}
      deletionWorking={false}
      onClose={vi.fn()}
      onShowDocument={vi.fn()}
      onClosePreview={vi.fn()}
      onRejectionReasonChange={vi.fn()}
      onSubmitDecision={vi.fn()}
      onDeleteData={vi.fn()}
    />
  );
}

describe('IdentityVerificationDetail - pemadaman data', () => {
  it('memaparkan fungsi pemadaman untuk akaun USER yang dibenarkan', () => {
    renderDetail(baseDetail, {
      canDeleteData: true
    });

    expect(
      screen.getByRole('heading', {
        name: 'Pemadaman Data Identiti'
      })
    ).toBeTruthy();
  });

  it.each(['PENDING', 'APPROVED'])(
    'menyembunyikan fungsi pemadaman apabila status runner ialah %s',
    (runnerStatus) => {
      const detail: Detail = {
        ...baseDetail,
        runner_status: runnerStatus
      };

      renderDetail(detail, {
        canDeleteData: true,
        canDeleteAnyRole: true
      });

      expect(
        screen.queryByRole('heading', {
          name: 'Pemadaman Data Identiti'
        })
      ).toBeNull();
    }
  );

  it('tidak memaparkan fungsi pemadaman tanpa permission', () => {
    renderDetail(baseDetail, {
      canDeleteData: false
    });

    expect(
      screen.queryByRole('heading', {
        name: 'Pemadaman Data Identiti'
      })
    ).toBeNull();
  });

  it('menyembunyikan fungsi pemadaman untuk role selain USER jika bukan Root', () => {
    const detail: Detail = {
      ...baseDetail,
      account_role: 'SUPER_ADMIN'
    };

    renderDetail(detail, {
      canDeleteData: true,
      canDeleteAnyRole: false
    });

    expect(
      screen.queryByRole('heading', {
        name: 'Pemadaman Data Identiti'
      })
    ).toBeNull();
  });

  it('membenarkan Root memaparkan fungsi pemadaman untuk role selain USER', () => {
    const detail: Detail = {
      ...baseDetail,
      account_role: 'SUPER_ADMIN'
    };

    renderDetail(detail, {
      canDeleteData: true,
      canDeleteAnyRole: true
    });

    expect(
      screen.getByRole('heading', {
        name: 'Pemadaman Data Identiti'
      })
    ).toBeTruthy();
  });

  it('menyembunyikan fungsi pemadaman jika role akaun tidak diketahui', () => {
    const detail: Detail = {
      ...baseDetail,
      account_role: undefined
    };

    renderDetail(detail, {
      canDeleteData: true
    });

    expect(
      screen.queryByRole('heading', {
        name: 'Pemadaman Data Identiti'
      })
    ).toBeNull();
  });
});
