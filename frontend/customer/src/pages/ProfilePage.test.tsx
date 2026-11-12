
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
} from '@testing-library/react';

import type {
    CustomerAccount,
} from '../api';

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
        onNameChange: vi.fn(),
        onSubmit: vi.fn((event) => event.preventDefault()),
        ...overrides,
    };

    return {
        ...render(<ProfilePage {...props} />),
        props,
    };
}

describe('ProfilePage', () => {
    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('displays the profile heading and description', () => {
        renderProfilePage();

        expect(
            screen.getByRole('heading', {
                name: 'Profil Saya',
            }),
        ).toBeTruthy();

        expect(
            screen.getByText(
                'Maklumat akaun pelanggan Super Runner.',
            ),
        ).toBeTruthy();
    });

    it('displays the customer profile name', () => {
        renderProfilePage();

        expect(
            screen.getByLabelText('Nama'),
        ).toHaveValue('Nazim');
    });

    it('displays the first letter of the profile name as avatar', () => {
        renderProfilePage();

        expect(
            document.querySelector('.customer-profile-avatar'),
        ).toHaveTextContent('N');
    });

    it('displays avatar letter in uppercase', () => {
        renderProfilePage({
            account: {
                ...account,
                profile_name: 'ali',
            },
        });

        expect(
            document.querySelector('.customer-profile-avatar'),
        ).toHaveTextContent('A');
    });

    it('displays question mark when profile name is empty', () => {
        renderProfilePage({
            account: {
                ...account,
                profile_name: '',
            },
        });

        expect(
            document.querySelector('.customer-profile-avatar'),
        ).toHaveTextContent('?');
    });

    it('calls name change callback when input changes', () => {
        const { props } = renderProfilePage();

        fireEvent.change(
            screen.getByLabelText('Nama'),
            {
                target: {
                    value: 'Nazim Baru',
                },
            },
        );

        expect(props.onNameChange).toHaveBeenCalledWith(
            'Nazim Baru',
        );
    });

    it('displays customer phone number', () => {
        renderProfilePage();

        expect(
            screen.getByText('0123456789'),
        ).toBeTruthy();
    });

    it('displays customer email', () => {
        renderProfilePage();

        expect(
            screen.getByText('nazim@example.com'),
        ).toBeTruthy();
    });

    it('displays account role and status', () => {
        renderProfilePage();

        expect(screen.getByText('USER')).toBeTruthy();
        expect(screen.getByText('ACTIVE')).toBeTruthy();
    });

    it('uses fallback when phone number is missing', () => {
        renderProfilePage({
            account: {
                ...account,
                phone: null,
            },
        });

        expect(
            screen.getByText('Nombor Telefon').parentElement,
        ).toHaveTextContent('Belum ditetapkan');
    });

    it('uses fallback when email is missing', () => {
        renderProfilePage({
            account: {
                ...account,
                email: null,
            },
        });

        expect(
            screen.getByText('E-mel').parentElement,
        ).toHaveTextContent('Belum ditetapkan');
    });

    it('submits profile form', () => {
        const { props } = renderProfilePage();

        fireEvent.submit(
            screen.getByRole('button', {
                name: 'Simpan Perubahan',
            }).closest('form')!,
        );

        expect(props.onSubmit).toHaveBeenCalledTimes(1);
    });

    it('displays profile saving state', () => {
        renderProfilePage({
            profileSaving: true,
        });

        expect(
            screen.getByRole('button', {
                name: 'Sedang menyimpan...',
            }),
        ).toBeDisabled();
    });

    it('disables name input while saving', () => {
        renderProfilePage({
            profileSaving: true,
        });

        expect(
            screen.getByLabelText('Nama'),
        ).toBeDisabled();
    });

    it('displays profile error message', () => {
        renderProfilePage({
            profileError: 'Gagal menyimpan profil.',
        });

        expect(
            screen.getByRole('alert'),
        ).toHaveTextContent('Gagal menyimpan profil.');
    });

    it('displays profile success message', () => {
        renderProfilePage({
            profileMessage: 'Profil berjaya dikemas kini.',
        });

        expect(
            screen.getByRole('status'),
        ).toHaveTextContent('Profil berjaya dikemas kini.');
    });

    it('limits name input to 255 characters', () => {
        renderProfilePage();

        expect(
            screen.getByLabelText('Nama'),
        ).toHaveAttribute('maxLength', '255');
    });
});
