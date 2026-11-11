
// @vitest-environment jsdom

import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

import {
    act,
    cleanup,
    renderHook,
    waitFor,
} from '@testing-library/react';

const {
    getRequestsMock,
    cancelRequestMock,
    isAxiosErrorMock,
} = vi.hoisted(() => ({
    getRequestsMock: vi.fn(),
    cancelRequestMock: vi.fn(),
    isAxiosErrorMock: vi.fn(() => false),
}));

vi.mock('axios', () => ({
    default: {
        isAxiosError: isAxiosErrorMock,
    },
}));

vi.mock('../../api', () => ({
    customerApi: {
        getRequests: getRequestsMock,
        cancelRequest: cancelRequestMock,
    },
}));

import { useCustomerRequests } from './useCustomerRequests';

function makeRequest(requestId = 'request-123') {
    const now = new Date().toISOString();

    return {
        request_id: requestId,
        task_class: 'NON_DELIVERY' as const,
        description: 'Bantu membeli barang.',
        pickup_location_snapshot: null,
        delivery_location_snapshot: null,
        task_location_snapshot: {
            address: 'Pekan Tuaran',
        },
        status: 'OPEN',
        expires_at: new Date(
            Date.now() + 60 * 60 * 1000,
        ).toISOString(),
        awarded_at: null,
        cancelled_at: null,
        created_at: now,
        updated_at: now,
    };
}

function makeListResult(requests = [makeRequest()]) {
    return {
        requests,
        pagination: {
            current_page: 1,
            per_page: 10,
            total: requests.length,
            last_page: 3,
        },
    };
}

