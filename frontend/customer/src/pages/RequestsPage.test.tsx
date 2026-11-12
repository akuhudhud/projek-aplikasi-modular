
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
    CustomerOwnMarketplaceRequest,
} from '../api';

import RequestsPage from './RequestsPage';

vi.mock('../CustomerRequestQuotations', () => ({
    default: () => (
        <div data-testid="customer-request-quotations">
            Senarai Sebut Harga
        </div>
    ),
}));

function createRequest(
    id: string,
    taskClass: 'PICKUP_DELIVERY' | 'NON_DELIVERY' = 'PICKUP_DELIVERY',
    status: string = 'OPEN',
): CustomerOwnMarketplaceRequest {
    return {
        request_id: id,
        task_class: taskClass,
        description: `Penerangan ${id}`,
        pickup_location_snapshot: taskClass === 'PICKUP_DELIVERY'
            ? {
                address: 'Kedai Tambalang',
                contact_name: 'Ali',
                phone: '0123456789',
            }
            : null,
        delivery_location_snapshot: taskClass === 'PICKUP_DELIVERY'
            ? {
                address: 'Pekan Tuaran',
                contact_name: 'Abu',
                phone: '0198765432',
            }
            : null,
        task_location_snapshot: taskClass === 'NON_DELIVERY'
            ? {
                address: 'Dewan Kampung',
                contact_name: 'Ali',
                phone: '0123456789',
            }
            : null,
        status,
        expires_at: '2026-11-13T10:00:00.000Z',
        awarded_at: null,
        cancelled_at: null,
        created_at: '2026-11-12T08:00:00.000Z',
        updated_at: '2026-11-12T08:00:00.000Z',
    } as CustomerOwnMarketplaceRequest;
}

function renderRequestsPage(
    overrides: Partial<React.ComponentProps<typeof RequestsPage>> = {},
) {
    const props: React.ComponentProps<typeof RequestsPage> = {
        requests: [],
        currentPage: 1,
        lastPage: 1,
        total: 0,
        loading: false,
        error: '',
        cancellingRequestId: null,
        actionMessage: '',
        actionError: '',
        onCancel: vi.fn(),
        onPageChange: vi.fn(),
        onQuotationAwarded: vi.fn(),
        ...overrides,
    };

    return {
        ...render(<RequestsPage {...props} />),
        props,
    };
}

