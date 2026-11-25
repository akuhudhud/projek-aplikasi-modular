import type { RunnerDirectoryItem } from '../../../types/admin';

import { formatRunnerDate } from '../utils/runnerApplicationUtils';

interface RunnerDirectoryCardProps {
  runner: RunnerDirectoryItem;
  canSuspend: boolean;
  canRestore: boolean;
  working: boolean;
  reason: string;
  onReasonChange: (value: string) => void;
  onStatusChange: (action: 'suspend' | 'restore') => void;
}

export default function RunnerDirectoryCard({
  runner,
  canSuspend,
  canRestore,
  working,
  reason,
  onReasonChange,
  onStatusChange
}: RunnerDirectoryCardProps) {
  const canAct =
    runner.runner_status === 'APPROVED'
      ? canSuspend
      : canRestore;

  return (
    <article className="admin-record">
      <div className="admin-record__heading">
        <div>
          <h3>{runner.profile_name}</h3>
          <span>{runner.phone || runner.email || '—'}</span>
        </div>

        <span className="admin-status">
          {runner.runner_status}
        </span>
      </div>

      <div className="admin-record__details">
        <div>
          <small>Status Akaun</small>
          <strong>{runner.status}</strong>
        </div>

        <div>
          <small>Tarikh Menjadi Runner</small>
          <strong>{formatRunnerDate(runner.created_at)}</strong>
        </div>

        <div>
          <small>Jenis Lesen</small>
          <strong>{runner.current_license?.license_type || '—'}</strong>
        </div>

        <div>
          <small>Kelas Lesen</small>
          <strong>{runner.current_license?.license_class || '—'}</strong>
        </div>

        <div>
          <small>Tarikh Luput Lesen</small>
          <strong>
            {formatRunnerDate(runner.current_license?.expires_at)}
          </strong>
        </div>
      </div>

      {canAct && (
        <>
          <label htmlFor={`runner-reason-${runner.id}`}>
            Alasan tindakan
          </label>

          <textarea
            id={`runner-reason-${runner.id}`}
            rows={2}
            value={reason}
            onChange={(event) => onReasonChange(event.target.value)}
            placeholder="Masukkan alasan tindakan..."
          />

          <div className="admin-record__actions">
            {runner.runner_status === 'APPROVED' && canSuspend && (
              <button
                type="button"
                className="admin-button admin-button--danger"
                disabled={working}
                onClick={() => onStatusChange('suspend')}
              >
                {working ? 'Memproses...' : 'Gantung Runner'}
              </button>
            )}

            {runner.runner_status === 'SUSPENDED' && canRestore && (
              <button
                type="button"
                className="admin-button admin-button--primary"
                disabled={working}
                onClick={() => onStatusChange('restore')}
              >
                {working ? 'Memproses...' : 'Pulihkan Runner'}
              </button>
            )}
          </div>
        </>
      )}
    </article>
  );
}
