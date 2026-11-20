
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import RunnerAuthPage from '../../../src/features/auth/RunnerAuthPage';
import { runnerApi, type RunnerAccount } from '../../../src/api';

vi.mock('../../../src/api', () => ({
    runnerApi: {
        login: vi.fn(),
    },
}));

const mockAccount: RunnerAccount = {
    id: 1,
    profile_name: 'Runner Test',
    phone: '0123456789',
    email: 'runner@example.com',
    profile_picture: null,
    role: 'USER',
    status: 'ACTIVE',
    runner_status: 'APPROVED',
    identity_verification_status: 'VERIFIED',
};

describe('RunnerAuthPage', () => {
    const onAuthenticated = vi.fn();

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders the Runner login form', () => {
        render(
            <RunnerAuthPage
                onAuthenticated={onAuthenticated}
            />,
        );

        expect(
            screen.getByRole('heading', {
                name: 'Log Masuk Runner',
            }),
        ).toBeInTheDocument();

        expect(
            screen.getByLabelText(
                'Nombor Telefon atau E-mel',
            ),
        ).toBeInTheDocument();

        expect(
            screen.getByLabelText('Kata Laluan'),
        ).toBeInTheDocument();
    });

    it('logs in using a phone number', async () => {
        vi.mocked(runnerApi.login).mockResolvedValue(
            mockAccount,
        );

        render(
            <RunnerAuthPage
                onAuthenticated={onAuthenticated}
            />,
        );

        fireEvent.change(
            screen.getByLabelText(
                'Nombor Telefon atau E-mel',
            ),
            {
                target: { value: '0123456789' },
            },
        );

        fireEvent.change(
            screen.getByLabelText('Kata Laluan'),
            {
                target: { value: 'Password123' },
            },
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'LOG MASUK',
            }),
        );

        await waitFor(() => {
            expect(runnerApi.login).toHaveBeenCalledWith({
                phone: '0123456789',
                password: 'Password123',
            });

            expect(onAuthenticated).toHaveBeenCalledWith(
                mockAccount,
            );
        });
    });

    it('logs in using an email address', async () => {
        vi.mocked(runnerApi.login).mockResolvedValue(
            mockAccount,
        );

        render(
            <RunnerAuthPage
                onAuthenticated={onAuthenticated}
            />,
        );

        fireEvent.change(
            screen.getByLabelText(
                'Nombor Telefon atau E-mel',
            ),
            {
                target: {
                    value: 'runner@example.com',
                },
            },
        );

        fireEvent.change(
            screen.getByLabelText('Kata Laluan'),
            {
                target: { value: 'Password123' },
            },
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'LOG MASUK',
            }),
        );

        await waitFor(() => {
            expect(runnerApi.login).toHaveBeenCalledWith({
                email: 'runner@example.com',
                password: 'Password123',
            });
        });
    });

    it('shows an error when login fails', async () => {
        vi.mocked(runnerApi.login).mockRejectedValue(
            new Error('Login failed'),
        );

        render(
            <RunnerAuthPage
                onAuthenticated={onAuthenticated}
            />,
        );

        fireEvent.change(
            screen.getByLabelText(
                'Nombor Telefon atau E-mel',
            ),
            {
                target: { value: '0123456789' },
            },
        );

        fireEvent.change(
            screen.getByLabelText('Kata Laluan'),
            {
                target: { value: 'WrongPassword' },
            },
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'LOG MASUK',
            }),
        );

        expect(
            await screen.findByRole('alert'),
        ).toHaveTextContent(
            'Ralat berlaku. Sila cuba semula.',
        );

        expect(onAuthenticated).not.toHaveBeenCalled();
    });

    it('toggles password visibility', () => {
        render(
            <RunnerAuthPage
                onAuthenticated={onAuthenticated}
            />,
        );

        const passwordInput = screen.getByLabelText(
            'Kata Laluan',
        );

        expect(passwordInput).toHaveAttribute(
            'type',
            'password',
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Paparkan kata laluan',
            }),
        );

        expect(passwordInput).toHaveAttribute(
            'type',
            'text',
        );
    });
});
