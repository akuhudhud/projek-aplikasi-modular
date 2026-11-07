
import { useState } from 'react';
import axios from 'axios';
import {
    customerApi,
    type CustomerMarketplaceQuotation,
    type CustomerMarketplaceRequestDetail,
} from './api';

interface CustomerRequestQuotationsProps {
    requestId: string;
    requestStatus: string;
    onAwarded: (
        requestId: string,
        result: Awaited<ReturnType<typeof customerApi.awardQuotation>>,
    ) => void;
}

function formatDate(value: string | null): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat('ms-MY', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatAmount(
    amount: string | number,
    currency: string,
): string {
    const numericAmount = Number(amount);

    if (!Number.isFinite(numericAmount)) {
        return `${amount} ${currency}`;
    }

    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency,
    }).format(numericAmount);
}

export default function CustomerRequestQuotations({
    requestId,
    requestStatus,
    onAwarded,
}: CustomerRequestQuotationsProps) {
    const [expanded, setExpanded] = useState(false);
    const [loading, setLoading] = useState(false);
    const [detail, setDetail] =
        useState<CustomerMarketplaceRequestDetail | null>(null);
    const [error, setError] = useState('');
    const [awardingQuotationId, setAwardingQuotationId] =
        useState<string | null>(null);
    const [successMessage, setSuccessMessage] = useState('');

    async function loadQuotations() {
        setLoading(true);
        setError('');

        try {
            const result = await customerApi.getRequest(requestId);
            setDetail(result);
            setExpanded(true);
        } catch (requestError) {
            if (axios.isAxiosError(requestError)) {
                setError(
                    requestError.response?.data?.message ??
                    'Tidak berjaya memuatkan sebut harga.',
                );
            } else {
                setError('Tidak berjaya memuatkan sebut harga.');
            }
        } finally {
            setLoading(false);
        }
    }

    async function handleAwardQuotation(
        quotation: CustomerMarketplaceQuotation,
    ) {
        if (requestStatus !== 'OPEN') {
            setError('Open Request ini tidak lagi boleh menerima pilihan Runner.');
            return;
        }

        const confirmed = window.confirm(
            `Pilih Runner ${quotation.runner_account_id} dengan harga ` +
            `${formatAmount(quotation.amount, quotation.currency)}? ` +
            'Selepas disahkan, sistem akan mencipta Core Task.',
        );

        if (!confirmed) return;

        setAwardingQuotationId(quotation.quotation_id);
        setError('');
        setSuccessMessage('');

        try {
            const result = await customerApi.awardQuotation(
                requestId,
                quotation.quotation_id,
            );

            setSuccessMessage(
                `Runner berjaya dipilih. ID Core Task: ${result.task.task_id}`,
            );

            onAwarded(requestId, result);

            const refreshed = await customerApi.getRequest(requestId);
            setDetail(refreshed);
        } catch (requestError) {
            if (axios.isAxiosError(requestError)) {
                setError(
                    requestError.response?.data?.message ??
                    'Tidak berjaya memilih Runner. Sila cuba semula.',
                );
            } else {
                setError('Tidak berjaya memilih Runner. Sila cuba semula.');
            }
        } finally {
            setAwardingQuotationId(null);
        }
    }

    return (
        <section className="customer-quotation-section">
            <button
                type="button"
                disabled={loading}
                onClick={() => {
                    if (expanded) {
                        setExpanded(false);
                        return;
                    }

                    void loadQuotations();
                }}
            >
                {loading
                    ? 'Memuatkan sebut harga...'
                    : expanded
                        ? 'Tutup Sebut Harga'
                        : 'Lihat Sebut Harga'}
            </button>

            {error && (
                <p role="alert">
                    {error}
                </p>
            )}

            {successMessage && (
                <p
                    className="customer-request-success"
                    role="status"
                >
                    {successMessage}
                </p>
            )}

            {expanded && detail && (
                <div className="customer-quotation-list">
                    <h4>Sebut Harga Runner</h4>

                    {detail.quotations.length === 0 ? (
                        <p>
                            Belum ada Runner menghantar sebut harga.
                        </p>
                    ) : (
                        detail.quotations.map((quotation) => {
                            const canAward =
                                detail.request.status === 'OPEN' &&
                                quotation.status === 'SUBMITTED';

                            return (
                                <article
                                    className="customer-quotation-card"
                                    key={quotation.quotation_id}
                                >
                                    <strong>
                                        {formatAmount(
                                            quotation.amount,
                                            quotation.currency,
                                        )}
                                    </strong>

                                    <span>
                                        ID Runner: {quotation.runner_account_id}
                                    </span>

                                    <span>
                                        Status: {quotation.status}
                                    </span>

                                    {quotation.message && (
                                        <p>{quotation.message}</p>
                                    )}

                                    <small>
                                        Dihantar: {formatDate(quotation.created_at)}
                                    </small>

                                    {quotation.expires_at && (
                                        <small>
                                            Tamat: {formatDate(quotation.expires_at)}
                                        </small>
                                    )}

                                    {canAward && (
                                        <button
                                            type="button"
                                            disabled={awardingQuotationId !== null}
                                            onClick={() => {
                                                void handleAwardQuotation(quotation);
                                            }}
                                        >
                                            {awardingQuotationId === quotation.quotation_id
                                                ? 'Memproses pilihan...'
                                                : 'Pilih Runner Ini'}
                                        </button>
                                    )}

                                    {quotation.status === 'ACCEPTED' && (
                                        <p role="status">
                                            Sebut harga ini telah dipilih.
                                        </p>
                                    )}
                                </article>
                            );
                        })
                    )}
                </div>
            )}
        </section>
    );
}
