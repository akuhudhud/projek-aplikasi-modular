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
} = vi.hoisted(() => ({
    meMock: vi.fn(),
    loginMock: vi.fn(),
    getTasksMock: vi.fn(),
    getRequestsMock: vi.fn(),
}));

vi.mock('axios', () => ({
    default: {
        isAxiosError: vi.fn(() => false),
    },
}));

vi.mock('./api', () => ({
    customerApi: {
        me: meMock,
        login: loginMock,
        getTasks: getTasksMock,
        getRequests: getRequestsMock,
        createRequest: vi.fn(),
        cancelRequest: vi.fn(),
        getTask: vi.fn(),
        getRequest: vi.fn(),
        awardQuotation: vi.fn(),
        updateProfile: vi.fn(),
        logout: vi.fn(),
        requestRegistrationOtp: vi.fn(),
        verifyRegistrationOtp: vi.fn(),
        completeRegistration: vi.fn(),
    },
}));

import { CustomerApp } from './main';

const account = {
    id: 'customer-test-id',
    name: 'Customer Test',
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

        await screen.findByText('Dashboard Pelanggan');

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
            name: 'Cipta Open Request',
        });

        expect(getTasksMock).not.toHaveBeenCalled();
        expect(getRequestsMock).not.toHaveBeenCalled();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Open Request',
            }),
        );

        await screen.findByRole('heading', {
            name: 'Open Request Saya',
        }).catch(() => screen.findByText('Senarai Open Request'));

        await waitFor(() => {
            expect(getRequestsMock).toHaveBeenCalledTimes(1);
        });

        expect(getTasksMock).not.toHaveBeenCalled();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Tugasan Saya',
            }),
        );

        await waitFor(() => {
            expect(getTasksMock).toHaveBeenCalledTimes(1);
        });

        expect(getRequestsMock).toHaveBeenCalledTimes(1);
        expect(getTasksMock).toHaveBeenCalledWith(1);
    });

    it('does not load task or request lists on the Profile page', async () => {
        await loginAt('/?runner=Test-Runner');

        await screen.findByRole('heading', {
            name: 'Cipta Open Request',
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Profil',
            }),
        );

        await screen.findByRole('heading', {
            name: 'My Profile',
        });

        expect(getTasksMock).not.toHaveBeenCalled();
        expect(getRequestsMock).not.toHaveBeenCalled();
    });

    it('shows task loading errors on the Dashboard', async () => {
        getTasksMock.mockRejectedValue(
            new Error('Tasks unavailable'),
        );

        await loginAt('/');

        await screen.findByText('Dashboard Pelanggan');

        expect(
            await screen.findByRole('alert'),
        ).toHaveTextContent(taskLoadingError);
    });

    it('shows task loading errors on My Tasks', async () => {
        getTasksMock.mockRejectedValue(
            new Error('Tasks unavailable'),
        );

        await loginAt('/?runner=Test-Runner');

        await screen.findByRole('heading', {
            name: 'Cipta Open Request',
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Tugasan Saya',
            }),
        );

        await screen.findByRole('heading', {
            name: 'My Tasks',
        });

        expect(
            await screen.findByRole('alert'),
        ).toHaveTextContent(taskLoadingError);
    });
});
