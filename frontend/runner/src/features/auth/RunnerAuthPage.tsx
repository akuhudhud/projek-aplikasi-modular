
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

    return (
        typeof message === 'string' &&
        message.trim() !== ''
    )
        ? message
        : 'Log masuk gagal. Sila semak maklumat akaun anda.';
}

export default function RunnerAuthPage({
    onAuthenticated,
}: RunnerAuthPageProps) {
    const [identifier, setIdentifier] = useState('');
    const [password, setPassword] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    async function handleLogin(
        event: FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();

        setError('');
        setLoading(true);

        try {
            const value = identifier.trim();

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
                getApiErrorMessage(requestError),
            );
        } finally {
            setLoading(false);
        }
    }

    return (
        <main className="runner-auth">
            <section className="runner-auth__panel">
                <div className="runner-auth__brand">
                    <span className="runner-auth__brand-mark">
                        SR
                    </span>

                    <div>
                        <h1>SUPER RUNNER</h1>
                        <p>RUNNER APPLICATION</p>
                    </div>
                </div>

                <div className="runner-auth__heading">
                    <h2>Selamat Kembali</h2>

                    <p>
                        Log masuk menggunakan akaun
                        Super Runner anda.
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

                        <input
                            id="runner-password"
                            type="password"
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
                            ? 'SEDANG LOG MASUK...'
                            : 'LOG MASUK'}
                    </button>
                </form>

                <p className="runner-auth__footer">
                    Akaun Super Runner diperlukan
                    untuk menggunakan aplikasi ini.
                </p>
            </section>
        </main>
    );
}
