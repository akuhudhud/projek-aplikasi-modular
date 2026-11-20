import type { RunnerAccount } from '../../api';

interface RunnerAccountSettingsProps {
    account: RunnerAccount;
    onLogout: () => void;
    logoutLoading: boolean;
}

export default function RunnerAccountSettings({
    account,
    onLogout,
    logoutLoading,
}: RunnerAccountSettingsProps) {
    return (
        <div className="runner-dashboard__list">
            <article className="runner-dashboard__item">
                <h3>Maklumat Akaun</h3>

                <div className="runner-profile__account-row">
                    <span>Nama paparan</span>
                    <strong>{account.profile_name}</strong>
                </div>

                <div className="runner-profile__account-row">
                    <span>Nombor telefon</span>
                    <strong>{account.phone ?? 'Belum ditetapkan'}</strong>
                </div>

                <div className="runner-profile__account-row">
                    <span>E-mel</span>
                    <strong>{account.email ?? 'Belum ditetapkan'}</strong>
                </div>

                <div className="runner-profile__account-row">
                    <span>Status akaun</span>
                    <strong>{account.status}</strong>
                </div>

                <div className="runner-profile__account-row">
                    <span>Status Runner</span>
                    <strong>{account.runner_status}</strong>
                </div>
            </article>

            <article className="runner-dashboard__item">
                <h3>Keselamatan Akaun</h3>
                <p>
                    Maklumat pengesahan identiti digunakan semasa
                    proses permohonan Runner. Maklumat peribadi
                    pengenalan tidak dipaparkan pada profil awam.
                </p>

                <button
                    type="button"
                    className="runner-dashboard__primary-action"
                    onClick={onLogout}
                    disabled={logoutLoading}
                >
                    {logoutLoading
                        ? 'SEDANG LOG KELUAR...'
                        : 'LOG KELUAR AKAUN'}
                </button>
            </article>
        </div>
    );
}
