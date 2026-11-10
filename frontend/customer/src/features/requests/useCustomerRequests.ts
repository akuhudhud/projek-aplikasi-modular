import {
    useCallback,
    useEffect,
    useState,
} from 'react';

import axios from 'axios';

import {
    customerApi,
    type CustomerAwardQuotationResult,
    type CustomerOwnMarketplaceRequest,
} from '../../api';

type CustomerRequestsState = {
    requests: CustomerOwnMarketplaceRequest[];
    currentPage: number;
    lastPage: number;
    total: number;
    loading: boolean;
    error: string;
    cancellingRequestId: string | null;
    actionMessage: string;
    actionError: string;
};

type CustomerRequestsActions = {
    setCurrentPage: (page: number) => void;
    refresh: () => void;
    cancelRequest: (requestId: string) => Promise<void>;
    handleQuotationAwarded: (
        requestId: string,
        result: CustomerAwardQuotationResult,
    ) => void;
    clearActionMessages: () => void;
};

export type CustomerRequests = CustomerRequestsState &
    CustomerRequestsActions;

type UseCustomerRequestsOptions = {
    enabled: boolean;
    onQuotationAwarded?: () => void;
};

export function useCustomerRequests({
    enabled,
    onQuotationAwarded,
}: UseCustomerRequestsOptions): CustomerRequests {
    const [requests, setRequests] = useState<
        CustomerOwnMarketplaceRequest[]
    >([]);

    const [currentPage, setCurrentPageState] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);

    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const [refreshKey, setRefreshKey] = useState(0);

    const [cancellingRequestId, setCancellingRequestId] =
        useState<string | null>(null);

    const [actionMessage, setActionMessage] = useState('');
    const [actionError, setActionError] = useState('');

    useEffect(() => {
        if (!enabled) {
            return;
        }

        let mounted = true;

        setLoading(true);
        setError('');

        customerApi.getRequests(currentPage)
            .then((result) => {
                if (!mounted) {
                    return;
                }

                setRequests(result.requests);
                setTotal(result.pagination.total);
                setLastPage(result.pagination.last_page);
            })
            .catch(() => {
                if (mounted) {
                    setError(
                        'Tidak berjaya memuatkan senarai Open Request.',
                    );
                }
            })
            .finally(() => {
                if (mounted) {
                    setLoading(false);
                }
            });

        return () => {
            mounted = false;
        };
    }, [enabled, currentPage, refreshKey]);

    const setCurrentPage = useCallback((page: number) => {
        setCurrentPageState(Math.max(1, page));
    }, []);

    const refresh = useCallback(() => {
        setRefreshKey((current) => current + 1);
    }, []);

    const clearActionMessages = useCallback(() => {
        setActionMessage('');
        setActionError('');
    }, []);

    const cancelRequest = useCallback(
        async (requestId: string): Promise<void> => {
            if (cancellingRequestId !== null) {
                return;
            }

            const confirmed = window.confirm(
                'Adakah anda pasti mahu membatalkan Open Request ini?',
            );

            if (!confirmed) {
                return;
            }

            setCancellingRequestId(requestId);
            setActionMessage('');
            setActionError('');

            try {
                const result = await customerApi.cancelRequest(
                    requestId,
                );

                setRequests((current) =>
                    current.map((request) =>
                        request.request_id === requestId
                            ? {
                                ...request,
                                status: result.status,
                                cancelled_at: result.cancelled_at,
                                updated_at: result.updated_at,
                            }
                            : request,
                    ),
                );

                setActionMessage(
                    'Open Request berjaya dibatalkan.',
                );
            } catch (requestError) {
                if (axios.isAxiosError(requestError)) {
                    setActionError(
                        requestError.response?.data?.message ??
                            'Tidak berjaya membatalkan Open Request.',
                    );
                } else {
                    setActionError(
                        'Ralat berlaku. Sila cuba semula.',
                    );
                }
            } finally {
                setCancellingRequestId(null);
            }
        },
        [cancellingRequestId],
    );

    const handleQuotationAwarded = useCallback(
        (
            requestId: string,
            result: CustomerAwardQuotationResult,
        ) => {
            setRequests((current) =>
                current.map((request) =>
                    request.request_id === requestId
                        ? {
                            ...request,
                            status: result.request.status,
                            awarded_at: result.request.awarded_at,
                            updated_at: result.request.updated_at,
                        }
                        : request,
                ),
            );

            setActionError('');

            setActionMessage(
                `Runner berjaya dipilih. Core Task: ${result.task.task_id}`,
            );

            onQuotationAwarded?.();
        },
        [onQuotationAwarded],
    );

    return {
        requests,
        currentPage,
        lastPage,
        total,
        loading,
        error,
        cancellingRequestId,
        actionMessage,
        actionError,
        setCurrentPage,
        refresh,
        cancelRequest,
        handleQuotationAwarded,
        clearActionMessages,
    };
}
