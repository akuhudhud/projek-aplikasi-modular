
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

import CreateRequestPage from './CreateRequestPage';

import type {
    CustomerTaskClass,
} from './CreateRequestPage';

function renderPage(
    overrides: Partial<React.ComponentProps<typeof CreateRequestPage>> = {},
) {
    const props: React.ComponentProps<typeof CreateRequestPage> = {
        taskClass: 'PICKUP_DELIVERY',
        description: '',
        pickupAddress: '',
        pickupContact: '',
        pickupPhone: '',
        deliveryAddress: '',
        deliveryContact: '',
        deliveryPhone: '',
        taskAddress: '',
        taskContact: '',
        taskPhone: '',
        expiresAt: '2026-11-13T10:00',
        requestLoading: false,
        requestSuccess: '',
        error: null,
        runnerSlug: null,
        hasInvalidRunnerSlug: false,
        onTaskClassChange: vi.fn(),
        onDescriptionChange: vi.fn(),
        onPickupAddressChange: vi.fn(),
        onPickupContactChange: vi.fn(),
        onPickupPhoneChange: vi.fn(),
        onDeliveryAddressChange: vi.fn(),
        onDeliveryContactChange: vi.fn(),
        onDeliveryPhoneChange: vi.fn(),
        onTaskAddressChange: vi.fn(),
        onTaskContactChange: vi.fn(),
        onTaskPhoneChange: vi.fn(),
        onExpiresAtChange: vi.fn(),
        onSubmit: vi.fn((event) => event.preventDefault()),
        ...overrides,
    };

    return {
        ...render(<CreateRequestPage {...props} />),
        props,
    };
}

