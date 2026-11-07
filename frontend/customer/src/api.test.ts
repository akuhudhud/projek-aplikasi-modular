
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { CustomerAccount } from './api';

const { getMock, patchMock, postMock } = vi.hoisted(() => ({
    getMock: vi.fn(),
    patchMock: vi.fn(),
    postMock: vi.fn(),
}));

vi.mock('axios', () => ({
    default: {
        create: vi.fn(() => ({
            get: getMock,
            patch: patchMock,
            post: postMock,
        })),
    },
}));

import { customerApi } from './api';

const account: CustomerAccount = {
    id: 101,
    name: 'Nazim Updated',
    phone: '60123456789',
    email: 'nazim@example.com',
    profile_picture: null,
    role: 'customer',
    status: 'active',
};

describe('customerApi.updateProfile', () => {
    beforeEach(() => {
        patchMock.mockReset();
    });

    it('sends the profile update to the correct endpoint', async () => {
        patchMock.mockResolvedValue({
            data: {
                success: true,
                message: 'Profile updated successfully.',
                data: {
                    account,
                },
            },
        });

        await customerApi.updateProfile({
            name: 'Nazim Updated',
        });

        expect(patchMock).toHaveBeenCalledTimes(1);
        expect(patchMock).toHaveBeenCalledWith(
            '/web/auth/profile',
            {
                name: 'Nazim Updated',
            },
        );
    });

    it('returns the updated account from the API response', async () => {
        patchMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    account,
                },
            },
        });

        const result = await customerApi.updateProfile({
            name: 'Nazim Updated',
        });

        expect(result).toEqual(account);
    });

    it('propagates API errors to the caller', async () => {
        const apiError = new Error('Profile update failed.');

        patchMock.mockRejectedValue(apiError);

        await expect(
            customerApi.updateProfile({
                name: 'Nazim Updated',
            }),
        ).rejects.toBe(apiError);
    });
});

describe('customerApi.getRequest', () => {
    beforeEach(() => {
        getMock.mockReset();
    });

    it('loads request details and quotations', async () => {
        const detail = {
            request: {
                request_id: 'request-123',
                task_class: 'NON_DELIVERY',
                description: 'Bantu beli barang.',
                pickup_location_snapshot: null,
                delivery_location_snapshot: null,
                task_location_snapshot: {
                    address: 'Kedai',
                },
                status: 'OPEN',
                expires_at: '2026-11-07T12:00:00.000000Z',
                awarded_at: null,
                cancelled_at: null,
                created_at: '2026-11-07T10:00:00.000000Z',
                updated_at: '2026-11-07T10:00:00.000000Z',
                winning_quotation_id: null,
            },
            quotations: [
                {
                    quotation_id: 'quotation-123',
                    request_id: 'request-123',
                    runner_account_id: 'runner-123',
                    amount: '18.50',
                    currency: 'MYR',
                    message: 'Saya boleh siapkan.',
                    status: 'SUBMITTED',
                    expires_at: null,
                    accepted_at: null,
                    withdrawn_at: null,
                    created_at: '2026-11-07T10:30:00.000000Z',
                },
            ],
        };

        getMock.mockResolvedValue({
            data: {
                success: true,
                data: detail,
            },
        });

        const result = await customerApi.getRequest('request-123');

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock).toHaveBeenCalledWith(
            '/web/customer/requests/request-123',
        );
        expect(result).toEqual(detail);
    });

    it('encodes the request ID in the endpoint', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    request: {},
                    quotations: [],
                },
            },
        });

        await customerApi.getRequest('request/123');

        expect(getMock).toHaveBeenCalledWith(
            '/web/customer/requests/request%2F123',
        );
    });

    it('propagates API errors to the caller', async () => {
        const apiError = new Error('Request detail failed.');

        getMock.mockRejectedValue(apiError);

        await expect(
            customerApi.getRequest('request-123'),
        ).rejects.toBe(apiError);
    });
});

