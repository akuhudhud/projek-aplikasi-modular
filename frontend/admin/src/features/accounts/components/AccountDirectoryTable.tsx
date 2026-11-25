import type { AccountPagination, AdminAccount } from '../../../types/admin';

interface Props {
  accounts: AdminAccount[];
  pagination: AccountPagination;
  loading: boolean;
  detailLoading: boolean;
  actionLoading: boolean;
  onOpenAccount: (accountId: string) => void;
  onPageChange: (page: number) => void;
}

export default function AccountDirectoryTable({
  accounts,
  pagination,
  loading,
  detailLoading,
  actionLoading,
  onOpenAccount,
  onPageChange
}: Props) {
  if (loading) {
    return (
      <div className="admin-state" role="status" aria-live="polite">
        Memuatkan senarai akaun...
      </div>
    );
  }

  if (accounts.length === 0) {
    return <div className="admin-state">Tiada rekod akaun ditemui.</div>;
  }

  return (
    <>
      <div className="admin-table-wrap">
        <table className="admin-table">
          <caption>Senarai akaun pengguna</caption>
          <thead>
            <tr>
              <th scope="col">Nama Paparan</th>
              <th scope="col">Nama Disahkan</th>
              <th scope="col">Telefon</th>
              <th scope="col">E-mel</th>
              <th scope="col">Peranan</th>
              <th scope="col">Status</th>
              <th scope="col">Tindakan</th>
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
                  <span className="admin-status">{item.status}</span>
                </td>
                <td>
                  <button
                    type="button"
                    className="admin-button admin-button--small"
                    disabled={detailLoading || actionLoading}
                    onClick={() => onOpenAccount(item.id)}
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
            onClick={() => onPageChange(pagination.current_page - 1)}
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
              loading || pagination.current_page >= pagination.last_page
            }
            onClick={() => onPageChange(pagination.current_page + 1)}
          >
            Seterusnya
          </button>
        </div>
      </div>
    </>
  );
}
