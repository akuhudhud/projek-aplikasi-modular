
import type {
    CustomerAccount,
    CustomerTaskItem,
    CustomerOwnMarketplaceRequest,
} from '../api';

import { formatCustomerDate } from '../customerPresentation';

type HomePageProps = {
    account: CustomerAccount;
    taskTotal: number;
    activeTaskTotal: number;
    ownRequestTotal: number;
    tasks: CustomerTaskItem[];
    ownRequests: CustomerOwnMarketplaceRequest[];
    tasksLoading: boolean;
    tasksError: string;
    ownRequestsLoading: boolean;
    ownRequestsError: string;
    onOpenTask: (item: CustomerTaskItem) => void;
    onNavigateTasks: () => void;
    onNavigateRequests: () => void;
    onNavigateCreate: () => void;
};

function taskClassLabel(taskClass: string): string {
    return taskClass === 'PICKUP_DELIVERY'
        ? 'Ambil dan Hantar'
        : 'Tugasan Bukan Penghantaran';
}

export default function HomePage({
    account,
    taskTotal,
    activeTaskTotal,
    ownRequestTotal,
    tasks,
    ownRequests,
    tasksLoading,
    tasksError,
    ownRequestsLoading,
    ownRequestsError,
    onOpenTask,
    onNavigateTasks,
    onNavigateRequests,
    onNavigateCreate,
}: HomePageProps) {
    return (
        <div className="sr-home-page">
            <section className="customer-welcome">
                <p>Halaman Utama Pelanggan</p>
                <h2>Hai, {account.name}</h2>
                <span>Selamat datang ke Super Runner.</span>
            </section>

            <section className="sr-home-page__quick-action">
                <div>
                    <span className="sr-home-page__eyebrow">
                        PERLUKAN BANTUAN?
                    </span>

                    <h2>Cipta Tugasan Baharu</h2>

                    <p>
                        Hantar permintaan anda dan terima sebut harga
                        daripada Runner.
                    </p>
                </div>

                <button
                    type="button"
                    onClick={onNavigateCreate}
                >
                    + Cipta Permintaan
                </button>
            </section>

            <section
                className="customer-summary-grid"
                aria-label="Ringkasan akaun"
            >
                <article className="customer-summary-card">
                    <span>Jumlah Tugasan</span>
                    <strong>{taskTotal}</strong>
                </article>

                <article className="customer-summary-card">
                    <span>Tugasan Aktif</span>
                    <strong>{activeTaskTotal}</strong>
                </article>

                <article className="customer-summary-card">
                    <span>Jumlah Permintaan</span>
                    <strong>{ownRequestTotal}</strong>
                </article>

                <article className="customer-summary-card">
                    <span>Status Akaun</span>
                    <strong>{account.status}</strong>
                </article>
            </section>

            <section className="customer-panel">
                <div className="customer-section-heading">
                    <h2>Tugasan Terkini</h2>

                    <button
                        type="button"
                        onClick={onNavigateTasks}
                    >
                        Lihat Semua
                    </button>
                </div>

                {tasksLoading ? (
                    <p>Sedang memuatkan tugasan...</p>
                ) : tasksError ? (
                    <p role="alert">{tasksError}</p>
                ) : tasks.length === 0 ? (
                    <section className="customer-empty">
                        <h3>Belum Ada Tugasan</h3>
                        <p>
                            Tugasan yang berjaya diwujudkan akan
                            dipaparkan di sini.
                        </p>
                    </section>
                ) : (
                    <div className="customer-task-list">
                        {tasks.slice(0, 3).map((item) => (
                            <button
                                type="button"
                                className="customer-task-card"
                                key={item.task.task_id}
                                onClick={() => onOpenTask(item)}
                            >
                                <span className="customer-task-card-top">
                                    {taskClassLabel(item.request.task_class)}
                                </span>

                                <strong>
                                    {item.request.description}
                                </strong>

                                <span>
                                    Status: {item.task.generic_status}
                                </span>

                                <span>
                                    {formatCustomerDate(item.task.created_at)}
                                </span>
                            </button>
                        ))}
                    </div>
                )}
            </section>

            <section className="customer-panel">
                <div className="customer-section-heading">
                    <h2>Permintaan Terkini</h2>

                    <button
                        type="button"
                        onClick={onNavigateRequests}
                    >
                        Lihat Semua
                    </button>
                </div>

                {ownRequestsLoading ? (
                    <p>Sedang memuatkan permintaan...</p>
                ) : ownRequestsError ? (
                    <p role="alert">{ownRequestsError}</p>
                ) : ownRequests.length === 0 ? (
                    <section className="customer-empty">
                        <h3>Belum Ada Permintaan</h3>
                        <p>
                            Permintaan yang anda cipta akan
                            dipaparkan di sini.
                        </p>

                        <button
                            type="button"
                            onClick={onNavigateCreate}
                        >
                            Cipta Permintaan
                        </button>
                    </section>
                ) : (
                    <div className="customer-task-list">
                        {ownRequests.slice(0, 3).map((request) => (
                            <article
                                className="customer-task-card"
                                key={request.request_id}
                            >
                                <span className="customer-task-card-top">
                                    {taskClassLabel(request.task_class)}
                                </span>

                                <strong>
                                    {request.description ||
                                        'Tiada penerangan.'}
                                </strong>

                                <span>
                                    Status: {request.status}
                                </span>

                                <span>
                                    Tarikh Tamat:{' '}
                                    {formatCustomerDate(request.expires_at)}
                                </span>
                            </article>
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}