describe('customerApi.awardQuotation', () => {
    beforeEach(() => {
        postMock.mockReset();
    });

    it('sends the selected quotation to the award endpoint', async () => {
        postMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    request: {
                        request_id: 'request-123',
                        status: 'AWARDED',
                        winning_quotation_id: 'quotation-123',
                        awarded_at: '2026-11-07T11:00:00.000000Z',
                        updated_at: '2026-11-07T11:00:00.000000Z',
                    },
                    task: {
                        task_id: 'task-123',
                        module_key: 'Marketplace',
                        task_type: 'MARKETPLACE_SERVICE_REQUEST',
                        source_type: 'MARKETPLACE_REQUEST',
                        source_id: 'request-123',
                        assigned_account_id: 'runner-123',
                        generic_status: 'ASSIGNED',
                        assigned_at: '2026-11-07T11:00:00.000000Z',
                        created_at: '2026-11-07T11:00:00.000000Z',
                    },
                },
            },
        });

        await customerApi.awardQuotation(
            'request-123',
            'quotation-123',
        );

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(postMock).toHaveBeenCalledWith(
            '/web/customer/requests/request-123/award',
            {
                quotation_id: 'quotation-123',
            },
        );
    });

    it('returns the created Core Task data', async () => {
        const resultData = {
            request: {
                request_id: 'request-123',
                status: 'AWARDED',
                winning_quotation_id: 'quotation-123',
                awarded_at: '2026-11-07T11:00:00.000000Z',
                updated_at: '2026-11-07T11:00:00.000000Z',
            },
            task: {
                task_id: 'task-123',
                module_key: 'Marketplace',
                task_type: 'MARKETPLACE_SERVICE_REQUEST',
                source_type: 'MARKETPLACE_REQUEST',
                source_id: 'request-123',
                assigned_account_id: 'runner-123',
                generic_status: 'ASSIGNED',
                assigned_at: '2026-11-07T11:00:00.000000Z',
                created_at: '2026-11-07T11:00:00.000000Z',
            },
        };

        postMock.mockResolvedValue({
            data: {
                success: true,
                data: resultData,
            },
        });

        const result = await customerApi.awardQuotation(
            'request-123',
            'quotation-123',
        );

        expect(result).toEqual(resultData);
    });

    it('encodes the request ID in the endpoint', async () => {
        postMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    request: {},
                    task: {},
                },
            },
        });

        await customerApi.awardQuotation(
            'request/123',
            'quotation-123',
        );

        expect(postMock).toHaveBeenCalledWith(
            '/web/customer/requests/request%2F123/award',
            {
                quotation_id: 'quotation-123',
            },
        );
    });

    it('propagates API errors to the caller', async () => {
        const apiError = new Error('Award quotation failed.');

        postMock.mockRejectedValue(apiError);

        await expect(
            customerApi.awardQuotation(
                'request-123',
                'quotation-123',
            ),
        ).rejects.toBe(apiError);
    });
});

describe('customerApi.cancelRequest', () => {
    beforeEach(() => {
        postMock.mockReset();
    });

    it('sends cancellation to the correct endpoint', async () => {
        postMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    request: {
                        request_id: 'request-123',
                        status: 'CANCELLED',
                        cancelled_at: '2026-11-07T10:00:00.000000Z',
                        updated_at: '2026-11-07T10:00:00.000000Z',
                    },
                },
            },
        });

        await customerApi.cancelRequest('request-123');

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(postMock).toHaveBeenCalledWith(
            '/web/customer/requests/request-123/cancel',
        );
    });

    it('returns the cancelled request data', async () => {
        const cancelledRequest = {
            request_id: 'request-123',
            status: 'CANCELLED',
            cancelled_at: '2026-11-07T10:00:00.000000Z',
            updated_at: '2026-11-07T10:00:00.000000Z',
        };

        postMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    request: cancelledRequest,
                },
            },
        });

        const result = await customerApi.cancelRequest('request-123');

        expect(result).toEqual(cancelledRequest);
    });

    it('encodes the request ID in the endpoint', async () => {
        postMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    request: {
                        request_id: 'request/123',
                        status: 'CANCELLED',
                        cancelled_at: null,
                        updated_at: '2026-11-07T10:00:00.000000Z',
                    },
                },
            },
        });

        await customerApi.cancelRequest('request/123');

        expect(postMock).toHaveBeenCalledWith(
            '/web/customer/requests/request%2F123/cancel',
        );
    });

    it('propagates API errors to the caller', async () => {
        const apiError = new Error('Cancellation failed.');

        postMock.mockRejectedValue(apiError);

        await expect(
            customerApi.cancelRequest('request-123'),
        ).rejects.toBe(apiError);
    });
});
