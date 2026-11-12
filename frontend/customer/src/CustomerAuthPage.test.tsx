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
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/react';

const {
    requestOtpMock,
    verifyOtpMock,
    completeRegistrationMock,
} = vi.hoisted(() => ({
    requestOtpMock: vi.fn(),
    verifyOtpMock: vi.fn(),
    completeRegistrationMock: vi.fn(),
}));

vi.mock('axios', () => ({
    default: {
        isAxiosError: vi.fn(() => false),
    },
}));

vi.mock('./api', () => ({
    customerApi: {
        login: vi.fn(),
        requestRegistrationOtp: requestOtpMock,
        verifyRegistrationOtp: verifyOtpMock,
        completeRegistration: completeRegistrationMock,
    },
}));

import CustomerAuthPage from './features/auth/CustomerAuthPage';

const account = {
    id: 101,
    profile_name: 'Customer Test',
    phone: '60123456789',
    email: 'customer@example.com',
    profile_picture: null,
    role: 'customer',
    status: 'active',
};

describe('Customer registration interface', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        requestOtpMock.mockResolvedValue({
            verification_id: 'verification-001',
            expires_at: '2026-11-10T12:00:00.000Z',
        });

        verifyOtpMock.mockResolvedValue({
            verification_token: 'verified-token',
            expires_at: '2026-11-10T12:00:00.000Z',
        });

        completeRegistrationMock.mockResolvedValue(account);
    });

    afterEach(() => {
        cleanup();
    });

    it('completes registration using phone and OTP', async () => {
        const onAuthenticated = vi.fn();

        render(
            <CustomerAuthPage
                onAuthenticated={onAuthenticated}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Daftar Akaun',
            }),
        );

        fireEvent.change(
            screen.getByLabelText('Nombor Telefon'),
            {
                target: {
                    value: '60123456789',
                },
            },
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Hantar Kod OTP',
            }),
        );

        await screen.findByLabelText('Kod OTP');

        expect(requestOtpMock).toHaveBeenCalledWith({
            channel: 'phone',
            contact: '60123456789',
            purpose: 'REGISTRATION',
        });

        fireEvent.change(
            screen.getByLabelText('Kod OTP'),
            {
                target: {
                    value: '123456',
                },
            },
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Sahkan OTP',
            }),
        );

        await screen.findByLabelText('Nama');

        expect(verifyOtpMock).toHaveBeenCalledWith({
            verification_id: 'verification-001',
            otp: '123456',
        });

        fireEvent.change(
            screen.getByLabelText('Nama'),
            {
                target: {
                    value: 'Customer Test',
                },
            },
        );

        fireEvent.change(
            screen.getByLabelText('Kata Laluan'),
            {
                target: {
                    value: 'Password123',
                },
            },
        );

        fireEvent.change(
            screen.getByLabelText('Sahkan Kata Laluan'),
            {
                target: {
                    value: 'Password123',
                },
            },
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Cipta Akaun',
            }),
        );

        await waitFor(() => {
            expect(
                completeRegistrationMock,
            ).toHaveBeenCalledWith({
                verification_token: 'verified-token',
                profile_name: 'Customer Test',
                password: 'Password123',
                password_confirmation: 'Password123',
            });
        });

        expect(onAuthenticated).toHaveBeenCalledWith(account);
    });

    it('requests registration OTP using email', async () => {
        const onAuthenticated = vi.fn();

        render(
            <CustomerAuthPage
                onAuthenticated={onAuthenticated}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Daftar Akaun',
            }),
        );

        fireEvent.change(
            screen.getByLabelText('Kaedah Pendaftaran'),
            {
                target: {
                    value: 'email',
                },
            },
        );

        fireEvent.change(
            screen.getByLabelText('Alamat E-mel'),
            {
                target: {
                    value: 'customer@example.com',
                },
            },
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Hantar Kod OTP',
            }),
        );

        await screen.findByLabelText('Kod OTP');

        expect(requestOtpMock).toHaveBeenCalledWith({
            channel: 'email',
            contact: 'customer@example.com',
            purpose: 'REGISTRATION',
        });

        expect(onAuthenticated).not.toHaveBeenCalled();
    });

    it('rejects mismatched passwords before completing registration', async () => {
        const onAuthenticated = vi.fn();

        render(
            <CustomerAuthPage
                onAuthenticated={onAuthenticated}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Daftar Akaun',
            }),
        );

        fireEvent.change(
            screen.getByLabelText('Nombor Telefon'),
            {
                target: {
                    value: '60123456789',
                },
            },
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Hantar Kod OTP',
            }),
        );

        await screen.findByLabelText('Kod OTP');

        fireEvent.change(
            screen.getByLabelText('Kod OTP'),
            {
                target: {
                    value: '123456',
                },
            },
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Sahkan OTP',
            }),
        );

        await screen.findByLabelText('Nama');

        fireEvent.change(
            screen.getByLabelText('Nama'),
            {
                target: {
                    value: 'Customer Test',
                },
            },
        );

        fireEvent.change(
            screen.getByLabelText('Kata Laluan'),
            {
                target: {
                    value: 'Password123',
                },
            },
        );

        fireEvent.change(
            screen.getByLabelText('Sahkan Kata Laluan'),
            {
                target: {
                    value: 'Different123',
                },
            },
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Cipta Akaun',
            }),
        );

        expect(
            await screen.findByRole('alert'),
        ).toBeTruthy();

        expect(
            screen.getByRole('alert').textContent,
        ).toContain('Pengesahan kata laluan tidak sepadan.');

        expect(
            completeRegistrationMock,
        ).not.toHaveBeenCalled();

        expect(onAuthenticated).not.toHaveBeenCalled();
    });
});
