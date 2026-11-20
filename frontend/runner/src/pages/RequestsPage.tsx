
import type {
    RunnerPagination,
    RunnerMarketplaceRequest,
    RunnerMarketplaceRequestDetail,
    RunnerQuotation,
} from '../api';

import {
    formatDate,
    getRequestLocation,
    requestStatusLabel,
} from '../utils/runnerFormatters';

import RunnerRequestCard from '../components/requests/RunnerRequestCard';
import PaginationControls from '../components/shared/PaginationControls';

interface RequestsPageProps {
    requests: RunnerMarketplaceRequest[];
    pagination: RunnerPagination;
    loading: boolean;
    error: string;
    quotationRequestId: string | null;
    quotationAmount: string;
    quotationMessage: string;
    quotationLoading: boolean;
    quotationDetailLoading: string;
    existingQuotation: RunnerQuotation | null;
    notificationRequest: RunnerMarketplaceRequestDetail | null;
    notificationRequestLoading: boolean;
    notificationRequestError: string;
    onRefresh: () => void;
    onPageChange: (page: number) => void;
    onOpenQuotation: (requestId: string) => void;
    onCloseQuotation: () => void;
    onAmountChange: (value: string) => void;
    onMessageChange: (value: string) => void;
    onSubmitQuotation: (
        event: React.FormEvent<HTMLFormElement>,
    ) => void;
    onCloseNotificationRequest: () => void;
}

export default function RequestsPage({
    requests,
    pagination,
    loading,
    error,
    quotationRequestId,
    quotationAmount,
    quotationMessage,
    quotationLoading,
    quotationDetailLoading,
    existingQuotation,
    notificationRequest,
    notificationRequestLoading,
    notificationRequestError,
    onRefresh,
    onPageChange,
    onOpenQuotation,
    onCloseQuotation,
    onAmountChange,
    onMessageChange,
    onSubmitQuotation,
    onCloseNotificationRequest,
}: RequestsPageProps) {
    return (
        <section className="runner-dashboard__section">
            <div className="runner-dashboard__section-heading">
                <div>
                    <h2>Open Request</h2>
                    <p>
                        Permintaan pelanggan yang tersedia untuk
                        sebut harga anda.
                    </p>
                </div>

                <button
                    type="button"
                    onClick={onRefresh}
                    disabled={loading}
                >
                    {loading ? 'MEMUAT...' : 'SEGARKAN'}
                </button>
            </div>

            {notificationRequestLoading && (
                <p className="runner-dashboard__notice" role="status">
                    Membuka permintaan daripada notifikasi...
                </p>
            )}

            {notificationRequestError && (
                <p className="runner-dashboard__alert" role="alert">
                    {notificationRequestError}
                </p>
            )}

            {notificationRequest && (
                <article className="runner-dashboard__item">
                    <div className="runner-dashboard__item-top">
                        <strong>
                            PERMINTAAN DARIPADA NOTIFIKASI
                        </strong>

                        <span className="runner-dashboard__status runner-dashboard__status--open">
                            {requestStatusLabel(
                                notificationRequest.status,
                            )}
                        </span>
                    </div>

                    <p>
                        {notificationRequest.task_class === 'PICKUP_DELIVERY'
                            ? 'PICKUP & DELIVERY'
                            : 'NON-DELIVERY'}
                    </p>

                    <p>
                        {notificationRequest.description}
                    </p>

                    <div className="runner-dashboard__request-location">
                        <strong>Lokasi</strong>
                        <span>
                            {getRequestLocation(notificationRequest)}
                        </span>
                    </div>

                    <small>
                        Tarikh permintaan: {
                            formatDate(notificationRequest.created_at)
                        }
                    </small>

                    <button
                        type="button"
                        className="runner-dashboard__primary-action"
                        onClick={onCloseNotificationRequest}
                    >
                        TUTUP MAKLUMAT PERMINTAAN
                    </button>
                </article>
            )}

            {loading && (
                <p className="runner-dashboard__notice">
                    Memuatkan Open Request...
                </p>
            )}

            {error && (
                <p className="runner-dashboard__alert" role="alert">
                    {error}
                </p>
            )}

            {!loading && !error && requests.length === 0 && (
                <div className="runner-dashboard__empty">
                    <h3>Tiada Open Request Buat Masa Ini</h3>
                    <p>
                        Permintaan pelanggan yang tersedia
                        akan dipaparkan di sini.
                    </p>
                </div>
            )}

            <div className="runner-dashboard__list">
                {requests.map((request) => (
                    <RunnerRequestCard
                        key={request.request_id}
                        request={request}
                        isQuotationOpen={
                            quotationRequestId === request.request_id
                        }
                        isCheckingQuotation={
                            quotationDetailLoading === request.request_id
                        }
                        anyQuotationDetailLoading={
                            Boolean(quotationDetailLoading)
                        }
                        quotationLoading={quotationLoading}
                        existingQuotation={
                            quotationRequestId === request.request_id
                                ? existingQuotation
                                : null
                        }
                        quotationAmount={quotationAmount}
                        quotationMessage={quotationMessage}
                        onOpenQuotation={onOpenQuotation}
                        onCloseQuotation={onCloseQuotation}
                        onAmountChange={onAmountChange}
                        onMessageChange={onMessageChange}
                        onSubmitQuotation={onSubmitQuotation}
                    />
                ))}
            </div>

            <PaginationControls
                currentPage={pagination.current_page}
                lastPage={pagination.last_page}
                total={pagination.total}
                loading={loading}
                onPageChange={onPageChange}
            />
        </section>
    );
}
