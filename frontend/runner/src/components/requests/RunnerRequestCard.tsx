
import type {
    RunnerMarketplaceRequest,
    RunnerQuotation,
} from '../../api';

import {
    formatAmount,
    formatDate,
    getRequestLocation,
    quotationStatusLabel,
    requestStatusLabel,
} from '../../utils/runnerFormatters';

import RunnerQuotationForm from './RunnerQuotationForm';

interface RunnerRequestCardProps {
    request: RunnerMarketplaceRequest;
    isQuotationOpen: boolean;
    isCheckingQuotation: boolean;
    quotationLoading: boolean;
    existingQuotation: RunnerQuotation | null;
    quotationAmount: string;
    quotationMessage: string;
    onOpenQuotation: (requestId: string) => void;
    onCloseQuotation: () => void;
    onAmountChange: (value: string) => void;
    onMessageChange: (value: string) => void;
    onSubmitQuotation: (
        event: React.FormEvent<HTMLFormElement>,
    ) => void;
}

export default function RunnerRequestCard({
    request,
    isQuotationOpen,
    isCheckingQuotation,
    quotationLoading,
    existingQuotation,
    quotationAmount,
    quotationMessage,
    onOpenQuotation,
    onCloseQuotation,
    onAmountChange,
    onMessageChange,
    onSubmitQuotation,
}: RunnerRequestCardProps) {
    const hasActiveQuotation =
        isQuotationOpen
        && existingQuotation?.status === 'SUBMITTED';

    return (
        <article className="runner-dashboard__item">
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
                    isCheckingQuotation
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
                    Sebut harga anda masih aktif dan sedang
                    menunggu keputusan pelanggan. Anda tidak
                    perlu menghantar sebut harga sekali lagi.
                </p>
            )}

            {isQuotationOpen && !hasActiveQuotation && (
                <RunnerQuotationForm
                    quotationAmount={quotationAmount}
                    quotationMessage={quotationMessage}
                    quotationLoading={quotationLoading}
                    hasPreviousQuotation={Boolean(existingQuotation)}
                    previousQuotationStatus={
                        existingQuotation
                            ? quotationStatusLabel(existingQuotation.status)
                            : ''
                    }
                    onAmountChange={onAmountChange}
                    onMessageChange={onMessageChange}
                    onClose={onCloseQuotation}
                    onSubmit={onSubmitQuotation}
                />
            )}
        </article>
    );
}
