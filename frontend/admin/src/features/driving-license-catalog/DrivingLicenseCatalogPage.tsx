
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';

import {
  createDrivingLicenseType,
  updateDrivingLicenseType,
  deactivateDrivingLicenseType,
  getDrivingLicenseTypes,
  createDrivingLicenseClass,
  updateDrivingLicenseClass,
  deactivateDrivingLicenseClass,
  getDrivingLicenseClasses,
  getApiError
} from '../../services/adminApi';

import type {
  DrivingLicenseType,
  DrivingLicenseClass
} from '../../types/drivingLicense';

import type { AdminProfile } from '../../types/admin';

interface DrivingLicenseCatalogPageProps {
  account: AdminProfile;
}

interface TypeForm {
  code: string;
  name: string;
  description: string;
  sort_order: string;
}

interface ClassForm {
  driving_license_type_id: string;
  code: string;
  name: string;
  description: string;
  sort_order: string;
}

const EMPTY_TYPE_FORM: TypeForm = {
  code: '',
  name: '',
  description: '',
  sort_order: '0'
};

const EMPTY_CLASS_FORM: ClassForm = {
  driving_license_type_id: '',
  code: '',
  name: '',
  description: '',
  sort_order: '0'
};

export default function DrivingLicenseCatalogPage({
  account
}: DrivingLicenseCatalogPageProps) {
  const isRoot = account.role === 'ROOT_SUPER_ADMIN';

  const canManageTypes =
    isRoot ||
    account.permissions.includes('driving_license_types.manage');

  const canManageClasses =
    isRoot ||
    account.permissions.includes('driving_license_catalog.manage');

  const [types, setTypes] = useState<DrivingLicenseType[]>([]);
  const [classes, setClasses] = useState<DrivingLicenseClass[]>([]);

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const [editingType, setEditingType] =
    useState<DrivingLicenseType | null>(null);

  const [editingClass, setEditingClass] =
    useState<DrivingLicenseClass | null>(null);

  const [typeForm, setTypeForm] =
    useState<TypeForm>(EMPTY_TYPE_FORM);

  const [classForm, setClassForm] =
    useState<ClassForm>(EMPTY_CLASS_FORM);

  async function loadCatalog() {
    setLoading(true);
    setError('');

    try {
      const [typeResults, classResults] = await Promise.all([
        canManageTypes
          ? getDrivingLicenseTypes()
          : Promise.resolve([]),

        canManageClasses
          ? getDrivingLicenseClasses()
          : Promise.resolve([])
      ]);

      setTypes(typeResults);
      setClasses(classResults);
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    let mounted = true;

    async function load() {
      setLoading(true);
      setError('');

      try {
        const [typeResults, classResults] = await Promise.all([
          canManageTypes
            ? getDrivingLicenseTypes()
            : Promise.resolve([]),

          canManageClasses
            ? getDrivingLicenseClasses()
            : Promise.resolve([])
        ]);

        if (!mounted) return;

        setTypes(typeResults);
        setClasses(classResults);
      } catch (err) {
        if (mounted) {
          setError(getApiError(err));
        }
      } finally {
        if (mounted) {
          setLoading(false);
        }
      }
    }

    void load();

    return () => {
      mounted = false;
    };
  }, [canManageTypes, canManageClasses]);

  function resetTypeForm() {
    setEditingType(null);
    setTypeForm(EMPTY_TYPE_FORM);
  }

  function resetClassForm() {
    setEditingClass(null);
    setClassForm(EMPTY_CLASS_FORM);
  }

  function editType(type: DrivingLicenseType) {
    setEditingType(type);
    setTypeForm({
      code: type.code,
      name: type.name,
      description: type.description ?? '',
      sort_order: String(type.sort_order)
    });

    setError('');
    setSuccess('');
  }

  function editClass(licenseClass: DrivingLicenseClass) {
    setEditingClass(licenseClass);
    setClassForm({
      driving_license_type_id: licenseClass.driving_license_type_id,
      code: licenseClass.code,
      name: licenseClass.name,
      description: licenseClass.description ?? '',
      sort_order: String(licenseClass.sort_order)
    });

    setError('');
    setSuccess('');
  }

  async function submitType(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!canManageTypes || saving) return;

    setSaving(true);
    setError('');
    setSuccess('');

    try {
      const payload = {
        code: typeForm.code.trim().toUpperCase(),
        name: typeForm.name.trim(),
        description: typeForm.description.trim() || null,
        sort_order: Number(typeForm.sort_order) || 0
      };

      if (editingType) {
        await updateDrivingLicenseType(editingType.id, payload);
        setSuccess('Jenis lesen berjaya dikemaskini.');
      } else {
        await createDrivingLicenseType(payload);
        setSuccess('Jenis lesen berjaya ditambah.');
      }

      resetTypeForm();
      await loadCatalog();
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setSaving(false);
    }
  }

  async function submitClass(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!canManageClasses || saving) return;

    setSaving(true);
    setError('');
    setSuccess('');

    try {
      const payload = {
        driving_license_type_id: classForm.driving_license_type_id,
        code: classForm.code.trim().toUpperCase(),
        name: classForm.name.trim(),
        description: classForm.description.trim() || null,
        sort_order: Number(classForm.sort_order) || 0
      };

      if (editingClass) {
        await updateDrivingLicenseClass(editingClass.id, payload);
        setSuccess('Kelas lesen berjaya dikemaskini.');
      } else {
        await createDrivingLicenseClass(payload);
        setSuccess('Kelas lesen berjaya ditambah.');
      }

      resetClassForm();
      await loadCatalog();
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setSaving(false);
    }
  }

  async function toggleTypeStatus(type: DrivingLicenseType) {
    if (!canManageTypes || saving) return;

    const action = type.is_active ? 'nyahaktifkan' : 'aktifkan';

    if (!window.confirm(
      `Adakah anda pasti mahu ${action} jenis lesen ${type.name}?`
    )) {
      return;
    }

    setSaving(true);
    setError('');
    setSuccess('');

    try {
      if (type.is_active) {
        await deactivateDrivingLicenseType(type.id);
      } else {
        await updateDrivingLicenseType(type.id, {
          is_active: true
        });
      }

      setSuccess(
        type.is_active
          ? 'Jenis lesen berjaya dinyahaktifkan.'
          : 'Jenis lesen berjaya diaktifkan.'
      );

      await loadCatalog();
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setSaving(false);
    }
  }

  async function toggleClassStatus(
    licenseClass: DrivingLicenseClass
  ) {
    if (!canManageClasses || saving) return;

    const action = licenseClass.is_active
      ? 'nyahaktifkan'
      : 'aktifkan';

    if (!window.confirm(
      `Adakah anda pasti mahu ${action} kelas lesen ${licenseClass.code}?`
    )) {
      return;
    }

    setSaving(true);
    setError('');
    setSuccess('');

    try {
      if (licenseClass.is_active) {
        await deactivateDrivingLicenseClass(licenseClass.id);
      } else {
        await updateDrivingLicenseClass(licenseClass.id, {
          is_active: true
        });
      }

      setSuccess(
        licenseClass.is_active
          ? 'Kelas lesen berjaya dinyahaktifkan.'
          : 'Kelas lesen berjaya diaktifkan.'
      );

      await loadCatalog();
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setSaving(false);
    }
  }

  const activeTypes = types.filter((type) => type.is_active);

  const inputStyle = {
    width: '100%',
    minHeight: '40px',
    padding: '10px 12px',
    border: '1px solid var(--admin-border)',
    borderRadius: '6px',
    outline: 'none',
    background: '#111',
    color: 'var(--admin-text)'
  };

  const fieldStyle = {
    display: 'grid',
    gap: '7px',
    color: 'var(--admin-muted)',
    fontSize: '11px',
    fontWeight: 700
  };

  return (
    <section className="admin-page">
      <div className="admin-page__heading">
        <div>
          <span className="dashboard-page__eyebrow">
            KONFIGURASI SISTEM
          </span>
          <h2>Katalog Lesen Memandu</h2>
          <p>
            Pengurusan jenis lesen dan kelas lesen yang digunakan
            dalam permohonan Runner.
          </p>
        </div>

        <button
          type="button"
          className="admin-button"
          onClick={() => void loadCatalog()}
          disabled={loading || saving}
        >
          Muat Semula
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

      {loading ? (
        <div className="admin-state" role="status">
          Memuatkan katalog lesen...
        </div>
      ) : (
        <>
          {canManageTypes && (
            <section className="admin-record">
              <div className="admin-page__heading">
                <div>
                  <h3>Jenis Lesen</h3>
                  <p>Tambah dan urus kategori jenis lesen memandu.</p>
                </div>
                <span className="admin-status">
                  {types.length} rekod
                </span>
              </div>

              <form
                onSubmit={(event) => void submitType(event)}
                style={{ display: 'grid', gap: '13px' }}
              >
                <div
                  style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))',
                    gap: '12px'
                  }}
                >
                  <label style={fieldStyle}>
                    Kod jenis lesen
                    <input
                      style={inputStyle}
                      value={typeForm.code}
                      onChange={(event) =>
                        setTypeForm({
                          ...typeForm,
                          code: event.target.value
                        })
                      }
                      placeholder="Contoh: CDL"
                      required
                    />
                  </label>

                  <label style={fieldStyle}>
                    Nama jenis lesen
                    <input
                      style={inputStyle}
                      value={typeForm.name}
                      onChange={(event) =>
                        setTypeForm({
                          ...typeForm,
                          name: event.target.value
                        })
                      }
                      placeholder="Nama jenis lesen"
                      required
                    />
                  </label>

                  <label style={fieldStyle}>
                    Susunan paparan
                    <input
                      style={inputStyle}
                      type="number"
                      value={typeForm.sort_order}
                      onChange={(event) =>
                        setTypeForm({
                          ...typeForm,
                          sort_order: event.target.value
                        })
                      }
                    />
                  </label>
                </div>

                <label style={fieldStyle}>
                  Penerangan
                  <textarea
                    style={{ ...inputStyle, minHeight: '75px' }}
                    value={typeForm.description}
                    onChange={(event) =>
                      setTypeForm({
                        ...typeForm,
                        description: event.target.value
                      })
                    }
                  />
                </label>

                <div className="admin-record__actions">
                  <button
                    type="submit"
                    className="admin-button admin-button--primary"
                    disabled={saving}
                  >
                    {saving
                      ? 'Menyimpan...'
                      : editingType
                        ? 'Simpan Perubahan'
                        : 'Tambah Jenis Lesen'}
                  </button>

                  {editingType && (
                    <button
                      type="button"
                      className="admin-button"
                      onClick={resetTypeForm}
                      disabled={saving}
                    >
                      Batal
                    </button>
                  )}
                </div>
              </form>

              <div className="admin-table-wrap">
                <table className="admin-table">
                  <thead>
                    <tr>
                      <th>Kod</th>
                      <th>Nama Jenis</th>
                      <th>Kelas</th>
                      <th>Status</th>
                      <th>Tindakan</th>
                    </tr>
                  </thead>
                  <tbody>
                    {types.length === 0 ? (
                      <tr>
                        <td colSpan={5}>Tiada jenis lesen direkodkan.</td>
                      </tr>
                    ) : (
                      types.map((type) => (
                        <tr key={type.id}>
                          <td>{type.code}</td>
                          <td>{type.name}</td>
                          <td>{type.classes_count ?? '—'}</td>
                          <td>
                            <span className="admin-status">
                              {type.is_active ? 'Aktif' : 'Tidak Aktif'}
                            </span>
                          </td>
                          <td>
                            <div className="admin-record__actions">
                              <button
                                type="button"
                                className="admin-button admin-button--small"
                                onClick={() => editType(type)}
                                disabled={saving}
                              >
                                Edit
                              </button>

                              <button
                                type="button"
                                className={[
                                  'admin-button',
                                  'admin-button--small',
                                  type.is_active
                                    ? 'admin-button--danger'
                                    : ''
                                ].join(' ')}
                                onClick={() => void toggleTypeStatus(type)}
                                disabled={saving}
                              >
                                {type.is_active ? 'Nyahaktif' : 'Aktifkan'}
                              </button>
                            </div>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </section>
          )}

          {canManageClasses && (
            <section className="admin-record">
              <div className="admin-page__heading">
                <div>
                  <h3>Kelas Lesen</h3>
                  <p>
                    Urus kelas lesen di bawah jenis lesen yang berkaitan.
                  </p>
                </div>
                <span className="admin-status">
                  {classes.length} rekod
                </span>
              </div>

              <form
                onSubmit={(event) => void submitClass(event)}
                style={{ display: 'grid', gap: '13px' }}
              >
                <div
                  style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))',
                    gap: '12px'
                  }}
                >
                  <label style={fieldStyle}>
                    Jenis lesen
                    <select
                      style={inputStyle}
                      value={classForm.driving_license_type_id}
                      onChange={(event) =>
                        setClassForm({
                          ...classForm,
                          driving_license_type_id: event.target.value
                        })
                      }
                      required
                    >
                      <option value="">Pilih jenis lesen</option>
                      {activeTypes.map((type) => (
                        <option key={type.id} value={type.id}>
                          {type.code} — {type.name}
                        </option>
                      ))}
                    </select>
                  </label>

                  <label style={fieldStyle}>
                    Kod kelas
                    <input
                      style={inputStyle}
                      value={classForm.code}
                      onChange={(event) =>
                        setClassForm({
                          ...classForm,
                          code: event.target.value
                        })
                      }
                      placeholder="Contoh: B2"
                      required
                    />
                  </label>

                  <label style={fieldStyle}>
                    Nama kelas
                    <input
                      style={inputStyle}
                      value={classForm.name}
                      onChange={(event) =>
                        setClassForm({
                          ...classForm,
                          name: event.target.value
                        })
                      }
                      placeholder="Nama kelas lesen"
                      required
                    />
                  </label>

                  <label style={fieldStyle}>
                    Susunan paparan
                    <input
                      style={inputStyle}
                      type="number"
                      value={classForm.sort_order}
                      onChange={(event) =>
                        setClassForm({
                          ...classForm,
                          sort_order: event.target.value
                        })
                      }
                    />
                  </label>
                </div>

                <label style={fieldStyle}>
                  Penerangan
                  <textarea
                    style={{ ...inputStyle, minHeight: '75px' }}
                    value={classForm.description}
                    onChange={(event) =>
                      setClassForm({
                        ...classForm,
                        description: event.target.value
                      })
                    }
                  />
                </label>

                <div className="admin-record__actions">
                  <button
                    type="submit"
                    className="admin-button admin-button--primary"
                    disabled={
                      saving ||
                      activeTypes.length === 0
                    }
                  >
                    {saving
                      ? 'Menyimpan...'
                      : editingClass
                        ? 'Simpan Perubahan'
                        : 'Tambah Kelas Lesen'}
                  </button>

                  {editingClass && (
                    <button
                      type="button"
                      className="admin-button"
                      onClick={resetClassForm}
                      disabled={saving}
                    >
                      Batal
                    </button>
                  )}
                </div>

                {activeTypes.length === 0 && (
                  <p className="admin-page__footnote">
                    Tiada jenis lesen aktif. Aktifkan atau tambah jenis
                    lesen sebelum mendaftarkan kelas.
                  </p>
                )}
              </form>

              <div className="admin-table-wrap">
                <table className="admin-table">
                  <thead>
                    <tr>
                      <th>Jenis Lesen</th>
                      <th>Kod Kelas</th>
                      <th>Nama Kelas</th>
                      <th>Status</th>
                      <th>Tindakan</th>
                    </tr>
                  </thead>
                  <tbody>
                    {classes.length === 0 ? (
                      <tr>
                        <td colSpan={5}>Tiada kelas lesen direkodkan.</td>
                      </tr>
                    ) : (
                      classes.map((licenseClass) => (
                        <tr key={licenseClass.id}>
                          <td>
                            {licenseClass.license_type?.name ??
                              licenseClass.driving_license_type_id}
                          </td>
                          <td>{licenseClass.code}</td>
                          <td>{licenseClass.name}</td>
                          <td>
                            <span className="admin-status">
                              {licenseClass.is_active
                                ? 'Aktif'
                                : 'Tidak Aktif'}
                            </span>
                          </td>
                          <td>
                            <div className="admin-record__actions">
                              <button
                                type="button"
                                className="admin-button admin-button--small"
                                onClick={() => editClass(licenseClass)}
                                disabled={saving}
                              >
                                Edit
                              </button>

                              <button
                                type="button"
                                className={[
                                  'admin-button',
                                  'admin-button--small',
                                  licenseClass.is_active
                                    ? 'admin-button--danger'
                                    : ''
                                ].join(' ')}
                                onClick={() =>
                                  void toggleClassStatus(licenseClass)
                                }
                                disabled={saving}
                              >
                                {licenseClass.is_active
                                  ? 'Nyahaktif'
                                  : 'Aktifkan'}
                              </button>
                            </div>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </section>
          )}

          {!canManageTypes && !canManageClasses && (
            <div className="admin-state">
              Akaun ini tidak mempunyai kebenaran mengurus katalog lesen.
            </div>
          )}
        </>
      )}

      <p className="admin-page__footnote">
        Rekod lesen yang dinyahaktifkan tidak dipadam. Sejarah lesen
        Runner dikekalkan. Perubahan kod atau jenis yang telah digunakan
        mungkin ditolak oleh sistem bagi melindungi rekod sejarah.
      </p>
    </section>
  );
}