describe('useCustomerRequests', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        getRequestsMock.mockResolvedValue(
            makeListResult(),
        );

        cancelRequestMock.mockResolvedValue({
            request_id: 'request-123',
            status: 'CANCELLED',
            cancelled_at: '2026-11-11T10:00:00.000Z',
            updated_at: '2026-11-11T10:00:00.000Z',
        });

        isAxiosErrorMock.mockReturnValue(false);
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('does not load requests when disabled', () => {
        const { result } = renderHook(() =>
            useCustomerRequests({
                enabled: false,
            }),
        );

        expect(getRequestsMock).not.toHaveBeenCalled();
        expect(result.current.requests).toEqual([]);
        expect(result.current.loading).toBe(false);
    });

    it('loads the customer request list and pagination', async () => {
        const requests = [
            makeRequest('request-1'),
            makeRequest('request-2'),
        ];

        getRequestsMock.mockResolvedValue(
            makeListResult(requests),
        );

        const { result } = renderHook(() =>
            useCustomerRequests({
                enabled: true,
            }),
        );

        expect(result.current.loading).toBe(true);

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(getRequestsMock).toHaveBeenCalledWith(1);
        expect(result.current.requests).toEqual(requests);
        expect(result.current.total).toBe(2);
        expect(result.current.lastPage).toBe(3);
        expect(result.current.error).toBe('');
    });

    it('changes page and prevents page number below one', async () => {
        const { result } = renderHook(() =>
            useCustomerRequests({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        act(() => {
            result.current.setCurrentPage(2);
        });

        await waitFor(() => {
            expect(getRequestsMock).toHaveBeenLastCalledWith(2);
        });

        expect(result.current.currentPage).toBe(2);

        act(() => {
            result.current.setCurrentPage(0);
        });

        await waitFor(() => {
            expect(getRequestsMock).toHaveBeenLastCalledWith(1);
        });

        expect(result.current.currentPage).toBe(1);
    });

    it('reloads the request list when refresh is called', async () => {
        const { result } = renderHook(() =>
            useCustomerRequests({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(getRequestsMock).toHaveBeenCalledTimes(1);
        });

        act(() => {
            result.current.refresh();
        });

        await waitFor(() => {
            expect(getRequestsMock).toHaveBeenCalledTimes(2);
        });
    });

    it('shows an error when loading requests fails', async () => {
        getRequestsMock.mockRejectedValue(
            new Error('Network error'),
        );

        const { result } = renderHook(() =>
            useCustomerRequests({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(result.current.error).toBe(
            'Tidak berjaya memuatkan senarai Open Request.',
        );
    });

    it('does not cancel when customer rejects confirmation', async () => {
        const confirmMock = vi
            .spyOn(window, 'confirm')
            .mockReturnValue(false);

        const { result } = renderHook(() =>
            useCustomerRequests({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        await act(async () => {
            await result.current.cancelRequest('request-123');
        });

        expect(confirmMock).toHaveBeenCalledWith(
            'Adakah anda pasti mahu membatalkan Open Request ini?',
        );

        expect(cancelRequestMock).not.toHaveBeenCalled();
        expect(result.current.cancellingRequestId).toBeNull();
    });

    it('cancels the request and updates its status', async () => {
        vi.spyOn(window, 'confirm').mockReturnValue(true);

        const { result } = renderHook(() =>
            useCustomerRequests({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        await act(async () => {
            await result.current.cancelRequest('request-123');
        });

        expect(cancelRequestMock).toHaveBeenCalledWith(
            'request-123',
        );

        expect(result.current.requests[0]).toEqual(
            expect.objectContaining({
                request_id: 'request-123',
                status: 'CANCELLED',
                cancelled_at: '2026-11-11T10:00:00.000Z',
                updated_at: '2026-11-11T10:00:00.000Z',
            }),
        );

        expect(result.current.actionMessage).toBe(
            'Open Request berjaya dibatalkan.',
        );

        expect(result.current.cancellingRequestId).toBeNull();
    });

    it('shows the API error message when cancellation fails', async () => {
        vi.spyOn(window, 'confirm').mockReturnValue(true);

        const apiError = {
            response: {
                data: {
                    message: 'Request cannot be cancelled.',
                },
            },
        };

        isAxiosErrorMock.mockReturnValue(true);
        cancelRequestMock.mockRejectedValue(apiError);

        const { result } = renderHook(() =>
            useCustomerRequests({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        await act(async () => {
            await result.current.cancelRequest('request-123');
        });

        expect(result.current.actionError).toBe(
            'Request cannot be cancelled.',
        );

        expect(result.current.cancellingRequestId).toBeNull();
    });

    it('shows a generic error when cancellation fails unexpectedly', async () => {
        vi.spyOn(window, 'confirm').mockReturnValue(true);

        cancelRequestMock.mockRejectedValue(
            new Error('Unexpected error'),
        );

        const { result } = renderHook(() =>
            useCustomerRequests({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        await act(async () => {
            await result.current.cancelRequest('request-123');
        });

        expect(result.current.actionError).toBe(
            'Ralat berlaku. Sila cuba semula.',
        );

        expect(result.current.cancellingRequestId).toBeNull();
    });

    it('updates request after quotation is awarded and creates Core Task message', async () => {
        const onQuotationAwarded = vi.fn();

        const { result } = renderHook(() =>
            useCustomerRequests({
                enabled: true,
                onQuotationAwarded,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        act(() => {
            result.current.handleQuotationAwarded(
                'request-123',
                {
                    request: {
                        request_id: 'request-123',
                        status: 'AWARDED',
                        winning_quotation_id: 'quotation-123',
                        awarded_at: '2026-11-11T11:00:00.000Z',
                        updated_at: '2026-11-11T11:00:00.000Z',
                    },
                    task: {
                        task_id: 'task-123',
                        module_key: 'Marketplace',
                        task_type: 'MARKETPLACE_SERVICE_REQUEST',
                        source_type: 'MARKETPLACE_REQUEST',
                        source_id: 'request-123',
                        generic_status: 'ASSIGNED',
                        assigned_at: '2026-11-11T11:00:00.000Z',
                        created_at: '2026-11-11T11:00:00.000Z',
                    },
                },
            );
        });

        expect(result.current.requests[0]).toEqual(
            expect.objectContaining({
                request_id: 'request-123',
                status: 'AWARDED',
                awarded_at: '2026-11-11T11:00:00.000Z',
                updated_at: '2026-11-11T11:00:00.000Z',
            }),
        );

        expect(result.current.actionMessage).toBe(
            'Runner berjaya dipilih. Core Task: task-123',
        );

        expect(onQuotationAwarded).toHaveBeenCalledTimes(1);
    });

    it('clears action messages', async () => {
        vi.spyOn(window, 'confirm').mockReturnValue(true);

        cancelRequestMock.mockRejectedValue(
            new Error('Unexpected error'),
        );

        const { result } = renderHook(() =>
            useCustomerRequests({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        await act(async () => {
            await result.current.cancelRequest('request-123');
        });

        expect(result.current.actionError).not.toBe('');

        act(() => {
            result.current.clearActionMessages();
        });

        expect(result.current.actionMessage).toBe('');
        expect(result.current.actionError).toBe('');
    });
});
