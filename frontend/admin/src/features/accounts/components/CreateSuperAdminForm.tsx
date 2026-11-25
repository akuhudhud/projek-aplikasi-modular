
import type { FormEvent } from 'react';
import type { CreateSuperAdminPayload } from '../../../types/admin';

interface Props {
  form: CreateSuperAdminPayload;
  creating: boolean;
  onChange: (field: keyof CreateSuperAdminPayload, value: string) => void;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

export default function CreateSuperAdminForm({
  form,
  creating,
  onChange,
  onSubmit
}: Props) {
  return (
    <form className="admin-record" onSubmit={onSubmit}>
      <h3>Daftar Super Admin Baharu</h3>

      <div className="admin-record__details">
        <label>
          Nama Paparan
          <input
            type="text"
            value={form.profile_name}
            maxLength={100}
            required
            disabled={creating}
            onChange={(event) => onChange('profile_name', event.target.value)}
          />
        </label>

        <label>
          Jawatan
          <input
            type="text"
            value={form.job_title}
            maxLength={100}
            required
            disabled={creating}
            onChange={(event) => onChange('job_title', event.target.value)}
          />
        </label>

        <label>
          Nombor Telefon
          <input
            type="tel"
            value={form.phone}
            required
            disabled={creating}
            onChange={(event) => onChange('phone', event.target.value)}
          />
        </label>

        <label>
          E-mel
          <input
            type="email"
            value={form.email}
            required
            disabled={creating}
            onChange={(event) => onChange('email', event.target.value)}
          />
        </label>

        <label>
          Kata Laluan
          <input
            type="password"
            value={form.password}
            minLength={8}
            required
            disabled={creating}
            autoComplete="new-password"
            onChange={(event) => onChange('password', event.target.value)}
          />
        </label>

        <label>
          Sahkan Kata Laluan
          <input
            type="password"
            value={form.password_confirmation}
            minLength={8}
            required
            disabled={creating}
            autoComplete="new-password"
            onChange={(event) =>
              onChange('password_confirmation', event.target.value)
            }
          />
        </label>
      </div>

      <label>
        Sebab Pelantikan
        <textarea
          rows={3}
          maxLength={1000}
          value={form.reason}
          required
          disabled={creating}
          onChange={(event) => onChange('reason', event.target.value)}
        />
      </label>

      <div className="admin-record__actions">
        <button
          type="submit"
          className="admin-button admin-button--primary"
          disabled={creating}
        >
          {creating ? 'Mewujudkan Akaun...' : 'Daftar Super Admin'}
        </button>
      </div>
    </form>
  );
}
