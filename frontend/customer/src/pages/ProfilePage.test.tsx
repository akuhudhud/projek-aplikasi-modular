
import {
    afterEach,
    beforeEach,
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
        pictureUploading: false,
        pictureError: '',
        pictureMessage: '',
        onNameChange: vi.fn(),
        onSubmit: vi.fn((event) => event.preventDefault()),
        onPictureSelect: vi.fn(),
        ...overrides,
    };

    return {
        ...render(<ProfilePage {...props} />),
        props,
    };
}

describe('ProfilePage', () => {
    beforeEach(() => {
        Object.assign(URL, {
            createObjectURL: vi.fn(() => 'blob:profile-preview'),
            revokeObjectURL: vi.fn(),
        });
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    function createImageFile() {
        return new File(
            ['profile-image'],
            'profile.png',
            { type: 'image/png' },
        );
    }

    function selectImage(file = createImageFile()) {
        fireEvent.change(screen.getByLabelText('Gambar Profil'), {
            target: { files: [file] },
        });

        return file;
    }

    it('displays the profile heading and description', () => {
        renderProfilePage();

        expect(screen.getByRole('heading', {
            name: 'Profil Saya',
        })).toBeTruthy();

        expect(screen.getByText(
            'Maklumat akaun pelanggan Super Runner.',
        )).toBeTruthy();
    });

    it('displays the customer profile name', () => {
        renderProfilePage();

        expect(screen.getByLabelText('Nama')).toHaveValue('Nazim');
    });

    it('displays the first letter of the profile name as avatar', () => {
        renderProfilePage();

        expect(
            document.querySelector('.customer-profile-avatar'),
        ).toHaveTextContent('N');
    });

    it('displays avatar letter in uppercase', () => {
        renderProfilePage({
            account: { ...account, profile_name: 'ali' },
        });

        expect(
            document.querySelector('.customer-profile-avatar'),
        ).toHaveTextContent('A');
    });

    it('displays question mark when profile name is empty', () => {
        renderProfilePage({
            account: { ...account, profile_name: '' },
        });

        expect(
            document.querySelector('.customer-profile-avatar'),
        ).toHaveTextContent('?');
    });

    it('displays uploaded profile picture', () => {
        renderProfilePage({
            account: {
                ...account,
                profile_picture: 'profile-pictures/customer-001/profile.jpg',
            },
        });

        expect(screen.getByRole('img', {
            name: 'Gambar profil Nazim',
        })).toHaveAttribute(
            'src',
            `${window.location.origin}/storage/profile-pictures/customer-001/profile.jpg`,
        );
    });

    it('calls name change callback when input changes', () => {
        const { props } = renderProfilePage();

        fireEvent.change(screen.getByLabelText('Nama'), {
            target: { value: 'Nazim Baru' },
        });

        expect(props.onNameChange).toHaveBeenCalledWith('Nazim Baru');
    });

    it('accepts supported profile picture formats', () => {
        renderProfilePage();

        expect(screen.getByLabelText('Gambar Profil')).toHaveAttribute(
            'accept',
            'image/jpeg,image/png,image/webp',
        );
    });

    it('shows image preview after selecting a picture', () => {
        renderProfilePage();

        selectImage();

        expect(screen.getByRole('img', {
            name: 'Pratonton gambar profil',
        })).toHaveAttribute('src', 'blob:profile-preview');

        expect(screen.getByText('profile.png')).toBeTruthy();
        expect(screen.getByRole('button', {
            name: 'Gunakan Gambar Ini',
        })).toBeTruthy();
    });

    it('does not upload before user confirms the preview', () => {
        const { props } = renderProfilePage();

        selectImage();

        expect(props.onPictureSelect).not.toHaveBeenCalled();
    });

    it('uploads selected image after confirmation', () => {
        const { props } = renderProfilePage();
        const file = selectImage();

        fireEvent.click(screen.getByRole('button', {
            name: 'Gunakan Gambar Ini',
        }));

        expect(props.onPictureSelect).toHaveBeenCalledWith(file);
    });

    it('allows user to cancel selected image', () => {
        const { props } = renderProfilePage();

        selectImage();

        fireEvent.click(screen.getByRole('button', {
            name: 'Batal',
        }));

        expect(props.onPictureSelect).not.toHaveBeenCalled();
        expect(screen.queryByRole('img', {
            name: 'Pratonton gambar profil',
        })).toBeNull();
    });

    it('clears file input after selecting an image', () => {
        renderProfilePage();

        const input = screen.getByLabelText(
            'Gambar Profil',
        ) as HTMLInputElement;

        selectImage();

        expect(input.value).toBe('');
    });

    it('disables picture input while uploading', () => {
        renderProfilePage({
            pictureUploading: true,
        });

        expect(
            screen.getByLabelText('Gambar Profil'),
        ).toBeDisabled();
    });

    it('displays picture uploading state', () => {
        renderProfilePage({
            pictureUploading: true,
        });

        expect(screen.getByRole('status')).toHaveTextContent(
            'Sedang memuat naik gambar...',
        );
    });

    it('displays picture upload error', () => {
        renderProfilePage({
            pictureError: 'Format gambar tidak sah.',
        });

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Format gambar tidak sah.',
        );
    });

    it('displays picture upload success message', () => {
        renderProfilePage({
            pictureMessage: 'Gambar profil berjaya dikemaskini.',
        });

        expect(screen.getByRole('status')).toHaveTextContent(
            'Gambar profil berjaya dikemaskini.',
        );
    });

    it('displays customer phone number', () => {
        renderProfilePage();

        expect(screen.getByText('0123456789')).toBeTruthy();
    });

    it('displays customer email', () => {
        renderProfilePage();

        expect(screen.getByText('nazim@example.com')).toBeTruthy();
    });

    it('displays account role and status', () => {
        renderProfilePage();

        expect(screen.getByText('USER')).toBeTruthy();
        expect(screen.getByText('ACTIVE')).toBeTruthy();
    });

    it('uses fallback when phone number is missing', () => {
        renderProfilePage({
            account: { ...account, phone: null },
        });

        expect(
            screen.getByText('Nombor Telefon').parentElement,
        ).toHaveTextContent('Belum ditetapkan');
    });

    it('uses fallback when email is missing', () => {
        renderProfilePage({
            account: { ...account, email: null },
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

        expect(screen.getByRole('button', {
            name: 'Sedang menyimpan...',
        })).toBeDisabled();
    });

    it('disables name input while saving', () => {
        renderProfilePage({
            profileSaving: true,
        });

        expect(screen.getByLabelText('Nama')).toBeDisabled();
    });

    it('displays profile error message', () => {
        renderProfilePage({
            profileError: 'Gagal menyimpan profil.',
        });

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Gagal menyimpan profil.',
        );
    });

    it('displays profile success message', () => {
        renderProfilePage({
            profileMessage: 'Profil berjaya dikemas kini.',
        });

        expect(screen.getByRole('status')).toHaveTextContent(
            'Profil berjaya dikemas kini.',
        );
    });

    it('limits name input to 255 characters', () => {
        renderProfilePage();

        expect(screen.getByLabelText('Nama')).toHaveAttribute(
            'maxLength',
            '255',
        );
    });
});
