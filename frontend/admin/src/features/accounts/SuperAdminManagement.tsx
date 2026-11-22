
import { useState } from 'react';
import type { FormEvent } from 'react';

import {
  createSuperAdmin,
  getApiError,
  getSuperAdminPermissions,
  updateSuperAdminJobTitle,
  updateSuperAdminPermissions
} from '../../services/adminApi';

import type {
  AdminAccount,
  AdminPermission,
  CreateSuperAdminPayload
} from '../../types/admin';

interface SuperAdminManagementProps {
  accounts: AdminAccount[];
  onRefresh: () => Promise<void>;
}

const EMPTY_FORM: CreateSuperAdminPayload = {
  profile_name: '',
  job_title: '',
  phone: '',
  email: '',
  password: '',
  password_confirmation: '',
  reason: ''
};

export default function SuperAdminManagement({
  accounts,
  onRefresh
}: SuperAdminManagementProps) {
  const [showCreate, setShowCreate] = useState(false);
  const [creating, setCreating] = useState(false);
  const [updating, setUpdating] = useState(false);

  const [form, setForm] = useState<CreateSuperAdminPayload>({
    ...EMPTY_FORM
  });

  const [selectedId, setSelectedId] = useState('');
  const [permissions, setPermissions] = useState<string[]>([]);
  const [catalog, setCatalog] = useState<AdminPermission[]>([]);
  const [jobTitle, setJobTitle] = useState('');
  const [reason, setReason] = useState('');

  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const superAdmins = accounts.filter(
    (account) => account.role === 'SUPER_ADMIN'
  );

  function updateForm(
    field: keyof CreateSuperAdminPayload,
    value: string
  ) {
    setForm((current) => ({
      ...current,
      [field]: value
    }));
  }

  async function submitCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (creating) return;

    setCreating(true);
    setError('');
    setSuccess('');

    try {
      await createSuperAdmin({
        ...form,
        profile_name: form.profile_name.trim(),
        job_title: form.job_title.trim(),
        phone: form.phone.trim(),
        email: form.email.trim(),
        reason: form.reason.trim()
      });

      setForm({ ...EMPTY_FORM });
      setShowCreate(false);
      setSuccess('Akaun Super Admin berjaya diwujudkan.');

      await onRefresh();
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setCreating(false);
    }
  }

  async function selectSuperAdmin(accountId: string) {
    setSelectedId(accountId);
    setError('');
    setSuccess('');
    setPermissions([]);
    setCatalog([]);
    setReason('');

    const selected = superAdmins.find(
      (item) => item.id === accountId
    );

    setJobTitle(selected?.job_title ?? '');

    if (!accountId) return;

    setUpdating(true);

    try {
      const result = await getSuperAdminPermissions(accountId);

      setCatalog(result.catalog);
      setPermissions(result.permissions);
      setJobTitle(result.account.job_title ?? '');
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setUpdating(false);
    }
  }

  function togglePermission(key: string) {
    setPermissions((current) =>
      current.includes(key)
        ? current.filter((item) => item !== key)
        : [...current, key]
    );
  }

  async function saveJobTitle() {
    if (!selectedId || updating || !reason.trim()) return;

    setUpdating(true);
    setError('');
    setSuccess('');

    try {
      await updateSuperAdminJobTitle(
        selectedId,
        jobTitle.trim(),
        reason.trim()
      );

      setSuccess('Jawatan Super Admin berjaya dikemas kini.');
      setReason('');

      await onRefresh();
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setUpdating(false);
    }
  }

  async function savePermissions() {
    if (!selectedId || updating || !reason.trim()) return;

    setUpdating(true);
    setError('');
    setSuccess('');

    try {
      await updateSuperAdminPermissions(
        selectedId,
        permissions,
        reason.trim()
      );

      setSuccess('Permission Super Admin berjaya dikemas kini.');
      setReason('');
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setUpdating(false);
    }
  }

  return (
    <section className="account-detail">
      <div className="admin-page__heading">
        <div>
          <span className="dashboard-page__eyebrow">
            ROOT SUPER ADMIN
          </span>
          <h3>Pengurusan Super Admin</h3>
          <p>
            Pelantikan, jawatan dan permission pentadbir.
          </p>
        </div>

        <button
          type="button"
          className="admin-button admin-button--primary"
          onClick={() => {
            setShowCreate((current) => !current);
            setError('');
            setSuccess('');
          }}
        >
          {showCreate ? 'Tutup Borang' : 'Tambah Super Admin'}
        </button>
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

      {showCreate && (
        <form className="admin-record" onSubmit={submitCreate}>
          <h3>Pelantikan Super Admin Baharu</h3>

          <label>
            Nama Paparan
            <input
              required
              maxLength={255}
              value={form.profile_name}
              onChange={(event) =>
                updateForm('profile_name', event.target.value)
              }
            />
          </label>

          <label>
            Jawatan
            <input
              maxLength={100}
              value={form.job_title}
              onChange={(event) =>
                updateForm('job_title', event.target.value)
              }
            />
          </label>

          <label>
            Nombor Telefon
            <input
              type="tel"
              value={form.phone}
              onChange={(event) =>
                updateForm('phone', event.target.value)
              }
              placeholder="Wajib isi telefon atau e-mel"
            />
          </label>

          <label>
            E-mel
            <input
              type="email"
              value={form.email}
              onChange={(event) =>
                updateForm('email', event.target.value)
              }
              placeholder="Wajib isi telefon atau e-mel"
            />
          </label>

          <label>
            Kata Laluan
            <input
              type="password"
              required
              minLength={8}
              maxLength={12}
              value={form.password}
              onChange={(event) =>
                updateForm('password', event.target.value)
              }
            />
          </label>

          <label>
            Sahkan Kata Laluan
            <input
              type="password"
              required
              minLength={8}
              maxLength={12}
              value={form.password_confirmation}
              onChange={(event) =>
                updateForm('password_confirmation', event.target.value)
              }
            />
          </label>

          <label>
            Sebab Pelantikan
            <textarea
              required
              maxLength={1000}
              rows={3}
              value={form.reason}
              onChange={(event) =>
                updateForm('reason', event.target.value)
              }
            />
          </label>

          <div className="admin-record__actions">
            <button
              type="submit"
              className="admin-button admin-button--primary"
              disabled={
                creating ||
                !form.profile_name.trim() ||
                (!form.phone.trim() && !form.email.trim()) ||
                !form.reason.trim()
              }
            >
              {creating ? 'Menyimpan...' : 'Sahkan Pelantikan'}
            </button>
          </div>
        </form>
      )}

      <div className="admin-record">
        <h3>Senarai Super Admin</h3>

        {superAdmins.length === 0 ? (
          <p>Tiada akaun Super Admin ditemui.</p>
        ) : (
          <label>
            Pilih Super Admin
            <select
              value={selectedId}
              onChange={(event) =>
                void selectSuperAdmin(event.target.value)
              }
            >
              <option value="">Pilih akaun</option>
              {superAdmins.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.profile_name}
                  {item.job_title ? ` — ${item.job_title}` : ''}
                </option>
              ))}
            </select>
          </label>
        )}
      </div>

      {selectedId && (
        <div className="admin-record">
          <h3>Kemas Kini Jawatan</h3>

          <label>
            Jawatan
            <input
              maxLength={100}
              value={jobTitle}
              onChange={(event) => setJobTitle(event.target.value)}
            />
          </label>

          <label>
            Sebab Perubahan
            <textarea
              rows={3}
              maxLength={1000}
              value={reason}
              onChange={(event) => setReason(event.target.value)}
            />
          </label>

          <button
            type="button"
            className="admin-button admin-button--primary"
            disabled={updating || !reason.trim()}
            onClick={() => void saveJobTitle()}
          >
            Simpan Jawatan
          </button>
        </div>
      )}

      {selectedId && (
        <div className="admin-record">
          <h3>Pengurusan Permission</h3>

          {updating && catalog.length === 0 ? (
            <p>Memuatkan permission...</p>
          ) : (
            <div className="admin-permission-list">
              {catalog.map((permission) => (
                <label
                  key={permission.key}
                  className="admin-permission-item"
                >
                  <input
                    type="checkbox"
                    checked={permissions.includes(permission.key)}
                    onChange={() => togglePermission(permission.key)}
                    disabled={updating}
                  />

                  <span>
                    <strong>{permission.label}</strong>
                    <small>{permission.key}</small>
                  </span>
                </label>
              ))}
            </div>
          )}

          <label>
            Sebab Perubahan Permission
            <textarea
              rows={3}
              maxLength={1000}
              value={reason}
              onChange={(event) => setReason(event.target.value)}
            />
          </label>

          <button
            type="button"
            className="admin-button admin-button--primary"
            disabled={
              updating ||
              catalog.length === 0 ||
              !reason.trim()
            }
            onClick={() => void savePermissions()}
          >
            Simpan Permission
          </button>
        </div>
      )}
    </section>
  );
}
