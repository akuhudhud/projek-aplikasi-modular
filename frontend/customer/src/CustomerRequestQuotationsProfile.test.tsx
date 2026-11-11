
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
    fireEvent,
    render,
    screen,
    waitFor,
    within,
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

vi.mock('./api', () => ({
    customerApi: {
        getRequest: getRequestMock,
        awardQuotation: awardQuotationMock,
    },
}));

import CustomerRequestQuotations from './CustomerRequestQuotations';

const runnerProfile = {
    public_slug: 'runner-tuaran',
    display_name: 'Runner Tuaran',
    bio: 'Servis penghantaran sekitar Tuaran.',
    base_location: 'Tuaran',
    service_area: 'Tuaran dan kawasan berhampiran',
    services: ['Penghantaran parcel'],
    rating_average: 4.8,
    rating_count: 12,
};

const quotation = {
    quotation_id: 'quotation-test-001',
    request_id: 'request-test-001',
    runner_account_id: 987654,
    amount: '15.00',
    currency: 'MYR',
    message: 'Saya boleh selesaikan tugasan ini.',
    status: 'SUBMITTED',
    expires_at: null,
    accepted_at: null,
    withdrawn_at: null,
    created_at: '2026-11-11T08:00:00.000Z',
    runner_profile: runnerProfile,
};

const requestDetail = {
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
        expires_at: null,
        awarded_at: null,
        cancelled_at: null,
        created_at: '2026-11-11T07:00:00.000Z',
        updated_at: '2026-11-11T07:00:00.000Z',
    },
    quotations: [quotation],
};

function renderQuotation() {
    return render(
        <CustomerRequestQuotations
            requestId="request-test-001"
            requestStatus="OPEN"
            onAwarded={vi.fn()}
        />,
    );
}

describe('Customer quotation Runner public profile', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        getRequestMock.mockResolvedValue(requestDetail);
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('displays published Runner public profile details', async () => {
        renderQuotation();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Lihat Sebut Harga',
            }),
        );

        const card = await screen.findByRole('article');

        expect(
            within(card).getByText('Runner Tuaran'),
        ).toBeTruthy();

        expect(
            within(card).getByText('Lokasi asas: Tuaran'),
        ).toBeTruthy();

        expect(
            within(card).getByText(
                'Kawasan servis: Tuaran dan kawasan berhampiran',
            ),
        ).toBeTruthy();

        expect(
            within(card).getByText('★ 4.8 / 5 (12 penilaian)'),
        ).toBeTruthy();

        const profileLink = within(card).getByRole('link', {
            name: 'Lihat Profil Runner',
        });

        expect(profileLink.getAttribute('href')).toBe(
            '/me/runner-tuaran',
        );

        expect(profileLink.getAttribute('target')).toBe('_blank');
        expect(profileLink.getAttribute('rel')).toBe(
            'noopener noreferrer',
        );

        expect(card.textContent).not.toContain('987654');
    });

    it('does not expose profile details when public profile is unavailable', async () => {
        getRequestMock.mockResolvedValue({
            ...requestDetail,
            quotations: [
                {
                    ...quotation,
                    runner_profile: null,
                },
            ],
        });

        renderQuotation();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Lihat Sebut Harga',
            }),
        );

        const card = await screen.findByRole('article');

        expect(
            within(card).getByText('Profil awam tidak tersedia'),
        ).toBeTruthy();

        expect(
            within(card).getByText(
                'Maklumat profil awam tidak tersedia.',
            ),
        ).toBeTruthy();

        expect(
            within(card).queryByRole('link', {
                name: 'Lihat Profil Runner',
            }),
        ).toBeNull();

        expect(card.textContent).not.toContain('987654');
        expect(card.textContent).not.toContain('Tuaran');
    });

    it('uses Runner display name in quotation award confirmation', async () => {
        const confirmMock = vi
            .spyOn(window, 'confirm')
            .mockReturnValue(false);

        renderQuotation();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Lihat Sebut Harga',
            }),
        );

        const awardButton = await screen.findByRole('button', {
            name: 'Pilih Runner Ini',
        });

        fireEvent.click(awardButton);

        await waitFor(() => {
            expect(confirmMock).toHaveBeenCalledTimes(1);
        });

        const confirmationMessage = String(
            confirmMock.mock.calls[0][0],
        );

        expect(confirmationMessage).toContain('Runner Tuaran');
        expect(confirmationMessage).not.toContain('987654');

        expect(awardQuotationMock).not.toHaveBeenCalled();
    });
});
