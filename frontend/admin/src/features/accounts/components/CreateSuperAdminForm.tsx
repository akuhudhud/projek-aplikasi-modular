
import type { FormEvent } from 'react';
import type { AdminAccount } from '../../../types/admin';

interface Props {
  users: AdminAccount[];
  loading: boolean;
  submitting: boolean;
  search: string;
  selectedAccountId: string;
  jobTitle: string;
  reason: string;
  onSearchChange: (value: string) => void;
  onSearch: () => void;
  onAccountChange: (value: string) => void;
  onJobTitleChange: (value: string) => void;
  onReasonChange: (value: string) => void;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

export default function CreateSuperAdminForm({
  users,
  loading,
  submitting,
  search,
  selectedAccountId,
  jobTitle,
  reason,
  onSearchChange,
  onSearch,
  onAccountChange,
  onJobTitleChange,
  onReasonChange,
  onSubmit
}: Props) {
  const selectedUser = users.find(
    (user) => user.id === selectedAccountId
  );

  return (
    <form className="admin-record" onSubmit={onSubmit}>
      <h3>Lantik Super Admin daripada Akaun USER</h3>
      <p>
        Pilih akaun pengguna sedia ada. Sistem akan menukar peranan
        akaun tersebut tanpa mencipta akaun atau ID baharu.
      </p>

      <div className="admin-record__details">
        <label>
          Cari Pengguna
          <input
            type="search"
            value={search}
            placeholder="Nama, nombor telefon atau e-mel"
            disabled={loading || submitting}
            onChange={(event) => onSearchChange(event.target.value)}
          />
        </label>

        <div className="admin-record__actions">
          <button
            type="button"
            className="admin-button"
            disabled={loading || submitting}
            onClick={onSearch}
          >
            {loading ? 'Memuatkan...' : 'Cari Pengguna'}
          </button>
        </div>

        <label>
          Pilih Akaun USER
          <select
            value={selectedAccountId}
            required
            disabled={loading || submitting}
            onChange={(event) => onAccountChange(event.target.value)}
          >
            <option value="">Pilih pengguna</option>
            {users.map((user) => (
              <option key={user.id} value={user.id}>
                {user.profile_name} — {user.phone || user.email || user.id}
              </option>
            ))}
          </select>
        </label>
      </div>

      {selectedUser && (
        <div className="admin-record__details">
          <label>
            Nama Paparan
            <input value={selectedUser.profile_name} readOnly />
          </label>

          <label>
            Nombor Telefon
            <input value={selectedUser.phone ?? 'Tiada'} readOnly />
          </label>

          <label>
            E-mel
            <input value={selectedUser.email ?? 'Tiada'} readOnly />
          </label>

          <label>
            ID Akaun
            <input value={selectedUser.id} readOnly />
          </label>
        </div>
      )}

      <label>
        Jawatan Super Admin
        <input
          type="text"
          value={jobTitle}
          maxLength={100}
          required
          disabled={submitting}
          placeholder="Contoh: Pengurus Operasi"
          onChange={(event) => onJobTitleChange(event.target.value)}
        />
      </label>

      <label>
        Sebab Pelantikan
        <textarea
          rows={3}
          maxLength={1000}
          value={reason}
          required
          disabled={submitting}
          onChange={(event) => onReasonChange(event.target.value)}
        />
      </label>

      <div className="admin-record__actions">
        <button
          type="submit"
          className="admin-button admin-button--primary"
          disabled={submitting || loading || !selectedAccountId}
        >
          {submitting ? 'Memproses Pelantikan...' : 'Lantik sebagai Super Admin'}
        </button>
      </div>
    </form>
  );
}
