
import {
    act,
    renderHook,
    waitFor,
} from '@testing-library/react';

import {
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

import type {
    RunnerMarketplaceRequest,
    RunnerMarketplaceRequestDetail,
} from '../../../src/api';

const {
    mockGetRequest,
    mockGetAvailableRequests,
    mockSubmitQuotation,
} = vi.hoisted(() => ({
    mockGetRequest: vi.fn(),
    mockGetAvailableRequests: vi.fn(),
    mockSubmitQuotation: vi.fn(),
}));

vi.mock('../../../src/api', () => ({
    runnerApi: {
        getRequest: mockGetRequest,
        getAvailableRequests: mockGetAvailableRequests,
        submitQuotation: mockSubmitQuotation,
    },
}));

import {
    useRunnerMarketplace,
} from '../../../src/features/marketplace/useRunnerMarketplace';

const mockRequest: RunnerMarketplaceRequestDetail = {
    request_id: 'request-12345678',
    source_type: 'CUSTOMER',
    task_class: 'NON_DELIVERY',
    description: 'Urusan penghantaran dokumen.',
    pickup_location_snapshot: null,
    delivery_location_snapshot: null,
    task_location_snapshot: {
        address: 'Tuaran, Sabah',
    },
    status: 'OPEN',
    expires_at: '2026-11-21T10:00:00Z',
    created_at: '2026-11-20T10:00:00Z',
    my_quotations: [],
};

const secondMockRequest: RunnerMarketplaceRequestDetail = {
    ...mockRequest,
    request_id: 'request-87654321',
    description: 'Permintaan kedua.',
};

const mockQuotation = {
    quotation_id: 'quotation-12345678',
    request_id: 'request-12345678',
    runner_account_id: 10,
    amount: '15.00',
    currency: 'MYR',
    message: 'Penghantaran dokumen.',
    status: 'SUBMITTED',
    created_at: '2026-11-20T11:00:00Z',
};

const mockPagination = {
    current_page: 1,
    per_page: 25,
    total: 0,
    last_page: 1,
};

function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason?: unknown) => void;

    const promise = new Promise<T>((res, rej) => {
        resolve = res;
        reject = rej;
    });

    return { promise, resolve, reject };
}

function createRequest(
    requestId: string,
    description: string,
): RunnerMarketplaceRequest {
    return {
        request_id: requestId,
        source_type: 'CUSTOMER',
        task_class: 'NON_DELIVERY',
        description,
        pickup_location_snapshot: null,
        delivery_location_snapshot: null,
        task_location_snapshot: {
            address: 'Tuaran, Sabah',
        },
        status: 'OPEN',
        expires_at: '2026-11-21T10:00:00Z',
        created_at: '2026-11-20T10:00:00Z',
        my_quotations: [],
    };
}

