
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

import {
    customerApi,
    type CustomerAccount,
} from '../../api';

import { useCustomerProfile } from './useCustomerProfile';

vi.mock('../../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../../api')>();

    return {
        ...actual,
        customerApi: {
            ...actual.customerApi,
            requestPhoneChangeOtp: vi.fn(),
            verifyPhoneChangeOtp: vi.fn(),
            completePhoneChange: vi.fn(),
            requestEmailChangeOtp: vi.fn(),
            verifyEmailChangeOtp: vi.fn(),
            completeEmailChange: vi.fn(),
        },
    };
});

const account: CustomerAccount = {
    id: 1,
    profile_name: 'Nazim',
    phone: '0123456789',
    email: 'nazim@example.com',
    profile_picture: null,
    role: 'CUSTOMER',
    status: 'active',
};

const updatedAccount: CustomerAccount = {
    ...account,
    phone: '60123456789',
    email: 'new@example.com',
};

describe('useCustomerProfile - pertukaran telefon dan e-mel', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        vi.mocked(customerApi.requestPhoneChangeOtp).mockResolvedValue({
            verification_id: 'phone-verification-001',
            expires_at: '2026-11-13T18:00:00+08:00',
        });

        vi.mocked(customerApi.verifyPhoneChangeOtp).mockResolvedValue({
            verification_token: 'phone-token-001',
            expires_at: '2026-11-13T18:00:00+08:00',
        });

        vi.mocked(customerApi.completePhoneChange).mockResolvedValue(
            updatedAccount,
        );

        vi.mocked(customerApi.requestEmailChangeOtp).mockResolvedValue({
            verification_id: 'email-verification-001',
            expires_at: '2026-11-13T18:00:00+08:00',
        });

        vi.mocked(customerApi.verifyEmailChangeOtp).mockResolvedValue({
            verification_token: 'email-token-001',
            expires_at: '2026-11-13T18:00:00+08:00',
        });

        vi.mocked(customerApi.completeEmailChange).mockResolvedValue(
            updatedAccount,
        );
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('meminta OTP untuk nombor telefon baharu', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
            }),
        );

        let success = false;

        await act(async () => {
            success = await result.current.requestPhoneChange({
                phone: '60123456789',
            });
        });

        expect(success).toBe(true);

        expect(customerApi.requestPhoneChangeOtp).toHaveBeenCalledWith({
            phone: '60123456789',
        });

        expect(
            result.current.phoneChangeVerification?.verification_id,
        ).toBe('phone-verification-001');

        expect(result.current.contactSaving).toBe(false);
    });

    it('mengesahkan OTP telefon menggunakan verification ID sebenar', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
            }),
        );

        await act(async () => {
            await result.current.requestPhoneChange({
                phone: '60123456789',
            });
        });

        let success = false;

        await act(async () => {
            success = await result.current.verifyPhoneChange({
                verification_id: 'phone-verification-001',
                otp: '123456',
            });
        });

        expect(success).toBe(true);

        expect(customerApi.verifyPhoneChangeOtp).toHaveBeenCalledWith({
            verification_id: 'phone-verification-001',
            otp: '123456',
        });

        expect(result.current.phoneChangeToken?.verification_token).toBe(
            'phone-token-001',
        );
    });

    it('mengemaskini nombor telefon selepas OTP disahkan', async () => {
        const onAccountUpdated = vi.fn();

        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated,
            }),
        );

        await act(async () => {
            await result.current.requestPhoneChange({
                phone: '60123456789',
            });

            await result.current.verifyPhoneChange({
                verification_id: 'phone-verification-001',
                otp: '123456',
            });
        });

        let success = false;

        await act(async () => {
            success = await result.current.completePhoneChange();
        });

        expect(success).toBe(true);

        expect(customerApi.completePhoneChange).toHaveBeenCalledWith({
            verification_token: 'phone-token-001',
        });

        expect(onAccountUpdated).toHaveBeenCalledWith(updatedAccount);

        expect(result.current.phoneChangeVerification).toBeNull();
        expect(result.current.phoneChangeToken).toBeNull();

        expect(result.current.contactMessage).toBe(
            'Nombor telefon berjaya dikemaskini.',
        );
    });

    it('meminta OTP untuk e-mel baharu', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
            }),
        );

        let success = false;

        await act(async () => {
            success = await result.current.requestEmailChange({
                email: 'new@example.com',
            });
        });

        expect(success).toBe(true);

        expect(customerApi.requestEmailChangeOtp).toHaveBeenCalledWith({
            email: 'new@example.com',
        });

        expect(
            result.current.emailChangeVerification?.verification_id,
        ).toBe('email-verification-001');
    });

    it('mengesahkan OTP e-mel menggunakan verification ID sebenar', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
            }),
        );

        await act(async () => {
            await result.current.requestEmailChange({
                email: 'new@example.com',
            });
        });

        let success = false;

        await act(async () => {
            success = await result.current.verifyEmailChange({
                verification_id: 'email-verification-001',
                otp: '123456',
            });
        });

        expect(success).toBe(true);

        expect(customerApi.verifyEmailChangeOtp).toHaveBeenCalledWith({
            verification_id: 'email-verification-001',
            otp: '123456',
        });

        expect(result.current.emailChangeToken?.verification_token).toBe(
            'email-token-001',
        );
    });

    it('mengemaskini e-mel selepas OTP disahkan', async () => {
        const onAccountUpdated = vi.fn();

        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated,
            }),
        );

        await act(async () => {
            await result.current.requestEmailChange({
                email: 'new@example.com',
            });

            await result.current.verifyEmailChange({
                verification_id: 'email-verification-001',
                otp: '123456',
            });
        });

        let success = false;

        await act(async () => {
            success = await result.current.completeEmailChange();
        });

        expect(success).toBe(true);

        expect(customerApi.completeEmailChange).toHaveBeenCalledWith({
            verification_token: 'email-token-001',
        });

        expect(onAccountUpdated).toHaveBeenCalledWith(updatedAccount);

        expect(result.current.emailChangeVerification).toBeNull();
        expect(result.current.emailChangeToken).toBeNull();

        expect(result.current.contactMessage).toBe(
            'E-mel berjaya dikemaskini.',
        );
    });

    it('tidak membenarkan pertukaran telefon tanpa akaun', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account: null,
                onAccountUpdated: vi.fn(),
            }),
        );

        let success = true;

        await act(async () => {
            success = await result.current.requestPhoneChange({
                phone: '60123456789',
            });
        });

        expect(success).toBe(false);
        expect(customerApi.requestPhoneChangeOtp).not.toHaveBeenCalled();
    });

    it('tidak membenarkan pertukaran e-mel tanpa akaun', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account: null,
                onAccountUpdated: vi.fn(),
            }),
        );

        let success = true;

        await act(async () => {
            success = await result.current.requestEmailChange({
                email: 'new@example.com',
            });
        });

        expect(success).toBe(false);
        expect(customerApi.requestEmailChangeOtp).not.toHaveBeenCalled();
    });

    it('tidak melengkapkan pertukaran telefon tanpa token pengesahan', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
            }),
        );

        let success = true;

        await act(async () => {
            success = await result.current.completePhoneChange();
        });

        expect(success).toBe(false);
        expect(customerApi.completePhoneChange).not.toHaveBeenCalled();
    });

    it('tidak melengkapkan pertukaran e-mel tanpa token pengesahan', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
            }),
        );

        let success = true;

        await act(async () => {
            success = await result.current.completeEmailChange();
        });

        expect(success).toBe(false);
        expect(customerApi.completeEmailChange).not.toHaveBeenCalled();
    });

    it('membersihkan status pengesahan apabila clearContactChange dipanggil', async () => {
        const { result } = renderHook(() =>
            useCustomerProfile({
                account,
                onAccountUpdated: vi.fn(),
            }),
        );

        await act(async () => {
            await result.current.requestPhoneChange({
                phone: '60123456789',
            });

            await result.current.requestEmailChange({
                email: 'new@example.com',
            });
        });

        act(() => {
            result.current.clearContactChange();
        });

        expect(result.current.phoneChangeVerification).toBeNull();
        expect(result.current.emailChangeVerification).toBeNull();
        expect(result.current.phoneChangeToken).toBeNull();
        expect(result.current.emailChangeToken).toBeNull();
    });
});
