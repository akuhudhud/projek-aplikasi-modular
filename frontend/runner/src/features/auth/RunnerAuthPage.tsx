
import { useState, type FormEvent } from 'react';
import axios from 'axios';

import {
    runnerApi,
    type RunnerAccount,
} from '../../api';

type RunnerAuthPageProps = {
    onAuthenticated: (account: RunnerAccount) => void;
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

    if (
        responseErrors &&
        typeof responseErrors === 'object'
    ) {
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

export default function RunnerAuthPage({
    onAuthenticated,
}: RunnerAuthPageProps) {
    const [identifier, setIdentifier] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    async function handleLogin(
        event: FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();

        if (loading) {
            return;
        }

        setError('');

        const value = identifier.trim();

        if (!value) {
            setError('Masukkan nombor telefon atau e-mel.');
            return;
        }

        if (!password) {
            setError('Masukkan kata laluan.');
            return;
        }

        setLoading(true);

        try {
            const account = await runnerApi.login({
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
                    'Log masuk gagal. Sila semak maklumat akaun anda.',
                ),
            );
        } finally {
            setLoading(false);
        }
    }

    return (
        <main className="runner-auth">
            <section
                className="runner-auth__panel"
                aria-labelledby="runner-auth-heading"
            >
                <div className="runner-auth__brand">
                    <div
                        className="runner-auth__brand-mark"
                        aria-hidden="true"
                    >
                        SR
                    </div>

                    <div>
                        <h1>SUPER RUNNER</h1>
                        <p>Runner Platform</p>
                    </div>
                </div>

                <div className="runner-auth__heading">
                    <h2 id="runner-auth-heading">
                        Log Masuk Runner
                    </h2>

                    <p>
                        Log masuk menggunakan akaun Super Runner
                        anda untuk meneruskan.
                    </p>
                </div>

                <form
                    className="runner-auth__form"
                    onSubmit={handleLogin}
                >
                    <div className="runner-auth__field">
                        <label htmlFor="runner-identifier">
                            Nombor Telefon atau E-mel
                        </label>

                        <input
                            id="runner-identifier"
                            type="text"
                            autoComplete="username"
                            value={identifier}
                            onChange={(event) => {
                                setIdentifier(event.target.value);
                                setError('');
                            }}
                            placeholder="Masukkan nombor telefon atau e-mel"
                            required
                            disabled={loading}
                        />
                    </div>

                    <div className="runner-auth__field">
                        <label htmlFor="runner-password">
                            Kata Laluan
                        </label>

                        <div className="runner-auth__password">
                            <input
                                id="runner-password"
                                type={
                                    showPassword
                                        ? 'text'
                                        : 'password'
                                }
                                autoComplete="current-password"
                                value={password}
                                onChange={(event) => {
                                    setPassword(event.target.value);
                                    setError('');
                                }}
                                placeholder="Masukkan kata laluan"
                                required
                                disabled={loading}
                            />

                            <button
                                type="button"
                                onClick={() =>
                                    setShowPassword((current) => !current)
                                }
                                disabled={loading}
                                aria-label={
                                    showPassword
                                        ? 'Sembunyikan kata laluan'
                                        : 'Paparkan kata laluan'
                                }
                            >
                                {showPassword ? 'Sembunyi' : 'Papar'}
                            </button>
                        </div>
                    </div>

                    {error && (
                        <p
                            className="runner-auth__error"
                            role="alert"
                        >
                            {error}
                        </p>
                    )}

                    <button
                        className="runner-auth__submit"
                        type="submit"
                        disabled={loading}
                    >
                        {loading
                            ? 'Sedang log masuk...'
                            : 'LOG MASUK'}
                    </button>
                </form>

                <div className="runner-auth__footer">
                    <p>
                        Belum mempunyai akaun?
                    </p>

                    <p>
                        Daftar akaun melalui platform
                        Super Runner Customer terlebih dahulu.
                    </p>
                </div>
            </section>
        </main>
    );
}
