
import { useEffect, useState } from 'react';

import { getAccounts, getApiError } from '../../services/adminApi';
import type { AdminAccount } from '../../types/admin';

export default function AccountsPage() {
  const [accounts, setAccounts] = useState<AdminAccount[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  async function loadAccounts() {
    setLoading(true);
    setError('');

    try {
      setAccounts(await getAccounts());
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadAccounts();
  }, []);

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
          onClick={() => void loadAccounts()}
          disabled={loading}
        >
          Muat Semula
        </button>
      </div>

      {error && (
        <div className="admin-alert admin-alert--error" role="alert">
          {error}
        </div>
      )}

      {loading ? (
        <div className="admin-state">Memuatkan senarai akaun...</div>
      ) : accounts.length === 0 ? (
        <div className="admin-state">Tiada rekod akaun diterima.</div>
      ) : (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <thead>
              <tr>
                <th>Nama Profil</th>
                <th>Nama Disahkan</th>
                <th>Telefon</th>
                <th>E-mel</th>
                <th>Peranan</th>
                <th>Status</th>
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
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <p className="admin-page__footnote">
        Paparan ini menggunakan data API sebenar. Fungsi pengurusan
        akaun akan diasingkan mengikut kebenaran pentadbir.
      </p>
    </section>
  );
}
