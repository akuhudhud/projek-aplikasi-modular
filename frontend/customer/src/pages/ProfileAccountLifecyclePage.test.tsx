import {
    afterEach,
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

function renderProfilePage(
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
        onNameChange: vi.fn(),
        onSubmit: vi.fn((event) => event.preventDefault()),
        onPictureSelect: vi.fn().mockResolvedValue(true),
        onDeactivateAccount: vi.fn().mockResolvedValue(true),
        onDeleteAccount: vi.fn().mockResolvedValue(true),
        ...overrides,
    };

    return {
        ...render(<ProfilePage {...props} />),
        props,
    };
}

describe('ProfilePage account lifecycle', () => {
    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('displays account management actions', () => {
        renderProfilePage();

        expect(screen.getByRole('heading', {
            name: 'Urus Akaun',
        })).toBeTruthy();

        expect(screen.getByRole('button', {
            name: 'Nyahaktifkan Akaun',
        })).toBeTruthy();

        expect(screen.getByRole('button', {
            name: 'Padam Akaun',
        })).toBeTruthy();
    });

    it('requires confirmation before deactivating account', () => {
        const { props } = renderProfilePage();

        fireEvent.click(screen.getByRole('button', {
            name: 'Nyahaktifkan Akaun',
        }));

        expect(screen.getByRole('dialog')).toBeTruthy();

        expect(screen.getByRole('heading', {
            name: 'Sahkan Penyahaktifan Akaun',
        })).toBeTruthy();

        expect(props.onDeactivateAccount).not.toHaveBeenCalled();
    });

    it('cancels account deactivation without calling the action', () => {
        const { props } = renderProfilePage();

        fireEvent.click(screen.getByRole('button', {
            name: 'Nyahaktifkan Akaun',
        }));

        fireEvent.click(screen.getByRole('button', {
            name: 'Batal',
        }));

        expect(screen.queryByRole('dialog')).toBeNull();
        expect(props.onDeactivateAccount).not.toHaveBeenCalled();
    });

    it('confirms account deactivation', async () => {
        const { props } = renderProfilePage();

        fireEvent.click(screen.getByRole('button', {
            name: 'Nyahaktifkan Akaun',
        }));

        fireEvent.click(screen.getByRole('button', {
            name: 'Ya, Teruskan',
        }));

        await waitFor(() => {
            expect(props.onDeactivateAccount).toHaveBeenCalledTimes(1);
        });

        await waitFor(() => {
            expect(screen.queryByRole('dialog')).toBeNull();
        });
    });

    it('requires confirmation before deleting account', () => {
        const { props } = renderProfilePage();

        fireEvent.click(screen.getByRole('button', {
            name: 'Padam Akaun',
        }));

        expect(screen.getByRole('dialog')).toBeTruthy();

        expect(screen.getByRole('heading', {
            name: 'Sahkan Pemadaman Akaun',
        })).toBeTruthy();

        expect(props.onDeleteAccount).not.toHaveBeenCalled();
    });

    it('cancels account deletion without calling the action', () => {
        const { props } = renderProfilePage();

        fireEvent.click(screen.getByRole('button', {
            name: 'Padam Akaun',
        }));

        fireEvent.click(screen.getByRole('button', {
            name: 'Batal',
        }));

        expect(screen.queryByRole('dialog')).toBeNull();
        expect(props.onDeleteAccount).not.toHaveBeenCalled();
    });

    it('confirms account deletion', async () => {
        const { props } = renderProfilePage();

        fireEvent.click(screen.getByRole('button', {
            name: 'Padam Akaun',
        }));

        fireEvent.click(screen.getByRole('button', {
            name: 'Ya, Teruskan',
        }));

        await waitFor(() => {
            expect(props.onDeleteAccount).toHaveBeenCalledTimes(1);
        });

        await waitFor(() => {
            expect(screen.queryByRole('dialog')).toBeNull();
        });
    });

    it('keeps confirmation open when deactivation fails', async () => {
        const { props } = renderProfilePage({
            onDeactivateAccount: vi.fn().mockResolvedValue(false),
            accountActionError: 'Tidak berjaya menyahaktifkan akaun.',
        });

        fireEvent.click(screen.getByRole('button', {
            name: 'Nyahaktifkan Akaun',
        }));

        fireEvent.click(screen.getByRole('button', {
            name: 'Ya, Teruskan',
        }));

        await waitFor(() => {
            expect(props.onDeactivateAccount).toHaveBeenCalledTimes(1);
        });

        expect(screen.getByRole('dialog')).toBeTruthy();
        expect(screen.getByRole('alert')).toHaveTextContent(
            'Tidak berjaya menyahaktifkan akaun.',
        );
    });

    it('keeps confirmation open when deletion fails', async () => {
        const { props } = renderProfilePage({
            onDeleteAccount: vi.fn().mockResolvedValue(false),
            accountActionError: 'Tidak berjaya memadam akaun.',
        });

        fireEvent.click(screen.getByRole('button', {
            name: 'Padam Akaun',
        }));

        fireEvent.click(screen.getByRole('button', {
            name: 'Ya, Teruskan',
        }));

        await waitFor(() => {
            expect(props.onDeleteAccount).toHaveBeenCalledTimes(1);
        });

        expect(screen.getByRole('dialog')).toBeTruthy();
        expect(screen.getByRole('alert')).toHaveTextContent(
            'Tidak berjaya memadam akaun.',
        );
    });

    it('disables account actions while request is processing', () => {
        renderProfilePage({
            accountActionSaving: true,
        });

        expect(screen.getByRole('button', {
            name: 'Nyahaktifkan Akaun',
        })).toBeDisabled();

        expect(screen.getByRole('button', {
            name: 'Padam Akaun',
        })).toBeDisabled();
    });

    it('displays account action error', () => {
        renderProfilePage({
            accountActionError: 'Permintaan tidak berjaya.',
        });

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Permintaan tidak berjaya.',
        );
    });

    it('displays processing state during confirmation', () => {
        renderProfilePage({
            accountActionSaving: true,
        });

        fireEvent.click(screen.getByRole('button', {
            name: 'Padam Akaun',
        }));

        expect(screen.getByRole('button', {
            name: 'Sedang diproses...',
        })).toBeDisabled();
    });
});
