
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

import axios from 'axios';

const {
    updateProfileMock,
} = vi.hoisted(() => ({
    updateProfileMock: vi.fn(),
}));

vi.mock('../../api', () => ({
    customerApi: {
        updateProfile: updateProfileMock,
    },
}));

import type { CustomerAccount } from '../../api';
import { useCustomerProfile } from './useCustomerProfile';

function makeAccount(
    overrides: Partial<CustomerAccount> = {},
): CustomerAccount {
    return {
        id: 1,
        name: 'Nazim',
        phone: '0123456789',
        email: 'nazim@example.com',
        profile_picture: null,
        role: 'CUSTOMER',
        status: 'active',
        ...overrides,
    };
}

describe('useCustomerProfile', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        updateProfileMock.mockResolvedValue(
            makeAccount({
                name: 'Nama Baharu',
            }),
        );
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('initializes profile name from account', async () => {
        const account = makeAccount({
            name: 'Nama Akaun',
        });

        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
            }),
        );

        await waitFor(() => {
            expect(result.current.profileName).toBe(
                'Nama Akaun',
            );
        });
    });

    it('uses an empty name when account is null', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account: null,
                onAccountUpdated: vi.fn(),
            }),
        );

        await waitFor(() => {
            expect(result.current.profileName).toBe('');
        });
    });

    it('updates profile name when account changes', async () => {
        const onAccountUpdated = vi.fn();

        const { result, rerender } = renderHook(
            ({ account }) =>
                useCustomerProfile({
                    account,
                    onAccountUpdated,
                }),
            {
                initialProps: {
                    account: makeAccount({
                        id: 1,
                        name: 'Nama Lama',
                    }),
                },
            },
        );

        await waitFor(() => {
            expect(result.current.profileName).toBe(
                'Nama Lama',
            );
        });

        rerender({
            account: makeAccount({
                id: 2,
                name: 'Nama Baharu',
            }),
        });

        await waitFor(() => {
            expect(result.current.profileName).toBe(
                'Nama Baharu',
            );
        });
    });

    it('clears messages when profile name changes', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account: makeAccount(),
                onAccountUpdated: vi.fn(),
            }),
        );

        act(() => {
            result.current.setProfileName('Nama Baharu');
        });

        expect(result.current.profileName).toBe(
            'Nama Baharu',
        );
        expect(result.current.error).toBe('');
        expect(result.current.message).toBe('');
    });

    it('rejects an empty profile name', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account: makeAccount(),
                onAccountUpdated: vi.fn(),
            }),
        );

        act(() => {
            result.current.setProfileName('   ');
        });

        let success: boolean | undefined;

        await act(async () => {
            success = await result.current.updateProfile();
        });

        expect(success).toBe(false);
        expect(updateProfileMock).not.toHaveBeenCalled();
        expect(result.current.error).toBe(
            'Nama tidak boleh kosong.',
        );
        expect(result.current.message).toBe('');
        expect(result.current.saving).toBe(false);
    });

    it('does not update profile when account is null', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account: null,
                onAccountUpdated: vi.fn(),
            }),
        );

        let success: boolean | undefined;

        await act(async () => {
            success = await result.current.updateProfile();
        });

        expect(success).toBe(false);
        expect(updateProfileMock).not.toHaveBeenCalled();
    });

    it('updates profile and notifies account callback', async () => {
        const updatedAccount = makeAccount({
            name: 'Nama Baharu',
        });

        const onAccountUpdated = vi.fn();

        updateProfileMock.mockResolvedValue(
            updatedAccount,
        );

        const { result } = renderHook(() =>
            useCustomerProfile({
                account: makeAccount(),
                onAccountUpdated,
            }),
        );

        act(() => {
            result.current.setProfileName(
                '  Nama Baharu  ',
            );
        });

        let success: boolean | undefined;

        await act(async () => {
            success = await result.current.updateProfile();
        });

        expect(success).toBe(true);

        expect(updateProfileMock).toHaveBeenCalledWith({
            name: 'Nama Baharu',
        });

        expect(result.current.profileName).toBe(
            'Nama Baharu',
        );

        expect(result.current.message).toBe(
            'Profil berjaya dikemaskini.',
        );

        expect(result.current.error).toBe('');
        expect(result.current.saving).toBe(false);

        expect(onAccountUpdated).toHaveBeenCalledWith(
            updatedAccount,
        );
    });

    it('shows validation error from Axios response', async () => {
        const requestError = {
            response: {
                data: {
                    errors: {
                        name: [
                            'Nama telah digunakan.',
                        ],
                    },
                },
            },
        };

        vi.spyOn(axios, 'isAxiosError')
            .mockReturnValue(true);

        updateProfileMock.mockRejectedValue(
            requestError,
        );

        const { result } = renderHook(() =>
            useCustomerProfile({
                account: makeAccount(),
                onAccountUpdated: vi.fn(),
            }),
        );

        act(() => {
            result.current.setProfileName('Nama Baharu');
        });

        let success: boolean | undefined;

        await act(async () => {
            success = await result.current.updateProfile();
        });

        expect(success).toBe(false);

        expect(result.current.error).toBe(
            'Nama telah digunakan.',
        );

        expect(result.current.message).toBe('');
        expect(result.current.saving).toBe(false);
    });

    it('uses Axios response message when validation error is absent', async () => {
        const requestError = {
            response: {
                data: {
                    message: 'Permintaan tidak sah.',
                },
            },
        };

        vi.spyOn(axios, 'isAxiosError')
            .mockReturnValue(true);

        updateProfileMock.mockRejectedValue(
            requestError,
        );

        const { result } = renderHook(() =>
            useCustomerProfile({
                account: makeAccount(),
                onAccountUpdated: vi.fn(),
            }),
        );

        act(() => {
            result.current.setProfileName('Nama Baharu');
        });

        await act(async () => {
            await result.current.updateProfile();
        });

        expect(result.current.error).toBe(
            'Permintaan tidak sah.',
        );
    });

    it('uses default error for Axios error without response message', async () => {
        vi.spyOn(axios, 'isAxiosError')
            .mockReturnValue(true);

        updateProfileMock.mockRejectedValue({
            response: undefined,
        });

        const { result } = renderHook(() =>
            useCustomerProfile({
                account: makeAccount(),
                onAccountUpdated: vi.fn(),
            }),
        );

        act(() => {
            result.current.setProfileName('Nama Baharu');
        });

        await act(async () => {
            await result.current.updateProfile();
        });

        expect(result.current.error).toBe(
            'Tidak berjaya mengemaskini profil.',
        );
    });

    it('handles non-Axios errors', async () => {
        updateProfileMock.mockRejectedValue(
            new Error('Unexpected error'),
        );

        const { result } = renderHook(() =>
            useCustomerProfile({
                account: makeAccount(),
                onAccountUpdated: vi.fn(),
            }),
        );

        act(() => {
            result.current.setProfileName('Nama Baharu');
        });

        let success: boolean | undefined;

        await act(async () => {
            success = await result.current.updateProfile();
        });

        expect(success).toBe(false);

        expect(result.current.error).toBe(
            'Tidak berjaya mengemaskini profil.',
        );

        expect(result.current.saving).toBe(false);
    });

    it('clears error and success messages', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account: makeAccount(),
                onAccountUpdated: vi.fn(),
            }),
        );

        act(() => {
            result.current.setProfileName('');
        });

        await act(async () => {
            await result.current.updateProfile();
        });

        expect(result.current.error).not.toBe('');

        act(() => {
            result.current.clearMessages();
        });

        expect(result.current.error).toBe('');
        expect(result.current.message).toBe('');
    });
});
