
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

const {
    meMock,
    loginMock,
    getTasksMock,
    getRequestsMock,
    createRequestMock,
} = vi.hoisted(() => ({
    meMock: vi.fn(),
    loginMock: vi.fn(),
    getTasksMock: vi.fn(),
    getRequestsMock: vi.fn(),
    createRequestMock: vi.fn(),
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
        createRequest: createRequestMock,
        getTask: vi.fn(),
        updateProfile: vi.fn(),
        logout: vi.fn(),
        requestRegistrationOtp: vi.fn(),
        verifyRegistrationOtp: vi.fn(),
        completeRegistration: vi.fn(),
        cancelRequest: vi.fn(),
        getRequest: vi.fn(),
        awardQuotation: vi.fn(),
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

function mockSuccessfulLogin() {
    meMock.mockRejectedValue(new Error('No active session'));

    loginMock.mockResolvedValue(account);

    getTasksMock.mockResolvedValue({
        tasks: [],
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

    createRequestMock.mockResolvedValue({
        request_id: 'request-test-id',
        customer_account_id: account.id,
        target_runner_account_id: 'runner-test-id',
        source_type: 'runner_profile_link',
        task_class: 'PICKUP_DELIVERY',
        description: 'Test request',
        pickup_location_snapshot: null,
        delivery_location_snapshot: null,
        task_location_snapshot: null,
        status: 'OPEN',
        expires_at: new Date(
            Date.now() + 60 * 60 * 1000,
        ).toISOString(),
        created_at: new Date().toISOString(),
    });
}

async function loginThroughRunnerProfile() {
    render(<CustomerApp />);

    fireEvent.change(
        screen.getByLabelText('Nombor Telefon atau E-mel'),
        {
            target: { value: '0123456789' },
        },
    );

    fireEvent.change(
        screen.getByLabelText('Kata Laluan'),
        {
            target: { value: 'TestPassword123' },
        },
    );

    fireEvent.click(
        screen.getByRole('button', {
            name: 'Log Masuk',
        }),
    );

    await screen.findByRole('heading', {
        name: 'Cipta Open Request',
    });
}

function submitRequestForm() {
    const button = screen.getByRole('button', {
        name: 'Hantar Open Request',
    });

    const form = button.closest('form');

    if (!form) {
        throw new Error('Open Request form not found.');
    }

    fireEvent.submit(form);
}

describe('Customer Open Request through Runner profile link', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        window.history.replaceState(
            {},
            '',
            '/?runner=Test-Runner',
        );

        mockSuccessfulLogin();
    });

    afterEach(() => {
        cleanup();

        window.history.replaceState(
            {},
            '',
            '/',
        );
    });

    it('sends PICKUP_DELIVERY request to the selected runner', async () => {
        await loginThroughRunnerProfile();

        expect(
            screen.getByText(
                'Nyatakan tugasan yang anda perlukan. Permintaan ini akan dihantar khusus kepada Runner daripada profil yang anda pilih.',
            ),
        ).toBeTruthy();

        fireEvent.change(
            screen.getByLabelText('Penerangan Tugasan'),
            {
                target: {
                    value: 'Hantar parcel kepada pelanggan.',
                },
            },
        );

        const pickup = screen.getByRole('group', {
            name: 'Lokasi Pickup',
        });

        fireEvent.change(
            within(pickup).getByRole('textbox'),
            {
                target: {
                    value: 'Pusat Pos Tuaran',
                },
            },
        );

        const delivery = screen.getByRole('group', {
            name: 'Lokasi Delivery',
        });

        fireEvent.change(
            within(delivery).getByRole('textbox'),
            {
                target: {
                    value: 'Kampung Tambalang',
                },
            },
        );

        submitRequestForm();

        await waitFor(() => {
            expect(createRequestMock).toHaveBeenCalledTimes(1);
        });

        expect(createRequestMock).toHaveBeenCalledWith(
            expect.objectContaining({
                source_type: 'runner_profile_link',
                target_runner_slug: 'test-runner',
                task_class: 'PICKUP_DELIVERY',
                description: 'Hantar parcel kepada pelanggan.',
                pickup_location_snapshot: expect.objectContaining({
                    address: 'Pusat Pos Tuaran',
                }),
                delivery_location_snapshot: expect.objectContaining({
                    address: 'Kampung Tambalang',
                }),
            }),
        );
    });

    it('sends NON_DELIVERY request with task location snapshot', async () => {
        await loginThroughRunnerProfile();

        fireEvent.change(
            screen.getByLabelText('Jenis Tugasan'),
            {
                target: {
                    value: 'NON_DELIVERY',
                },
            },
        );

        fireEvent.change(
            screen.getByLabelText('Penerangan Tugasan'),
            {
                target: {
                    value: 'Bantu membeli barang.',
                },
            },
        );

        const taskLocation = screen.getByRole('group', {
            name: 'Lokasi Tugasan',
        });

        fireEvent.change(
            within(taskLocation).getByRole('textbox'),
            {
                target: {
                    value: 'Pekan Tuaran',
                },
            },
        );

        submitRequestForm();

        await waitFor(() => {
            expect(createRequestMock).toHaveBeenCalledTimes(1);
        });

        expect(createRequestMock).toHaveBeenCalledWith(
            expect.objectContaining({
                source_type: 'runner_profile_link',
                target_runner_slug: 'test-runner',
                task_class: 'NON_DELIVERY',
                description: 'Bantu membeli barang.',
                task_location_snapshot: expect.objectContaining({
                    address: 'Pekan Tuaran',
                }),
            }),
        );

        const payload = createRequestMock.mock.calls[0][0];

        expect(payload).not.toHaveProperty(
            'pickup_location_snapshot',
        );

        expect(payload).not.toHaveProperty(
            'delivery_location_snapshot',
        );
    });

    it('rejects an invalid runner slug before API submission', async () => {
        window.history.replaceState(
            {},
            '',
            '/?runner=!!invalid!!',
        );

        await loginThroughRunnerProfile();

        fireEvent.change(
            screen.getByLabelText('Penerangan Tugasan'),
            {
                target: {
                    value: 'Invalid profile test.',
                },
            },
        );

        submitRequestForm();

        expect(
            await screen.findByRole('alert'),
        ).toBeTruthy();

        expect(createRequestMock).not.toHaveBeenCalled();
    });
});
