
import {
    afterEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

import type {
    ComponentProps,
    FormEvent,
} from 'react';

import {
    cleanup,
    fireEvent,
    render,
    screen,
} from '@testing-library/react';

import LoginPage from './LoginPage';

import type {
    CustomerAuthMode,
    CustomerRegistrationStep,
} from './LoginPage';

function renderLoginPage(
    overrides: Partial<ComponentProps<typeof LoginPage>> = {},
) {
    const props: ComponentProps<typeof LoginPage> = {
        authMode: 'login',
        loading: false,
        error: '',
        identifier: '',
        password: '',
        registrationStep: 1,
        registrationChannel: 'phone',
        registrationContact: '',
        registrationOtp: '',
        registrationName: '',
        registrationPassword: '',
        registrationPasswordConfirmation: '',
        onIdentifierChange: vi.fn(),
        onPasswordChange: vi.fn(),
        onRegistrationChannelChange: vi.fn(),
        onRegistrationContactChange: vi.fn(),
        onRegistrationOtpChange: vi.fn(),
        onRegistrationNameChange: vi.fn(),
        onRegistrationPasswordChange: vi.fn(),
        onRegistrationPasswordConfirmationChange: vi.fn(),
        onLogin: vi.fn((event: FormEvent<HTMLFormElement>) =>
            event.preventDefault(),
        ),
        onRequestOtp: vi.fn((event: FormEvent<HTMLFormElement>) =>
            event.preventDefault(),
        ),
        onVerifyOtp: vi.fn((event: FormEvent<HTMLFormElement>) =>
            event.preventDefault(),
        ),
        onCompleteRegistration: vi.fn(
            (event: FormEvent<HTMLFormElement>) =>
                event.preventDefault(),
        ),
        onChangeAuthMode: vi.fn(),
        onChangeRegistrationStep: vi.fn(),
        ...overrides,
    };

    return {
        ...render(<LoginPage {...props} />),
        props,
    };
}

describe('LoginPage', () => {
    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('displays Super Runner customer branding', () => {
        renderLoginPage();

        expect(
            screen.getByRole('heading', {
                name: 'Super Runner',
            }),
        ).toBeTruthy();

        expect(screen.getByText('Pelanggan')).toBeTruthy();
    });

    it('displays login form by default', () => {
        renderLoginPage();

        expect(
            screen.getByRole('heading', {
                name: 'Log Masuk Pelanggan',
            }),
        ).toBeTruthy();

        expect(
            screen.getByLabelText('Nombor Telefon atau E-mel'),
        ).toBeTruthy();

        expect(
            screen.getByLabelText('Kata Laluan'),
        ).toBeTruthy();
    });

    it('calls identifier callback when identifier changes', () => {
        const { props } = renderLoginPage();

        fireEvent.change(
            screen.getByLabelText('Nombor Telefon atau E-mel'),
            {
                target: {
                    value: '0123456789',
                },
            },
        );

        expect(props.onIdentifierChange).toHaveBeenCalledWith(
            '0123456789',
        );
    });

    it('calls password callback when password changes', () => {
        const { props } = renderLoginPage();

        fireEvent.change(
            screen.getByLabelText('Kata Laluan'),
            {
                target: {
                    value: 'Password123',
                },
            },
        );

        expect(props.onPasswordChange).toHaveBeenCalledWith(
            'Password123',
        );
    });

    it('submits login form', () => {
        const { props } = renderLoginPage();

        fireEvent.submit(
            screen.getByRole('button', {
                name: 'Log Masuk',
            }).closest('form')!,
        );

        expect(props.onLogin).toHaveBeenCalledTimes(1);
    });

    it('displays login error', () => {
        renderLoginPage({
            error: 'Maklumat log masuk tidak sah.',
        });

        expect(
            screen.getByRole('alert'),
        ).toHaveTextContent('Maklumat log masuk tidak sah.');
    });

    it('displays login loading state', () => {
        renderLoginPage({
            loading: true,
        });

        expect(
            screen.getByRole('button', {
                name: 'Sedang log masuk...',
            }),
        ).toBeDisabled();

        expect(
            screen.getByLabelText('Nombor Telefon atau E-mel'),
        ).toBeDisabled();

        expect(
            screen.getByLabelText('Kata Laluan'),
        ).toBeDisabled();
    });

    it('changes auth mode to registration', () => {
        const { props } = renderLoginPage();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Daftar Akaun',
            }),
        );

        expect(props.onChangeAuthMode).toHaveBeenCalledWith(
            'register' satisfies CustomerAuthMode,
        );
    });

    it('displays registration step one', () => {
        renderLoginPage({
            authMode: 'register',
            registrationStep: 1,
        });

        expect(
            screen.getByRole('heading', {
                name: 'Pendaftaran Pelanggan',
            }),
        ).toBeTruthy();

        expect(
            screen.getByLabelText('Daftar menggunakan'),
        ).toBeTruthy();

        expect(
            screen.getByLabelText('Nombor Telefon'),
        ).toBeTruthy();
    });

    it('displays phone registration channel by default', () => {
        renderLoginPage({
            authMode: 'register',
        });

        expect(
            screen.getByLabelText('Nombor Telefon'),
        ).toHaveAttribute('type', 'tel');
    });

    it('displays email field when email channel is selected', () => {
        renderLoginPage({
            authMode: 'register',
            registrationChannel: 'email',
        });

        expect(
            screen.getByLabelText('Alamat E-mel'),
        ).toHaveAttribute('type', 'email');
    });

    it('calls registration channel callback', () => {
        const { props } = renderLoginPage({
            authMode: 'register',
        });

        fireEvent.change(
            screen.getByLabelText('Daftar menggunakan'),
            {
                target: {
                    value: 'email',
                },
            },
        );

        expect(
            props.onRegistrationChannelChange,
        ).toHaveBeenCalledWith('email');
    });

    it('calls registration contact callback', () => {
        const { props } = renderLoginPage({
            authMode: 'register',
        });

        fireEvent.change(
            screen.getByLabelText('Nombor Telefon'),
            {
                target: {
                    value: '0123456789',
                },
            },
        );

        expect(
            props.onRegistrationContactChange,
        ).toHaveBeenCalledWith('0123456789');
    });

    it('submits request OTP form', () => {
        const { props } = renderLoginPage({
            authMode: 'register',
            registrationStep: 1,
        });

        fireEvent.submit(
            screen.getByRole('button', {
                name: 'Hantar Kod OTP',
            }).closest('form')!,
        );

        expect(props.onRequestOtp).toHaveBeenCalledTimes(1);
    });

    it('displays OTP verification step', () => {
        renderLoginPage({
            authMode: 'register',
            registrationStep: 2,
            registrationContact: '0123456789',
        });

        expect(
            screen.getByText(/kod OTP 6 digit/),
        ).toBeTruthy();

        expect(
            screen.getByLabelText('Kod OTP'),
        ).toBeTruthy();
    });

    it('removes non-numeric characters from OTP input', () => {
        const { props } = renderLoginPage({
            authMode: 'register',
            registrationStep: 2,
        });

        fireEvent.change(
            screen.getByLabelText('Kod OTP'),
            {
                target: {
                    value: '12AB34',
                },
            },
        );

        expect(
            props.onRegistrationOtpChange,
        ).toHaveBeenCalledWith('1234');
    });

    it('limits OTP input to six digits', () => {
        const { props } = renderLoginPage({
            authMode: 'register',
            registrationStep: 2,
        });

        fireEvent.change(
            screen.getByLabelText('Kod OTP'),
            {
                target: {
                    value: '123456789',
                },
            },
        );

        expect(
            props.onRegistrationOtpChange,
        ).toHaveBeenCalledWith('123456');
    });

    it('disables OTP verification until six digits are entered', () => {
        renderLoginPage({
            authMode: 'register',
            registrationStep: 2,
            registrationOtp: '12345',
        });

        expect(
            screen.getByRole('button', {
                name: 'Sahkan OTP',
            }),
        ).toBeDisabled();
    });

    it('submits OTP verification form', () => {
        const { props } = renderLoginPage({
            authMode: 'register',
            registrationStep: 2,
            registrationOtp: '123456',
        });

        fireEvent.submit(
            screen.getByRole('button', {
                name: 'Sahkan OTP',
            }).closest('form')!,
        );

        expect(props.onVerifyOtp).toHaveBeenCalledTimes(1);
    });

    it('allows customer to change registration contact', () => {
        const { props } = renderLoginPage({
            authMode: 'register',
            registrationStep: 2,
            registrationOtp: '123456',
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Tukar Nombor Telefon / E-mel',
            }),
        );

        expect(
            props.onChangeRegistrationStep,
        ).toHaveBeenCalledWith(
            1 satisfies CustomerRegistrationStep,
        );
    });

    it('displays account information step', () => {
        renderLoginPage({
            authMode: 'register',
            registrationStep: 3,
            registrationContact: 'nazim@example.com',
        });

        expect(
            screen.getByLabelText('Nama'),
        ).toBeTruthy();

        expect(
            screen.getByLabelText('Kata Laluan'),
        ).toBeTruthy();

        expect(
            screen.getByLabelText('Sahkan Kata Laluan'),
        ).toBeTruthy();
    });

    it('calls registration name callback', () => {
        const { props } = renderLoginPage({
            authMode: 'register',
            registrationStep: 3,
        });

        fireEvent.change(
            screen.getByLabelText('Nama'),
            {
                target: {
                    value: 'Nazim',
                },
            },
        );

        expect(
            props.onRegistrationNameChange,
        ).toHaveBeenCalledWith('Nazim');
    });

    it('calls registration password callback', () => {
        const { props } = renderLoginPage({
            authMode: 'register',
            registrationStep: 3,
        });

        fireEvent.change(
            screen.getByLabelText('Kata Laluan'),
            {
                target: {
                    value: 'Password123',
                },
            },
        );

        expect(
            props.onRegistrationPasswordChange,
        ).toHaveBeenCalledWith('Password123');
    });

    it('calls password confirmation callback', () => {
        const { props } = renderLoginPage({
            authMode: 'register',
            registrationStep: 3,
        });

        fireEvent.change(
            screen.getByLabelText('Sahkan Kata Laluan'),
            {
                target: {
                    value: 'Password123',
                },
            },
        );

        expect(
            props.onRegistrationPasswordConfirmationChange,
        ).toHaveBeenCalledWith('Password123');
    });

    it('submits account registration form', () => {
        const { props } = renderLoginPage({
            authMode: 'register',
            registrationStep: 3,
        });

        fireEvent.submit(
            screen.getByRole('button', {
                name: 'Cipta Akaun',
            }).closest('form')!,
        );

        expect(
            props.onCompleteRegistration,
        ).toHaveBeenCalledTimes(1);
    });

    it('allows customer to return to OTP step', () => {
        const { props } = renderLoginPage({
            authMode: 'register',
            registrationStep: 3,
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Kembali',
            }),
        );

        expect(
            props.onChangeRegistrationStep,
        ).toHaveBeenCalledWith(
            2 satisfies CustomerRegistrationStep,
        );
    });

    it('allows customer to return to login mode', () => {
        const { props } = renderLoginPage({
            authMode: 'register',
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Log Masuk',
            }),
        );

        expect(props.onChangeAuthMode).toHaveBeenCalledWith(
            'login',
        );
    });

    it('displays registration error', () => {
        renderLoginPage({
            authMode: 'register',
            registrationStep: 1,
            error: 'Nombor telefon telah digunakan.',
        });

        expect(
            screen.getByRole('alert'),
        ).toHaveTextContent('Nombor telefon telah digunakan.');
    });

    it('disables registration controls while loading', () => {
        renderLoginPage({
            authMode: 'register',
            registrationStep: 1,
            loading: true,
        });

        expect(
            screen.getByLabelText('Daftar menggunakan'),
        ).toBeDisabled();

        expect(
            screen.getByLabelText('Nombor Telefon'),
        ).toBeDisabled();

        expect(
            screen.getByRole('button', {
                name: 'Sedang menghantar OTP...',
            }),
        ).toBeDisabled();
    });
});
