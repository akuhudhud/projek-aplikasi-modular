
import type {
    RunnerAccount,
} from '../../api';

export type RunnerAccessReason =
    | 'ACCOUNT_UNAVAILABLE'
    | 'IDENTITY_NOT_SUBMITTED'
    | 'IDENTITY_PENDING'
    | 'IDENTITY_REJECTED'
    | 'RUNNER_NOT_APPLIED'
    | 'RUNNER_PENDING'
    | 'RUNNER_REJECTED'
    | 'RUNNER_SUSPENDED';

type RunnerAccessPageProps = {
    account: RunnerAccount;
    reason: RunnerAccessReason;
    onLogout: () => void;
    logoutLoading: boolean;
    logoutError: string;
};

const ACCESS_CONTENT: Record<
    RunnerAccessReason,
    {
        title: string;
        description: string;
        instruction: string;
    }
> = {
    ACCOUNT_UNAVAILABLE: {
        title: 'Akaun Tidak Aktif',
        description:
            'Akaun anda tidak dapat digunakan buat masa ini.',
        instruction:
            'Sila hubungi pihak Super Runner untuk mendapatkan bantuan.',
    },

    IDENTITY_NOT_SUBMITTED: {
        title: 'Pengesahan Identiti Diperlukan',
        description:
            'Anda perlu melengkapkan pengesahan identiti sebelum memohon menjadi Runner.',
        instruction:
            'Sediakan dokumen pengenalan diri untuk proses pengesahan.',
    },

    IDENTITY_PENDING: {
        title: 'Identiti Sedang Disemak',
        description:
            'Maklumat identiti anda telah diterima dan sedang melalui proses semakan.',
        instruction:
            'Sila tunggu keputusan pengesahan daripada pihak Super Runner.',
    },

    IDENTITY_REJECTED: {
        title: 'Pengesahan Identiti Ditolak',
        description:
            'Pengesahan identiti anda tidak diluluskan.',
        instruction:
            'Sila hubungi pihak Super Runner untuk mengetahui tindakan seterusnya.',
    },

    RUNNER_NOT_APPLIED: {
        title: 'Sertai Sebagai Runner',
        description:
            'Identiti anda telah disahkan. Anda kini boleh meneruskan proses permohonan Runner.',
        instruction:
            'Permohonan Runner perlu dilengkapkan sebelum anda boleh menerima tugasan.',
    },

    RUNNER_PENDING: {
        title: 'Permohonan Sedang Disemak',
        description:
            'Permohonan Runner anda telah diterima dan sedang menunggu kelulusan.',
        instruction:
            'Anda akan mendapat akses Runner selepas permohonan diluluskan.',
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
            'Akses Runner anda digantung buat sementara waktu.',
        instruction:
            'Sila hubungi pihak Super Runner untuk mendapatkan penjelasan.',
    },
};

export default function RunnerAccessPage({
    account,
    reason,
    onLogout,
    logoutLoading,
    logoutError,
}: RunnerAccessPageProps) {
    const content = ACCESS_CONTENT[reason];

    return (
        <main className="runner-access">
            <section className="runner-access__panel">
                <div className="runner-access__brand">
                    <span className="runner-access__brand-mark">
                        SR
                    </span>

                    <span>SUPER RUNNER</span>
                </div>

                <div className="runner-access__status">
                    <span className="runner-access__status-icon">
                        !
                    </span>

                    <h1>{content.title}</h1>

                    <p>
                        {content.description}
                    </p>
                </div>

                <div className="runner-access__account">
                    <span>AKAUN</span>

                    <strong>
                        {account.profile_name}
                    </strong>
                </div>

                <div className="runner-access__instruction">
                    <strong>Apakah langkah seterusnya?</strong>

                    <p>
                        {content.instruction}
                    </p>
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
                    type="button"
                    className="runner-access__logout"
                    onClick={onLogout}
                    disabled={logoutLoading}
                >
                    {logoutLoading
                        ? 'SEDANG LOG KELUAR...'
                        : 'LOG KELUAR'}
                </button>
            </section>
        </main>
    );
}
