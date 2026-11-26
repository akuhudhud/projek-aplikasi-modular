
import { useState } from 'react';

interface Props {
  working: boolean;
  onDelete: (reason: string) => void;
}

export default function IdentityVerificationDataDeletion({
  working,
  onDelete
}: Props) {
  const [reason, setReason] = useState('');

  function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const trimmedReason = reason.trim();

    if (trimmedReason.length < 5 || trimmedReason.length > 500) return;

    const confirmed = window.confirm(
      'AMARAN: Semua data pengesahan identiti dan dokumen berkaitan akan dipadam. Tindakan ini tidak boleh dibatalkan. Teruskan?'
    );

    if (!confirmed) return;

    onDelete(trimmedReason);
  }

  return (
    <form className="admin-record" onSubmit={submit}>
      <h3>Pemadaman Data Identiti</h3>

      <div className="admin-alert admin-alert--error" role="note">
        Tindakan ini akan memadam rekod pengesahan identiti dan dokumen
        berkaitan secara kekal. Rekod audit tindakan akan dikekalkan.
      </div>

      <label htmlFor="identity-deletion-reason">
        Alasan pemadaman
      </label>

      <textarea
        id="identity-deletion-reason"
        rows={3}
        minLength={5}
        maxLength={500}
        required
        value={reason}
        onChange={(event) => setReason(event.target.value)}
        placeholder="Nyatakan alasan pemadaman data identiti."
        disabled={working}
      />

      <div className="admin-page__footnote">
        Minimum 5 aksara, maksimum 500 aksara.
      </div>

      <div className="admin-record__actions">
        <button
          type="submit"
          className="admin-button admin-button--danger"
          disabled={
            working ||
            reason.trim().length < 5 ||
            reason.trim().length > 500
          }
        >
          {working ? 'Memadam Data...' : 'Padam Data Identiti'}
        </button>
      </div>
    </form>
  );
}
