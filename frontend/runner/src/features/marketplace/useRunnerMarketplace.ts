
import {
    useCallback,
    useEffect,
    useRef,
    useState,
} from 'react';

import {
    runnerApi,
    type RunnerPagination,
    type RunnerMarketplaceRequest,
    type RunnerMarketplaceRequestDetail,
    type RunnerQuotation,
} from '../../api';

import { getLatestQuotation } from '../../utils/runnerFormatters';

const EMPTY_PAGINATION: RunnerPagination = {
    current_page: 1,
    per_page: 25,
    total: 0,
    last_page: 1,
};

interface UseRunnerMarketplaceResult {
    requests: RunnerMarketplaceRequest[];
    requestsPagination: RunnerPagination;
    requestsLoading: boolean;
    requestsError: string;
    quotationRequestId: string | null;
    quotationAmount: string;
    quotationMessage: string;
    quotationLoading: boolean;
    quotationDetailLoading: string;
    existingQuotation: RunnerQuotation | null;
    notificationRequest: RunnerMarketplaceRequestDetail | null;
    notificationRequestLoading: boolean;
    notificationRequestError: string;
    loadRequests: (page?: number) => Promise<void>;
    openQuotation: (requestId: string) => Promise<void>;
    closeQuotation: () => void;
    openNotificationRequest: (requestId: string) => Promise<void>;
    closeNotificationRequest: () => void;
    setQuotationAmount: (value: string) => void;
    setQuotationMessage: (value: string) => void;
    submitQuotation: () => Promise<boolean>;
}

