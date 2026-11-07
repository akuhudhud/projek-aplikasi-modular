
 // @vitest-environment jsdom

import {
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

import {
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/react';

const { getRequestMock, awardQuotationMock } = vi.hoisted(() => ({
    getRequestMock: vi.fn(),
    awardQuotationMock: vi.fn(),
}));

vi.mock('./api', () => ({
    customerApi: {
        getRequest: getRequestMock,
        awardQuotation: awardQuotationMock,
    },
}));

vi.mock('axios', () => ({
    default: {
        isAxiosError: vi.fn(() => false),
    },
}));

import CustomerRequestQuotations from './CustomerRequestQuotations';

const requestId = 'request-001';

const quotation = {
    quotation_id: 'quotation-001',
    request_id: requestId,
    runner_account_id: 501,
    amount: '25.00',
    currency: 'MYR',
    message: 'Saya boleh buat tugasan ini.',
    status: 'SUBMITTED',
    expires_at: '2026-11-08T12:00:00.000Z',
    accepted_at: null,
    withdrawn_at: null,
    created_at: '2026-11-07T10:00:00.000Z',
};

const requestDetail = {
    request: {
        request_id: requestId,
        task_class: 'NON_DELIVERY',
        description: 'Bantu beli barang.',
        pickup_location_snapshot: null,
        delivery_location_snapshot: null,
        task_location_snapshot: {
            address: 'Kedai Tuaran',
        },
        status: 'OPEN',
        expires_at: '2026-11-08T12:00:00.000Z',
        awarded_at: null,
        cancelled_at: null,
        created_at: '2026-11-07T09:00:00.000Z',
        updated_at: '2026-11-07T09:00:00.000Z',
    },
    quotations: [quotation],
};

const awardResult = {
    request: {
        request_id: requestId,
        status: 'AWARDED',
        winning_quotation_id: quotation.quotation_id,
        awarded_at: '2026-11-07T11:00:00.000Z',
        updated_at: '2026-11-07T11:00:00.000Z',
    },
    task: {
        task_id: 'task-001',
        module_key: 'Marketplace',
        task_type: 'MARKETPLACE_SERVICE_REQUEST',
        source_type: 'marketplace_request',
        source_id: requestId,
        assigned_account_id: 501,
        generic_status: 'ASSIGNED',
        assigned_at: '2026-11-07T11:00:00.000Z',
        created_at: '2026-11-07T11:00:00.000Z',
    },
};

describe('CustomerRequestQuotations', () => {
    beforeEach(() => {
        getRequestMock.mockReset();
        awardQuotationMock.mockReset();

        vi.stubGlobal(
            'confirm',
            vi.fn(() => true),
        );
    });

    function renderComponent(
        props: Partial<React.ComponentProps<
            typeof CustomerRequestQuotations
        >> = {},
    ) {
        const onAwarded = vi.fn();

        render(
            <CustomerRequestQuotations
                requestId={requestId}
                requestStatus="OPEN"
                onAwarded={onAwarded}
                {...props}
            />,
        );

        return { onAwarded };
    }

    it('loads and displays submitted quotations', async () => {
        getRequestMock.mockResolvedValue(requestDetail);

        renderComponent();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Lihat Sebut Harga',
            }),
        );

        expect(
            await screen.findByText('Sebut Harga Runner'),
        ).toBeTruthy();

        expect(
            screen.getByText('ID Runner: 501'),
        ).toBeTruthy();

        expect(
            screen.getByText('Saya boleh buat tugasan ini.'),
        ).toBeTruthy();

        expect(getRequestMock).toHaveBeenCalledWith(requestId);
    });

    it('displays an empty message when no quotations exist', async () => {
        getRequestMock.mockResolvedValue({
            ...requestDetail,
            quotations: [],
        });

        renderComponent();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Lihat Sebut Harga',
            }),
        );

        expect(
            await screen.findByText(
                'Belum ada Runner menghantar sebut harga.',
            ),
        ).toBeTruthy();
    });

    it('does not award when customer cancels confirmation', async () => {
        vi.stubGlobal('confirm', vi.fn(() => false));
        getRequestMock.mockResolvedValue(requestDetail);

        renderComponent();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Lihat Sebut Harga',
            }),
        );

        await screen.findByText('ID Runner: 501');

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Pilih Runner Ini',
            }),
        );

        expect(awardQuotationMock).not.toHaveBeenCalled();
    });

    it('awards a quotation and refreshes the request detail', async () => {
        getRequestMock
            .mockResolvedValueOnce(requestDetail)
            .mockResolvedValueOnce({
                ...requestDetail,
                request: {
                    ...requestDetail.request,
                    status: 'AWARDED',
                    winning_quotation_id: quotation.quotation_id,
                },
                quotations: [
                    {
                        ...quotation,
                        status: 'ACCEPTED',
                    },
                ],
            });

        awardQuotationMock.mockResolvedValue(awardResult);

        const { onAwarded } = renderComponent();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Lihat Sebut Harga',
            }),
        );

        await screen.findByText('ID Runner: 501');

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Pilih Runner Ini',
            }),
        );

        await waitFor(() => {
            expect(awardQuotationMock).toHaveBeenCalledWith(
                requestId,
                quotation.quotation_id,
            );
        });

        expect(onAwarded).toHaveBeenCalledWith(
            requestId,
            awardResult,
        );

        expect(
            await screen.findByText(
                'Runner berjaya dipilih. ID Core Task: task-001',
            ),
        ).toBeTruthy();

        await waitFor(() => {
            expect(getRequestMock).toHaveBeenCalledTimes(2);
        });
    });

    it('does not show the award button for a non-submitted quotation', async () => {
        getRequestMock.mockResolvedValue({
            ...requestDetail,
            quotations: [
                {
                    ...quotation,
                    status: 'EXPIRED',
                },
            ],
        });

        renderComponent();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Lihat Sebut Harga',
            }),
        );

        await screen.findByText('ID Runner: 501');

        expect(
            screen.queryByRole('button', {
                name: 'Pilih Runner Ini',
            }),
        ).toBeNull();
    });

    it('shows an error when loading quotations fails', async () => {
        getRequestMock.mockRejectedValue(
            new Error('Network error'),
        );

        renderComponent();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Lihat Sebut Harga',
            }),
        );

        expect(
            await screen.findByRole('alert'),
        ).toBeTruthy();

        expect(
            screen.getByRole('alert').textContent,
        ).toContain('Tidak berjaya memuatkan sebut harga.');
    });

    it('shows an error when awarding fails', async () => {
        getRequestMock.mockResolvedValue(requestDetail);

        awardQuotationMock.mockRejectedValue(
            new Error('Award failed'),
        );

        renderComponent();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Lihat Sebut Harga',
            }),
        );

        await screen.findByText('ID Runner: 501');

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Pilih Runner Ini',
            }),
        );

        expect(
            await screen.findByRole('alert'),
        ).toBeTruthy();

        expect(
            screen.getByRole('alert').textContent,
        ).toContain('Tidak berjaya memilih Runner.');
    });

    it('keeps the award success when refreshing detail fails', async () => {
        getRequestMock
            .mockResolvedValueOnce(requestDetail)
            .mockRejectedValueOnce(new Error('Refresh failed'));

        awardQuotationMock.mockResolvedValue(awardResult);

        const { onAwarded } = renderComponent();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Lihat Sebut Harga',
            }),
        );

        await screen.findByText('ID Runner: 501');

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Pilih Runner Ini',
            }),
        );

        expect(
            await screen.findByText(
                'Runner berjaya dipilih. ID Core Task: task-001',
            ),
        ).toBeTruthy();

        expect(onAwarded).toHaveBeenCalledTimes(1);

        expect(
            await screen.findByRole('status'),
        ).toBeTruthy();
    });
});
