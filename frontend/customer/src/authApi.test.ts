import { beforeEach, describe, expect, it, vi } from 'vitest';

const {
    getMock,
    postMock,
    responseInterceptorUseMock,
} = vi.hoisted(() => ({
    getMock: vi.fn(),
    postMock: vi.fn(),
    responseInterceptorUseMock: vi.fn(),
}));

vi.mock('axios', () => ({
    default: {
        create: vi.fn(() => ({
            get: getMock,
            post: postMock,
            interceptors: {
                response: {
                    use: responseInterceptorUseMock,
                },
            },
        })),
        isAxiosError: vi.fn(() => false),
    },
}));

import { customerApi } from './api';

const account = {
    id: 101,
    profile_name: 'Customer Test',
    phone: '60123456789',
    email: 'customer@example.com',
    profile_picture: null,
    role: 'customer',
    status: 'active',
};

describe('customerApi authentication', () => {
    beforeEach(() => {
        getMock.mockReset();
        postMock.mockReset();
        responseInterceptorUseMock.mockReset();
    });

    it('logs in using the supplied credentials and returns the account', async () => {
        postMock.mockResolvedValue({
            data: {
                success: true,
                data: { account },
            },
        });

        const result = await customerApi.login({
            phone: '60123456789',
            password: 'Password123',
        });

        expect(postMock).toHaveBeenCalledWith('/web/auth/login', {
            phone: '60123456789',
            password: 'Password123',
        });
        expect(result).toEqual(account);
    });

    it('loads the current authenticated account', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: { account },
            },
        });

        await expect(customerApi.me()).resolves.toEqual(account);
        expect(getMock).toHaveBeenCalledWith('/web/auth/me');
    });

    it('logs out through the authentication endpoint', async () => {
        postMock.mockResolvedValue({ data: { success: true } });

        await expect(customerApi.logout()).resolves.toBeUndefined();
        expect(postMock).toHaveBeenCalledWith('/web/auth/logout');
    });

    it('requests a registration OTP with the exact registration payload', async () => {
        const payload = {
            channel: 'phone' as const,
            contact: '60123456789',
            purpose: 'REGISTRATION' as const,
        };
        const result = {
            verification_id: 'verification-001',
            expires_at: '2026-11-07T12:00:00.000Z',
        };

        postMock.mockResolvedValue({
            data: {
                success: true,
                data: result,
            },
        });

        await expect(
            customerApi.requestRegistrationOtp(payload),
        ).resolves.toEqual(result);

        expect(postMock).toHaveBeenCalledWith(
            '/web/auth/register/otp/request',
            payload,
        );
    });

    it('verifies a registration OTP and returns the verification token', async () => {
        const payload = {
            verification_id: 'verification-001',
            otp: '123456',
        };
        const result = {
            verification_token: 'verified-token',
            expires_at: '2026-11-07T12:00:00.000Z',
        };

        postMock.mockResolvedValue({
            data: {
                success: true,
                data: result,
            },
        });

        await expect(
            customerApi.verifyRegistrationOtp(payload),
        ).resolves.toEqual(result);

        expect(postMock).toHaveBeenCalledWith(
            '/web/auth/register/otp/verify',
            payload,
        );
    });

    it('completes registration and returns the new account', async () => {
        const payload = {
            verification_token: 'verified-token',
            profile_name: 'Customer Test',
            password: 'Password123',
            password_confirmation: 'Password123',
        };

        postMock.mockResolvedValue({
            data: {
                success: true,
                data: { account },
            },
        });

        await expect(
            customerApi.completeRegistration(payload),
        ).resolves.toEqual(account);

        expect(postMock).toHaveBeenCalledWith(
            '/web/auth/register/complete',
            payload,
        );
    });

    it('propagates authentication API errors to the caller', async () => {
        const apiError = new Error('Authentication request failed.');

        postMock.mockRejectedValue(apiError);

        await expect(
            customerApi.login({
                email: 'customer@example.com',
                password: 'Password123',
            }),
        ).rejects.toBe(apiError);
    });
});
