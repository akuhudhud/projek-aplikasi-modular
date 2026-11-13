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
} from '@testing-library/react';

import axios from 'axios';

const {
    deactivateAccountMock,
    deleteAccountMock,
} = vi.hoisted(() => ({
    deactivateAccountMock: vi.fn(),
    deleteAccountMock: vi.fn(),
}));

vi.mock('../../api', () => ({
    customerApi: {
        deactivateAccount: deactivateAccountMock,
        deleteAccount: deleteAccountMock,
    },
}));

import type { CustomerAccount } from '../../api';
import { useCustomerProfile } from './useCustomerProfile';

const account: CustomerAccount = {
    id: 'customer-001',
    profile_name: 'Nazim',
    phone: '0123456789',
    email: 'nazim@example.com',
    profile_picture: null,
    role: 'USER',
    status: 'ACTIVE',
};

describe('useCustomerProfile account lifecycle', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        deactivateAccountMock.mockResolvedValue(undefined);
        deleteAccountMock.mockResolvedValue(undefined);
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('deactivates account and terminates the session', async () => {
        const onAccountTerminated = vi.fn();

        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
                onAccountTerminated,
            }),
        );

        let success = false;

        await act(async () => {
            success = await result.current.deactivateAccount();
        });

        expect(success).toBe(true);
        expect(deactivateAccountMock).toHaveBeenCalledTimes(1);
        expect(onAccountTerminated).toHaveBeenCalledTimes(1);
        expect(result.current.accountActionError).toBe('');
        expect(result.current.accountActionSaving).toBe(false);
    });

    it('deletes account and terminates the session', async () => {
        const onAccountTerminated = vi.fn();

        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
                onAccountTerminated,
            }),
        );

        let success = false;

        await act(async () => {
            success = await result.current.deleteAccount();
        });

        expect(success).toBe(true);
        expect(deleteAccountMock).toHaveBeenCalledTimes(1);
        expect(onAccountTerminated).toHaveBeenCalledTimes(1);
        expect(result.current.accountActionError).toBe('');
        expect(result.current.accountActionSaving).toBe(false);
    });

    it('does not deactivate when account is missing', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account: null,
                onAccountUpdated: vi.fn(),
                onAccountTerminated: vi.fn(),
            }),
        );

        let success = true;

        await act(async () => {
            success = await result.current.deactivateAccount();
        });

        expect(success).toBe(false);
        expect(deactivateAccountMock).not.toHaveBeenCalled();
    });

    it('does not delete when account is missing', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account: null,
                onAccountUpdated: vi.fn(),
                onAccountTerminated: vi.fn(),
            }),
        );

        let success = true;

        await act(async () => {
            success = await result.current.deleteAccount();
        });

        expect(success).toBe(false);
        expect(deleteAccountMock).not.toHaveBeenCalled();
    });

    it('handles deactivation API errors', async () => {
        vi.spyOn(axios, 'isAxiosError').mockReturnValue(true);

        deactivateAccountMock.mockRejectedValue({
            response: {
                data: {
                    message: 'Akaun tidak boleh dinyahaktifkan.',
                },
            },
        });

        const onAccountTerminated = vi.fn();

        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
                onAccountTerminated,
            }),
        );

        let success = true;

        await act(async () => {
            success = await result.current.deactivateAccount();
        });

        expect(success).toBe(false);
        expect(onAccountTerminated).not.toHaveBeenCalled();
        expect(result.current.accountActionError).toBe(
            'Akaun tidak boleh dinyahaktifkan.',
        );
        expect(result.current.accountActionSaving).toBe(false);
    });

    it('handles deletion API errors', async () => {
        vi.spyOn(axios, 'isAxiosError').mockReturnValue(true);

        deleteAccountMock.mockRejectedValue({
            response: {
                data: {
                    message: 'Akaun tidak boleh dipadam.',
                },
            },
        });

        const onAccountTerminated = vi.fn();

        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
                onAccountTerminated,
            }),
        );

        let success = true;

        await act(async () => {
            success = await result.current.deleteAccount();
        });

        expect(success).toBe(false);
        expect(onAccountTerminated).not.toHaveBeenCalled();
        expect(result.current.accountActionError).toBe(
            'Akaun tidak boleh dipadam.',
        );
        expect(result.current.accountActionSaving).toBe(false);
    });

    it('uses fallback error when deactivation fails unexpectedly', async () => {
        deactivateAccountMock.mockRejectedValue(
            new Error('Unexpected error'),
        );

        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
                onAccountTerminated: vi.fn(),
            }),
        );

        await act(async () => {
            await result.current.deactivateAccount();
        });

        expect(result.current.accountActionError).toBe(
            'Tidak berjaya menyahaktifkan akaun. Sila cuba semula.',
        );
    });

    it('uses fallback error when deletion fails unexpectedly', async () => {
        deleteAccountMock.mockRejectedValue(
            new Error('Unexpected error'),
        );

        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
                onAccountTerminated: vi.fn(),
            }),
        );

        await act(async () => {
            await result.current.deleteAccount();
        });

        expect(result.current.accountActionError).toBe(
            'Tidak berjaya memadam akaun. Sila cuba semula.',
        );
    });

    it('clears account action errors when messages are cleared', async () => {
        vi.spyOn(axios, 'isAxiosError').mockReturnValue(true);

        deactivateAccountMock.mockRejectedValue({
            response: {
                data: {
                    message: 'Ralat ujian.',
                },
            },
        });

        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
                onAccountTerminated: vi.fn(),
            }),
        );

        await act(async () => {
            await result.current.deactivateAccount();
        });

        expect(result.current.accountActionError).toBe('Ralat ujian.');

        act(() => {
            result.current.clearMessages();
        });

        expect(result.current.accountActionError).toBe('');
    });
});
