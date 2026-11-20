
import { useCallback, useState } from 'react';

import {
    runnerApi,
    type RunnerMarketplaceRequest,
    type RunnerQuotation,
} from '../../api';

import { getLatestQuotation } from '../../utils/runnerFormatters';

interface UseRunnerMarketplaceResult {
    requests: RunnerMarketplaceRequest[];
    requestsLoading: boolean;
    requestsError: string;
    quotationRequestId: string | null;
    quotationAmount: string;
    quotationMessage: string;
    quotationLoading: boolean;
    quotationDetailLoading: string;
    existingQuotation: RunnerQuotation | null;
    loadRequests: () => Promise<void>;
    openQuotation: (requestId: string) => Promise<void>;
    closeQuotation: () => void;
    setQuotationAmount: (value: string) => void;
    setQuotationMessage: (value: string) => void;
    submitQuotation: () => Promise<boolean>;
}

export function useRunnerMarketplace(): UseRunnerMarketplaceResult {
    const [requests, setRequests] = useState<RunnerMarketplaceRequest[]>([]);
    const [requestsLoading, setRequestsLoading] = useState(false);
    const [requestsError, setRequestsError] = useState('');

    const [quotationRequestId, setQuotationRequestId] =
        useState<string | null>(null);

    const [quotationAmount, setQuotationAmount] = useState('');
    const [quotationMessage, setQuotationMessage] = useState('');

    const [quotationLoading, setQuotationLoading] = useState(false);
    const [quotationDetailLoading, setQuotationDetailLoading] = useState('');

    const [existingQuotation, setExistingQuotation] =
        useState<RunnerQuotation | null>(null);

    const loadRequests = useCallback(async () => {
        setRequestsLoading(true);
        setRequestsError('');

        try {
            const result = await runnerApi.getAvailableRequests();
            setRequests(result.requests);
        } catch {
            setRequestsError(
                'Tidak berjaya mendapatkan Open Request. Sila cuba semula.',
            );
        } finally {
            setRequestsLoading(false);
        }
    }, []);

    const closeQuotation = useCallback(() => {
        setQuotationRequestId(null);
        setExistingQuotation(null);
        setQuotationAmount('');
        setQuotationMessage('');
    }, []);

    const openQuotation = useCallback(async (requestId: string) => {
        if (quotationDetailLoading || quotationLoading) {
            return;
        }

        if (quotationRequestId === requestId) {
            closeQuotation();
            return;
        }

        setQuotationDetailLoading(requestId);
        setExistingQuotation(null);
        setQuotationAmount('');
        setQuotationMessage('');

        try {
            const detail = await runnerApi.getRequest(requestId);

            setExistingQuotation(
                getLatestQuotation(detail.my_quotations ?? []),
            );

            setQuotationRequestId(requestId);
        } catch {
            setQuotationRequestId(null);
            setExistingQuotation(null);
            setRequestsError(
                'Tidak berjaya mendapatkan status sebut harga. Sila cuba semula.',
            );
        } finally {
            setQuotationDetailLoading('');
        }
    }, [
        quotationDetailLoading,
        quotationLoading,
        quotationRequestId,
        closeQuotation,
    ]);

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
            await runnerApi.submitQuotation(
                submittedRequestId,
                {
                    amount,
                    currency: 'MYR',
                    message: quotationMessage.trim() || null,
                },
            );

            setQuotationAmount('');
            setQuotationMessage('');

            await loadRequests();

            const detail = await runnerApi.getRequest(
                submittedRequestId,
            );

            setExistingQuotation(
                getLatestQuotation(detail.my_quotations ?? []),
            );

            setQuotationRequestId(submittedRequestId);

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

    return {
        requests,
        requestsLoading,
        requestsError,
        quotationRequestId,
        quotationAmount,
        quotationMessage,
        quotationLoading,
        quotationDetailLoading,
        existingQuotation,
        loadRequests,
        openQuotation,
        closeQuotation,
        setQuotationAmount,
        setQuotationMessage,
        submitQuotation,
    };
}