describe('CreateRequestPage', () => {
    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('displays the create request heading', () => {
        renderPage();

        expect(
            screen.getByRole('heading', {
                name: 'Cipta Permintaan Terbuka',
            }),
        ).toBeTruthy();
    });

    it('displays the generic runner quotation description', () => {
        renderPage();

        expect(
            screen.getByText(
                'Nyatakan tugasan yang anda perlukan. Runner yang layak boleh menghantar sebut harga.',
            ),
        ).toBeTruthy();
    });

    it('displays the specific runner description for a valid runner slug', () => {
        renderPage({
            runnerSlug: 'runner-nazim',
            hasInvalidRunnerSlug: false,
        });

        expect(
            screen.getByText(
                'Nyatakan tugasan yang anda perlukan. Permintaan ini akan dihantar khusus kepada Runner daripada profil yang anda pilih.',
            ),
        ).toBeTruthy();
    });

    it('uses the generic description when runner slug is invalid', () => {
        renderPage({
            runnerSlug: 'runner-tidak-sah',
            hasInvalidRunnerSlug: true,
        });

        expect(
            screen.getByText(
                'Nyatakan tugasan yang anda perlukan. Runner yang layak boleh menghantar sebut harga.',
            ),
        ).toBeTruthy();
    });

    it('displays both pickup and delivery locations for PICKUP_DELIVERY', () => {
        renderPage({
            taskClass: 'PICKUP_DELIVERY',
        });

        expect(
            screen.getByText('Lokasi Pengambilan'),
        ).toBeTruthy();

        expect(
            screen.getByText('Lokasi Penghantaran'),
        ).toBeTruthy();

        expect(
            screen.queryByText('Lokasi Tugasan'),
        ).toBeNull();
    });

    it('displays only task location for NON_DELIVERY', () => {
        renderPage({
            taskClass: 'NON_DELIVERY',
        });

        expect(
            screen.getByText('Lokasi Tugasan'),
        ).toBeTruthy();

        expect(
            screen.queryByText('Lokasi Pengambilan'),
        ).toBeNull();

        expect(
            screen.queryByText('Lokasi Penghantaran'),
        ).toBeNull();
    });

    it('calls task class callback when selection changes', () => {
        const { props } = renderPage();

        fireEvent.change(
            screen.getByLabelText('Jenis Tugasan'),
            {
                target: {
                    value: 'NON_DELIVERY',
                },
            },
        );

        expect(props.onTaskClassChange).toHaveBeenCalledWith(
            'NON_DELIVERY' satisfies CustomerTaskClass,
        );
    });

    it('calls description callback when description changes', () => {
        const { props } = renderPage();

        fireEvent.change(
            screen.getByLabelText('Penerangan Tugasan'),
            {
                target: {
                    value: 'Hantar dokumen ke pejabat.',
                },
            },
        );

        expect(props.onDescriptionChange).toHaveBeenCalledWith(
            'Hantar dokumen ke pejabat.',
        );
    });

    it('calls pickup location callbacks', () => {
        const { props } = renderPage();

        const fieldset = screen
            .getByText('Lokasi Pengambilan')
            .closest('fieldset');

        if (!fieldset) {
            throw new Error('Pickup fieldset not found.');
        }

        const address = fieldset.querySelector('textarea');
        const inputs = fieldset.querySelectorAll('input');

        if (!address || inputs.length !== 2) {
            throw new Error('Pickup fields not found.');
        }

        fireEvent.change(address, {
            target: {
                value: 'Kedai Tambalang',
            },
        });

        fireEvent.change(inputs[0], {
            target: {
                value: 'Ali',
            },
        });

        fireEvent.change(inputs[1], {
            target: {
                value: '0123456789',
            },
        });

        expect(props.onPickupAddressChange).toHaveBeenCalledWith(
            'Kedai Tambalang',
        );

        expect(props.onPickupContactChange).toHaveBeenCalledWith(
            'Ali',
        );

        expect(props.onPickupPhoneChange).toHaveBeenCalledWith(
            '0123456789',
        );
    });

    it('calls delivery location callbacks', () => {
        const { props } = renderPage();

        const fieldset = screen
            .getByText('Lokasi Penghantaran')
            .closest('fieldset');

        if (!fieldset) {
            throw new Error('Delivery fieldset not found.');
        }

        const address = fieldset.querySelector('textarea');

        if (!address) {
            throw new Error('Delivery address field not found.');
        }

        fireEvent.change(address, {
            target: {
                value: 'Pekan Tuaran',
            },
        });

        expect(props.onDeliveryAddressChange).toHaveBeenCalledWith(
            'Pekan Tuaran',
        );
    });

    it('calls task location callbacks for NON_DELIVERY', () => {
        const { props } = renderPage({
            taskClass: 'NON_DELIVERY',
        });

        const fieldset = screen
            .getByText('Lokasi Tugasan')
            .closest('fieldset');

        if (!fieldset) {
            throw new Error('Task fieldset not found.');
        }

        const address = fieldset.querySelector('textarea');

        if (!address) {
            throw new Error('Task address field not found.');
        }

        fireEvent.change(address, {
            target: {
                value: 'Dewan Kampung Tambalang',
            },
        });

        expect(props.onTaskAddressChange).toHaveBeenCalledWith(
            'Dewan Kampung Tambalang',
        );
    });

    it('calls expiry callback when date changes', () => {
        const { props } = renderPage();

        fireEvent.change(
            screen.getByLabelText(
                'Tarikh dan Masa Tamat Permintaan',
            ),
            {
                target: {
                    value: '2026-11-13T12:00',
                },
            },
        );

        expect(props.onExpiresAtChange).toHaveBeenCalledWith(
            '2026-11-13T12:00',
        );
    });

    it('displays success message', () => {
        renderPage({
            requestSuccess: 'Permintaan berjaya dihantar.',
        });

        expect(
            screen.getByRole('status'),
        ).toHaveTextContent('Permintaan berjaya dihantar.');
    });

    it('displays error message', () => {
        renderPage({
            error: 'Permintaan gagal dihantar.',
        });

        expect(
            screen.getByRole('alert'),
        ).toHaveTextContent('Permintaan gagal dihantar.');
    });

    it('submits the form', () => {
        const { props } = renderPage();

        fireEvent.submit(
            screen.getByRole('button', {
                name: 'Hantar Permintaan',
            }).closest('form')!,
        );

        expect(props.onSubmit).toHaveBeenCalledTimes(1);
    });

    it('disables form controls while request is loading', () => {
        renderPage({
            requestLoading: true,
        });

        expect(
            screen.getByLabelText('Jenis Tugasan'),
        ).toBeDisabled();

        expect(
            screen.getByLabelText('Penerangan Tugasan'),
        ).toBeDisabled();

        expect(
            screen.getByRole('button', {
                name: 'Sedang menghantar permintaan...',
            }),
        ).toBeDisabled();
    });

    it('displays the 24-hour expiry guidance', () => {
        renderPage();

        expect(
            screen.getByText(
                'Permintaan mesti tamat dalam tempoh maksimum 24 jam.',
            ),
        ).toBeTruthy();
    });
});
