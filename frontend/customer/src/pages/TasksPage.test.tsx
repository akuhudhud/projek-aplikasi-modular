
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
    CustomerTaskItem,
} from '../api';

import TasksPage from './TasksPage';

function createTask(
    id: string,
    taskClass: 'PICKUP_DELIVERY' | 'NON_DELIVERY' = 'PICKUP_DELIVERY',
): CustomerTaskItem {
    return {
        task: {
            task_id: id,
            module_key: 'Marketplace',
            task_type: 'MARKETPLACE_SERVICE_REQUEST',
            source_type: 'marketplace',
            source_id: `request-${id}`,
            generic_status: 'IN_PROGRESS',
            assigned_at: null,
            started_at: null,
            completed_at: null,
            cancelled_at: null,
            created_at: '2026-11-12T08:00:00.000Z',
            updated_at: '2026-11-12T08:00:00.000Z',
        },
        request: {
            request_id: `request-${id}`,
            task_class: taskClass,
            description: `Penerangan tugasan ${id}`,
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

function renderTasksPage(
    overrides: Partial<React.ComponentProps<typeof TasksPage>> = {},
) {
    const props: React.ComponentProps<typeof TasksPage> = {
        tasks: [],
        tasksLoading: false,
        tasksError: '',
        taskPage: 1,
        taskLastPage: 1,
        onOpenTask: vi.fn(),
        onPageChange: vi.fn(),
        ...overrides,
    };

    return {
        ...render(<TasksPage {...props} />),
        props,
    };
}

describe('TasksPage', () => {
    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('displays the page heading and description', () => {
        renderTasksPage();

        expect(
            screen.getByRole('heading', {
                name: 'Tugasan Saya',
            }),
        ).toBeTruthy();

        expect(
            screen.getByText(
                'Senarai tugasan yang telah berjaya diwujudkan daripada Permintaan Terbuka.',
            ),
        ).toBeTruthy();
    });

    it('displays the number of tasks', () => {
        renderTasksPage({
            tasks: [
                createTask('task-001'),
                createTask('task-002'),
            ],
        });

        expect(
            screen.getByText('2 tugasan'),
        ).toBeTruthy();
    });

    it('displays loading state', () => {
        renderTasksPage({
            tasksLoading: true,
        });

        expect(
            screen.getByText('Memuatkan tugasan...'),
        ).toBeTruthy();
    });

    it('displays error state', () => {
        renderTasksPage({
            tasksError: 'Gagal memuatkan tugasan.',
        });

        expect(
            screen.getByRole('alert'),
        ).toHaveTextContent('Gagal memuatkan tugasan.');
    });

    it('displays empty state when there are no tasks', () => {
        renderTasksPage();

        expect(
            screen.getByRole('heading', {
                name: 'Belum ada tugasan',
            }),
        ).toBeTruthy();

        expect(
            screen.getByText(
                'Tugasan yang berjaya diwujudkan akan dipaparkan di sini.',
            ),
        ).toBeTruthy();
    });

    it('displays pickup and delivery task class label', () => {
        renderTasksPage({
            tasks: [
                createTask('task-003', 'PICKUP_DELIVERY'),
            ],
        });

        expect(
            screen.getByText('Ambil dan Hantar'),
        ).toBeTruthy();
    });

    it('displays non-delivery task class label', () => {
        renderTasksPage({
            tasks: [
                createTask('task-004', 'NON_DELIVERY'),
            ],
        });

        expect(
            screen.getByText('Tugasan Bukan Penghantaran'),
        ).toBeTruthy();
    });

    it('displays task description and status', () => {
        renderTasksPage({
            tasks: [
                createTask('task-005'),
            ],
        });

        expect(
            screen.getByText('Penerangan tugasan task-005'),
        ).toBeTruthy();

        expect(
            screen.getByText('Status: IN_PROGRESS'),
        ).toBeTruthy();
    });

    it('opens task when its card is clicked', () => {
        const task = createTask('task-006');

        const { props } = renderTasksPage({
            tasks: [task],
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: /Penerangan tugasan task-006/,
            }),
        );

        expect(props.onOpenTask).toHaveBeenCalledWith(task);
    });

    it('displays multiple tasks', () => {
        renderTasksPage({
            tasks: [
                createTask('task-007'),
                createTask('task-008'),
                createTask('task-009'),
            ],
        });

        expect(
            screen.getByText('Penerangan tugasan task-007'),
        ).toBeTruthy();

        expect(
            screen.getByText('Penerangan tugasan task-008'),
        ).toBeTruthy();

        expect(
            screen.getByText('Penerangan tugasan task-009'),
        ).toBeTruthy();
    });

    it('displays current pagination page', () => {
        renderTasksPage({
            tasks: [createTask('task-010')],
            taskPage: 2,
            taskLastPage: 5,
        });

        expect(
            screen.getByText('2 / 5'),
        ).toBeTruthy();
    });

    it('navigates to previous page', () => {
        const { props } = renderTasksPage({
            tasks: [createTask('task-011')],
            taskPage: 2,
            taskLastPage: 5,
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Sebelum',
            }),
        );

        expect(props.onPageChange).toHaveBeenCalledWith(1);
    });

    it('navigates to next page', () => {
        const { props } = renderTasksPage({
            tasks: [createTask('task-012')],
            taskPage: 2,
            taskLastPage: 5,
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Seterusnya',
            }),
        );

        expect(props.onPageChange).toHaveBeenCalledWith(3);
    });

    it('disables previous button on first page', () => {
        renderTasksPage({
            tasks: [createTask('task-013')],
            taskPage: 1,
            taskLastPage: 3,
        });

        expect(
            screen.getByRole('button', {
                name: 'Sebelum',
            }),
        ).toBeDisabled();
    });

    it('disables next button on last page', () => {
        renderTasksPage({
            tasks: [createTask('task-014')],
            taskPage: 3,
            taskLastPage: 3,
        });

        expect(
            screen.getByRole('button', {
                name: 'Seterusnya',
            }),
        ).toBeDisabled();
    });

    it('hides pagination while tasks are loading', () => {
        renderTasksPage({
            tasks: [createTask('task-015')],
            tasksLoading: true,
            taskPage: 2,
            taskLastPage: 3,
        });

        expect(
            screen.getByText('Memuatkan tugasan...'),
        ).toBeTruthy();

        expect(
            screen.queryByRole('button', {
                name: 'Sebelum',
            }),
        ).toBeNull();

        expect(
            screen.queryByRole('button', {
                name: 'Seterusnya',
            }),
        ).toBeNull();
    });
});
