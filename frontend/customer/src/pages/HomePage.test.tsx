
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
    CustomerAccount,
    CustomerTaskItem,
    CustomerOwnMarketplaceRequest,
} from '../api';

import HomePage from './HomePage';

const account: CustomerAccount = {
    id: 'customer-001',
    profile_name: 'Nazim',
    phone: '0123456789',
    email: null,
    profile_picture: null,
    role: 'USER',
    status: 'ACTIVE',
};

function createTask(
    index: number,
    taskClass: 'PICKUP_DELIVERY' | 'NON_DELIVERY' = 'PICKUP_DELIVERY',
): CustomerTaskItem {
    return {
        task: {
            task_id: `task-${index}`,
            module_key: 'Marketplace',
            task_type: 'MARKETPLACE_SERVICE_REQUEST',
            source_type: 'marketplace',
            source_id: `request-${index}`,
            generic_status: 'IN_PROGRESS',
            assigned_at: null,
            started_at: null,
            completed_at: null,
            cancelled_at: null,
            created_at: '2026-11-10T10:00:00.000Z',
            updated_at: '2026-11-10T10:00:00.000Z',
        },
        request: {
            request_id: `request-${index}`,
            task_class: taskClass,
            description: `Penerangan tugasan ${index}`,
            pickup_location_snapshot: null,
            delivery_location_snapshot: null,
            task_location_snapshot: null,
            status: 'AWARDED',
            expires_at: '2026-11-13T10:00:00.000Z',
            awarded_at: null,
            cancelled_at: null,
        },
    };
}

function createRequest(
    index: number,
    taskClass: 'PICKUP_DELIVERY' | 'NON_DELIVERY' = 'NON_DELIVERY',
): CustomerOwnMarketplaceRequest {
    return {
        request_id: `own-request-${index}`,
        task_class: taskClass,
        description: `Permintaan pelanggan ${index}`,
        pickup_location_snapshot: null,
        delivery_location_snapshot: null,
        task_location_snapshot: null,
        status: 'OPEN',
        expires_at: '2026-11-13T10:00:00.000Z',
        awarded_at: null,
        cancelled_at: null,
        created_at: '2026-11-10T10:00:00.000Z',
        updated_at: '2026-11-10T10:00:00.000Z',
    };
}

function renderHomePage(
    overrides: Partial<React.ComponentProps<typeof HomePage>> = {},
) {
    const props: React.ComponentProps<typeof HomePage> = {
        account,
        taskTotal: 5,
        activeTaskTotal: 2,
        ownRequestTotal: 3,
        tasks: [],
        ownRequests: [],
        tasksLoading: false,
        tasksError: '',
        ownRequestsLoading: false,
        ownRequestsError: '',
        onOpenTask: vi.fn(),
        onNavigateTasks: vi.fn(),
        onNavigateRequests: vi.fn(),
        onNavigateCreate: vi.fn(),
        ...overrides,
    };

    return {
        ...render(<HomePage {...props} />),
        props,
    };
}