export function useRunnerMarketplace(): UseRunnerMarketplaceResult {
    const [requests, setRequests] =
        useState<RunnerMarketplaceRequest[]>([]);

    const [requestsPagination, setRequestsPagination] =
        useState<RunnerPagination>(EMPTY_PAGINATION);

    const [requestsLoading, setRequestsLoading] = useState(false);
    const [requestsError, setRequestsError] = useState('');

    const [quotationRequestId, setQuotationRequestId] =
        useState<string | null>(null);

    const [quotationAmount, setQuotationAmount] = useState('');
    const [quotationMessage, setQuotationMessage] = useState('');

    const [quotationLoading, setQuotationLoading] = useState(false);
    const [quotationDetailLoading, setQuotationDetailLoading] =
        useState('');

    const [existingQuotation, setExistingQuotation] =
        useState<RunnerQuotation | null>(null);

    const [notificationRequest, setNotificationRequest] =
        useState<RunnerMarketplaceRequestDetail | null>(null);

    const [notificationRequestLoading, setNotificationRequestLoading] =
        useState(false);

    const [notificationRequestError, setNotificationRequestError] =
        useState('');

    // Independent request identities prevent stale responses
    // from overwriting newer list or detail state.
    const requestsRequestId = useRef(0);
    const quotationDetailRequestId = useRef(0);
    const notificationDetailRequestId = useRef(0);

    const loadRequests = useCallback(async (
        page = requestsPagination.current_page,
    ): Promise<void> => {
        const requestId = ++requestsRequestId.current;

        setRequestsLoading(true);
        setRequestsError('');

        try {
            const result = await runnerApi.getAvailableRequests(page);

            if (requestId !== requestsRequestId.current) {
                return;
            }

            setRequests(result.requests);
            setRequestsPagination(result.pagination);
        } catch {
            if (requestId !== requestsRequestId.current) {
                return;
            }

            setRequestsError(
                'Tidak berjaya mendapatkan Open Request. Sila cuba semula.',
            );
        } finally {
            if (requestId === requestsRequestId.current) {
                setRequestsLoading(false);
            }
        }
    }, [requestsPagination.current_page]);

    const closeQuotation = useCallback(() => {
        quotationDetailRequestId.current += 1;

        setQuotationRequestId(null);
        setQuotationDetailLoading('');
        setExistingQuotation(null);
        setQuotationAmount('');
        setQuotationMessage('');
    }, []);

    const openQuotation = useCallback(async (requestId: string) => {
        if (quotationLoading) {
            return;
        }

        if (quotationRequestId === requestId) {
            closeQuotation();
            return;
        }

        const requestSequence = ++quotationDetailRequestId.current;

        setQuotationDetailLoading(requestId);
        setQuotationRequestId(null);
        setExistingQuotation(null);
        setQuotationAmount('');
        setQuotationMessage('');
        setRequestsError('');

        try {
            const detail = await runnerApi.getRequest(requestId);

            if (
                requestSequence !== quotationDetailRequestId.current
            ) {
                return;
            }

            setExistingQuotation(
                getLatestQuotation(detail.my_quotations ?? []),
            );

            setQuotationRequestId(requestId);
        } catch {
            if (
                requestSequence !== quotationDetailRequestId.current
            ) {
                return;
            }

            setQuotationRequestId(null);
            setExistingQuotation(null);

            setRequestsError(
                'Tidak berjaya mendapatkan status sebut harga. Sila cuba semula.',
            );
        } finally {
            if (
                requestSequence === quotationDetailRequestId.current
            ) {
                setQuotationDetailLoading('');
            }
        }
    }, [
        quotationLoading,
        quotationRequestId,
        closeQuotation,
    ]);

    const openNotificationRequest = useCallback(async (
        requestId: string,
    ) => {
        const requestSequence = ++notificationDetailRequestId.current;

        setNotificationRequestLoading(true);
        setNotificationRequestError('');
        setNotificationRequest(null);

        closeQuotation();

        try {
            const detail = await runnerApi.getRequest(requestId);

            if (
                requestSequence !== notificationDetailRequestId.current
            ) {
                return;
            }

            setNotificationRequest(detail);
        } catch {
            if (
                requestSequence !== notificationDetailRequestId.current
            ) {
                return;
            }

            setNotificationRequestError(
                'Permintaan ini tidak dapat dibuka. Ia mungkin telah dibatalkan, tamat tempoh atau tidak lagi boleh diakses.',
            );
        } finally {
            if (
                requestSequence === notificationDetailRequestId.current
            ) {
                setNotificationRequestLoading(false);
            }
        }
    }, [closeQuotation]);

    const closeNotificationRequest = useCallback(() => {
        notificationDetailRequestId.current += 1;

        setNotificationRequest(null);
        setNotificationRequestLoading(false);
        setNotificationRequestError('');
    }, []);

    const submitQuotation = useCallback(async (): Promise<boolean> => {
        if (!quotationRequestId || quotationLoading) {
            return false;
        }

        const amount = Number(quotationAmount);

        if (!Number.isFinite(amount) || amount <= 0) {
            setRequestsError('Masukkan jumlah sebut harga yang sah.');
            return false;
        }

        const submittedRequestId = quotationRequestId;

        setQuotationLoading(true);
        setRequestsError('');

        try {
            const quotation = await runnerApi.submitQuotation(
                submittedRequestId,
                {
                    amount,
                    currency: 'MYR',
                    message: quotationMessage.trim() || null,
                },
            );

            setExistingQuotation(quotation);
            setQuotationAmount('');
            setQuotationMessage('');
            setQuotationRequestId(submittedRequestId);

            // A refresh failure must not turn a successful
            // quotation submission into a failed submission.
            try {
                await loadRequests();
            } catch {
                // loadRequests handles its own error state.
            }

            return true;
        } catch {
            setRequestsError(
                'Sebut harga tidak berjaya dihantar. Permintaan mungkin telah tamat tempoh atau anda sudah mempunyai sebut harga aktif.',
            );

            return false;
        } finally {
            setQuotationLoading(false);
        }
    }, [
        quotationRequestId,
        quotationLoading,
        quotationAmount,
        quotationMessage,
        loadRequests,
    ]);

    // Invalidate all outstanding requests when the hook unmounts.
    useEffect(() => {
        return () => {
            requestsRequestId.current += 1;
            quotationDetailRequestId.current += 1;
            notificationDetailRequestId.current += 1;
        };
    }, []);

    return {
        requests,
        requestsPagination,
        requestsLoading,
        requestsError,
        quotationRequestId,
        quotationAmount,
        quotationMessage,
        quotationLoading,
        quotationDetailLoading,
        existingQuotation,
        notificationRequest,
        notificationRequestLoading,
        notificationRequestError,
        loadRequests,
        openQuotation,
        closeQuotation,
        openNotificationRequest,
        closeNotificationRequest,
        setQuotationAmount,
        setQuotationMessage,
        submitQuotation,
    };
}
