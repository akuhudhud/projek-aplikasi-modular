
import type {
    RunnerMarketplaceRequest,
    RunnerQuotation,
} from '../api';

import {
    formatAmount,
    formatDate,
    getRequestLocation,
    quotationStatusLabel,
    requestStatusLabel,
} from '../utils/runnerFormatters';

interface RequestsPageProps {
    requests: RunnerMarketplaceRequest[];
    loading: boolean;
    error: string;
    quotationRequestId: string | null;
    quotationAmount: string;
    quotationMessage: string;
    quotationLoading: boolean;
    quotationDetailLoading: string;
    existingQuotation: RunnerQuotation | null;
    onRefresh: () => void;
    onOpenQuotation: (requestId: string) => void;
    onCloseQuotation: () => void;
    onAmountChange: (value: string) => void;
    onMessageChange: (value: string) => void;
    onSubmitQuotation: (event: React.FormEvent<HTMLFormElement>) => void;
}

export default function RequestsPage({
    requests,
    loading,
    error,
    quotationRequestId,
    quotationAmount,
    quotationMessage,
    quotationLoading,
    quotationDetailLoading,
    existingQuotation,
    onRefresh,
    onOpenQuotation,
    onCloseQuotation,
    onAmountChange,
    onMessageChange,
    onSubmitQuotation,
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
                {requests.map((request) => {
                    const isQuotationOpen =
                        quotationRequestId === request.request_id;

                    const isCheckingQuotation =
                        quotationDetailLoading === request.request_id;

                    const hasActiveQuotation =
                        isQuotationOpen
                        && existingQuotation?.status === 'SUBMITTED';

                    return (
                        <article
                            className="runner-dashboard__item"
                            key={request.request_id}
                        >
                            <div className="runner-dashboard__item-top">
                                <strong>
                                    {request.task_class === 'PICKUP_DELIVERY'
                                        ? 'PICKUP & DELIVERY'
                                        : 'NON-DELIVERY'}
                                </strong>

                                <span className="runner-dashboard__status runner-dashboard__status--open">
                                    {requestStatusLabel(request.status)}
                                </span>
                            </div>

                            <p>{request.description}</p>

                            <div className="runner-dashboard__request-location">
                                <strong>Lokasi</strong>
                                <span>{getRequestLocation(request)}</span>
                            </div>

                            <small>
                                Tamat: {formatDate(request.expires_at)}
                            </small>

                            <button
                                type="button"
                                className="runner-dashboard__primary-action"
                                onClick={() => onOpenQuotation(request.request_id)}
                                disabled={
                                    Boolean(quotationDetailLoading)
                                    || quotationLoading
                                }
                            >
                                {isCheckingQuotation
                                    ? 'MENYEMAK SEBUT HARGA...'
                                    : isQuotationOpen
                                        ? 'TUTUP'
                                        : 'SEMAK / HANTAR SEBUT HARGA'}
                            </button>

                            {isQuotationOpen && existingQuotation && (
                                <div
                                    className="runner-dashboard__notice"
                                    role="status"
                                >
                                    <strong>Sebut harga terdahulu:</strong>

                                    <p>
                                        Status: {
                                            quotationStatusLabel(
                                                existingQuotation.status,
                                            )
                                        }
                                    </p>

                                    <p>
                                        Nilai: {
                                            formatAmount(
                                                Number(existingQuotation.amount),
                                                existingQuotation.currency,
                                            )
                                        }
                                    </p>

                                    <small>
                                        Dihantar: {
                                            formatDate(existingQuotation.created_at)
                                        }
                                    </small>
                                </div>
                            )}

                            {isQuotationOpen && hasActiveQuotation && (
                                <p
                                    className="runner-dashboard__notice"
                                    role="status"
                                >
                                    Sebut harga anda masih aktif dan
                                    sedang menunggu keputusan pelanggan.
                                    Anda tidak perlu menghantar sebut
                                    harga sekali lagi.
                                </p>
                            )}

                            {isQuotationOpen && !hasActiveQuotation && (
                                <form
                                    className="runner-dashboard__quotation-form"
                                    onSubmit={onSubmitQuotation}
                                >
                                    {existingQuotation && (
                                        <p className="runner-dashboard__notice">
                                            Sebut harga terdahulu: {
                                                quotationStatusLabel(
                                                    existingQuotation.status,
                                                )
                                            }. Anda boleh menghantar
                                            sebut harga baharu.
                                        </p>
                                    )}

                                    <label>
                                        Jumlah sebut harga (RM)
                                        <input
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            required
                                            value={quotationAmount}
                                            onChange={(event) =>
                                                onAmountChange(event.target.value)
                                            }
                                            placeholder="Contoh: 15.00"
                                        />
                                    </label>

                                    <label>
                                        Mesej kepada pelanggan (pilihan)
                                        <textarea
                                            rows={3}
                                            value={quotationMessage}
                                            onChange={(event) =>
                                                onMessageChange(event.target.value)
                                            }
                                            placeholder="Maklumat tambahan..."
                                        />
                                    </label>

                                    <div className="runner-dashboard__quotation-actions">
                                        <button
                                            type="button"
                                            onClick={onCloseQuotation}
                                            disabled={quotationLoading}
                                        >
                                            BATAL
                                        </button>

                                        <button
                                            type="submit"
                                            disabled={quotationLoading}
                                        >
                                            {quotationLoading
                                                ? 'MENGHANTAR...'
                                                : 'HANTAR SEBUT HARGA'}
                                        </button>
                                    </div>
                                </form>
                            )}
                        </article>
                    );
                })}
            </div>
        </section>
    );
}