describe('RequestsPage', () => {
    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('displays the page heading and total', () => {
        renderRequestsPage({
            total: 8,
        });

        expect(
            screen.getByRole('heading', {
                name: 'Permintaan Saya',
            }),
        ).toBeTruthy();

        expect(
            screen.getByText('Jumlah permintaan: 8'),
        ).toBeTruthy();
    });

    it('displays loading state', () => {
        renderRequestsPage({
            loading: true,
        });

        expect(
            screen.getByText('Memuatkan senarai permintaan...'),
        ).toBeTruthy();
    });

    it('displays an error state', () => {
        renderRequestsPage({
            error: 'Gagal memuatkan permintaan.',
        });

        expect(
            screen.getByRole('alert'),
        ).toHaveTextContent('Gagal memuatkan permintaan.');
    });

    it('displays an empty state', () => {
        renderRequestsPage();

        expect(
            screen.getByRole('heading', {
                name: 'Belum ada permintaan',
            }),
        ).toBeTruthy();
    });

    it('displays action success message', () => {
        renderRequestsPage({
            actionMessage: 'Permintaan berjaya dibatalkan.',
        });

        expect(
            screen.getByRole('status'),
        ).toHaveTextContent('Permintaan berjaya dibatalkan.');
    });

    it('displays action error message', () => {
        renderRequestsPage({
            actionError: 'Tidak dapat membatalkan permintaan.',
        });

        expect(
            screen.getByRole('alert'),
        ).toHaveTextContent('Tidak dapat membatalkan permintaan.');
    });

    it('displays pickup and delivery request details', () => {
        renderRequestsPage({
            requests: [
                createRequest('request-001'),
            ],
        });

        expect(
            screen.getByText('Ambil dan Hantar'),
        ).toBeTruthy();

        expect(
            screen.getByText('Lokasi Pengambilan'),
        ).toBeTruthy();

        expect(
            screen.getByText('Lokasi Penghantaran'),
        ).toBeTruthy();

        expect(
            screen.getByText('Kedai Tambalang'),
        ).toBeTruthy();

        expect(
            screen.getByText('Pekan Tuaran'),
        ).toBeTruthy();
    });

    it('displays non-delivery request details', () => {
        renderRequestsPage({
            requests: [
                createRequest(
                    'request-002',
                    'NON_DELIVERY',
                ),
            ],
        });

        expect(
            screen.getByText('Tugasan Bukan Penghantaran'),
        ).toBeTruthy();

        expect(
            screen.getByText('Lokasi Tugasan'),
        ).toBeTruthy();

        expect(
            screen.getByText('Dewan Kampung'),
        ).toBeTruthy();

        expect(
            screen.queryByText('Lokasi Pengambilan'),
        ).toBeNull();
    });

    it('displays request status and description', () => {
        renderRequestsPage({
            requests: [
                createRequest('request-003'),
            ],
        });

        expect(
            screen.getByText('Penerangan request-003'),
        ).toBeTruthy();

        expect(
            screen.getByText('Status: OPEN'),
        ).toBeTruthy();
    });

    it('allows customer to cancel an open request', () => {
        const request = createRequest('request-004');

        const { props } = renderRequestsPage({
            requests: [request],
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Batalkan Permintaan',
            }),
        );

        expect(props.onCancel).toHaveBeenCalledWith(
            'request-004',
        );
    });

    it('shows cancellation loading state for the selected request', () => {
        renderRequestsPage({
            requests: [
                createRequest('request-005'),
            ],
            cancellingRequestId: 'request-005',
        });

        expect(
            screen.getByRole('button', {
                name: 'Sedang membatalkan...',
            }),
        ).toBeDisabled();
    });

    it('disables cancellation buttons while another cancellation is running', () => {
        renderRequestsPage({
            requests: [
                createRequest('request-006'),
                createRequest('request-007'),
            ],
            cancellingRequestId: 'request-006',
        });

        const cancelButtons = screen.getAllByRole('button', {
            name: /membatalkan|Batalkan Permintaan/,
        });

        expect(cancelButtons).toHaveLength(2);

        cancelButtons.forEach((button) => {
            expect(button).toBeDisabled();
        });
    });

    it('does not display cancellation button for awarded request', () => {
        renderRequestsPage({
            requests: [
                createRequest(
                    'request-008',
                    'PICKUP_DELIVERY',
                    'AWARDED',
                ),
            ],
        });

        expect(
            screen.queryByRole('button', {
                name: 'Batalkan Permintaan',
            }),
        ).toBeNull();
    });

    it('displays quotation section for open requests', () => {
        renderRequestsPage({
            requests: [
                createRequest('request-009'),
            ],
        });

        expect(
            screen.getByTestId('customer-request-quotations'),
        ).toBeTruthy();
    });

    it('displays quotation section for awarded requests', () => {
        renderRequestsPage({
            requests: [
                createRequest(
                    'request-010',
                    'PICKUP_DELIVERY',
                    'AWARDED',
                ),
            ],
        });

        expect(
            screen.getByTestId('customer-request-quotations'),
        ).toBeTruthy();
    });

    it('does not display quotation section for cancelled requests', () => {
        renderRequestsPage({
            requests: [
                createRequest(
                    'request-011',
                    'PICKUP_DELIVERY',
                    'CANCELLED',
                ),
            ],
        });

        expect(
            screen.queryByTestId('customer-request-quotations'),
        ).toBeNull();
    });

    it('navigates to the previous page', () => {
        const { props } = renderRequestsPage({
            requests: [createRequest('request-012')],
            currentPage: 2,
            lastPage: 3,
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Sebelum',
            }),
        );

        expect(props.onPageChange).toHaveBeenCalledWith(1);
    });

    it('navigates to the next page', () => {
        const { props } = renderRequestsPage({
            requests: [createRequest('request-013')],
            currentPage: 1,
            lastPage: 3,
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Seterusnya',
            }),
        );

        expect(props.onPageChange).toHaveBeenCalledWith(2);
    });

    it('disables previous page button on first page', () => {
        renderRequestsPage({
            requests: [createRequest('request-014')],
            currentPage: 1,
            lastPage: 3,
        });

        expect(
            screen.getByRole('button', {
                name: 'Sebelum',
            }),
        ).toBeDisabled();
    });

    it('disables next page button on last page', () => {
        renderRequestsPage({
            requests: [createRequest('request-015')],
            currentPage: 3,
            lastPage: 3,
        });

        expect(
            screen.getByRole('button', {
                name: 'Seterusnya',
            }),
        ).toBeDisabled();
    });

    it('disables pagination buttons while loading', () => {
        renderRequestsPage({
            requests: [createRequest('request-016')],
            currentPage: 2,
            lastPage: 3,
            loading: true,
        });

        expect(
            screen.getByRole('button', {
                name: 'Sebelum',
            }),
        ).toBeDisabled();

        expect(
            screen.getByRole('button', {
                name: 'Seterusnya',
            }),
        ).toBeDisabled();
    });
});
