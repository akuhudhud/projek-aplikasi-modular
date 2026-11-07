import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { CustomerAccount } from './api';

const { patchMock, postMock } = vi.hoisted(() => ({
    patchMock: vi.fn(),
    postMock: vi.fn(),
}));

vi.mock('axios', () => ({
    default: {
        create: vi.fn(() => ({
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
