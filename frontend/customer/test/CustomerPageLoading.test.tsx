
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
} from '@testing-library/react';

const {
    meMock,
    loginMock,
    getTasksMock,
    getRequestsMock,
    getNotificationsMock,
    getRequestMock,
} = vi.hoisted(() => ({
    meMock: vi.fn(),
    loginMock: vi.fn(),
    getTasksMock: vi.fn(),
    getRequestsMock: vi.fn(),
    getNotificationsMock: vi.fn(),
    getRequestMock: vi.fn(),
}));

vi.mock('axios', () => ({
    default: {
        isAxiosError: vi.fn(() => false),
    },
}));

vi.mock('../src/api', () => ({
    CUSTOMER_SESSION_EXPIRED_EVENT: 'customer-session-expired',
    customerApi: {
        me: meMock,
        login: loginMock,
        getTasks: getTasksMock,
        getRequests: getRequestsMock,
        getNotifications: getNotificationsMock,
        markNotificationAsRead: vi.fn(),
        deleteNotification: vi.fn(),
        deleteAllNotifications: vi.fn(),
        createRequest: vi.fn(),
        cancelRequest: vi.fn(),
        getTask: vi.fn(),
        getRequest: getRequestMock,
        awardQuotation: vi.fn(),
        updateProfile: vi.fn(),
        logout: vi.fn(),
        requestRegistrationOtp: vi.fn(),
        verifyRegistrationOtp: vi.fn(),
        completeRegistration: vi.fn(),
    },
}));

import { CustomerApp } from '../src/main';

const account = {
    id: 'customer-test-id',
    profile_name: 'Customer Test',
    phone: '0123456789',
    email: 'customer@example.com',
    profile_picture: null,
    role: 'USER',
    status: 'ACTIVE',
};

const taskLoadingError =
    'Tidak berjaya memuatkan senarai tugasan.';

function configureSuccessfulLogin() {
    meMock.mockRejectedValue(
        new Error('No active session'),
    );

    loginMock.mockResolvedValue(account);

    getTasksMock.mockResolvedValue({
        tasks: [],
        summary: {
            active_total: 0,
        },
        pagination: {
            current_page: 1,
            per_page: 10,
            total: 0,
            last_page: 1,
        },
    });

    getRequestsMock.mockResolvedValue({
        requests: [],
        pagination: {
            current_page: 1,
            per_page: 10,
            total: 0,
            last_page: 1,
        },
    });

    getNotificationsMock.mockResolvedValue({
        notifications: [],
        summary: {
            unread_total: 0,
        },
        pagination: {
            current_page: 1,
            per_page: 10,
            total: 0,
            last_page: 1,
        },
    });
}

async function loginAt(path: string) {
    window.history.replaceState({}, '', path);

    render(<CustomerApp />);

    await screen.findByRole('heading', {
        name: 'Log Masuk Pelanggan',
    });

    fireEvent.change(
        screen.getByLabelText('Nombor Telefon atau E-mel'),
        {
            target: {
                value: '0123456789',
            },
        },
    );

    fireEvent.change(
        screen.getByLabelText('Kata Laluan'),
        {
            target: {
                value: 'TestPassword123',
            },
        },
    );

    fireEvent.click(
        screen.getByRole('button', {
            name: 'Log Masuk',
        }),
    );
}

