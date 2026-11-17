
import type {
    RunnerAccount,
} from '../../api';

export type RunnerAccessReason =
    | 'ACCOUNT_INACTIVE'
    | 'IDENTITY_NOT_SUBMITTED'
    | 'IDENTITY_PENDING'
    | 'IDENTITY_REJECTED'
    | 'RUNNER_NOT_REGISTERED'
    | 'RUNNER_PENDING'
    | 'RUNNER_REJECTED'
    | 'RUNNER_SUSPENDED';

type RunnerAccessPageProps = {
    account: RunnerAccount;
    reason: RunnerAccessReason;
    logout: () => Promise<boolean>;
    logoutLoading: boolean;
    logoutError: string;
};

type AccessContent = {
    title: string;
    description: string;
    instruction: string;
};

const ACCESS_CONTENT: Record<
    RunnerAccessReason,
    AccessContent
> = {
    ACCOUNT_INACTIVE: {
        title: 'Akaun Tidak Aktif',
        description:
            'Akaun anda tidak berada dalam keadaan aktif.',
        instruction:
            'Sila hubungi pihak Super Runner untuk mendapatkan bantuan.',
    },

    IDENTITY_NOT_SUBMITTED: {
        title: 'Pengesahan Identiti Diperlukan',
        description:
            'Identiti anda belum dihantar untuk pengesahan.',
        instruction:
            'Lengkapkan proses pengesahan identiti sebelum memohon sebagai Runner. Proses penghantaran melalui aplikasi ini belum tersedia.',
    },

    IDENTITY_PENDING: {
        title: 'Pengesahan Sedang Diproses',
        description:
            'Maklumat identiti anda sedang disemak oleh pihak pentadbir.',
        instruction:
            'Sila tunggu keputusan pengesahan. Anda belum boleh menggunakan fungsi Runner.',
    },

    IDENTITY_REJECTED: {
        title: 'Pengesahan Identiti Tidak Berjaya',
        description:
            'Pengesahan identiti anda tidak diluluskan.',
        instruction:
            'Sila hubungi pihak Super Runner untuk mengetahui tindakan seterusnya.',
    },

    RUNNER_NOT_REGISTERED: {
        title: 'Permohonan Runner Diperlukan',
        description:
            'Akaun anda belum mempunyai permohonan Runner.',
        instruction:
            'Anda perlu menghantar permohonan Runner dan mendapatkan kelulusan sebelum mengakses platform Runner. Pendaftaran melalui aplikasi ini belum tersedia.',
    },

    RUNNER_PENDING: {
        title: 'Permohonan Dalam Semakan',
        description:
            'Permohonan Runner anda sedang disemak.',
        instruction:
            'Sila tunggu keputusan pihak pentadbir. Anda akan mendapat akses selepas permohonan diluluskan.',
    },

    RUNNER_REJECTED: {
        title: 'Permohonan Tidak Diluluskan',
        description:
            'Permohonan Runner anda tidak diluluskan.',
        instruction:
            'Sila hubungi pihak Super Runner untuk mendapatkan maklumat lanjut.',
    },

    RUNNER_SUSPENDED: {
        title: 'Akses Runner Digantung',
        description:
            'Akses Runner untuk akaun anda telah digantung.',
        instruction:
            'Sila hubungi pihak Super Runner untuk mendapatkan bantuan lanjut.',
    },
};

export default function RunnerAccessPage({
    account,
    reason,
    logout,
    logoutLoading,
    logoutError,
}: RunnerAccessPageProps) {
    const content = ACCESS_CONTENT[reason];

    async function handleLogout() {
        await logout();
    }

    return (
        <main className="runner-access">
            <section
                className="runner-access__panel"
                aria-labelledby="runner-access-heading"
            >
                <div className="runner-access__brand">
                    <div
                        className="runner-access__brand-mark"
                        aria-hidden="true"
                    >
                        SR
                    </div>

                    <div>
                        <h1>SUPER RUNNER</h1>
                        <p>Runner Platform</p>
                    </div>
                </div>

                <div className="runner-access__status">
                    <div
                        className="runner-access__status-icon"
                        aria-hidden="true"
                    >
                        !
                    </div>

                    <h2 id="runner-access-heading">
                        {content.title}
                    </h2>

                    <p>{content.description}</p>
                </div>

                <div className="runner-access__account">
                    <span>AKAUN</span>
                    <strong>{account.profile_name}</strong>
                </div>

                <div className="runner-access__instruction">
                    <h3>Apakah tindakan seterusnya?</h3>
                    <p>{content.instruction}</p>
                </div>

                {logoutError && (
                    <p
                        className="runner-access__error"
                        role="alert"
                    >
                        {logoutError}
                    </p>
                )}

                <button
                    className="runner-access__logout"
                    type="button"
                    onClick={handleLogout}
                    disabled={logoutLoading}
                >
                    {logoutLoading
                        ? 'Sedang log keluar...'
                        : 'LOG KELUAR'}
                </button>
            </section>
        </main>
    );
}
