import type { AdminPermission } from '../../../types/admin';

interface Props {
  permissions: string[];
  catalog: AdminPermission[];
  permissionReason: string;
  updating: boolean;
  onToggle: (key: string) => void;
  onReasonChange: (value: string) => void;
  onSave: () => void;
}

export default function SuperAdminPermissionEditor({
  permissions,
  catalog,
  permissionReason,
  updating,
  onToggle,
  onReasonChange,
  onSave
}: Props) {
  return (
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
                onChange={() => onToggle(permission.key)}
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
          value={permissionReason}
          disabled={updating}
          onChange={(event) => onReasonChange(event.target.value)}
        />
      </label>

      <button
        type="button"
        className="admin-button admin-button--primary"
        disabled={updating || catalog.length === 0 || !permissionReason.trim()}
        onClick={onSave}
      >
        Simpan Permission
      </button>
    </div>
  );
}