describe('Customer page-specific data loading', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        configureSuccessfulLogin();
    });

    afterEach(() => {
        cleanup();

        window.history.replaceState(
            {},
            '',
            '/',
        );
    });

    it('loads tasks and requests on the dashboard', async () => {
        await loginAt('/');

        await screen.findByText('Halaman Utama Pelanggan');

        await waitFor(() => {
            expect(getTasksMock).toHaveBeenCalledTimes(1);
            expect(getRequestsMock).toHaveBeenCalledTimes(1);
        });

        expect(getTasksMock).toHaveBeenCalledWith(1);
        expect(getRequestsMock).toHaveBeenCalledWith(1);
    });

    it('loads only the relevant list when navigating from Create to Requests and Tasks', async () => {
        await loginAt('/?runner=Test-Runner');

        await screen.findByRole('heading', {
            name: 'Cipta Tugasan Baharu',
        });

        expect(getTasksMock).not.toHaveBeenCalled();
        expect(getRequestsMock).not.toHaveBeenCalled();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Permintaan',
            }),
        );

        await screen.findByRole('heading', {
            name: 'Permintaan Saya',
        });

        await waitFor(() => {
            expect(getRequestsMock).toHaveBeenCalledTimes(1);
        });

        expect(getTasksMock).not.toHaveBeenCalled();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Tugasan',
            }),
        );

        await screen.findByRole('heading', {
            name: 'Tugasan Saya',
        });

        await waitFor(() => {
            expect(getTasksMock).toHaveBeenCalledTimes(1);
        });

        expect(getRequestsMock).toHaveBeenCalledTimes(1);
        expect(getTasksMock).toHaveBeenCalledWith(1);
    });

    it('does not load task or request lists on the Profile page', async () => {
        await loginAt('/?runner=Test-Runner');

        await screen.findByRole('heading', {
            name: 'Cipta Tugasan Baharu',
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Profil',
            }),
        );

        await screen.findByRole('heading', {
            name: 'Profil Saya',
        });

        expect(getTasksMock).not.toHaveBeenCalled();
        expect(getRequestsMock).not.toHaveBeenCalled();
    });

    it('shows task loading errors on the Dashboard', async () => {
        getTasksMock.mockRejectedValue(
            new Error('Tasks unavailable'),
        );

        await loginAt('/');

        await screen.findByText('Halaman Utama Pelanggan');

        const alert = await screen.findByRole('alert');

        expect(alert.textContent).toContain(taskLoadingError);
    });

    it('shows task loading errors on My Tasks', async () => {
        getTasksMock.mockRejectedValue(
            new Error('Tasks unavailable'),
        );

        await loginAt('/?runner=Test-Runner');

        await screen.findByRole('heading', {
            name: 'Cipta Tugasan Baharu',
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Tugasan',
            }),
        );

        await screen.findByRole('heading', {
            name: 'Tugasan Saya',
        });

        const alert = await screen.findByRole('alert');

        expect(alert.textContent).toContain(taskLoadingError);
    });

    it('opens the exact request from a notification and keeps it visible after navigation', async () => {
        const targetRequest = {
            request_id: 'request-from-notification-001',
            task_class: 'NON_DELIVERY' as const,
            description: 'Permintaan sasaran daripada notifikasi',
            pickup_location_snapshot: null,
            delivery_location_snapshot: null,
            task_location_snapshot: {
                address: 'Kampung Tambalang, Tuaran',
            },
            status: 'CANCELLED',
            expires_at: '2026-12-01T10:00:00Z',
            awarded_at: null,
            cancelled_at: '2026-11-16T10:00:00Z',
            created_at: '2026-11-15T10:00:00Z',
            updated_at: '2026-11-16T10:00:00Z',
        };

        getNotificationsMock.mockResolvedValue({
            notifications: [
                {
                    id: 'notification-001',
                    type: 'request',
                    data: {
                        title: 'Permintaan dikemaskini',
                        message: 'Permintaan anda telah dikemaskini.',
                        request_id: targetRequest.request_id,
                    },
                    read_at: '2026-11-16T10:00:00Z',
                    created_at: '2026-11-16T10:00:00Z',
                },
            ],
            summary: {
                unread_total: 0,
            },
            pagination: {
                current_page: 1,
                per_page: 10,
                total: 1,
                last_page: 1,
            },
        });

        getRequestMock.mockResolvedValue({
            request: targetRequest,
            quotations: [],
        });

        await loginAt('/?runner=Test-Runner');

        await screen.findByRole('heading', {
            name: 'Cipta Tugasan Baharu',
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Notifikasi',
            }),
        );

        await screen.findByRole('heading', {
            name: 'Notifikasi',
        });

        fireEvent.click(
            await screen.findByRole('button', {
                name: 'Lihat',
            }),
        );

        await screen.findByRole('heading', {
            name: 'Permintaan Saya',
        });

        await waitFor(() => {
            expect(getRequestMock).toHaveBeenCalledWith(
                targetRequest.request_id,
            );
        });

        expect(
            await screen.findByText(
                targetRequest.description,
            ),
        ).toBeTruthy();

        const targetCard = screen
            .getByText(targetRequest.description)
            .closest('article');

        expect(targetCard).not.toBeNull();

        expect(
            targetCard?.className,
        ).toContain('customer-task-card-notification-target');
    });
});