describe('HomePage', () => {
    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('displays the customer welcome message', () => {
        renderHomePage();

        expect(
            screen.getByRole('heading', {
                name: 'Hai, Nazim',
            }),
        ).toBeTruthy();

        expect(
            screen.getByText('Selamat datang ke Super Runner.'),
        ).toBeTruthy();
    });

    it('displays the account summary values', () => {
        renderHomePage();

        expect(screen.getByText('Jumlah Tugasan')).toBeTruthy();
        expect(screen.getByText('Tugasan Aktif')).toBeTruthy();
        expect(screen.getByText('Jumlah Permintaan')).toBeTruthy();
        expect(screen.getByText('Status Akaun')).toBeTruthy();

        expect(screen.getByText('5')).toBeTruthy();
        expect(screen.getByText('2')).toBeTruthy();
        expect(screen.getByText('3')).toBeTruthy();
        expect(screen.getByText('ACTIVE')).toBeTruthy();
    });

    it('navigates to create request when quick action is clicked', () => {
        const { props } = renderHomePage();

        fireEvent.click(
            screen.getByRole('button', {
                name: '+ Cipta Permintaan',
            }),
        );

        expect(props.onNavigateCreate).toHaveBeenCalledTimes(1);
    });

    it('navigates to all tasks', () => {
        const { props } = renderHomePage();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Lihat Semua',
            }),
        );

        expect(props.onNavigateTasks).toHaveBeenCalledTimes(1);
    });

    it('navigates to all customer requests', () => {
        const { props } = renderHomePage();

        const buttons = screen.getAllByRole('button', {
            name: 'Lihat Semua',
        });

        fireEvent.click(buttons[1]);

        expect(props.onNavigateRequests).toHaveBeenCalledTimes(1);
    });

    it('displays loading state for tasks', () => {
        renderHomePage({
            tasksLoading: true,
        });

        expect(
            screen.getByText('Sedang memuatkan tugasan...'),
        ).toBeTruthy();
    });

    it('displays task loading errors', () => {
        renderHomePage({
            tasksError: 'Tidak dapat memuatkan tugasan.',
        });

        expect(
            screen.getByText('Tidak dapat memuatkan tugasan.'),
        ).toBeTruthy();

        expect(screen.getByRole('alert')).toBeTruthy();
    });

    it('displays an empty state when there are no tasks', () => {
        renderHomePage({
            tasks: [],
        });

        expect(
            screen.getByRole('heading', {
                name: 'Belum Ada Tugasan',
            }),
        ).toBeTruthy();
    });

    it('displays only the three latest tasks', () => {
        renderHomePage({
            tasks: [
                createTask(1),
                createTask(2),
                createTask(3),
                createTask(4),
            ],
        });

        expect(screen.getByText('Penerangan tugasan 1')).toBeTruthy();
        expect(screen.getByText('Penerangan tugasan 2')).toBeTruthy();
        expect(screen.getByText('Penerangan tugasan 3')).toBeTruthy();

        expect(
            screen.queryByText('Penerangan tugasan 4'),
        ).toBeNull();
    });

    it('opens a task when its card is clicked', () => {
        const task = createTask(1);
        const { props } = renderHomePage({
            tasks: [task],
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: /Penerangan tugasan 1/,
            }),
        );

        expect(props.onOpenTask).toHaveBeenCalledWith(task);
    });

    it('displays the pickup and delivery task class label', () => {
        renderHomePage({
            tasks: [createTask(1, 'PICKUP_DELIVERY')],
        });

        expect(
            screen.getByText('Ambil dan Hantar'),
        ).toBeTruthy();
    });

    it('displays the non-delivery task class label', () => {
        renderHomePage({
            tasks: [createTask(1, 'NON_DELIVERY')],
        });

        expect(
            screen.getByText('Tugasan Bukan Penghantaran'),
        ).toBeTruthy();
    });

    it('displays loading state for customer requests', () => {
        renderHomePage({
            ownRequestsLoading: true,
        });

        expect(
            screen.getByText('Sedang memuatkan permintaan...'),
        ).toBeTruthy();
    });

    it('displays customer request loading errors', () => {
        renderHomePage({
            ownRequestsError: 'Tidak dapat memuatkan permintaan.',
        });

        expect(
            screen.getByText('Tidak dapat memuatkan permintaan.'),
        ).toBeTruthy();

        expect(screen.getByRole('alert')).toBeTruthy();
    });

    it('displays an empty state when there are no customer requests', () => {
        renderHomePage({
            ownRequests: [],
        });

        expect(
            screen.getByRole('heading', {
                name: 'Belum Ada Permintaan',
            }),
        ).toBeTruthy();
    });

    it('allows customers to create a request from the empty state', () => {
        const { props } = renderHomePage({
            ownRequests: [],
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Cipta Permintaan',
            }),
        );

        expect(props.onNavigateCreate).toHaveBeenCalledTimes(1);
    });

    it('displays only the three latest customer requests', () => {
        renderHomePage({
            ownRequests: [
                createRequest(1),
                createRequest(2),
                createRequest(3),
                createRequest(4),
            ],
        });

        expect(screen.getByText('Permintaan pelanggan 1')).toBeTruthy();
        expect(screen.getByText('Permintaan pelanggan 2')).toBeTruthy();
        expect(screen.getByText('Permintaan pelanggan 3')).toBeTruthy();

        expect(
            screen.queryByText('Permintaan pelanggan 4'),
        ).toBeNull();
    });

    it('displays a fallback when a request has no description', () => {
        renderHomePage({
            ownRequests: [
                {
                    ...createRequest(1),
                    description: '',
                },
            ],
        });

        expect(
            screen.getByText('Tiada penerangan.'),
        ).toBeTruthy();
    });
});
