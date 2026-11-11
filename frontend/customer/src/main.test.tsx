
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
    cancelRequestMock,
    getTaskMock,
    updateProfileMock,
    logoutMock,
} = vi.hoisted(() => ({
    meMock: vi.fn(),
    loginMock: vi.fn(),
    getTasksMock: vi.fn(),
    getRequestsMock: vi.fn(),
    createRequestMock: vi.fn(),
    cancelRequestMock: vi.fn(),
    getTaskMock: vi.fn(),
    updateProfileMock: vi.fn(),
    logoutMock: vi.fn(),
}));

vi.mock('axios', () => ({
    default: {
        isAxiosError: vi.fn(() => false),
    },
}));

vi.mock('./api', () => ({
    CUSTOMER_SESSION_EXPIRED_EVENT: 'customer-session-expired',
    customerApi: {
        me: meMock,
        login: loginMock,
        getTasks: getTasksMock,
        getRequests: getRequestsMock,
        createRequest: createRequestMock,
        getTask: getTaskMock,
        updateProfile: updateProfileMock,
        logout: logoutMock,
        requestRegistrationOtp: vi.fn(),
        verifyRegistrationOtp: vi.fn(),
        completeRegistration: vi.fn(),
        cancelRequest: cancelRequestMock,
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

    await screen.findByRole('heading', {
        name: 'Cipta Permintaan Terbuka',
    });
}

async function loginThroughCustomerDashboard() {
    window.history.replaceState({}, '', '/');

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

    await screen.findByText('Halaman Utama Pelanggan');
}

function submitRequestForm() {
    const button = screen.getByRole('button', {
        name: 'Hantar Permintaan',
    });

    const form = button.closest('form');

    if (!form) {
        throw new Error(
            'Open Request form not found.',
        );
    }

    fireEvent.submit(form);
}

function makeOpenRequest() {
    const now = new Date().toISOString();

    return {
        request_id: 'request-to-cancel',
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
    };
}

describe(
    'Customer Open Request through Runner profile link',
    () => {
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
            vi.restoreAllMocks();

            window.history.replaceState(
                {},
                '',
                '/',
            );
        });

        it(
            'sends PICKUP_DELIVERY request to the selected runner',
            async () => {
                await loginThroughRunnerProfile();

                expect(
                    screen.getByText(
                        'Nyatakan tugasan yang anda perlukan. Permintaan ini akan dihantar khusus kepada Runner daripada profil yang anda pilih.',
                    ),
                ).toBeTruthy();

                fireEvent.change(
                    screen.getByLabelText(
                        'Penerangan Tugasan',
                    ),
                    {
                        target: {
                            value: 'Hantar parcel kepada pelanggan.',
                        },
                    },
                );

                const pickup = screen.getByRole(
                    'group',
                    {
                        name: 'Lokasi Pickup',
                    },
                );

                fireEvent.change(
                    within(pickup).getByLabelText('Alamat / Lokasi'),
                    {
                        target: {
                            value: 'Pusat Pos Tuaran',
                        },
                    },
                );

                const delivery = screen.getByRole(
                    'group',
                    {
                        name: 'Lokasi Delivery',
                    },
                );

                fireEvent.change(
                    within(delivery).getByLabelText('Alamat / Lokasi'),
                    {
                        target: {
                            value: 'Kampung Tambalang',
                        },
                    },
                );

                submitRequestForm();

                await waitFor(() => {
                    expect(
                        createRequestMock,
                    ).toHaveBeenCalledTimes(1);
                });

                expect(
                    createRequestMock,
                ).toHaveBeenCalledWith(
                    expect.objectContaining({
                        source_type: 'runner_profile_link',
                        target_runner_slug: 'test-runner',
                        task_class: 'PICKUP_DELIVERY',
                        description: 'Hantar parcel kepada pelanggan.',
                        pickup_location_snapshot:
                            expect.objectContaining({
                                address: 'Pusat Pos Tuaran',
                            }),
                        delivery_location_snapshot:
                            expect.objectContaining({
                                address: 'Kampung Tambalang',
                            }),
                    }),
                );
            },
        );

        it(
            'sends NON_DELIVERY request with task location snapshot',
            async () => {
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
                    screen.getByLabelText(
                        'Penerangan Tugasan',
                    ),
                    {
                        target: {
                            value: 'Bantu membeli barang.',
                        },
                    },
                );

                const taskLocation = screen.getByRole(
                    'group',
                    {
                        name: 'Lokasi Tugasan',
                    },
                );

                fireEvent.change(
                    within(taskLocation).getByLabelText('Alamat / Lokasi'),
                    {
                        target: {
                            value: 'Pekan Tuaran',
                        },
                    },
                );

                submitRequestForm();

                await waitFor(() => {
                    expect(
                        createRequestMock,
                    ).toHaveBeenCalledTimes(1);
                });

                expect(
                    createRequestMock,
                ).toHaveBeenCalledWith(
                    expect.objectContaining({
                        source_type: 'runner_profile_link',
                        target_runner_slug: 'test-runner',
                        task_class: 'NON_DELIVERY',
                        description: 'Bantu membeli barang.',
                        task_location_snapshot:
                            expect.objectContaining({
                                address: 'Pekan Tuaran',
                            }),
                    }),
                );

                const payload =
                    createRequestMock.mock.calls[0][0];

                expect(payload).not.toHaveProperty(
                    'pickup_location_snapshot',
                );

                expect(payload).not.toHaveProperty(
                    'delivery_location_snapshot',
                );
            },
        );

        it(
            'cancels an OPEN request and updates its displayed status',
            async () => {
                getRequestsMock.mockResolvedValue({
                    requests: [makeOpenRequest()],
                    pagination: {
                        current_page: 1,
                        per_page: 10,
                        total: 1,
                        last_page: 1,
                    },
                });

                cancelRequestMock.mockResolvedValue({
                    request_id: 'request-to-cancel',
                    status: 'CANCELLED',
                    cancelled_at: new Date().toISOString(),
                    updated_at: new Date().toISOString(),
                });

                const confirmMock = vi
                    .spyOn(window, 'confirm')
                    .mockReturnValue(true);

                await loginThroughRunnerProfile();

                fireEvent.click(
                    screen.getByRole('button', {
                        name: 'Permintaan',
                    }),
                );

                expect(
                    await screen.findByText(
                        'Bantu membeli barang.',
                    ),
                ).toBeTruthy();

                fireEvent.click(
                    screen.getByRole('button', {
                        name: 'Batalkan Permintaan',
                    }),
                );

                expect(confirmMock).toHaveBeenCalledWith(
                    'Adakah anda pasti mahu membatalkan Open Request ini?',
                );

                await waitFor(() => {
                    expect(
                        cancelRequestMock,
                    ).toHaveBeenCalledTimes(1);
                });

                expect(
                    cancelRequestMock,
                ).toHaveBeenCalledWith(
                    'request-to-cancel',
                );

                const successMessage =
                    await screen.findByRole('status');

                expect(successMessage.textContent).toContain(
                    'Open Request berjaya dibatalkan.',
                );

                expect(
                    screen.getByText('Status: CANCELLED'),
                ).toBeTruthy();

                expect(
                    screen.queryByRole('button', {
                        name: 'Batalkan Permintaan',
                    }),
                ).toBeNull();
            },
        );

        it(
            'does not cancel an OPEN request when customer declines confirmation',
            async () => {
                getRequestsMock.mockResolvedValue({
                    requests: [makeOpenRequest()],
                    pagination: {
                        current_page: 1,
                        per_page: 10,
                        total: 1,
                        last_page: 1,
                    },
                });

                const confirmMock = vi
                    .spyOn(window, 'confirm')
                    .mockReturnValue(false);

                await loginThroughRunnerProfile();

                fireEvent.click(
                    screen.getByRole('button', {
                        name: 'Permintaan',
                    }),
                );

                expect(
                    await screen.findByText(
                        'Bantu membeli barang.',
                    ),
                ).toBeTruthy();

                fireEvent.click(
                    screen.getByRole('button', {
                        name: 'Batalkan Permintaan',
                    }),
                );

                expect(confirmMock).toHaveBeenCalledTimes(1);
                expect(cancelRequestMock).not.toHaveBeenCalled();

                expect(
                    screen.getByText('Status: OPEN'),
                ).toBeTruthy();
            },
        );

        it(
            'loads Customer-owned tasks and opens task details',
            async () => {
                const now = new Date().toISOString();
                const task = {
                    task_id: 'customer-task-1',
                    module_key: 'Marketplace',
                    task_type: 'MARKETPLACE_SERVICE_REQUEST',
                    source_type: 'marketplace_request',
                    source_id: 'customer-request-1',
                    generic_status: 'ASSIGNED',
                    assigned_at: now,
                    started_at: null,
                    completed_at: null,
                    cancelled_at: null,
                    created_at: now,
                    updated_at: now,
                };
                const request = {
                    request_id: 'customer-request-1',
                    task_class: 'NON_DELIVERY' as const,
                    description: 'Bantu urus tugasan pelanggan.',
                    pickup_location_snapshot: null,
                    delivery_location_snapshot: null,
                    task_location_snapshot: {
                        address: 'Pekan Tuaran',
                    },
                    status: 'AWARDED',
                    expires_at: null,
                    awarded_at: now,
                    cancelled_at: null,
                };

                getTasksMock.mockResolvedValue({
                    tasks: [{ task, request }],
                    summary: {
                        active_total: 1,
                    },
                    pagination: {
                        current_page: 1,
                        per_page: 10,
                        total: 1,
                        last_page: 1,
                    },
                });

                getTaskMock.mockResolvedValue({
                    task,
                    request,
                });

                await loginThroughRunnerProfile();

                fireEvent.click(
                    screen.getByRole('button', {
                        name: 'Tugasan',
                    }),
                );

                const taskCard = await screen.findByText(
                    'Bantu urus tugasan pelanggan.',
                );

                fireEvent.click(taskCard.closest('button')!);

                expect(
                    await screen.findByRole('heading', {
                        name: 'Butiran Tugasan',
                    }),
                ).toBeTruthy();

                expect(getTaskMock).toHaveBeenCalledWith(
                    'customer-task-1',
                );

                expect(
                    screen.getByText('Pekan Tuaran'),
                ).toBeTruthy();

                expect(
                    screen.getByText('ASSIGNED'),
                ).toBeTruthy();
            },
        );

        it(
            'shows dashboard task and Open Request summaries',
            async () => {
                getTasksMock.mockResolvedValue({
                    tasks: [],
                    summary: {
                        active_total: 2,
                    },
                    pagination: {
                        current_page: 1,
                        per_page: 10,
                        total: 5,
                        last_page: 1,
                    },
                });

                getRequestsMock.mockResolvedValue({
                    requests: [makeOpenRequest()],
                    pagination: {
                        current_page: 1,
                        per_page: 10,
                        total: 3,
                        last_page: 1,
                    },
                });

                await loginThroughCustomerDashboard();

                expect(
                    getRequestsMock,
                ).toHaveBeenCalledWith(1);

                const summaryGrid = document.querySelector(
                    '.customer-summary-grid',
                );

                expect(summaryGrid).not.toBeNull();

                const taskSummary = within(
                    summaryGrid as HTMLElement,
                ).getByText('Jumlah Tugasan').closest('article');

                const activeTaskSummary = within(
                    summaryGrid as HTMLElement,
                ).getByText('Tugasan Aktif').closest('article');

                const requestSummary = within(
                    summaryGrid as HTMLElement,
                ).getByText('Jumlah Permintaan').closest('article');

                expect(taskSummary?.textContent).toContain('5');
                expect(activeTaskSummary?.textContent).toContain('2');
                expect(requestSummary?.textContent).toContain('3');

                expect(
                    screen.getByRole('heading', {
                        name: 'Permintaan Terkini',
                    }),
                ).toBeTruthy();

                expect(
                    screen.getByText('Bantu membeli barang.'),
                ).toBeTruthy();
            },
        );

        it(
            'updates the Customer profile name',
            async () => {
                updateProfileMock.mockResolvedValue({
                    ...account,
                    name: 'Updated Customer',
                });

                await loginThroughCustomerDashboard();

                fireEvent.click(
                    screen.getByRole('button', {
                        name: 'Profil',
                    }),
                );

                fireEvent.change(
                    screen.getByLabelText('Nama'),
                    {
                        target: {
                            value: 'Updated Customer',
                        },
                    },
                );

                fireEvent.click(
                    screen.getByRole('button', {
                        name: 'Simpan Perubahan',
                    }),
                );

                await waitFor(() => {
                    expect(
                        updateProfileMock,
                    ).toHaveBeenCalledWith({
                        name: 'Updated Customer',
                    });
                });

                const profileSuccess =
                    await screen.findByRole('status');

                expect(profileSuccess.textContent).toContain(
                    'Profil berjaya dikemaskini.',
                );

                expect(
                    screen.getByText('Updated Customer'),
                ).toBeTruthy();
            },
        );

        it(
            'logs out the Customer and returns to the login screen',
            async () => {
                logoutMock.mockResolvedValue(undefined);

                await loginThroughRunnerProfile();

                fireEvent.click(
                    screen.getByRole('button', {
                        name: 'Keluar',
                    }),
                );

                expect(
                    await screen.findByRole('heading', {
                        name: 'Log Masuk Pelanggan',
                    }),
                ).toBeTruthy();

                expect(logoutMock).toHaveBeenCalledTimes(1);
            },
        );

        it(
            'rejects an invalid runner slug before API submission',
            async () => {
                window.history.replaceState(
                    {},
                    '',
                    '/?runner=!!invalid!!',
                );

                await loginThroughRunnerProfile();

                fireEvent.change(
                    screen.getByLabelText(
                        'Penerangan Tugasan',
                    ),
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

                expect(
                    createRequestMock,
                ).not.toHaveBeenCalled();
            },
        );
    },
);