describe('useRunnerMarketplace notification request', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('loads request details successfully', async () => {
        mockGetRequest.mockResolvedValueOnce(mockRequest);

        const { result } = renderHook(() =>
            useRunnerMarketplace(),
        );

        await act(async () => {
            await result.current.openNotificationRequest(
                'request-12345678',
            );
        });

        expect(mockGetRequest).toHaveBeenCalledWith(
            'request-12345678',
        );

        expect(result.current.notificationRequest).toEqual(
            mockRequest,
        );

        expect(result.current.notificationRequestError).toBe('');
        expect(result.current.notificationRequestLoading).toBe(false);
    });

    it.each([
        {
            scenario: 'request telah dibatalkan',
            error: new Error('Request cancelled'),
        },
        {
            scenario: 'request telah tamat tempoh',
            error: new Error('Request expired'),
        },
        {
            scenario: 'Runner tidak lagi mempunyai akses',
            error: new Error('Request unavailable'),
        },
    ])(
        'shows an error when $scenario',
        async ({ error }) => {
            mockGetRequest.mockRejectedValueOnce(error);

            const { result } = renderHook(() =>
                useRunnerMarketplace(),
            );

            await act(async () => {
                await result.current.openNotificationRequest(
                    'request-12345678',
                );
            });

            expect(mockGetRequest).toHaveBeenCalledWith(
                'request-12345678',
            );

            expect(result.current.notificationRequest).toBeNull();

            expect(
                result.current.notificationRequestError,
            ).toContain('Permintaan ini tidak dapat dibuka.');

            expect(
                result.current.notificationRequestError,
            ).toContain('dibatalkan');

            expect(result.current.notificationRequestLoading).toBe(false);
        },
    );

    it('clears the request error when the notification request is closed', async () => {
        mockGetRequest.mockRejectedValueOnce(
            new Error('Request unavailable'),
        );

        const { result } = renderHook(() =>
            useRunnerMarketplace(),
        );

        await act(async () => {
            await result.current.openNotificationRequest(
                'request-12345678',
            );
        });

        expect(
            result.current.notificationRequestError,
        ).not.toBe('');

        act(() => {
            result.current.closeNotificationRequest();
        });

        expect(result.current.notificationRequest).toBeNull();
        expect(result.current.notificationRequestError).toBe('');
    });

    it('does not reopen a notification request after it is closed while loading', async () => {
        const pendingRequest = deferred<RunnerMarketplaceRequestDetail>();

        mockGetRequest.mockReturnValueOnce(pendingRequest.promise);

        const { result } = renderHook(() =>
            useRunnerMarketplace(),
        );

        act(() => {
            void result.current.openNotificationRequest(
                'request-12345678',
            );
        });

        expect(result.current.notificationRequestLoading).toBe(true);

        act(() => {
            result.current.closeNotificationRequest();
        });

        expect(result.current.notificationRequestLoading).toBe(false);
        expect(result.current.notificationRequest).toBeNull();

        await act(async () => {
            pendingRequest.resolve(mockRequest);
            await pendingRequest.promise;
        });

        expect(result.current.notificationRequest).toBeNull();
        expect(result.current.notificationRequestLoading).toBe(false);
    });

    it('keeps the latest notification request when responses finish out of order', async () => {
        const firstRequest = deferred<RunnerMarketplaceRequestDetail>();
        const secondRequest = deferred<RunnerMarketplaceRequestDetail>();

        mockGetRequest
            .mockReturnValueOnce(firstRequest.promise)
            .mockReturnValueOnce(secondRequest.promise);

        const { result } = renderHook(() =>
            useRunnerMarketplace(),
        );

        act(() => {
            void result.current.openNotificationRequest(
                'request-12345678',
            );
        });

        act(() => {
            void result.current.openNotificationRequest(
                'request-87654321',
            );
        });

        expect(mockGetRequest).toHaveBeenCalledTimes(2);

        await act(async () => {
            secondRequest.resolve(secondMockRequest);
            await secondRequest.promise;
        });

        await waitFor(() => {
            expect(result.current.notificationRequest).toEqual(
                secondMockRequest,
            );
        });

        await act(async () => {
            firstRequest.resolve(mockRequest);
            await firstRequest.promise;
        });

        expect(result.current.notificationRequest).toEqual(
            secondMockRequest,
        );
        expect(result.current.notificationRequestLoading).toBe(false);
    });
});

