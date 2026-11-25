import { useCallback, useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';

import {
  getAccounts,
  getAccountDetail,
  getApiError,
  suspendAccount,
  unsuspendAccount,
  reactivateAccount
} from '../../services/adminApi';

import type {
  AccountPagination,
  AdminAccount,
  AdminAccountDetail,
  AdminProfile
} from '../../types/admin';

import SuperAdminManagement from './SuperAdminManagement';
import AccountDirectoryTable from './components/AccountDirectoryTable';
import AccountDetailPanel from './components/AccountDetailPanel';

import type { AccountAction } from './components/AccountDetailPanel';
import { ACTION_LABELS } from './components/AccountDetailPanel';

interface AccountsPageProps {
  account: AdminProfile;
}

const EMPTY_PAGINATION: AccountPagination = {
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: null,
  to: null
};

export default function AccountsPage({ account }: AccountsPageProps) {
  const [accounts, setAccounts] = useState<AdminAccount[]>([]);
  const [pagination, setPagination] =
    useState<AccountPagination>(EMPTY_PAGINATION);

  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [role, setRole] = useState('');

  const [selectedAccount, setSelectedAccount] =
    useState<AdminAccountDetail | null>(null);

  const [loading, setLoading] = useState(true);
  const [detailLoading, setDetailLoading] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);

  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const [pendingAction, setPendingAction] =
    useState<AccountAction | null>(null);

  const [actionReason, setActionReason] = useState('');

  const accountListRequestId = useRef(0);
  const accountDetailRequestId = useRef(0);
  const mountedRef = useRef(false);

  const isRoot = account.role === 'ROOT_SUPER_ADMIN';

  const hasPermission = (permission: string): boolean =>
    isRoot || account.permissions.includes(permission);

  useEffect(() => {
    mountedRef.current = true;

    return () => {
      mountedRef.current = false;
      accountListRequestId.current += 1;
      accountDetailRequestId.current += 1;
    };
  }, []);

  const loadAccounts = useCallback(async (page = 1) => {
    const requestId = ++accountListRequestId.current;

    setLoading(true);
    setError('');

    try {
      const result = await getAccounts({
        search,
        status,
        role,
        page,
        per_page: 15
      });

      if (
        !mountedRef.current ||
        requestId !== accountListRequestId.current
      ) return;

      setAccounts(result.accounts);
      setPagination(result.pagination);
    } catch (err) {
      if (
        mountedRef.current &&
        requestId === accountListRequestId.current
      ) {
        setError(getApiError(err));
      }
    } finally {
      if (
        mountedRef.current &&
        requestId === accountListRequestId.current
      ) {
        setLoading(false);
      }
    }
  }, [search, status, role]);

  useEffect(() => {
    void loadAccounts(1);
  }, [loadAccounts]);

  async function openAccount(accountId: string) {
    const requestId = ++accountDetailRequestId.current;

    setDetailLoading(true);
    setSelectedAccount(null);
    setPendingAction(null);
    setActionReason('');
    setError('');
    setSuccess('');

    try {
      const detail = await getAccountDetail(accountId);

      if (
        !mountedRef.current ||
        requestId !== accountDetailRequestId.current
      ) return;

      setSelectedAccount(detail);
    } catch (err) {
      if (
        mountedRef.current &&
        requestId === accountDetailRequestId.current
      ) {
        setError(getApiError(err));
      }
    } finally {
      if (
        mountedRef.current &&
        requestId === accountDetailRequestId.current
      ) {
        setDetailLoading(false);
      }
    }
  }

  function closeAccountDetail() {
    if (actionLoading) return;

    accountDetailRequestId.current += 1;
    setSelectedAccount(null);
    setPendingAction(null);
    setActionReason('');
    setDetailLoading(false);
  }

  function submitSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSearch(searchInput.trim());
  }

  function beginAction(action: AccountAction) {
    setPendingAction(action);
    setActionReason('');
    setError('');
    setSuccess('');
  }

  function cancelAction() {
    if (actionLoading) return;

    setPendingAction(null);
    setActionReason('');
  }

  async function submitAction(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!selectedAccount || !pendingAction || actionLoading) return;

    const reason = actionReason.trim();

    if (!reason) {
      setError('Sebab tindakan wajib diisi.');
      return;
    }

    const accountId = selectedAccount.id;
    const action = pendingAction;
    const detailRequest = accountDetailRequestId.current;

    setActionLoading(true);
    setError('');
    setSuccess('');

    try {
      if (action === 'suspend') {
        await suspendAccount(accountId, reason);
      } else if (action === 'unsuspend') {
        await unsuspendAccount(accountId, reason);
      } else {
        await reactivateAccount(accountId, reason);
      }

      if (!mountedRef.current) return;

      setSuccess(`${ACTION_LABELS[action]} berjaya dilaksanakan.`);
      setPendingAction(null);
      setActionReason('');

      const updatedDetail = await getAccountDetail(accountId);

      if (
        !mountedRef.current ||
        detailRequest !== accountDetailRequestId.current
      ) return;

      setSelectedAccount(updatedDetail);

      await loadAccounts(pagination.current_page);
    } catch (err) {
      if (mountedRef.current) {
        setError(getApiError(err));
      }
    } finally {
      if (mountedRef.current) {
        setActionLoading(false);
      }
    }
  }

  return (
    <section className="admin-page">
      <div className="admin-page__heading">
        <div>
          <span className="dashboard-page__eyebrow">
            PENGURUSAN AKAUN
          </span>
          <h2>Pengurusan Pengguna</h2>
          <p>Direktori akaun daripada sistem utama.</p>
        </div>

        <button
          type="button"
          className="admin-button"
          onClick={() => void loadAccounts(pagination.current_page)}
          disabled={loading}
        >
          Muat Semula
        </button>
      </div>

      {isRoot && (
        <div
          id="super-admin-management"
          className="admin-navigation-target"
        >
          <SuperAdminManagement
            onRefresh={async () => {
              await loadAccounts(pagination.current_page);
            }}
          />
        </div>
      )}

      <div id="account-directory" className="admin-navigation-target">
        <form className="account-filters" onSubmit={submitSearch}>
          <label>
            <span>Carian akaun</span>
            <input
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Nama, telefon atau e-mel"
            />
          </label>

          <label>
            <span>Status akaun</span>
            <select
              value={status}
              onChange={(event) => setStatus(event.target.value)}
            >
              <option value="">Semua status</option>
              <option value="ACTIVE">Aktif</option>
              <option value="SUSPENDED">Digantung</option>
              <option value="DEACTIVATED">Dinyahaktifkan</option>
              <option value="DELETED">Dipadam</option>
            </select>
          </label>

          <label>
            <span>Peranan</span>
            <select
              value={role}
              onChange={(event) => setRole(event.target.value)}
            >
              <option value="">Semua peranan</option>
              <option value="USER">Pengguna</option>
              <option value="SUPER_ADMIN">Super Admin</option>
              <option value="ROOT_SUPER_ADMIN">Root Super Admin</option>
            </select>
          </label>

          <button
            className="admin-button admin-button--primary"
            type="submit"
          >
            Cari
          </button>
        </form>
      </div>

      {error && (
        <div className="admin-alert admin-alert--error" role="alert">
          {error}
        </div>
      )}

      {success && (
        <div className="admin-alert admin-alert--success" role="status">
          {success}
        </div>
      )}

      {detailLoading && (
        <div className="admin-state" role="status" aria-live="polite">
          Memuatkan butiran akaun...
        </div>
      )}

      {selectedAccount && (
        <AccountDetailPanel
          account={selectedAccount}
          actionLoading={actionLoading}
          pendingAction={pendingAction}
          actionReason={actionReason}
          canSuspend={hasPermission('accounts.suspend')}
          canUnsuspend={hasPermission('accounts.unsuspend')}
          canReactivate={hasPermission('accounts.reactivate')}
          onClose={closeAccountDetail}
          onBeginAction={beginAction}
          onCancelAction={cancelAction}
          onActionReasonChange={setActionReason}
          onSubmitAction={(event) => void submitAction(event)}
        />
      )}

      <AccountDirectoryTable
        accounts={accounts}
        pagination={pagination}
        loading={loading}
        detailLoading={detailLoading}
        actionLoading={actionLoading}
        onOpenAccount={(accountId) => void openAccount(accountId)}
        onPageChange={(nextPage) => void loadAccounts(nextPage)}
      />

      <p className="admin-page__footnote">
        Data direktori dibekalkan oleh API sistem utama. Maklumat identiti
        sulit dan nombor MyKad penuh tidak dipaparkan di sini.
      </p>
    </section>
  );
}
