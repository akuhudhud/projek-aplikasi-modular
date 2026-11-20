
// @vitest-environment jsdom

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
    render,
    screen,
} from '@testing-library/react';

const { getRequestMock, awardQuotationMock } = vi.hoisted(() => ({
    getRequestMock: vi.fn(),
    awardQuotationMock: vi.fn(),
}));

vi.mock('axios', () => ({
    default: {
        isAxiosError: vi.fn(() => false),
    },
}));

vi.mock('../src/api', () => ({
    customerApi: {
        getRequest: getRequestMock,
        awardQuotation: awardQuotationMock,
    },
}));

import CustomerRequestQuotations from '../src/CustomerRequestQuotations';

const quotationStatuses = [
    { status: 'SUBMITTED', label: 'Dihantar' },
    { status: 'ACCEPTED', label: 'Diterima' },
    { status: 'NOT_SELECTED', label: 'Tidak dipilih' },
    { status: 'WITHDRAWN', label: 'Ditarik balik' },
    { status: 'EXPIRED', label: 'Tamat tempoh' },
];

function makeRequestDetail() {
    const now = new Date().toISOString();

    return {
        request: {
            request_id: 'request-test-001',
            task_class: 'NON_DELIVERY' as const,
            description: 'Bantu membeli barang.',
            pickup_location_snapshot: null,
            delivery_location_snapshot: null,
            task_location_snapshot: {
                address: 'Pekan Tuaran',
            },
            status: 'OPEN',
            expires_at: new Date(
                Date.now() + 60 * 60 * 1000,
            ).toISOString(),
            awarded_at: null,
            cancelled_at: null,
            created_at: now,
            updated_at: now,
            winning_quotation_id: null,
        },
        quotations: quotationStatuses.map((item, index) => ({
            quotation_id: `quotation-${index + 1}`,
            request_id: 'request-test-001',
            amount: '15.00',
            currency: 'MYR',
            message: null,
            status: item.status,
            expires_at: null,
            accepted_at: null,
            withdrawn_at: null,
            created_at: now,
            runner_profile: null,
        })),
    };
}

describe('CustomerRequestQuotations component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        getRequestMock.mockResolvedValue(makeRequestDetail());
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('displays all supported quotation statuses in Malay', async () => {
        render(
            <CustomerRequestQuotations
                requestId="request-test-001"
                requestStatus="OPEN"
                autoExpand
                onAwarded={vi.fn()}
            />,
        );

        expect(
            await screen.findByRole('heading', {
                name: 'Sebut Harga daripada Runner',
            }),
        ).toBeTruthy();

        for (const item of quotationStatuses) {
            expect(
                screen.getByText(item.label),
            ).toBeTruthy();
        }

        expect(getRequestMock).toHaveBeenCalledWith(
            'request-test-001',
        );
    });

    it('does not display raw English quotation status values', async () => {
        render(
            <CustomerRequestQuotations
                requestId="request-test-001"
                requestStatus="OPEN"
                autoExpand
                onAwarded={vi.fn()}
            />,
        );

        await screen.findByRole('heading', {
            name: 'Sebut Harga daripada Runner',
        });

        for (const item of quotationStatuses) {
            expect(
                screen.queryByText(item.status, {
                    exact: true,
                }),
            ).toBeNull();
        }
    });

    it('shows an empty message when there are no quotations', async () => {
        getRequestMock.mockResolvedValue({
            ...makeRequestDetail(),
            quotations: [],
        });

        render(
            <CustomerRequestQuotations
                requestId="request-test-001"
                requestStatus="OPEN"
                autoExpand
                onAwarded={vi.fn()}
            />,
        );

        expect(
            await screen.findByText(
                'Belum ada Runner menghantar sebut harga.',
            ),
        ).toBeTruthy();
    });
});
