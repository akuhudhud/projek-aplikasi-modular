
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { CustomerAccount } from './api';

const { patchMock } = vi.hoisted(() => ({
    patchMock: vi.fn(),
}));

vi.mock('axios', () => ({
    default: {
        create: vi.fn(() => ({
            patch: patchMock,
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