describe('useRunnerMarketplace quotation request lifecycle', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it.each([
        {
            scenario: 'request telah dibatalkan sebelum borang dibuka',
            error: new Error('Request cancelled'),
        },
        {
            scenario: 'request telah tamat tempoh sebelum borang dibuka',
            error: new Error('Request expired'),
        },
    ])(
        'does not open quotation form when $scenario',
        async ({ error }) => {
            mockGetRequest.mockRejectedValueOnce(error);

            const { result } = renderHook(() =>
                useRunnerMarketplace(),
            );

            await act(async () => {
                await result.current.openQuotation(
                    'request-12345678',
                );
            });

            expect(mockGetRequest).toHaveBeenCalledWith(
                'request-12345678',
            );

            expect(result.current.quotationRequestId).toBeNull();
            expect(result.current.existingQuotation).toBeNull();
            expect(result.current.quotationDetailLoading).toBe('');

            expect(result.current.requestsError).toContain(
                'Tidak berjaya mendapatkan status sebut harga.',
            );
        },
    );

    it.each([
        {
            scenario: 'request dibatalkan ketika Runner mengisi sebut harga',
            error: new Error('Request cancelled'),
        },
        {
            scenario: 'request tamat tempoh ketika Runner mengisi sebut harga',
            error: new Error('Request expired'),
        },
    ])(
        'rejects quotation submission when $scenario',
        async ({ error }) => {
            mockGetRequest.mockResolvedValueOnce(mockRequest);
            mockSubmitQuotation.mockRejectedValueOnce(error);

            const { result } = renderHook(() =>
                useRunnerMarketplace(),
            );

            await act(async () => {
                await result.current.openQuotation(
                    'request-12345678',
                );
            });

            expect(result.current.quotationRequestId).toBe(
                'request-12345678',
            );

            act(() => {
                result.current.setQuotationAmount('15.00');
                result.current.setQuotationMessage(
                    'Penghantaran dokumen.',
                );
            });

            let submissionResult = true;

            await act(async () => {
                submissionResult =
                    await result.current.submitQuotation();
            });

            expect(mockSubmitQuotation).toHaveBeenCalledWith(
                'request-12345678',
                {
                    amount: 15,
                    currency: 'MYR',
                    message: 'Penghantaran dokumen.',
                },
            );

            expect(submissionResult).toBe(false);

            expect(result.current.requestsError).toContain(
                'Sebut harga tidak berjaya dihantar.',
            );

            expect(result.current.quotationLoading).toBe(false);
        },
    );

    it('keeps successful quotation submission successful without another detail request', async () => {
        mockGetRequest.mockResolvedValueOnce(mockRequest);
        mockSubmitQuotation.mockResolvedValueOnce(mockQuotation);

        mockGetAvailableRequests.mockResolvedValueOnce({
            requests: [],
            pagination: mockPagination,
        });

        const { result } = renderHook(() =>
            useRunnerMarketplace(),
        );

        await act(async () => {
            await result.current.openQuotation(
                'request-12345678',
            );
        });

        act(() => {
            result.current.setQuotationAmount('15.00');
            result.current.setQuotationMessage(
                'Penghantaran dokumen.',
            );
        });

        let submissionResult = false;

        await act(async () => {
            submissionResult =
                await result.current.submitQuotation();
        });

        expect(mockSubmitQuotation).toHaveBeenCalledWith(
            'request-12345678',
            {
                amount: 15,
                currency: 'MYR',
                message: 'Penghantaran dokumen.',
            },
        );

        expect(submissionResult).toBe(true);
        expect(mockGetRequest).toHaveBeenCalledTimes(1);

        expect(result.current.existingQuotation).toEqual(
            mockQuotation,
        );

        expect(result.current.quotationRequestId).toBe(
            'request-12345678',
        );

        expect(result.current.quotationAmount).toBe('');
        expect(result.current.quotationMessage).toBe('');
        expect(result.current.quotationLoading).toBe(false);
    });

    it('does not report submission failure when refreshing request list fails', async () => {
        mockGetRequest.mockResolvedValueOnce(mockRequest);
        mockSubmitQuotation.mockResolvedValueOnce(mockQuotation);

        mockGetAvailableRequests.mockRejectedValueOnce(
            new Error('Network error'),
        );

        const { result } = renderHook(() =>
            useRunnerMarketplace(),
        );

        await act(async () => {
            await result.current.openQuotation(
                'request-12345678',
            );
        });

        act(() => {
            result.current.setQuotationAmount('15.00');
        });

        let submissionResult = false;

        await act(async () => {
            submissionResult =
                await result.current.submitQuotation();
        });

        expect(submissionResult).toBe(true);
        expect(result.current.existingQuotation).toEqual(
            mockQuotation,
        );

        expect(result.current.requestsError).toContain(
            'Tidak berjaya mendapatkan Open Request.',
        );
    });

    it('does not open an old quotation detail after a newer request is selected', async () => {
        const firstRequest = deferred<RunnerMarketplaceRequestDetail>();
        const secondRequest = deferred<RunnerMarketplaceRequestDetail>();

        mockGetRequest
            .mockReturnValueOnce(firstRequest.promise)
            .mockReturnValueOnce(secondRequest.promise);

        const { result } = renderHook(() =>
            useRunnerMarketplace(),
        );

        act(() => {
            void result.current.openQuotation('request-12345678');
        });

        act(() => {
            void result.current.openQuotation('request-87654321');
        });

        expect(mockGetRequest).toHaveBeenCalledTimes(2);

        await act(async () => {
            secondRequest.resolve(secondMockRequest);
            await secondRequest.promise;
        });

        await waitFor(() => {
            expect(result.current.quotationRequestId).toBe(
                'request-87654321',
            );
        });

        await act(async () => {
            firstRequest.resolve(mockRequest);
            await firstRequest.promise;
        });

        expect(result.current.quotationRequestId).toBe(
            'request-87654321',
        );
        expect(result.current.quotationDetailLoading).toBe('');
    });

    it('does not reopen quotation details after closing while loading', async () => {
        const pendingRequest = deferred<RunnerMarketplaceRequestDetail>();

        mockGetRequest.mockReturnValueOnce(pendingRequest.promise);

        const { result } = renderHook(() =>
            useRunnerMarketplace(),
        );

        act(() => {
            void result.current.openQuotation('request-12345678');
        });

        expect(result.current.quotationDetailLoading).toBe(
            'request-12345678',
        );

        act(() => {
            result.current.closeQuotation();
        });

        expect(result.current.quotationRequestId).toBeNull();
        expect(result.current.quotationDetailLoading).toBe('');

        await act(async () => {
            pendingRequest.resolve(mockRequest);
            await pendingRequest.promise;
        });

        expect(result.current.quotationRequestId).toBeNull();
        expect(result.current.existingQuotation).toBeNull();
    });
});

