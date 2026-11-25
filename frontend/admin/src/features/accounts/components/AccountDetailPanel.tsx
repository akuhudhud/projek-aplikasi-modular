import type { FormEvent } from 'react';
import type { AdminAccountDetail } from '../../../types/admin';

export type AccountAction = 'suspend' | 'unsuspend' | 'reactivate';

export const ACTION_LABELS: Record<AccountAction, string> = {
  suspend: 'Gantung Akaun',
  unsuspend: 'Pulihkan Akaun',
  reactivate: 'Aktifkan Semula Akaun'
};

interface Props {
  account: AdminAccountDetail;
  actionLoading: boolean;
  pendingAction: AccountAction | null;
  actionReason: string;
  canSuspend: boolean;
  canUnsuspend: boolean;
  canReactivate: boolean;
  onClose: () => void;
  onBeginAction: (action: AccountAction) => void;
  onCancelAction: () => void;
  onActionReasonChange: (value: string) => void;
  onSubmitAction: (event: FormEvent<HTMLFormElement>) => void;
}

export default function AccountDetailPanel({
  account,
  actionLoading,
  pendingAction,
  actionReason,
  canSuspend,
  canUnsuspend,
  canReactivate,
  onClose,
  onBeginAction,
  onCancelAction,
  onActionReasonChange,
  onSubmitAction
}: Props) {
  const actions: { action: AccountAction; className?: string }[] = [];

  if (account.role === 'USER') {
    if (account.status === 'ACTIVE' && canSuspend) {
      actions.push({ action: 'suspend', className: 'admin-button--danger' });
    }

    if (account.status === 'SUSPENDED' && canUnsuspend) {
      actions.push({ action: 'unsuspend' });
    }

    if (account.status === 'DEACTIVATED' && canReactivate) {
      actions.push({ action: 'reactivate' });
    }
  }

  return (
    <section className="account-detail" aria-labelledby="account-detail-title">
      <div className="admin-page__heading">
        <div>
          <span className="dashboard-page__eyebrow">BUTIRAN AKAUN</span>
          <h3 id="account-detail-title">{account.profile_name}</h3>
        </div>

        <button
          type="button"
          className="admin-button"
          disabled={actionLoading}
          onClick={onClose}
        >
          Tutup
        </button>
      </div>

      <div className="admin-record__details">
        <div>
          <small>Nama Paparan</small>
          <strong>{account.profile_name}</strong>
        </div>
        <div>
          <small>Nama Disahkan</small>
          <strong>{account.verified_name || 'Belum disahkan'}</strong>
        </div>
        <div>
          <small>Telefon</small>
          <strong>{account.phone || '—'}</strong>
        </div>
        <div>
          <small>E-mel</small>
          <strong>{account.email || '—'}</strong>
        </div>
        <div>
          <small>Peranan</small>
          <strong>{account.role}</strong>
        </div>
        <div>
          <small>Status Akaun</small>
          <strong>{account.status}</strong>
        </div>
        <div>
          <small>Status Identiti</small>
          <strong>{account.identity_status || 'Tiada rekod'}</strong>
        </div>
        <div>
          <small>Jawatan</small>
          <strong>{account.job_title || '—'}</strong>
        </div>
        <div>
          <small>Tarikh Daftar</small>
          <strong>{new Date(account.created_at).toLocaleString('ms-MY')}</strong>
        </div>
      </div>

      {actions.length > 0 && (
        <div className="admin-record__actions">
          {actions.map(({ action, className }) => (
            <button
              key={action}
              type="button"
              className={`admin-button ${className ?? ''}`}
              disabled={actionLoading}
              onClick={() => onBeginAction(action)}
            >
              {ACTION_LABELS[action]}
            </button>
          ))}
        </div>
      )}

      {pendingAction && (
        <form className="admin-record" onSubmit={onSubmitAction}>
          <div className="admin-record__heading">
            <div>
              <h3>{ACTION_LABELS[pendingAction]}</h3>
              <span>Akaun: {account.profile_name}</span>
            </div>
          </div>

          <label htmlFor="account-action-reason">Sebab tindakan</label>

          <textarea
            id="account-action-reason"
            rows={4}
            maxLength={1000}
            required
            value={actionReason}
            onChange={(event) => onActionReasonChange(event.target.value)}
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
              onClick={onCancelAction}
            >
              Batal
            </button>
          </div>
        </form>
      )}
    </section>
  );
}
