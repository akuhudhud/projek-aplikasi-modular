import { useCallback, useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';

import {
  createSuperAdmin,
  getAccounts,
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

import CreateSuperAdminForm from './components/CreateSuperAdminForm';
import SuperAdminPermissionEditor from './components/SuperAdminPermissionEditor';
import SuperAdminJobTitleEditor from './components/SuperAdminJobTitleEditor';

import './SuperAdminManagement.css';

interface SuperAdminManagementProps {
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
  onRefresh
}: SuperAdminManagementProps) {
  const [accounts, setAccounts] = useState<AdminAccount[]>([]);
  const [showCreate, setShowCreate] = useState(false);
  const [creating, setCreating] = useState(false);
  const [updating, setUpdating] = useState(false);
  const [loadingAccounts, setLoadingAccounts] = useState(true);

  const [form, setForm] = useState<CreateSuperAdminPayload>({
    ...EMPTY_FORM
  });

  const [selectedId, setSelectedId] = useState('');
  const [permissions, setPermissions] = useState<string[]>([]);
  const [catalog, setCatalog] = useState<AdminPermission[]>([]);
  const [jobTitle, setJobTitle] = useState('');
  const [jobTitleReason, setJobTitleReason] = useState('');
  const [permissionReason, setPermissionReason] = useState('');

  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const mountedRef = useRef(false);
  const accountListRequestId = useRef(0);
  const permissionRequestId = useRef(0);
  const creatingRef = useRef(false);
  const operationRef = useRef(false);

  useEffect(() => {
    mountedRef.current = true;

    return () => {
      mountedRef.current = false;
      accountListRequestId.current += 1;
      permissionRequestId.current += 1;
    };
  }, []);

  const loadSuperAdmins = useCallback(async () => {
    const requestId = ++accountListRequestId.current;

    if (mountedRef.current) {
      setLoadingAccounts(true);
    }

    try {
      const result = await getAccounts({
        role: 'SUPER_ADMIN',
        page: 1,
        per_page: 100
      });

      if (
        !mountedRef.current ||
        requestId !== accountListRequestId.current
      ) return;

      setAccounts(result.accounts);
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
        setLoadingAccounts(false);
      }
    }
  }, []);

  useEffect(() => {
    void loadSuperAdmins();
  }, [loadSuperAdmins]);

  function updateForm(field: keyof CreateSuperAdminPayload, value: string) {
    setForm((current) => ({
      ...current,
      [field]: value
    }));
  }

  async function refreshAll() {
    await Promise.all([loadSuperAdmins(), onRefresh()]);
  }

  async function submitCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (creatingRef.current || !mountedRef.current) return;

    creatingRef.current = true;
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

      if (!mountedRef.current) return;

      setForm({ ...EMPTY_FORM });
      setShowCreate(false);
      setSuccess('Akaun Super Admin berjaya diwujudkan.');

      await refreshAll();
    } catch (err) {
      if (mountedRef.current) setError(getApiError(err));
    } finally {
      creatingRef.current = false;

      if (mountedRef.current) setCreating(false);
    }
  }

  async function selectSuperAdmin(accountId: string) {
    if (operationRef.current || !mountedRef.current) return;

    const requestId = ++permissionRequestId.current;

    setSelectedId(accountId);
    setError('');
    setSuccess('');
    setPermissions([]);
    setCatalog([]);
    setJobTitleReason('');
    setPermissionReason('');

    const selected = accounts.find((item) => item.id === accountId);
    setJobTitle(selected?.job_title ?? '');

    if (!accountId) {
      setUpdating(false);
      return;
    }

    setUpdating(true);

    try {
      const result = await getSuperAdminPermissions(accountId);

      if (
        !mountedRef.current ||
        requestId !== permissionRequestId.current
      ) return;

      setCatalog(result.catalog);
      setPermissions(result.permissions);
      setJobTitle(result.account.job_title ?? '');
    } catch (err) {
      if (
        mountedRef.current &&
        requestId === permissionRequestId.current
      ) {
        setError(getApiError(err));
      }
    } finally {
      if (
        mountedRef.current &&
        requestId === permissionRequestId.current
      ) {
        setUpdating(false);
      }
    }
  }

  function togglePermission(key: string) {
    if (updating || operationRef.current) return;

    setPermissions((current) =>
      current.includes(key)
        ? current.filter((item) => item !== key)
        : [...current, key]
    );
  }

  async function saveJobTitle() {
    const targetId = selectedId;

    if (
      !targetId ||
      updating ||
      operationRef.current ||
      !jobTitleReason.trim() ||
      !mountedRef.current
    ) return;

    operationRef.current = true;
    setUpdating(true);
    setError('');
    setSuccess('');

    try {
      await updateSuperAdminJobTitle(
        targetId,
        jobTitle.trim(),
        jobTitleReason.trim()
      );

      if (!mountedRef.current) return;

      setSuccess('Jawatan Super Admin berjaya dikemas kini.');
      setJobTitleReason('');

      await refreshAll();
    } catch (err) {
      if (mountedRef.current) setError(getApiError(err));
    } finally {
      operationRef.current = false;

      if (mountedRef.current) setUpdating(false);
    }
  }

  async function savePermissions() {
    const targetId = selectedId;
    const selectedPermissions = [...permissions];

    if (
      !targetId ||
      updating ||
      operationRef.current ||
      !permissionReason.trim() ||
      catalog.length === 0 ||
      !mountedRef.current
    ) return;

    operationRef.current = true;
    setUpdating(true);
    setError('');
    setSuccess('');

    try {
      await updateSuperAdminPermissions(
        targetId,
        selectedPermissions,
        permissionReason.trim()
      );

      if (!mountedRef.current) return;

      setSuccess('Permission Super Admin berjaya dikemas kini.');
      setPermissionReason('');
    } catch (err) {
      if (mountedRef.current) setError(getApiError(err));
    } finally {
      operationRef.current = false;

      if (mountedRef.current) setUpdating(false);
    }
  }

  return (
    <section className="account-detail super-admin-management">
      <div className="admin-page__heading">
        <div>
          <span className="dashboard-page__eyebrow">ROOT SUPER ADMIN</span>
          <h3>Pengurusan Super Admin</h3>
          <p>Pelantikan, jawatan dan permission pentadbir.</p>
        </div>

        <button
          type="button"
          className="admin-button admin-button--primary"
          disabled={creating}
          onClick={() => {
            if (creating) return;

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
        <CreateSuperAdminForm
          form={form}
          creating={creating}
          onChange={updateForm}
          onSubmit={(event) => void submitCreate(event)}
        />
      )}

      <div className="admin-record">
        <h3>Senarai Super Admin</h3>

        {loadingAccounts ? (
          <p>Memuatkan senarai Super Admin...</p>
        ) : accounts.length === 0 ? (
          <p>Tiada akaun Super Admin ditemui.</p>
        ) : (
          <label>
            Pilih Super Admin
            <select
              value={selectedId}
              disabled={updating || creating}
              onChange={(event) => void selectSuperAdmin(event.target.value)}
            >
              <option value="">Pilih akaun</option>
              {accounts.map((item) => (
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
        <>
          <SuperAdminJobTitleEditor
            jobTitle={jobTitle}
            reason={jobTitleReason}
            updating={updating}
            onJobTitleChange={setJobTitle}
            onReasonChange={setJobTitleReason}
            onSave={() => void saveJobTitle()}
          />

          <SuperAdminPermissionEditor
            permissions={permissions}
            catalog={catalog}
            permissionReason={permissionReason}
            updating={updating}
            onToggle={togglePermission}
            onReasonChange={setPermissionReason}
            onSave={() => void savePermissions()}
          />
        </>
      )}
    </section>
  );
}
