
import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { runnerApi } from '../../../src/api';
import { getCurrentCoordinates } from '../../../src/utils/geolocation';
import { useRunnerTasks } from '../../../src/features/tasks/useRunnerTasks';

vi.mock('../../../src/api', () => ({
    runnerApi: {
        getTasks: vi.fn(),
        getTaskHistory: vi.fn(),
        startTask: vi.fn(),
        arriveTask: vi.fn(),
        completeTask: vi.fn(),
    },
}));

vi.mock('../../../src/utils/geolocation', () => ({
    getCurrentCoordinates: vi.fn(),
}));

const pagination = {
    current_page: 1,
    per_page: 50,
    total: 2,
    last_page: 1,
};

function createTask(taskId: string, status: string) {
    return {
        task_id: taskId,
        module_key: 'MARKETPLACE',
        task_type: 'DELIVERY',
        source_type: 'CUSTOMER',
        source_id: `request-${taskId}`,
        assigned_account_id: 1,
        generic_status: status,
        assigned_at: '2026-11-20T10:00:00Z',
        started_at: null,
        arrived_at: null,
        arrival_latitude: null,
        arrival_longitude: null,
        completed_at: null,
        completion_proof_path: null,
        cancelled_at: null,
        created_at: '2026-11-20T10:00:00Z',
        updated_at: '2026-11-20T10:00:00Z',
    };
}

const assignedTask = createTask('task-001', 'ASSIGNED');
const inProgressTask = createTask('task-002', 'IN_PROGRESS');
const completedTask = createTask('task-003', 'COMPLETED');

