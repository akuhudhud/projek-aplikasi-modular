import type { CustomerOwnMarketplaceRequest } from '../api';
import CustomerRequestQuotations from '../CustomerRequestQuotations';
import { LocationSnapshotPanel } from '../customerPresentation';

type RequestsPageProps = {
    requests: CustomerOwnMarketplaceRequest[];
    currentPage: number;
    lastPage: number;
    total: number;
    loading: boolean;
    error: string;
    cancellingRequestId: string | null;
    actionMessage: string;
    actionError: string;
    onCancel: (requestId: string) => void;
    onPageChange: (page: number) => void;
    onQuotationAwarded: (
        requestId: string,
        result: Awaited<
            ReturnType<
                typeof import('../api').customerApi.awardQuotation
            >
        >,
    ) => void;
};

function formatDate(value: string | null): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat('ms-MY', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function taskClassLabel(taskClass: string): string {
    return taskClass === 'PICKUP_DELIVERY'
        ? 'Pickup & Delivery'
        : 'Non-Delivery';
}

export default function RequestsPage({
    requests,
    currentPage,
    lastPage,
    total,
    loading,
    error,
    cancellingRequestId,
    actionMessage,
    actionError,
    onCancel,
    onPageChange,
    onQuotationAwarded,
}: RequestsPageProps) {
    return (
        <div className="sr-requests-page">
            <section className="customer-panel">
                <div className="customer-section-heading">
                    <div>
                        <h2>Open Request Saya</h2>
                        <p>Jumlah permintaan: {total}</p>
                    </div>
                </div>

                {actionMessage && (
                    <p
                        className="customer-request-success"
                        role="status"
                    >
                        {actionMessage}
                    </p>
                )}

                {actionError && (
                    <p role="alert">{actionError}</p>
                )}

                {loading ? (
                    <p>Memuatkan senarai permintaan...</p>
                ) : error ? (
                    <p role="alert">{error}</p>
                ) : requests.length === 0 ? (
                    <section className="customer-empty">
                        <h3>Belum ada Open Request</h3>
                        <p>
                            Permintaan yang anda cipta akan dipaparkan di sini.
                        </p>
                    </section>
                ) : (
                    <>
                        <div className="customer-task-list">
                            {requests.map((request) => (
                                <article
                                    className="customer-task-card"
                                    key={request.request_id}
                                >
                                    <span className="customer-task-card-top">
                                        {taskClassLabel(request.task_class)}
                                    </span>

                                    <strong>{request.description}</strong>

                                    <span>
                                        Status: {request.status}
                                    </span>

                                    <span>
                                        Tarikh luput:{' '}
                                        {formatDate(request.expires_at)}
                                    </span>

                                    <span>
                                        Dicipta:{' '}
                                        {formatDate(request.created_at)}
                                    </span>

                                    {request.task_class ===
                                        'PICKUP_DELIVERY' && (
                                        <div className="customer-location-grid">
                                            <LocationSnapshotPanel
                                                title="Lokasi Pickup"
                                                snapshot={
                                                    request.pickup_location_snapshot
                                                }
                                            />

                                            <LocationSnapshotPanel
                                                title="Lokasi Delivery"
                                                snapshot={
                                                    request.delivery_location_snapshot
                                                }
                                            />
                                        </div>
                                    )}

                                    {request.task_class ===
                                        'NON_DELIVERY' && (
                                        <div className="customer-location-grid">
                                            <LocationSnapshotPanel
                                                title="Lokasi Tugasan"
                                                snapshot={
                                                    request.task_location_snapshot
                                                }
                                            />
                                        </div>
                                    )}

                                    {request.cancelled_at && (
                                        <span>
                                            Dibatalkan:{' '}
                                            {formatDate(request.cancelled_at)}
                                        </span>
                                    )}

                                    {request.status === 'OPEN' && (
                                        <button
                                            type="button"
                                            disabled={
                                                cancellingRequestId !== null
                                            }
                                            onClick={() =>
                                                onCancel(request.request_id)
                                            }
                                        >
                                            {cancellingRequestId ===
                                            request.request_id
                                                ? 'Membatalkan...'
                                                : 'Batalkan Permintaan'}
                                        </button>
                                    )}

                                    {(request.status === 'OPEN' ||
                                        request.status === 'AWARDED') && (
                                        <CustomerRequestQuotations
                                            requestId={request.request_id}
                                            requestStatus={request.status}
                                            onAwarded={onQuotationAwarded}
                                        />
                                    )}
                                </article>
                            ))}
                        </div>

                        <div className="customer-pagination">
                            <button
                                type="button"
                                disabled={currentPage <= 1 || loading}
                                onClick={() =>
                                    onPageChange(currentPage - 1)
                                }
                            >
                                Sebelum
                            </button>

                            <span>
                                {currentPage} / {lastPage}
                            </span>

                            <button
                                type="button"
                                disabled={
                                    currentPage >= lastPage || loading
                                }
                                onClick={() =>
                                    onPageChange(currentPage + 1)
                                }
                            >
                                Seterusnya
                            </button>
                        </div>
                    </>
                )}
            </section>
        </div>
    );
}
