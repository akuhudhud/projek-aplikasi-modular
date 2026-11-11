
import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import {
    customerApi,
    type CustomerMarketplaceQuotation,
    type CustomerMarketplaceRequestDetail,
} from './api';
import './CustomerRequestQuotations.css';

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

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('ms-MY', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function formatAmount(
    amount: string | number,
    currency: string,
): string {
    const numericAmount = Number(amount);

    if (!Number.isFinite(numericAmount)) {
        return `${amount} ${currency}`;
    }

    try {
        return new Intl.NumberFormat('ms-MY', {
            style: 'currency',
            currency,
        }).format(numericAmount);
    } catch {
        return `${numericAmount.toFixed(2)} ${currency}`;
    }
}

function getApiErrorMessage(
    error: unknown,
    fallback: string,
): string {
    if (axios.isAxiosError(error)) {
        const message = error.response?.data?.message;

        if (typeof message === 'string' && message.trim() !== '') {
            return message;
        }
    }

    return fallback;
}

function getRunnerDisplayName(
    quotation: CustomerMarketplaceQuotation,
): string {
    return quotation.runner_profile?.display_name?.trim()
        || 'Profil awam tidak tersedia';
}

function formatRunnerRating(
    quotation: CustomerMarketplaceQuotation,
): string {
    const profile = quotation.runner_profile;

    if (
        !profile ||
        profile.rating_average === null ||
        profile.rating_count === 0
    ) {
        return 'Belum dinilai';
    }

    return (
        `★ ${profile.rating_average.toFixed(1)} / 5 ` +
        `(${profile.rating_count} penilaian)`
    );
}

export default function CustomerRequestQuotations({
    requestId,
    requestStatus,
    onAwarded,
}: CustomerRequestQuotationsProps) {
    const requestIdRef = useRef(requestId);

    const [expanded, setExpanded] = useState(false);
    const [loading, setLoading] = useState(false);
    const [detail, setDetail] =
        useState<CustomerMarketplaceRequestDetail | null>(null);
    const [loadedRequestId, setLoadedRequestId] =
        useState<string | null>(null);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [awardingQuotationId, setAwardingQuotationId] =
        useState<string | null>(null);
    const [successMessage, setSuccessMessage] = useState('');

    requestIdRef.current = requestId;

    useEffect(() => {
        setExpanded(false);
        setLoading(false);
        setDetail(null);
        setLoadedRequestId(null);
        setError('');
        setNotice('');
        setAwardingQuotationId(null);
        setSuccessMessage('');
    }, [requestId]);

    async function loadQuotations() {
        const targetRequestId = requestId;

        setLoading(true);
        setError('');
        setNotice('');

        try {
            const result = await customerApi.getRequest(targetRequestId);

            if (requestIdRef.current !== targetRequestId) {
                return;
            }

            setDetail(result);
            setLoadedRequestId(targetRequestId);
            setExpanded(true);
        } catch (requestError) {
            if (requestIdRef.current !== targetRequestId) {
                return;
            }

            setError(
                getApiErrorMessage(
                    requestError,
                    'Tidak berjaya memuatkan sebut harga.',
                ),
            );
        } finally {
            if (requestIdRef.current === targetRequestId) {
                setLoading(false);
            }
        }
    }

    async function handleAwardQuotation(
        quotation: CustomerMarketplaceQuotation,
    ) {
        const targetRequestId = requestId;
        const currentDetail =
            loadedRequestId === targetRequestId ? detail : null;

        const currentStatus =
            currentDetail?.request.status ?? requestStatus;

        if (currentStatus !== 'OPEN') {
            setError(
                'Open Request ini tidak lagi boleh menerima pilihan Runner.',
            );
            return;
        }

        if (
            !currentDetail ||
            currentDetail.request.request_id !== targetRequestId
        ) {
            setError(
                'Sila muat semula senarai sebut harga sebelum memilih Runner.',
            );
            return;
        }

        const currentQuotation = currentDetail.quotations.find(
            (item) => item.quotation_id === quotation.quotation_id,
        );

        if (
            !currentQuotation ||
            currentQuotation.status !== 'SUBMITTED'
        ) {
            setError(
                'Sebut harga ini tidak lagi tersedia untuk dipilih.',
            );
            return;
        }

        if (awardingQuotationId !== null) {
            return;
        }

        const runnerName = getRunnerDisplayName(currentQuotation);

        const confirmed = window.confirm(
            `Pilih ${runnerName} dengan harga ` +
            `${formatAmount(
                currentQuotation.amount,
                currentQuotation.currency,
            )}? Selepas disahkan, sistem akan mencipta Core Task.`,
        );

        if (!confirmed) {
            return;
        }

        setAwardingQuotationId(currentQuotation.quotation_id);
        setError('');
        setNotice('');
        setSuccessMessage('');

        let awardResult:
            Awaited<ReturnType<typeof customerApi.awardQuotation>>;

        try {
            awardResult = await customerApi.awardQuotation(
                targetRequestId,
                currentQuotation.quotation_id,
            );
        } catch (requestError) {
            if (requestIdRef.current === targetRequestId) {
                setError(
                    getApiErrorMessage(
                        requestError,
                        'Tidak berjaya memilih Runner. Sila cuba semula.',
                    ),
                );

                setAwardingQuotationId(null);
            }

            return;
        }

        if (requestIdRef.current !== targetRequestId) {
            return;
        }

        setSuccessMessage(
            `Runner berjaya dipilih. ID Core Task: ${awardResult.task.task_id}`,
        );

        setDetail((current) => {
            if (
                !current ||
                current.request.request_id !== targetRequestId
            ) {
                return current;
            }

            return {
                ...current,
                request: {
                    ...current.request,
                    status: 'AWARDED',
                    winning_quotation_id: currentQuotation.quotation_id,
                },
                quotations: current.quotations.map((item) => ({
                    ...item,
                    status:
                        item.quotation_id === currentQuotation.quotation_id
                            ? 'ACCEPTED'
                            : item.status === 'SUBMITTED'
                                ? 'NOT_SELECTED'
                                : item.status,
                })),
            };
        });

        try {
            onAwarded(targetRequestId, awardResult);
        } catch {
            setNotice(
                'Core Task telah berjaya dicipta, tetapi paparan halaman utama gagal dikemas kini.',
            );
        }

        try {
            const refreshed = await customerApi.getRequest(
                targetRequestId,
            );

            if (requestIdRef.current !== targetRequestId) {
                return;
            }

            setDetail(refreshed);
            setLoadedRequestId(targetRequestId);
        } catch {
            if (requestIdRef.current === targetRequestId) {
                setNotice(
                    'Runner berjaya dipilih dan Core Task telah dicipta. ' +
                    'Status senarai belum dapat dikemas kini. ' +
                    'Tutup dan buka semula senarai untuk mendapatkan status terkini.',
                );
            }
        } finally {
            if (requestIdRef.current === targetRequestId) {
                setAwardingQuotationId(null);
            }
        }
    }

    const visibleDetail =
        loadedRequestId === requestId ? detail : null;

    return (
        <section className="customer-quotation-section">
            <button
                type="button"
                disabled={loading || awardingQuotationId !== null}
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

            {notice && (
                <p role="status">
                    {notice}
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

            {expanded && visibleDetail && (
                <div className="customer-quotation-list">
                    <h4>Sebut Harga Runner</h4>

                    {visibleDetail.quotations.length === 0 ? (
                        <p className="customer-quotation-empty">
                            Belum ada Runner menghantar sebut harga.
                        </p>
                    ) : (
                        visibleDetail.quotations.map((quotation) => {
                            const canAward =
                                visibleDetail.request.status === 'OPEN' &&
                                quotation.status === 'SUBMITTED';

                            const profile = quotation.runner_profile;

                            return (
                                <article
                                    className="customer-quotation-card"
                                    key={quotation.quotation_id}
                                >
                                    <div className="customer-quotation-card-header">
                                        <strong>
                                            {formatAmount(
                                                quotation.amount,
                                                quotation.currency,
                                            )}
                                        </strong>

                                        <span
                                            className={
                                                `customer-quotation-status ` +
                                                `status-${quotation.status.toLowerCase()}`
                                            }
                                        >
                                            {quotation.status}
                                        </span>
                                    </div>

                                    <div className="customer-quotation-runner-profile">
                                        <strong className="customer-quotation-runner">
                                            {getRunnerDisplayName(quotation)}
                                        </strong>

                                        {profile && (
                                            <>
                                                <span className="customer-quotation-location">
                                                    {profile.base_location && (
                                                        <span>
                                                            Lokasi asas: {profile.base_location}
                                                        </span>
                                                    )}

                                                    {profile.service_area && (
                                                        <span>
                                                            Kawasan servis: {profile.service_area}
                                                        </span>
                                                    )}
                                                </span>

                                                <span className="customer-quotation-rating">
                                                    {formatRunnerRating(quotation)}
                                                </span>

                                                <a
                                                    className="customer-quotation-profile-link"
                                                    href={`/me/${encodeURIComponent(profile.public_slug)}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    Lihat Profil Runner
                                                </a>
                                            </>
                                        )}

                                        {!profile && (
                                            <span className="customer-quotation-rating">
                                                Maklumat profil awam tidak tersedia.
                                            </span>
                                        )}
                                    </div>

                                    {quotation.message && (
                                        <p className="customer-quotation-message">
                                            {quotation.message}
                                        </p>
                                    )}

                                    <div className="customer-quotation-dates">
                                        <small>
                                            Dihantar: {formatDate(quotation.created_at)}
                                        </small>

                                        {quotation.expires_at && (
                                            <small>
                                                Tamat: {formatDate(quotation.expires_at)}
                                            </small>
                                        )}
                                    </div>

                                    {canAward && (
                                        <button
                                            className="customer-quotation-award-button"
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
                                        <p
                                            className="customer-quotation-accepted"
                                            role="status"
                                        >
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
