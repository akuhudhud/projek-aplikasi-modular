
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
    role: 'CUSTOMER',
    status: 'active',
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
        contactSaving: false,
        contactError: '',
        contactMessage: '',
        onNameChange: vi.fn(),
        onSubmit: vi.fn((event) => event.preventDefault()),
        onPictureSelect: vi.fn().mockResolvedValue(true),
        onRequestPhoneChange: vi.fn().mockResolvedValue(true),
        onVerifyPhoneChange: vi.fn().mockResolvedValue(true),
        onCompletePhoneChange: vi.fn().mockResolvedValue(true),
        onRequestEmailChange: vi.fn().mockResolvedValue(true),
        onVerifyEmailChange: vi.fn().mockResolvedValue(true),
        onCompleteEmailChange: vi.fn().mockResolvedValue(true),
        ...overrides,
    };

    return {
        ...render(<ProfilePage {...props} />),
        props,
    };
}

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

describe('ProfilePage - pertukaran nombor telefon', () => {
    it('memaparkan borang nombor telefon baharu', () => {
        renderPage();

        expect(screen.getByRole('heading', {
            name: 'Tukar Nombor Telefon',
        })).toBeTruthy();

        expect(screen.getByLabelText('Nombor Telefon Baharu')).toBeTruthy();
    });

    it('menghantar nombor telefon baharu untuk permintaan OTP', async () => {
        const { props } = renderPage();

        fireEvent.change(screen.getByLabelText('Nombor Telefon Baharu'), {
            target: { value: '60123456789' },
        });

        fireEvent.submit(
            screen.getByLabelText('Nombor Telefon Baharu').closest('form')!,
        );

        await waitFor(() => {
            expect(props.onRequestPhoneChange).toHaveBeenCalledWith({
                phone: '60123456789',
            });
        });
    });

    it('memaparkan ruangan OTP selepas permintaan berjaya', () => {
        renderPage({
            phoneChangeVerificationId: 'phone-verification-001',
        });

        expect(screen.getByLabelText('Kod Pengesahan Telefon')).toBeTruthy();
    });

    it('menghantar OTP bersama verification ID sebenar', async () => {
        const { props } = renderPage({
            phoneChangeVerificationId: 'phone-verification-001',
        });

        fireEvent.change(screen.getByLabelText('Kod Pengesahan Telefon'), {
            target: { value: '123456' },
        });

        fireEvent.submit(
            screen.getByLabelText('Kod Pengesahan Telefon').closest('form')!,
        );

        await waitFor(() => {
            expect(props.onVerifyPhoneChange).toHaveBeenCalledWith({
                verification_id: 'phone-verification-001',
                otp: '123456',
            });
        });
    });

    it('memaparkan tindakan lengkap selepas telefon disahkan', () => {
        renderPage({
            phoneChangeVerified: true,
        });

        expect(screen.getByRole('button', {
            name: 'Sahkan Nombor Baharu',
        })).toBeTruthy();
    });

    it('melengkapkan pertukaran telefon selepas pengesahan', async () => {
        const { props } = renderPage({
            phoneChangeVerified: true,
        });

        fireEvent.click(screen.getByRole('button', {
            name: 'Sahkan Nombor Baharu',
        }));

        await waitFor(() => {
            expect(props.onCompletePhoneChange).toHaveBeenCalledTimes(1);
        });

        expect(await screen.findByText(
            'Nombor telefon berjaya dikemaskini.',
        )).toBeTruthy();
    });

    it('memaparkan ralat pertukaran telefon', () => {
        renderPage({
            contactError: 'Nombor telefon telah digunakan.',
        });

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Nombor telefon telah digunakan.',
        );
    });
});

describe('ProfilePage - pertukaran e-mel', () => {
    it('memaparkan borang e-mel baharu', () => {
        renderPage();

        expect(screen.getByRole('heading', {
            name: 'Tukar E-mel',
        })).toBeTruthy();

        expect(screen.getByLabelText('E-mel Baharu')).toBeTruthy();
    });

    it('menghantar e-mel baharu untuk permintaan OTP', async () => {
        const { props } = renderPage();

        fireEvent.change(screen.getByLabelText('E-mel Baharu'), {
            target: { value: 'new@example.com' },
        });

        fireEvent.submit(
            screen.getByLabelText('E-mel Baharu').closest('form')!,
        );

        await waitFor(() => {
            expect(props.onRequestEmailChange).toHaveBeenCalledWith({
                email: 'new@example.com',
            });
        });
    });

    it('memaparkan ruangan OTP selepas permintaan berjaya', () => {
        renderPage({
            emailChangeVerificationId: 'email-verification-001',
        });

        expect(screen.getByLabelText('Kod Pengesahan E-mel')).toBeTruthy();
    });

    it('menghantar OTP bersama verification ID sebenar', async () => {
        const { props } = renderPage({
            emailChangeVerificationId: 'email-verification-001',
        });

        fireEvent.change(screen.getByLabelText('Kod Pengesahan E-mel'), {
            target: { value: '654321' },
        });

        fireEvent.submit(
            screen.getByLabelText('Kod Pengesahan E-mel').closest('form')!,
        );

        await waitFor(() => {
            expect(props.onVerifyEmailChange).toHaveBeenCalledWith({
                verification_id: 'email-verification-001',
                otp: '654321',
            });
        });
    });

    it('memaparkan tindakan lengkap selepas e-mel disahkan', () => {
        renderPage({
            emailChangeVerified: true,
        });

        expect(screen.getByRole('button', {
            name: 'Sahkan E-mel Baharu',
        })).toBeTruthy();
    });

    it('melengkapkan pertukaran e-mel selepas pengesahan', async () => {
        const { props } = renderPage({
            emailChangeVerified: true,
        });

        fireEvent.click(screen.getByRole('button', {
            name: 'Sahkan E-mel Baharu',
        }));

        await waitFor(() => {
            expect(props.onCompleteEmailChange).toHaveBeenCalledTimes(1);
        });

        expect(await screen.findByText(
            'E-mel berjaya dikemaskini.',
        )).toBeTruthy();
    });

    it('memaparkan ralat pertukaran e-mel', () => {
        renderPage({
            contactError: 'Alamat e-mel telah digunakan.',
        });

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Alamat e-mel telah digunakan.',
        );
    });
});

describe('ProfilePage - keadaan proses', () => {
    it('memaparkan status sedang memproses pengesahan', () => {
        renderPage({
            contactSaving: true,
        });

        expect(screen.getByRole('status')).toHaveTextContent(
            'Sedang memproses pengesahan maklumat hubungan...',
        );
    });

    it('memaparkan mesej kejayaan pengesahan', () => {
        renderPage({
            contactMessage: 'Kod pengesahan berjaya dihantar.',
        });

        expect(screen.getByRole('status')).toHaveTextContent(
            'Kod pengesahan berjaya dihantar.',
        );
    });

    it('menetapkan input OTP kepada enam digit', () => {
        renderPage({
            phoneChangeVerificationId: 'phone-verification-001',
        });

        const otpInput = screen.getByLabelText(
            'Kod Pengesahan Telefon',
        );

        expect(otpInput).toHaveAttribute('maxLength', '6');
        expect(otpInput).toHaveAttribute('pattern', '[0-9]{6}');
    });
});