describe('useRunnerMarketplace request list race conditions', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('keeps the latest Open Request list when responses finish out of order', async () => {
        const firstRequest = deferred<{
            requests: RunnerMarketplaceRequest[];
            pagination: typeof mockPagination;
        }>();

        const secondRequest = deferred<{
            requests: RunnerMarketplaceRequest[];
            pagination: typeof mockPagination;
        }>();

        mockGetAvailableRequests
            .mockReturnValueOnce(firstRequest.promise)
            .mockReturnValueOnce(secondRequest.promise);

        const { result } = renderHook(() =>
            useRunnerMarketplace(),
        );

        act(() => {
            void result.current.loadRequests(2);
        });

        act(() => {
            void result.current.loadRequests(3);
        });

        expect(mockGetAvailableRequests).toHaveBeenCalledTimes(2);

        const latestRequest = createRequest(
            'request-latest',
            'Permintaan terkini.',
        );

        await act(async () => {
            secondRequest.resolve({
                requests: [latestRequest],
                pagination: {
                    ...mockPagination,
                    current_page: 3,
                },
            });

            await secondRequest.promise;
        });

        await waitFor(() => {
            expect(result.current.requests).toEqual([latestRequest]);
            expect(result.current.requestsPagination.current_page).toBe(3);
        });

        await act(async () => {
            firstRequest.resolve({
                requests: [
                    createRequest('request-old', 'Permintaan lama.'),
                ],
                pagination: {
                    ...mockPagination,
                    current_page: 2,
                },
            });

            await firstRequest.promise;
        });

        expect(result.current.requests).toEqual([latestRequest]);
        expect(result.current.requestsPagination.current_page).toBe(3);
        expect(result.current.requestsLoading).toBe(false);
    });
});
