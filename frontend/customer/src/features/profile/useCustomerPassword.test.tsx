
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
    changePasswordMock,
} = vi.hoisted(() => ({
    changePasswordMock: vi.fn(),
}));

vi.mock('../../api', () => ({
    customerApi: {
        changePassword: changePasswordMock,
    },
}));

import type {
    CustomerAccount,
    ChangePasswordPayload,
} from '../../api';

import { useCustomerProfile } from './useCustomerProfile';

const account: CustomerAccount = {
    id: 1,
    profile_name: 'Nazim',
    phone: '0123456789',
    email: 'nazim@example.com',
    profile_picture: null,
    role: 'CUSTOMER',
    status: 'active',
};

const validPayload: ChangePasswordPayload = {
    current_password: 'OldPass123',
    new_password: 'NewPass123',
    new_password_confirmation: 'NewPass123',
};

describe('useCustomerProfile - tukar kata laluan', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        changePasswordMock.mockResolvedValue(undefined);
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('menukar kata laluan dengan berjaya', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
            }),
        );

        let success = false;

        await act(async () => {
            success = await result.current.changePassword(
                validPayload,
            );
        });

        expect(success).toBe(true);

        expect(changePasswordMock).toHaveBeenCalledWith(
            validPayload,
        );

        expect(result.current.passwordSaving).toBe(false);

        expect(result.current.passwordError).toBe('');

        expect(result.current.passwordMessage).toBe(
            'Kata laluan berjaya ditukar. Sila log masuk semula.',
        );
    });

    it('menolak pengesahan kata laluan yang tidak sepadan', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
            }),
        );

        let success = true;

        await act(async () => {
            success = await result.current.changePassword({
                ...validPayload,
                new_password_confirmation: 'WrongPass123',
            });
        });

        expect(success).toBe(false);
        expect(changePasswordMock).not.toHaveBeenCalled();

        expect(result.current.passwordError).toBe(
            'Pengesahan kata laluan baharu tidak sepadan.',
        );
    });

    it('menolak kata laluan kurang daripada 8 aksara', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
            }),
        );

        let success = true;

        await act(async () => {
            success = await result.current.changePassword({
                ...validPayload,
                new_password: 'Abc123',
                new_password_confirmation: 'Abc123',
            });
        });

        expect(success).toBe(false);
        expect(changePasswordMock).not.toHaveBeenCalled();

        expect(result.current.passwordError).toBe(
            'Kata laluan baharu mesti antara 8 hingga 12 aksara.',
        );
    });

    it('menolak kata laluan melebihi 12 aksara', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
            }),
        );

        let success = true;

        await act(async () => {
            success = await result.current.changePassword({
                ...validPayload,
                new_password: 'LongPassword123',
                new_password_confirmation: 'LongPassword123',
            });
        });

        expect(success).toBe(false);
        expect(changePasswordMock).not.toHaveBeenCalled();

        expect(result.current.passwordError).toBe(
            'Kata laluan baharu mesti antara 8 hingga 12 aksara.',
        );
    });

    it('tidak menukar kata laluan apabila akaun tiada', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account: null,
                onAccountUpdated: vi.fn(),
            }),
        );

        let success = true;

        await act(async () => {
            success = await result.current.changePassword(
                validPayload,
            );
        });

        expect(success).toBe(false);
        expect(changePasswordMock).not.toHaveBeenCalled();
    });

    it('memaparkan ralat kata laluan semasa daripada backend', async () => {
        vi.spyOn(axios, 'isAxiosError')
            .mockReturnValue(true);

        changePasswordMock.mockRejectedValue({
            response: {
                data: {
                    errors: {
                        current_password: [
                            'Kata laluan semasa tidak betul.',
                        ],
                    },
                },
            },
        });

        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
            }),
        );

        let success = true;

        await act(async () => {
            success = await result.current.changePassword(
                validPayload,
            );
        });

        expect(success).toBe(false);

        expect(result.current.passwordError).toBe(
            'Kata laluan semasa tidak betul.',
        );

        expect(result.current.passwordSaving).toBe(false);
    });

    it('memaparkan ralat validasi kata laluan baharu daripada backend', async () => {
        vi.spyOn(axios, 'isAxiosError')
            .mockReturnValue(true);

        changePasswordMock.mockRejectedValue({
            response: {
                data: {
                    errors: {
                        new_password: [
                            'Kata laluan mesti mengandungi huruf besar, huruf kecil dan nombor.',
                        ],
                    },
                },
            },
        });

        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
            }),
        );

        await act(async () => {
            await result.current.changePassword(
                validPayload,
            );
        });

        expect(result.current.passwordError).toBe(
            'Kata laluan mesti mengandungi huruf besar, huruf kecil dan nombor.',
        );
    });

    it('menggunakan mesej backend apabila tiada ralat validasi', async () => {
        vi.spyOn(axios, 'isAxiosError')
            .mockReturnValue(true);

        changePasswordMock.mockRejectedValue({
            response: {
                data: {
                    message: 'Permintaan tidak dibenarkan.',
                },
            },
        });

        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
            }),
        );

        await act(async () => {
            await result.current.changePassword(
                validPayload,
            );
        });

        expect(result.current.passwordError).toBe(
            'Permintaan tidak dibenarkan.',
        );
    });

    it('memaparkan mesej lalai apabila permintaan gagal', async () => {
        changePasswordMock.mockRejectedValue(
            new Error('Network error'),
        );

        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
            }),
        );

        let success = true;

        await act(async () => {
            success = await result.current.changePassword(
                validPayload,
            );
        });

        expect(success).toBe(false);

        expect(result.current.passwordError).toBe(
            'Tidak berjaya menukar kata laluan. Sila cuba semula.',
        );

        expect(result.current.passwordSaving).toBe(false);
    });

    it('membersihkan mesej kata laluan apabila clearMessages dipanggil', async () => {
        changePasswordMock.mockRejectedValue(
            new Error('Network error'),
        );

        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
            }),
        );

        await act(async () => {
            await result.current.changePassword(
                validPayload,
            );
        });

        expect(result.current.passwordError).not.toBe('');

        act(() => {
            result.current.clearMessages();
        });

        expect(result.current.passwordError).toBe('');
        expect(result.current.passwordMessage).toBe('');
    });
});