describe('useRunnerTasks', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        vi.mocked(runnerApi.getTasks).mockResolvedValue({
            tasks: [assignedTask, inProgressTask],
            pagination,
        });

        vi.mocked(runnerApi.getTaskHistory).mockResolvedValue({
            tasks: [completedTask],
            pagination,
            summary: {
                completed_total: 7,
            },
        });

        vi.mocked(runnerApi.startTask).mockResolvedValue(
            inProgressTask,
        );

        vi.mocked(getCurrentCoordinates).mockResolvedValue({
            latitude: 5.978,
            longitude: 116.075,
        });

        vi.mocked(runnerApi.arriveTask).mockResolvedValue(
            inProgressTask,
        );

        vi.mocked(runnerApi.completeTask).mockResolvedValue(
            completedTask,
        );
    });

    it('loads active tasks and history when the hook mounts', async () => {
        const { result } = renderHook(() => useRunnerTasks());

        await waitFor(() => {
            expect(result.current.tasksLoading).toBe(false);
            expect(result.current.historyLoading).toBe(false);
        });

        expect(runnerApi.getTasks).toHaveBeenCalledWith(1);
        expect(runnerApi.getTaskHistory).toHaveBeenCalledWith(1);
        expect(result.current.tasks).toHaveLength(2);
        expect(result.current.history).toHaveLength(1);
        expect(result.current.completedTaskCount).toBe(7);
    });

    it('separates active and completed tasks', async () => {
        const { result } = renderHook(() => useRunnerTasks());

        await waitFor(() => {
            expect(result.current.historyLoading).toBe(false);
        });

        expect(result.current.activeTasks).toHaveLength(2);
        expect(result.current.completedTasks).toHaveLength(1);

        expect(
            result.current.activeTasks.map((task) => task.task_id),
        ).toEqual(['task-001', 'task-002']);

        expect(result.current.completedTasks[0].task_id).toBe(
            'task-003',
        );
    });

    it('loads the requested task and history pages', async () => {
        const { result } = renderHook(() => useRunnerTasks());

        await waitFor(() => {
            expect(result.current.tasksLoading).toBe(false);
            expect(result.current.historyLoading).toBe(false);
        });

        await act(async () => {
            await result.current.loadTasks(2);
            await result.current.loadHistory(3);
        });

        expect(runnerApi.getTasks).toHaveBeenLastCalledWith(2);
        expect(runnerApi.getTaskHistory).toHaveBeenLastCalledWith(3);
    });

    it('keeps the latest task response when requests finish out of order', async () => {
        const { result } = renderHook(() => useRunnerTasks());

        await waitFor(() => {
            expect(result.current.tasksLoading).toBe(false);
        });

        type TasksResponse = Awaited<
            ReturnType<typeof runnerApi.getTasks>
        >;

        let resolveFirst!: (value: TasksResponse) => void;
        let resolveSecond!: (value: TasksResponse) => void;

        const firstResponse = new Promise<TasksResponse>((resolve) => {
            resolveFirst = resolve;
        });

        const secondResponse = new Promise<TasksResponse>((resolve) => {
            resolveSecond = resolve;
        });

        vi.mocked(runnerApi.getTasks)
            .mockImplementationOnce(() => firstResponse)
            .mockImplementationOnce(() => secondResponse);

        let firstRequest!: Promise<void>;
        let secondRequest!: Promise<void>;

        act(() => {
            firstRequest = result.current.loadTasks(2);
            secondRequest = result.current.loadTasks(3);
        });

        await act(async () => {
            resolveSecond({
                tasks: [inProgressTask],
                pagination: {
                    ...pagination,
                    current_page: 3,
                },
            });

            await secondRequest;
        });

        await act(async () => {
            resolveFirst({
                tasks: [assignedTask],
                pagination: {
                    ...pagination,
                    current_page: 2,
                },
            });

            await firstRequest;
        });

        expect(result.current.tasks).toEqual([inProgressTask]);
        expect(result.current.tasksPagination.current_page).toBe(3);
        expect(result.current.tasksLoading).toBe(false);
    });

    it('keeps the latest history response when requests finish out of order', async () => {
        const { result } = renderHook(() => useRunnerTasks());

        await waitFor(() => {
            expect(result.current.historyLoading).toBe(false);
        });

        type HistoryResponse = Awaited<
            ReturnType<typeof runnerApi.getTaskHistory>
        >;

        let resolveFirst!: (value: HistoryResponse) => void;
        let resolveSecond!: (value: HistoryResponse) => void;

        const firstResponse = new Promise<HistoryResponse>((resolve) => {
            resolveFirst = resolve;
        });

        const secondResponse = new Promise<HistoryResponse>((resolve) => {
            resolveSecond = resolve;
        });

        vi.mocked(runnerApi.getTaskHistory)
            .mockImplementationOnce(() => firstResponse)
            .mockImplementationOnce(() => secondResponse);

        let firstRequest!: Promise<void>;
        let secondRequest!: Promise<void>;

        act(() => {
            firstRequest = result.current.loadHistory(2);
            secondRequest = result.current.loadHistory(3);
        });

        await act(async () => {
            resolveSecond({
                tasks: [completedTask],
                pagination: {
                    ...pagination,
                    current_page: 3,
                },
                summary: {
                    completed_total: 9,
                },
            });

            await secondRequest;
        });

        await act(async () => {
            resolveFirst({
                tasks: [assignedTask],
                pagination: {
                    ...pagination,
                    current_page: 2,
                },
                summary: {
                    completed_total: 4,
                },
            });

            await firstRequest;
        });

        expect(result.current.history).toEqual([completedTask]);
        expect(result.current.historyPagination.current_page).toBe(3);
        expect(result.current.completedTaskCount).toBe(9);
        expect(result.current.historyLoading).toBe(false);
    });

    it('shows errors when task lists cannot be loaded', async () => {
        vi.mocked(runnerApi.getTasks).mockRejectedValue(
            new Error('Network error'),
        );

        vi.mocked(runnerApi.getTaskHistory).mockRejectedValue(
            new Error('Network error'),
        );

        const { result } = renderHook(() => useRunnerTasks());

        await waitFor(() => {
            expect(result.current.tasksLoading).toBe(false);
            expect(result.current.historyLoading).toBe(false);
        });

        expect(result.current.tasksError).toBe(
            'Tidak berjaya mendapatkan senarai tugasan. Sila cuba semula.',
        );

        expect(result.current.historyError).toBe(
            'Tidak berjaya mendapatkan sejarah tugasan.',
        );
    });

    it('starts an assigned task successfully', async () => {
        const { result } = renderHook(() => useRunnerTasks());

        await waitFor(() => {
            expect(result.current.tasksLoading).toBe(false);
        });

        await act(async () => {
            await result.current.startTask(assignedTask);
        });

        expect(runnerApi.startTask).toHaveBeenCalledWith('task-001');
        expect(result.current.actionSuccess).toBe(
            'Tugasan berjaya dimulakan.',
        );
        expect(result.current.actionError).toBe('');
        expect(result.current.actionLoading).toBe('');
    });

    it('shows an error when starting a task fails', async () => {
        vi.mocked(runnerApi.startTask).mockRejectedValue(
            new Error('Task unavailable'),
        );

        const { result } = renderHook(() => useRunnerTasks());

        await waitFor(() => {
            expect(result.current.tasksLoading).toBe(false);
        });

        await act(async () => {
            await result.current.startTask(assignedTask);
        });

        expect(result.current.actionError).toBe(
            'Tidak berjaya memulakan tugasan. Sila cuba semula.',
        );
        expect(result.current.actionLoading).toBe('');
    });

    it('records arrival coordinates successfully', async () => {
        const { result } = renderHook(() => useRunnerTasks());

        await waitFor(() => {
            expect(result.current.tasksLoading).toBe(false);
        });

        await act(async () => {
            await result.current.arriveTask(inProgressTask);
        });

        expect(getCurrentCoordinates).toHaveBeenCalledOnce();

        expect(runnerApi.arriveTask).toHaveBeenCalledWith(
            'task-002',
            {
                latitude: 5.978,
                longitude: 116.075,
            },
        );

        expect(result.current.actionSuccess).toBe(
            'Lokasi ketibaan berjaya direkodkan.',
        );
        expect(result.current.actionLoading).toBe('');
    });

    it('shows an error when arrival coordinates cannot be recorded', async () => {
        vi.mocked(runnerApi.arriveTask).mockRejectedValue(
            new Error('Network error'),
        );

        const { result } = renderHook(() => useRunnerTasks());

        await waitFor(() => {
            expect(result.current.tasksLoading).toBe(false);
        });

        await act(async () => {
            await result.current.arriveTask(inProgressTask);
        });

        expect(result.current.actionError).toBe(
            'Tidak berjaya merekodkan ketibaan. Sila cuba semula.',
        );
    });

    it('shows a GPS-specific error when geolocation fails', async () => {
        const gpsError = new Error('Location permission denied');
        gpsError.name = 'RunnerGeolocationError';

        vi.mocked(getCurrentCoordinates).mockRejectedValue(gpsError);

        const { result } = renderHook(() => useRunnerTasks());

        await waitFor(() => {
            expect(result.current.tasksLoading).toBe(false);
        });

        await act(async () => {
            await result.current.arriveTask(inProgressTask);
        });

        expect(runnerApi.arriveTask).not.toHaveBeenCalled();
        expect(result.current.actionError).toBe(
            'Tidak berjaya mendapatkan GPS. Benarkan akses lokasi dan cuba semula.',
        );
        expect(result.current.actionLoading).toBe('');
    });

    it('requires an image before completing a task', async () => {
        const { result } = renderHook(() => useRunnerTasks());

        await waitFor(() => {
            expect(result.current.tasksLoading).toBe(false);
        });

        await act(async () => {
            await result.current.completeTask(inProgressTask);
        });

        expect(runnerApi.completeTask).not.toHaveBeenCalled();
        expect(result.current.actionError).toBe(
            'Pilih gambar bukti tugasan dahulu.',
        );
    });

    it('rejects non-image proof files', async () => {
        const { result } = renderHook(() => useRunnerTasks());

        await waitFor(() => {
            expect(result.current.tasksLoading).toBe(false);
        });

        const file = new File(['document'], 'proof.pdf', {
            type: 'application/pdf',
        });

        await act(async () => {
            await result.current.completeTask(inProgressTask, file);
        });

        expect(runnerApi.completeTask).not.toHaveBeenCalled();
        expect(result.current.actionError).toBe(
            'Fail bukti mestilah gambar.',
        );
    });

    it('rejects proof files larger than five megabytes', async () => {
        const { result } = renderHook(() => useRunnerTasks());

        await waitFor(() => {
            expect(result.current.tasksLoading).toBe(false);
        });

        const file = new File(
            [new Uint8Array(5 * 1024 * 1024 + 1)],
            'proof.jpg',
            { type: 'image/jpeg' },
        );

        await act(async () => {
            await result.current.completeTask(inProgressTask, file);
        });

        expect(runnerApi.completeTask).not.toHaveBeenCalled();
        expect(result.current.actionError).toBe(
            'Saiz gambar tidak boleh melebihi 5 MB.',
        );
    });

    it('completes a task with image proof and refreshes task lists', async () => {
        const { result } = renderHook(() => useRunnerTasks());

        await waitFor(() => {
            expect(result.current.tasksLoading).toBe(false);
            expect(result.current.historyLoading).toBe(false);
        });

        const file = new File(['image-content'], 'proof.jpg', {
            type: 'image/jpeg',
        });

        await act(async () => {
            await result.current.completeTask(inProgressTask, file);
        });

        expect(runnerApi.completeTask).toHaveBeenCalledWith(
            'task-002',
            file,
        );

        expect(runnerApi.getTasks).toHaveBeenCalledTimes(2);
        expect(runnerApi.getTaskHistory).toHaveBeenCalledTimes(2);

        expect(result.current.actionSuccess).toBe(
            'Tugasan berjaya diselesaikan. Bukti gambar telah dihantar.',
        );

        expect(result.current.actionLoading).toBe('');
    });

    it('clears action messages when requested', async () => {
        const { result } = renderHook(() => useRunnerTasks());

        await waitFor(() => {
            expect(result.current.tasksLoading).toBe(false);
        });

        await act(async () => {
            await result.current.startTask(assignedTask);
        });

        expect(result.current.actionSuccess).not.toBe('');

        act(() => {
            result.current.clearActionMessages();
        });

        expect(result.current.actionSuccess).toBe('');
        expect(result.current.actionError).toBe('');
    });
});
