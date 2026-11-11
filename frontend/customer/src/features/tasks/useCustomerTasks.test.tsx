
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
    act,
    cleanup,
    renderHook,
    waitFor,
} from '@testing-library/react';

const {
    getTasksMock,
    getTaskMock,
} = vi.hoisted(() => ({
    getTasksMock: vi.fn(),
    getTaskMock: vi.fn(),
}));

vi.mock('../../api', () => ({
    customerApi: {
        getTasks: getTasksMock,
        getTask: getTaskMock,
    },
}));

import { useCustomerTasks } from './useCustomerTasks';

function makeTaskItem(taskId = 'task-123') {
    return {
        task: {
            task_id: taskId,
            module_key: 'Marketplace',
            task_type: 'MARKETPLACE_SERVICE_REQUEST',
            source_type: 'MARKETPLACE_REQUEST',
            source_id: 'request-123',
            generic_status: 'ASSIGNED',
            assigned_at: null,
            started_at: null,
            completed_at: null,
            cancelled_at: null,
            created_at: '2026-11-11T10:00:00.000Z',
            updated_at: '2026-11-11T10:00:00.000Z',
        },
        request: {
            request_id: 'request-123',
            task_class: 'NON_DELIVERY' as const,
            description: 'Bantu membeli barang.',
            pickup_location_snapshot: null,
            delivery_location_snapshot: null,
            task_location_snapshot: {
                address: 'Pekan Tuaran',
            },
            status: 'AWARDED',
            expires_at: null,
            awarded_at: '2026-11-11T10:00:00.000Z',
            cancelled_at: null,
        },
    };
}

function makeTasksResult(tasks = [makeTaskItem()]) {
    return {
        tasks,
        summary: {
            active_total: 1,
        },
        pagination: {
            current_page: 1,
            per_page: 10,
            total: tasks.length,
            last_page: 3,
        },
    };
}

describe('useCustomerTasks', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        getTasksMock.mockResolvedValue(
            makeTasksResult(),
        );

        getTaskMock.mockResolvedValue(
            makeTaskItem(),
        );
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('does not load tasks when disabled', () => {
        const { result } = renderHook(() =>
            useCustomerTasks({
                enabled: false,
            }),
        );

        expect(getTasksMock).not.toHaveBeenCalled();
        expect(result.current.tasks).toEqual([]);
        expect(result.current.loading).toBe(false);
    });

    it('loads tasks, summary and pagination', async () => {
        const tasks = [
            makeTaskItem('task-1'),
            makeTaskItem('task-2'),
        ];

        getTasksMock.mockResolvedValue({
            ...makeTasksResult(tasks),
            summary: {
                active_total: 2,
            },
        });

        const { result } = renderHook(() =>
            useCustomerTasks({
                enabled: true,
            }),
        );

        expect(result.current.loading).toBe(true);

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(getTasksMock).toHaveBeenCalledWith(1);
        expect(result.current.tasks).toEqual(tasks);
        expect(result.current.total).toBe(2);
        expect(result.current.activeTotal).toBe(2);
        expect(result.current.lastPage).toBe(3);
        expect(result.current.error).toBe('');
    });

    it('changes page and prevents page number below one', async () => {
        const { result } = renderHook(() =>
            useCustomerTasks({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        act(() => {
            result.current.setCurrentPage(2);
        });

        await waitFor(() => {
            expect(getTasksMock).toHaveBeenLastCalledWith(2);
        });

        expect(result.current.currentPage).toBe(2);

        act(() => {
            result.current.setCurrentPage(0);
        });

        await waitFor(() => {
            expect(getTasksMock).toHaveBeenLastCalledWith(1);
        });

        expect(result.current.currentPage).toBe(1);
    });

    it('refreshes the task list', async () => {
        const { result } = renderHook(() =>
            useCustomerTasks({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(getTasksMock).toHaveBeenCalledTimes(1);
        });

        act(() => {
            result.current.refresh();
        });

        await waitFor(() => {
            expect(getTasksMock).toHaveBeenCalledTimes(2);
        });
    });

    it('shows an error and clears tasks when task loading fails', async () => {
        getTasksMock.mockRejectedValue(
            new Error('Network error'),
        );

        const { result } = renderHook(() =>
            useCustomerTasks({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(result.current.tasks).toEqual([]);
        expect(result.current.error).toBe(
            'Tidak berjaya memuatkan senarai tugasan.',
        );
    });

    it('loads selected task details', async () => {
        const item = makeTaskItem('task-detail-123');

        getTaskMock.mockResolvedValue({
            task: item.task,
            request: item.request,
        });

        const { result } = renderHook(() =>
            useCustomerTasks({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        act(() => {
            result.current.openTask(item);
        });

        expect(result.current.selectedTaskId).toBe(
            'task-detail-123',
        );

        expect(result.current.detailLoading).toBe(true);

        await waitFor(() => {
            expect(result.current.detailLoading).toBe(false);
        });

        expect(getTaskMock).toHaveBeenCalledWith(
            'task-detail-123',
        );

        expect(result.current.selectedTask).toEqual({
            task: item.task,
            request: item.request,
        });

        expect(result.current.detailError).toBe('');
    });

    it('shows an error when task details fail to load', async () => {
        getTaskMock.mockRejectedValue(
            new Error('Task detail failed'),
        );

        const item = makeTaskItem('task-error');

        const { result } = renderHook(() =>
            useCustomerTasks({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        act(() => {
            result.current.openTask(item);
        });

        await waitFor(() => {
            expect(result.current.detailLoading).toBe(false);
        });

        expect(result.current.selectedTask).toBeNull();

        expect(result.current.detailError).toBe(
            'Tidak berjaya memuatkan butiran tugasan. Sila cuba semula.',
        );
    });

    it('closes selected task details and clears detail state', async () => {
        const item = makeTaskItem('task-close');

        const { result } = renderHook(() =>
            useCustomerTasks({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        act(() => {
            result.current.openTask(item);
        });

        await waitFor(() => {
            expect(result.current.selectedTask).not.toBeNull();
        });

        act(() => {
            result.current.closeTask();
        });

        expect(result.current.selectedTaskId).toBeNull();
        expect(result.current.selectedTask).toBeNull();
        expect(result.current.detailError).toBe('');
        expect(result.current.detailLoading).toBe(false);
    });

    it('ignores task detail response after details are closed', async () => {
        let resolveTask!: (value: ReturnType<typeof makeTaskItem>) => void;

        getTaskMock.mockReturnValue(
            new Promise((resolve) => {
                resolveTask = resolve;
            }),
        );

        const item = makeTaskItem('task-late-response');

        const { result } = renderHook(() =>
            useCustomerTasks({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        act(() => {
            result.current.openTask(item);
        });

        act(() => {
            result.current.closeTask();
        });

        await act(async () => {
            resolveTask(item);
        });

        expect(result.current.selectedTaskId).toBeNull();
        expect(result.current.selectedTask).toBeNull();
        expect(result.current.detailLoading).toBe(false);
    });
});
