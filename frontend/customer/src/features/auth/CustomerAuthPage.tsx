
import { useState, type FormEvent } from 'react';
import axios from 'axios';

import {
    customerApi,
    type CustomerAccount,
    type RegistrationOtpRequestPayload,
} from '../../api';

type AuthMode = 'login' | 'register';
type RegistrationStep = 1 | 2 | 3;
type RegistrationChannel = 'phone' | 'email';

type CustomerAuthPageProps = {
    onAuthenticated: (account: CustomerAccount) => void;
};

function getApiErrorMessage(
    error: unknown,
    fallback: string,
): string {
    if (!axios.isAxiosError(error)) {
        return 'Ralat berlaku. Sila cuba semula.';
    }

    const responseData = error.response?.data;
    const responseErrors = responseData?.errors;

    if (responseErrors && typeof responseErrors === 'object') {
        const firstError = Object.values(responseErrors)
            .flat()
            .find((value) => typeof value === 'string');

        if (typeof firstError === 'string') {
            return firstError;
        }
    }

    const message = responseData?.message;

    return typeof message === 'string' && message.trim() !== ''
        ? message
        : fallback;
}

export default function CustomerAuthPage({
    onAuthenticated,
}: CustomerAuthPageProps) {
    const [authMode, setAuthMode] = useState<AuthMode>('login');

    const [identifier, setIdentifier] = useState('');
    const [password, setPassword] = useState('');

    const [registrationStep, setRegistrationStep] =
        useState<RegistrationStep>(1);

    const [registrationChannel, setRegistrationChannel] =
        useState<RegistrationChannel>('phone');

    const [registrationContact, setRegistrationContact] =
        useState('');

    const [registrationVerificationId, setRegistrationVerificationId] =
        useState('');

    const [registrationVerificationToken, setRegistrationVerificationToken] =
        useState('');

    const [registrationOtp, setRegistrationOtp] = useState('');
    const [registrationName, setRegistrationName] = useState('');
    const [registrationPassword, setRegistrationPassword] = useState('');

    const [
        registrationPasswordConfirmation,
        setRegistrationPasswordConfirmation,
    ] = useState('');

    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    function resetRegistration() {
        setRegistrationStep(1);
        setRegistrationChannel('phone');
        setRegistrationContact('');
        setRegistrationVerificationId('');
        setRegistrationVerificationToken('');
        setRegistrationOtp('');
        setRegistrationName('');
        setRegistrationPassword('');
        setRegistrationPasswordConfirmation('');
        setError('');
    }

    function switchToLogin() {
        resetRegistration();
        setAuthMode('login');
    }

    function switchToRegistration() {
        resetRegistration();
        setAuthMode('register');
    }

    async function handleLogin(
        event: FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();
        setError('');
        setLoading(true);

        try {
            const value = identifier.trim();

            const account = await customerApi.login({
                ...(value.includes('@')
                    ? { email: value }
                    : { phone: value }),
                password,
            });

            setPassword('');
            onAuthenticated(account);
        } catch (requestError) {
            setError(
                getApiErrorMessage(
                    requestError,
                    'Log masuk gagal. Sila semak maklumat anda.',
                ),
            );
        } finally {
            setLoading(false);
        }
    }

    async function handleRequestRegistrationOtp(
        event: FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();
        setError('');
        setLoading(true);

        try {
            const payload: RegistrationOtpRequestPayload = {
                channel: registrationChannel,
                contact: registrationContact.trim(),
                purpose: 'REGISTRATION',
            };

            const result =
                await customerApi.requestRegistrationOtp(payload);

            setRegistrationVerificationId(result.verification_id);
            setRegistrationVerificationToken('');
            setRegistrationOtp('');
            setRegistrationStep(2);
        } catch (requestError) {
            setError(
                getApiErrorMessage(
                    requestError,
                    'Tidak berjaya menghantar OTP. Sila cuba semula.',
                ),
            );
        } finally {
            setLoading(false);
        }
    }

    async function handleVerifyRegistrationOtp(
        event: FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();
        setError('');

        if (!/^\d{6}$/.test(registrationOtp)) {
            setError('Masukkan kod OTP 6 digit.');
            return;
        }

        if (!registrationVerificationId) {
            setError('Sesi OTP tidak sah. Sila minta kod OTP baharu.');
            setRegistrationStep(1);
            return;
        }

        setLoading(true);

        try {
            const result =
                await customerApi.verifyRegistrationOtp({
                    verification_id: registrationVerificationId,
                    otp: registrationOtp,
                });

            setRegistrationVerificationToken(
                result.verification_token,
            );

            setRegistrationStep(3);
        } catch (requestError) {
            setError(
                getApiErrorMessage(
                    requestError,
                    'OTP tidak sah atau telah tamat tempoh.',
                ),
            );
        } finally {
            setLoading(false);
        }
    }

    async function handleCompleteRegistration(
        event: FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();
        setError('');

        if (!registrationVerificationToken) {
            setError(
                'Pengesahan OTP diperlukan. Sila ulang proses pendaftaran.',
            );

            setRegistrationStep(1);
            return;
        }

        if (
            registrationPassword.length < 8 ||
            registrationPassword.length > 12 ||
            !/[A-Z]/.test(registrationPassword) ||
            !/[a-z]/.test(registrationPassword) ||
            !/\d/.test(registrationPassword)
        ) {
            setError(
                'Kata laluan mesti 8–12 aksara dan mengandungi huruf besar, huruf kecil serta nombor.',
            );
            return;
        }

        if (
            registrationPassword !==
            registrationPasswordConfirmation
        ) {
            setError('Pengesahan kata laluan tidak sepadan.');
            return;
        }

        setLoading(true);

        try {
            const account =
                await customerApi.completeRegistration({
                    verification_token: registrationVerificationToken,
                    name: registrationName.trim(),
                    password: registrationPassword,
                    password_confirmation:
                        registrationPasswordConfirmation,
                });

            setRegistrationVerificationToken('');
            setRegistrationPassword('');
            setRegistrationPasswordConfirmation('');

            onAuthenticated(account);
        } catch (requestError) {
            setError(
                getApiErrorMessage(
                    requestError,
                    'Pendaftaran tidak berjaya. Sila cuba semula.',
                ),
            );
        } finally {
            setLoading(false);
        }
    }

    function handleChangeRegistrationChannel(
        channel: RegistrationChannel,
    ) {
        setRegistrationChannel(channel);
        setRegistrationContact('');
        setError('');
    }

    function handleChangeRegistrationContact(value: string) {
        setRegistrationContact(value);
        setError('');
    }

    function handleChangeOtp(value: string) {
        setRegistrationOtp(
            value.replace(/\D/g, '').slice(0, 6),
        );
        setError('');
    }

    return (
        <main className="customer-login">
            <h1>Super Runner</h1>

            {authMode === 'login' ? (
                <>
                    <h2>Log Masuk Pelanggan</h2>

                    <form onSubmit={handleLogin}>
                        <div>
                            <label htmlFor="identifier">
                                Nombor Telefon atau E-mel
                            </label>

                            <input
                                id="identifier"
                                type="text"
                                autoComplete="username"
                                value={identifier}
                                onChange={(event) =>
                                    setIdentifier(event.target.value)
                                }
                                required
                                disabled={loading}
                            />
                        </div>

                        <div>
                            <label htmlFor="password">
                                Kata Laluan
                            </label>

                            <input
                                id="password"
                                type="password"
                                autoComplete="current-password"
                                value={password}
                                onChange={(event) =>
                                    setPassword(event.target.value)
                                }
                                required
                                disabled={loading}
                            />
                        </div>

                        {error && (
                            <p role="alert">{error}</p>
                        )}

                        <button
                            type="submit"
                            disabled={loading}
                        >
                            {loading
                                ? 'Sedang log masuk...'
                                : 'Log Masuk'}
                        </button>
                    </form>

                    <p>
                        Belum mempunyai akaun?{' '}

                        <button
                            type="button"
                            disabled={loading}
                            onClick={switchToRegistration}
                        >
                            Daftar Akaun
                        </button>
                    </p>
                </>
            ) : (
                <>
                    <h2>Pendaftaran Pelanggan</h2>

                    <p>
                        Daftar akaun menggunakan nombor telefon
                        atau alamat e-mel.
                    </p>

                    <div
                        className="customer-registration-steps"
                        aria-label="Langkah pendaftaran"
                    >
                        <span
                            className={
                                registrationStep >= 1 ? 'active' : ''
                            }
                        >
                            1. Maklumat Hubungan
                        </span>

                        <span
                            className={
                                registrationStep >= 2 ? 'active' : ''
                            }
                        >
                            2. Pengesahan OTP
                        </span>

                        <span
                            className={
                                registrationStep >= 3 ? 'active' : ''
                            }
                        >
                            3. Maklumat Akaun
                        </span>
                    </div>

                    {registrationStep === 1 && (
                        <form
                            onSubmit={handleRequestRegistrationOtp}
                        >
                            <div>
                                <label htmlFor="registration-channel">
                                    Kaedah Pendaftaran
                                </label>

                                <select
                                    id="registration-channel"
                                    value={registrationChannel}
                                    onChange={(event) =>
                                        handleChangeRegistrationChannel(
                                            event.target.value as RegistrationChannel,
                                        )
                                    }
                                    disabled={loading}
                                >
                                    <option value="phone">
                                        Nombor Telefon
                                    </option>

                                    <option value="email">
                                        E-mel
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label htmlFor="registration-contact">
                                    {registrationChannel === 'phone'
                                        ? 'Nombor Telefon'
                                        : 'Alamat E-mel'}
                                </label>

                                <input
                                    id="registration-contact"
                                    type={
                                        registrationChannel === 'phone'
                                            ? 'tel'
                                            : 'email'
                                    }
                                    autoComplete={
                                        registrationChannel === 'phone'
                                            ? 'tel'
                                            : 'email'
                                    }
                                    value={registrationContact}
                                    onChange={(event) =>
                                        handleChangeRegistrationContact(
                                            event.target.value,
                                        )
                                    }
                                    required
                                    maxLength={
                                        registrationChannel === 'phone'
                                            ? 30
                                            : 254
                                    }
                                    disabled={loading}
                                />
                            </div>

                            {error && (
                                <p role="alert">{error}</p>
                            )}

                            <button
                                type="submit"
                                disabled={loading}
                            >
                                {loading
                                    ? 'Sedang menghantar OTP...'
                                    : 'Hantar Kod OTP'}
                            </button>
                        </form>
                    )}

                    {registrationStep === 2 && (
                        <form
                            onSubmit={handleVerifyRegistrationOtp}
                        >
                            <p>
                                Masukkan kod OTP 6 digit yang dihantar
                                ke{' '}
                                <strong>
                                    {registrationContact}
                                </strong>.
                            </p>

                            <div>
                                <label htmlFor="registration-otp">
                                    Kod OTP
                                </label>

                                <input
                                    id="registration-otp"
                                    type="text"
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    value={registrationOtp}
                                    onChange={(event) =>
                                        handleChangeOtp(
                                            event.target.value,
                                        )
                                    }
                                    required
                                    minLength={6}
                                    maxLength={6}
                                    pattern="[0-9]{6}"
                                    disabled={loading}
                                />
                            </div>

                            {error && (
                                <p role="alert">{error}</p>
                            )}

                            <button
                                type="submit"
                                disabled={
                                    loading ||
                                    registrationOtp.length !== 6
                                }
                            >
                                {loading
                                    ? 'Sedang mengesahkan...'
                                    : 'Sahkan OTP'}
                            </button>

                            <button
                                type="button"
                                disabled={loading}
                                onClick={() => {
                                    setRegistrationStep(1);
                                    setRegistrationVerificationId('');
                                    setRegistrationVerificationToken('');
                                    setRegistrationOtp('');
                                    setError('');
                                }}
                            >
                                Tukar Nombor Telefon / E-mel
                            </button>
                        </form>
                    )}

                    {registrationStep === 3 && (
                        <form
                            onSubmit={handleCompleteRegistration}
                        >
                            <p>
                                OTP berjaya disahkan untuk{' '}
                                <strong>
                                    {registrationContact}
                                </strong>.
                            </p>

                            <div>
                                <label htmlFor="registration-name">
                                    Nama
                                </label>

                                <input
                                    id="registration-name"
                                    type="text"
                                    autoComplete="name"
                                    value={registrationName}
                                    onChange={(event) =>
                                        setRegistrationName(
                                            event.target.value,
                                        )
                                    }
                                    required
                                    maxLength={150}
                                    disabled={loading}
                                />
                            </div>

                            <div>
                                <label htmlFor="registration-password">
                                    Kata Laluan
                                </label>

                                <input
                                    id="registration-password"
                                    type="password"
                                    autoComplete="new-password"
                                    value={registrationPassword}
                                    onChange={(event) =>
                                        setRegistrationPassword(
                                            event.target.value,
                                        )
                                    }
                                    required
                                    minLength={8}
                                    maxLength={12}
                                    disabled={loading}
                                />

                                <small>
                                    8–12 aksara, mesti mengandungi huruf
                                    besar, huruf kecil dan nombor.
                                </small>
                            </div>

                            <div>
                                <label htmlFor="registration-password-confirmation">
                                    Sahkan Kata Laluan
                                </label>

                                <input
                                    id="registration-password-confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    value={
                                        registrationPasswordConfirmation
                                    }
                                    onChange={(event) =>
                                        setRegistrationPasswordConfirmation(
                                            event.target.value,
                                        )
                                    }
                                    required
                                    minLength={8}
                                    maxLength={12}
                                    disabled={loading}
                                />
                            </div>

                            {error && (
                                <p role="alert">{error}</p>
                            )}

                            <button
                                type="submit"
                                disabled={loading}
                            >
                                {loading
                                    ? 'Sedang mencipta akaun...'
                                    : 'Cipta Akaun'}
                            </button>

                            <button
                                type="button"
                                disabled={loading}
                                onClick={() => {
                                    setRegistrationStep(2);
                                    setRegistrationVerificationToken('');
                                    setError('');
                                }}
                            >
                                Kembali
                            </button>
                        </form>
                    )}

                    <p>
                        Sudah mempunyai akaun?{' '}

                        <button
                            type="button"
                            disabled={loading}
                            onClick={switchToLogin}
                        >
                            Log Masuk
                        </button>
                    </p>
                </>
            )}
        </main>
    );
}
