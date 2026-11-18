
import {
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/react';

import {
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

import RunnerApp from './RunnerApp';

import {
    runnerApi,
    type RunnerAccount,
    type RunnerMarketplaceRequest,
    type RunnerQuotation,
} from '../api';

import {
    useRunnerSession,
} from '../features/auth/useRunnerSession';

vi.mock('../api', () => ({
    runnerApi: {
        me: vi.fn(),
        login: vi.fn(),
        logout: vi.fn(),
        getTasks: vi.fn(),
        startTask: vi.fn(),
        completeTask: vi.fn(),
        getAvailableRequests: vi.fn(),
        getRequest: vi.fn(),
        submitQuotation: vi.fn(),
        saveProfile: vi.fn(),
        getProfileAnalytics: vi.fn(),
    },
}));

vi.mock('../features/auth/useRunnerSession', () => ({
    useRunnerSession: vi.fn(),
}));

const mockAccount: RunnerAccount = {
    id: 1,
    profile_name: 'Runner Test',
    phone: '0123456789',
    email: 'runner@example.com',
    profile_picture: null,
    role: 'USER',
    status: 'ACTIVE',
    runner_status: 'APPROVED',
    identity_verification_status: 'VERIFIED',
};

const mockRequest: RunnerMarketplaceRequest = {
    request_id: 'request-12345678',
    source_type: 'MARKETPLACE',
    task_class: 'NON_DELIVERY',
    description: 'Hantar dokumen ke pejabat',
    pickup_location_snapshot: null,
    delivery_location_snapshot: null,
    task_location_snapshot: {
        address: 'Tuaran, Sabah',
        contact_name: null,
        phone: null,
    },
    status: 'OPEN',
    expires_at: '2026-12-01T12:00:00Z',
    created_at: '2026-11-18T10:00:00Z',
};

const submittedQuotation: RunnerQuotation = {
    quotation_id: 'quotation-001',
    amount: 25,
    currency: 'MYR',
    message: 'Saya boleh buat penghantaran',
    status: 'SUBMITTED',
    expires_at: null,
    created_at: '2026-11-18T10:30:00Z',
};

const withdrawnQuotation: RunnerQuotation = {
    quotation_id: 'quotation-002',
    amount: 20,
    currency: 'MYR',
    message: null,
    status: 'WITHDRAWN',
    expires_at: null,
    created_at: '2026-11-18T10:20:00Z',
};

function mockSession() {
    vi.mocked(useRunnerSession).mockReturnValue({
        account: mockAccount,
        checkingSession: false,
        logoutLoading: false,
        logoutError: '',
        setAccount: vi.fn(),
        logout: vi.fn().mockResolvedValue(true),
        clearLogoutError: vi.fn(),
    });
}

function mockEmptyRequests() {
    vi.mocked(runnerApi.getAvailableRequests).mockResolvedValue({
        requests: [],
        pagination: {
            current_page: 1,
            per_page: 15,
            total: 0,
            last_page: 1,
        },
    });
}

function openRequestsTab() {
    fireEvent.click(
        screen.getByRole('button', {
            name: 'OPEN REQUEST',
        }),
    );
}

describe('RunnerApp dashboard', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        mockSession();

        vi.mocked(runnerApi.getTasks).mockResolvedValue({
            tasks: [],
            pagination: {
                current_page: 1,
                per_page: 15,
                total: 0,
                last_page: 1,
            },
        });

        mockEmptyRequests();
    });

    it('displays the approved Runner dashboard', async () => {
        render(<RunnerApp />);

        expect(
            screen.getByRole('heading', {
                name: 'Runner Test',
            }),
        ).toBeInTheDocument();

        expect(
            screen.getByText('SUPER RUNNER'),
        ).toBeInTheDocument();

        await waitFor(() => {
            expect(runnerApi.getTasks).toHaveBeenCalled();
        });
    });

    it('loads available Open Requests when the tab is opened', async () => {
        vi.mocked(
            runnerApi.getAvailableRequests,
        ).mockResolvedValue({
            requests: [mockRequest],
            pagination: {
                current_page: 1,
                per_page: 15,
                total: 1,
                last_page: 1,
            },
        });

        render(<RunnerApp />);

        openRequestsTab();

        expect(
            await screen.findByText(
                'Hantar dokumen ke pejabat',
            ),
        ).toBeInTheDocument();

        expect(
            screen.getByText('Tuaran, Sabah'),
        ).toBeInTheDocument();
    });

    it('shows an existing submitted quotation without allowing another submission', async () => {
        vi.mocked(
            runnerApi.getAvailableRequests,
        ).mockResolvedValue({
            requests: [mockRequest],
            pagination: {
                current_page: 1,
                per_page: 15,
                total: 1,
                last_page: 1,
            },
        });

        vi.mocked(
            runnerApi.getRequest,
        ).mockResolvedValue({
            ...mockRequest,
            my_quotations: [submittedQuotation],
        });

        render(<RunnerApp />);

        openRequestsTab();

        fireEvent.click(
            await screen.findByRole('button', {
                name: 'SEMAK / HANTAR SEBUT HARGA',
            }),
        );

        expect(
            await screen.findByText(
                'SEBUT HARGA DIHANTAR',
            ),
        ).toBeInTheDocument();

        expect(
            screen.getByText(/masih aktif dan sedang menunggu keputusan pelanggan/i),
        ).toBeInTheDocument();

        expect(
            screen.queryByLabelText(
                'Jumlah sebut harga (RM)',
            ),
        ).not.toBeInTheDocument();

        expect(
            runnerApi.submitQuotation,
        ).not.toHaveBeenCalled();
    });

    it('allows a new quotation after the previous quotation was withdrawn', async () => {
        vi.mocked(
            runnerApi.getAvailableRequests,
        ).mockResolvedValue({
            requests: [mockRequest],
            pagination: {
                current_page: 1,
                per_page: 15,
                total: 1,
                last_page: 1,
            },
        });

        vi.mocked(
            runnerApi.getRequest,
        )
            .mockResolvedValueOnce({
                ...mockRequest,
                my_quotations: [withdrawnQuotation],
            })
            .mockResolvedValueOnce({
                ...mockRequest,
                my_quotations: [submittedQuotation],
            });

        vi.mocked(
            runnerApi.submitQuotation,
        ).mockResolvedValue({
            ...submittedQuotation,
            request_id: mockRequest.request_id,
            runner_account_id: 1,
        });

        render(<RunnerApp />);

        openRequestsTab();

        fireEvent.click(
            await screen.findByRole('button', {
                name: 'SEMAK / HANTAR SEBUT HARGA',
            }),
        );

        expect(
            await screen.findByLabelText(
                'Jumlah sebut harga (RM)',
            ),
        ).toBeInTheDocument();

        fireEvent.change(
            screen.getByLabelText(
                'Jumlah sebut harga (RM)',
            ),
            {
                target: {
                    value: '30.00',
                },
            },
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'HANTAR SEBUT HARGA',
            }),
        );

        await waitFor(() => {
            expect(
                runnerApi.submitQuotation,
            ).toHaveBeenCalledWith(
                mockRequest.request_id,
                {
                    amount: 30,
                    currency: 'MYR',
                    message: null,
                },
            );
        });

        expect(
            await screen.findByText(
                /masih aktif dan sedang menunggu keputusan pelanggan/i,
            ),
        ).toBeInTheDocument();
    });

    it('does not show private identity information on the dashboard', async () => {
        render(<RunnerApp />);

        expect(
            screen.queryByText(/mykad/i),
        ).not.toBeInTheDocument();

        expect(
            screen.queryByText(/verified name/i),
        ).not.toBeInTheDocument();
    });
});
