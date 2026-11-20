
import { useState, type FormEvent } from 'react';
import axios from 'axios';

import {
    runnerApi,
    type RunnerAccount,
} from '../../api';

import RunnerLoginForm from '../../components/auth/RunnerLoginForm';

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

    function handleIdentifierChange(value: string) {
        setIdentifier(value);
        setError('');
    }

    function handlePasswordChange(value: string) {
        setPassword(value);
        setError('');
    }

    function handleTogglePassword() {
        setShowPassword((current) => !current);
    }

    return (
        <RunnerLoginForm
            identifier={identifier}
            password={password}
            showPassword={showPassword}
            loading={loading}
            error={error}
            onIdentifierChange={handleIdentifierChange}
            onPasswordChange={handlePasswordChange}
            onTogglePassword={handleTogglePassword}
            onSubmit={handleLogin}
        />
    );
}
