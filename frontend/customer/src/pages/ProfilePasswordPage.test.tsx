import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

import type { CustomerAccount } from '../api';
import ProfilePage from './ProfilePage';

const account: CustomerAccount = {
    id: 'customer-001',
    profile_name: 'Nazim',
    phone: '0123456789',
    email: 'nazim@example.com',
    profile_picture: null,
    role: 'USER',
    status: 'ACTIVE',
};

function renderPage(
    overrides: Partial<React.ComponentProps<typeof ProfilePage>> = {},
) {
    const props: React.ComponentProps<typeof ProfilePage> = {
        account,
        profileName: 'Nazim',
        profileSaving: false,
        profileError: '',
        profileMessage: '',
        pictureUploading: false,
        pictureError: '',
        pictureMessage: '',
        passwordSaving: false,
        passwordError: '',
        passwordMessage: '',
        onNameChange: vi.fn(),
        onSubmit: vi.fn((event) => event.preventDefault()),
        onPictureSelect: vi.fn().mockResolvedValue(true),
        onChangePassword: vi.fn().mockResolvedValue(true),
        onReturnToLogin: vi.fn(),
        ...overrides,
    };

    return {
        ...render(<ProfilePage {...props} />),
        props,
    };
}

describe('ProfilePage - Tukar Kata Laluan', () => {
    it('memaparkan borang tukar kata laluan', () => {
        renderPage();

        expect(screen.getByRole('heading', {
            name: 'Tukar Kata Laluan',
        })).toBeTruthy();

        expect(screen.getByLabelText('Kata Laluan Semasa')).toBeTruthy();
        expect(screen.getByLabelText('Kata Laluan Baharu')).toBeTruthy();
        expect(screen.getByLabelText('Sahkan Kata Laluan Baharu')).toBeTruthy();
    });

    it('menghantar maklumat kata laluan kepada callback', async () => {
        const { props } = renderPage();

        fireEvent.change(screen.getByLabelText('Kata Laluan Semasa'), {
            target: { value: 'OldPass123' },
        });

        fireEvent.change(screen.getByLabelText('Kata Laluan Baharu'), {
            target: { value: 'NewPass123' },
        });

        fireEvent.change(screen.getByLabelText('Sahkan Kata Laluan Baharu'), {
            target: { value: 'NewPass123' },
        });

        fireEvent.click(screen.getByRole('button', {
            name: 'Tukar Kata Laluan',
        }));

        await waitFor(() => {
            expect(props.onChangePassword).toHaveBeenCalledWith({
                current_password: 'OldPass123',
                new_password: 'NewPass123',
                new_password_confirmation: 'NewPass123',
            });
        });
    });

    it('memaparkan pengesahan selepas kata laluan berjaya ditukar', async () => {
        const { props } = renderPage({
            onChangePassword: vi.fn().mockResolvedValue(true),
        });

        fireEvent.change(screen.getByLabelText('Kata Laluan Semasa'), {
            target: { value: 'OldPass123' },
        });

        fireEvent.change(screen.getByLabelText('Kata Laluan Baharu'), {
            target: { value: 'NewPass123' },
        });

        fireEvent.change(screen.getByLabelText('Sahkan Kata Laluan Baharu'), {
            target: { value: 'NewPass123' },
        });

        fireEvent.click(screen.getByRole('button', {
            name: 'Tukar Kata Laluan',
        }));

        expect(await screen.findByRole('status')).toHaveTextContent(
            'Kata laluan berjaya ditukar.',
        );

        expect(screen.getByRole('button', {
            name: 'Kembali ke Log Masuk',
        })).toBeTruthy();

        expect(screen.queryByLabelText('Kata Laluan Semasa')).toBeNull();
        expect(props.onChangePassword).toHaveBeenCalledTimes(1);
    });

    it('tidak memaparkan kejayaan apabila pertukaran gagal', async () => {
        const { props } = renderPage({
            onChangePassword: vi.fn().mockResolvedValue(false),
            passwordError: 'Kata laluan semasa tidak betul.',
        });

        fireEvent.change(screen.getByLabelText('Kata Laluan Semasa'), {
            target: { value: 'WrongPass123' },
        });

        fireEvent.change(screen.getByLabelText('Kata Laluan Baharu'), {
            target: { value: 'NewPass123' },
        });

        fireEvent.change(screen.getByLabelText('Sahkan Kata Laluan Baharu'), {
            target: { value: 'NewPass123' },
        });

        fireEvent.click(screen.getByRole('button', {
            name: 'Tukar Kata Laluan',
        }));

        await waitFor(() => {
            expect(props.onChangePassword).toHaveBeenCalledTimes(1);
        });

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Kata laluan semasa tidak betul.',
        );

        expect(screen.getByLabelText('Kata Laluan Semasa')).toBeTruthy();
    });

    it('memaparkan keadaan sedang menukar kata laluan', () => {
        renderPage({
            passwordSaving: true,
        });

        expect(screen.getByRole('button', {
            name: 'Sedang menukar kata laluan...',
        })).toBeDisabled();
    });

    it('memaparkan mesej ralat daripada backend', () => {
        renderPage({
            passwordError: 'Kata laluan baharu tidak memenuhi syarat.',
        });

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Kata laluan baharu tidak memenuhi syarat.',
        );
    });

    it('membawa pengguna kembali ke log masuk selepas berjaya', async () => {
        const { props } = renderPage({
            onChangePassword: vi.fn().mockResolvedValue(true),
        });

        fireEvent.change(screen.getByLabelText('Kata Laluan Semasa'), {
            target: { value: 'OldPass123' },
        });

        fireEvent.change(screen.getByLabelText('Kata Laluan Baharu'), {
            target: { value: 'NewPass123' },
        });

        fireEvent.change(screen.getByLabelText('Sahkan Kata Laluan Baharu'), {
            target: { value: 'NewPass123' },
        });

        fireEvent.click(screen.getByRole('button', {
            name: 'Tukar Kata Laluan',
        }));

        fireEvent.click(await screen.findByRole('button', {
            name: 'Kembali ke Log Masuk',
        }));

        expect(props.onReturnToLogin).toHaveBeenCalledTimes(1);
    });

    it('menetapkan jenis input kata laluan dengan betul', () => {
        renderPage();

        expect(screen.getByLabelText('Kata Laluan Semasa')).toHaveAttribute(
            'type',
            'password',
        );

        expect(screen.getByLabelText('Kata Laluan Baharu')).toHaveAttribute(
            'type',
            'password',
        );

        expect(screen.getByLabelText('Sahkan Kata Laluan Baharu')).toHaveAttribute(
            'type',
            'password',
        );
    });
});
