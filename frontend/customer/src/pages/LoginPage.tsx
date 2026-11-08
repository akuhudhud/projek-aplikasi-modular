import React from 'react';

export type CustomerAuthMode = 'login' | 'register';

export type CustomerRegistrationStep = 1 | 2 | 3;

type LoginPageProps = {
    authMode: CustomerAuthMode;
    loading: boolean;
    error: string;

    identifier: string;
    password: string;

    registrationStep: CustomerRegistrationStep;
    registrationChannel: 'phone' | 'email';
    registrationContact: string;
    registrationOtp: string;
    registrationName: string;
    registrationPassword: string;
    registrationPasswordConfirmation: string;

    onIdentifierChange: (value: string) => void;
    onPasswordChange: (value: string) => void;

    onRegistrationChannelChange: (
        value: 'phone' | 'email',
    ) => void;
    onRegistrationContactChange: (value: string) => void;
    onRegistrationOtpChange: (value: string) => void;
    onRegistrationNameChange: (value: string) => void;
    onRegistrationPasswordChange: (value: string) => void;
    onRegistrationPasswordConfirmationChange: (
        value: string,
    ) => void;

    onLogin: (event: React.FormEvent<HTMLFormElement>) => void;
    onRequestOtp: (event: React.FormEvent<HTMLFormElement>) => void;
    onVerifyOtp: (event: React.FormEvent<HTMLFormElement>) => void;
    onCompleteRegistration: (
        event: React.FormEvent<HTMLFormElement>,
    ) => void;

    onChangeAuthMode: (mode: CustomerAuthMode) => void;
    onChangeRegistrationStep: (
        step: CustomerRegistrationStep,
    ) => void;
};

export default function LoginPage({
    authMode,
    loading,
    error,

    identifier,
    password,

    registrationStep,
    registrationChannel,
    registrationContact,
    registrationOtp,
    registrationName,
    registrationPassword,
    registrationPasswordConfirmation,

    onIdentifierChange,
    onPasswordChange,

    onRegistrationChannelChange,
    onRegistrationContactChange,
    onRegistrationOtpChange,
    onRegistrationNameChange,
    onRegistrationPasswordChange,
    onRegistrationPasswordConfirmationChange,

    onLogin,
    onRequestOtp,
    onVerifyOtp,
    onCompleteRegistration,

    onChangeAuthMode,
    onChangeRegistrationStep,
}: LoginPageProps) {
    return (
        <main className="customer-login">
            <div className="sr-auth-brand">
                <h1>Super Runner</h1>
                <p>Customer</p>
            </div>

            {authMode === 'login' ? (
                <>
                    <h2>Log Masuk Pelanggan</h2>

                    <form onSubmit={onLogin}>
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
                                    onIdentifierChange(event.target.value)
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
                                    onPasswordChange(event.target.value)
                                }
                                required
                                disabled={loading}
                            />
                        </div>

                        {error && <p role="alert">{error}</p>}

                        <button type="submit" disabled={loading}>
                            {loading ? 'Sedang log masuk...' : 'Log Masuk'}
                        </button>
                    </form>

                    <p>
                        Belum ada akaun?{' '}
                        <button
                            type="button"
                            disabled={loading}
                            onClick={() => onChangeAuthMode('register')}
                        >
                            Daftar Akaun
                        </button>
                    </p>
                </>
            ) : (
                <>
                    <h2>Pendaftaran Pelanggan</h2>

                    <p>
                        Daftar akaun menggunakan nombor telefon atau e-mel.
                    </p>

                    <div
                        className="customer-registration-steps"
                        aria-label="Langkah pendaftaran"
                    >
                        <span className={registrationStep >= 1 ? 'active' : ''}>
                            1. Hubungan
                        </span>

                        <span className={registrationStep >= 2 ? 'active' : ''}>
                            2. OTP
                        </span>

                        <span className={registrationStep >= 3 ? 'active' : ''}>
                            3. Akaun
                        </span>
                    </div>

                    {registrationStep === 1 && (
                        <form onSubmit={onRequestOtp}>
                            <div>
                                <label htmlFor="registration-channel">
                                    Daftar menggunakan
                                </label>

                                <select
                                    id="registration-channel"
                                    value={registrationChannel}
                                    onChange={(event) => {
                                        onRegistrationChannelChange(
                                            event.target.value as 'phone' | 'email',
                                        );
                                        onRegistrationContactChange('');
                                    }}
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
                                        onRegistrationContactChange(
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

                            {error && <p role="alert">{error}</p>}

                            <button type="submit" disabled={loading}>
                                {loading ? 'Menghantar OTP...' : 'Hantar Kod OTP'}
                            </button>
                        </form>
                    )}

                    {registrationStep === 2 && (
                        <form onSubmit={onVerifyOtp}>
                            <p>
                                Masukkan kod OTP 6 digit yang dihantar ke{' '}
                                <strong>{registrationContact}</strong>.
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
                                        onRegistrationOtpChange(
                                            event.target.value
                                                .replace(/\D/g, '')
                                                .slice(0, 6),
                                        )
                                    }
                                    required
                                    minLength={6}
                                    maxLength={6}
                                    pattern="[0-9]{6}"
                                    disabled={loading}
                                />
                            </div>

                            {error && <p role="alert">{error}</p>}

                            <button
                                type="submit"
                                disabled={loading || registrationOtp.length !== 6}
                            >
                                {loading ? 'Mengesahkan...' : 'Sahkan OTP'}
                            </button>

                            <button
                                type="button"
                                disabled={loading}
                                onClick={() => onChangeRegistrationStep(1)}
                            >
                                Tukar Nombor / E-mel
                            </button>
                        </form>
                    )}

                    {registrationStep === 3 && (
                        <form onSubmit={onCompleteRegistration}>
                            <p>
                                OTP berjaya disahkan untuk{' '}
                                <strong>{registrationContact}</strong>.
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
                                        onRegistrationNameChange(
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
                                        onRegistrationPasswordChange(
                                            event.target.value,
                                        )
                                    }
                                    required
                                    minLength={8}
                                    maxLength={12}
                                    disabled={loading}
                                />

                                <small>
                                    8–12 aksara, mesti ada huruf besar,
                                    huruf kecil dan nombor.
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
                                    value={registrationPasswordConfirmation}
                                    onChange={(event) =>
                                        onRegistrationPasswordConfirmationChange(
                                            event.target.value,
                                        )
                                    }
                                    required
                                    minLength={8}
                                    maxLength={12}
                                    disabled={loading}
                                />
                            </div>

                            {error && <p role="alert">{error}</p>}

                            <button type="submit" disabled={loading}>
                                {loading ? 'Mencipta akaun...' : 'Cipta Akaun'}
                            </button>

                            <button
                                type="button"
                                disabled={loading}
                                onClick={() => onChangeRegistrationStep(2)}
                            >
                                Kembali
                            </button>
                        </form>
                    )}

                    <p>
                        Sudah ada akaun?{' '}
                        <button
                            type="button"
                            disabled={loading}
                            onClick={() => onChangeAuthMode('login')}
                        >
                            Log Masuk
                        </button>
                    </p>
                </>
            )}
        </main>
    );
}
