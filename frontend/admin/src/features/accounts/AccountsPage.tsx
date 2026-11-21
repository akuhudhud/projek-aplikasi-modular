
import { useCallback, useEffect, useState } from 'react';

import {
  getAccounts,
  getAccountDetail,
  getApiError
} from '../../services/adminApi';

import type {
  AccountPagination,
  AdminAccount,
  AdminAccountDetail
} from '../../types/admin';

const EMPTY_PAGINATION: AccountPagination = {
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: null,
  to: null
};

export default function AccountsPage() {
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
  const [error, setError] = useState('');

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
    setError('');

    try {
      setSelectedAccount(await getAccountDetail(accountId));
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setDetailLoading(false);
    }
  }

  function submitSearch(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSearch(searchInput.trim());
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
          className="admin-button"
          onClick={() => void loadAccounts(pagination.current_page)}
          disabled={loading}
        >
          Muat Semula
        </button>
      </div>

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

        <button className="admin-button admin-button--primary" type="submit">
          Cari
        </button>
      </form>

      {error && (
        <div className="admin-alert admin-alert--error" role="alert">
          {error}
        </div>
      )}

      {detailLoading && (
        <div className="admin-state">Memuatkan butiran akaun...</div>
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
              className="admin-button"
              onClick={() => setSelectedAccount(null)}
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
              <strong>{selectedAccount.verified_name || 'Belum disahkan'}</strong>
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
              <strong>{selectedAccount.identity_status || 'Tiada rekod'}</strong>
            </div>
            <div>
              <small>Jawatan</small>
              <strong>{selectedAccount.job_title || '—'}</strong>
            </div>
            <div>
              <small>Tarikh Daftar</small>
              <strong>{new Date(selectedAccount.created_at).toLocaleString('ms-MY')}</strong>
            </div>
          </div>
        </section>
      )}

      {loading ? (
        <div className="admin-state">Memuatkan senarai akaun...</div>
      ) : accounts.length === 0 ? (
        <div className="admin-state">Tiada rekod akaun ditemui.</div>
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
                {accounts.map((account) => (
                  <tr key={account.id}>
                    <td>{account.profile_name}</td>
                    <td>{account.verified_name || '—'}</td>
                    <td>{account.phone || '—'}</td>
                    <td>{account.email || '—'}</td>
                    <td>{account.role}</td>
                    <td>
                      <span className="admin-status">
                        {account.status}
                      </span>
                    </td>
                    <td>
                      <button
                        className="admin-button admin-button--small"
                        onClick={() => void openAccount(account.id)}
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
                className="admin-button admin-button--small"
                disabled={loading || pagination.current_page >= pagination.last_page}
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
