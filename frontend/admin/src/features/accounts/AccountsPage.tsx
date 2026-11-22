
import { useCallback, useEffect, useState } from 'react';
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

interface AccountsPageProps {
  account: AdminProfile;
}

type AccountAction = 'suspend' | 'unsuspend' | 'reactivate';

const EMPTY_PAGINATION: AccountPagination = {
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: null,
  to: null
};

const ACTION_LABELS: Record<AccountAction, string> = {
  suspend: 'Gantung Akaun',
  unsuspend: 'Pulihkan Akaun',
  reactivate: 'Aktifkan Semula Akaun'
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

  const isRoot = account.role === 'ROOT_SUPER_ADMIN';

  const hasPermission = (permission: string): boolean => {
    return isRoot || account.permissions.includes(permission);
  };

  const loadAccounts = useCallback(async (page = 1) => {
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

      setAccounts(result.accounts);
      setPagination(result.pagination);
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setLoading(false);
    }
  }, [search, status, role]);

  useEffect(() => {
    void loadAccounts(1);
  }, [loadAccounts]);

  async function openAccount(accountId: string) {
    setDetailLoading(true);
    setSelectedAccount(null);
    setPendingAction(null);
    setActionReason('');
    setError('');
    setSuccess('');

    try {
      const detail = await getAccountDetail(accountId);
      setSelectedAccount(detail);
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setDetailLoading(false);
    }
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

    setActionLoading(true);
    setError('');
    setSuccess('');

    try {
      if (pendingAction === 'suspend') {
        await suspendAccount(selectedAccount.id, reason);
      } else if (pendingAction === 'unsuspend') {
        await unsuspendAccount(selectedAccount.id, reason);
      } else {
        await reactivateAccount(selectedAccount.id, reason);
      }

      setSuccess(
        `${ACTION_LABELS[pendingAction]} berjaya dilaksanakan.`
      );

      setPendingAction(null);
      setActionReason('');

      const updatedDetail = await getAccountDetail(selectedAccount.id);
      setSelectedAccount(updatedDetail);

      await loadAccounts(pagination.current_page);
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setActionLoading(false);
    }
  }

  function renderAccountActions() {
    if (!selectedAccount || selectedAccount.role !== 'USER') {
      return null;
    }

    const actions: {
      action: AccountAction;
      permission: string;
      className?: string;
    }[] = [];

    if (
      selectedAccount.status === 'ACTIVE' &&
      hasPermission('accounts.suspend')
    ) {
      actions.push({
        action: 'suspend',
        permission: 'accounts.suspend',
        className: 'admin-button--danger'
      });
    }

    if (
      selectedAccount.status === 'SUSPENDED' &&
      hasPermission('accounts.unsuspend')
    ) {
      actions.push({
        action: 'unsuspend',
        permission: 'accounts.unsuspend'
      });
    }

    if (
      selectedAccount.status === 'DEACTIVATED' &&
      hasPermission('accounts.reactivate')
    ) {
      actions.push({
        action: 'reactivate',
        permission: 'accounts.reactivate'
      });
    }

    if (actions.length === 0) return null;

    return (
      <div className="admin-record__actions">
        {actions.map(({ action, className }) => (
          <button
            key={action}
            type="button"
            className={`admin-button ${className ?? ''}`}
            disabled={actionLoading}
            onClick={() => beginAction(action)}
          >
            {ACTION_LABELS[action]}
          </button>
        ))}
      </div>
    );
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
        <SuperAdminManagement
          onRefresh={async () => {
            await loadAccounts(pagination.current_page);
          }}
        />
      )}

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
        <div className="admin-state">
          Memuatkan butiran akaun...
        </div>
      )}

      {selectedAccount && (
        <section className="account-detail">
          <div className="admin-page__heading">
            <div>
              <span className="dashboard-page__eyebrow">
                BUTIRAN AKAUN
              </span>
              <h3>{selectedAccount.profile_name}</h3>
            </div>

            <button
              type="button"
              className="admin-button"
              onClick={() => {
                setSelectedAccount(null);
                setPendingAction(null);
              }}
            >
              Tutup
            </button>
          </div>

          <div className="admin-record__details">
            <div>
              <small>Nama Paparan</small>
              <strong>{selectedAccount.profile_name}</strong>
            </div>

            <div>
              <small>Nama Disahkan</small>
              <strong>
                {selectedAccount.verified_name || 'Belum disahkan'}
              </strong>
            </div>

            <div>
              <small>Telefon</small>
              <strong>{selectedAccount.phone || '—'}</strong>
            </div>

            <div>
              <small>E-mel</small>
              <strong>{selectedAccount.email || '—'}</strong>
            </div>

            <div>
              <small>Peranan</small>
              <strong>{selectedAccount.role}</strong>
            </div>

            <div>
              <small>Status Akaun</small>
              <strong>{selectedAccount.status}</strong>
            </div>

            <div>
              <small>Status Identiti</small>
              <strong>
                {selectedAccount.identity_status || 'Tiada rekod'}
              </strong>
            </div>

            <div>
              <small>Jawatan</small>
              <strong>{selectedAccount.job_title || '—'}</strong>
            </div>

            <div>
              <small>Tarikh Daftar</small>
              <strong>
                {new Date(selectedAccount.created_at).toLocaleString('ms-MY')}
              </strong>
            </div>
          </div>

          {renderAccountActions()}

          {pendingAction && (
            <form className="admin-record" onSubmit={submitAction}>
              <div className="admin-record__heading">
                <div>
                  <h3>{ACTION_LABELS[pendingAction]}</h3>
                  <span>
                    Akaun: {selectedAccount.profile_name}
                  </span>
                </div>
              </div>

              <label htmlFor="account-action-reason">
                Sebab tindakan
              </label>

              <textarea
                id="account-action-reason"
                rows={4}
                maxLength={1000}
                required
                value={actionReason}
                onChange={(event) => setActionReason(event.target.value)}
                placeholder="Masukkan sebab tindakan ini..."
              />

              <div className="admin-record__actions">
                <button
                  type="submit"
                  className="admin-button admin-button--primary"
                  disabled={actionLoading || !actionReason.trim()}
                >
                  {actionLoading ? 'Memproses...' : 'Sahkan Tindakan'}
                </button>

                <button
                  type="button"
                  className="admin-button"
                  disabled={actionLoading}
                  onClick={cancelAction}
                >
                  Batal
                </button>
              </div>
            </form>
          )}
        </section>
      )}

      {loading ? (
        <div className="admin-state">
          Memuatkan senarai akaun...
        </div>
      ) : accounts.length === 0 ? (
        <div className="admin-state">
          Tiada rekod akaun ditemui.
        </div>
      ) : (
        <>
          <div className="admin-table-wrap">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Nama Paparan</th>
                  <th>Nama Disahkan</th>
                  <th>Telefon</th>
                  <th>E-mel</th>
                  <th>Peranan</th>
                  <th>Status</th>
                  <th>Tindakan</th>
                </tr>
              </thead>

              <tbody>
                {accounts.map((item) => (
                  <tr key={item.id}>
                    <td>{item.profile_name}</td>
                    <td>{item.verified_name || '—'}</td>
                    <td>{item.phone || '—'}</td>
                    <td>{item.email || '—'}</td>
                    <td>{item.role}</td>
                    <td>
                      <span className="admin-status">
                        {item.status}
                      </span>
                    </td>
                    <td>
                      <button
                        type="button"
                        className="admin-button admin-button--small"
                        onClick={() => void openAccount(item.id)}
                      >
                        Lihat
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="account-pagination">
            <span>
              Memaparkan {pagination.from ?? 0}–{pagination.to ?? 0}
              {' '}daripada {pagination.total} akaun
            </span>

            <div>
              <button
                type="button"
                className="admin-button admin-button--small"
                disabled={loading || pagination.current_page <= 1}
                onClick={() => void loadAccounts(pagination.current_page - 1)}
              >
                Sebelum
              </button>

              <span>
                Halaman {pagination.current_page} / {pagination.last_page}
              </span>

              <button
                type="button"
                className="admin-button admin-button--small"
                disabled={
                  loading ||
                  pagination.current_page >= pagination.last_page
                }
                onClick={() => void loadAccounts(pagination.current_page + 1)}
              >
                Seterusnya
              </button>
            </div>
          </div>
        </>
      )}

      <p className="admin-page__footnote">
        Data direktori dibekalkan oleh API sistem utama. Maklumat identiti
        sulit dan nombor MyKad penuh tidak dipaparkan di sini.
      </p>
    </section>
  );
}
