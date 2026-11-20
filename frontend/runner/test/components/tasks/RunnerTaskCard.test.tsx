
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import RunnerTaskCard from '../../../src/components/tasks/RunnerTaskCard';
import type { RunnerTask } from '../../../src/api';

const task: RunnerTask = {
    task_id: 'task-12345678',
    module_key: 'MARKETPLACE',
    task_type: 'DELIVERY',
    source_type: 'CUSTOMER',
    source_id: 'request-001',
    assigned_account_id: 1,
    generic_status: 'ASSIGNED',
    assigned_at: null,
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

describe('RunnerTaskCard', () => {
    it('renders task reference and assigned status', () => {
        render(
            <RunnerTaskCard
                task={task}
                actionLoading=""
                onStart={vi.fn()}
                onArrive={vi.fn()}
                onComplete={vi.fn()}
            />,
        );

        expect(screen.getByText('Tugasan #task-12')).toBeInTheDocument();
        expect(screen.getByText('Rujukan: request-001')).toBeInTheDocument();
        expect(screen.getByText('DITUGASKAN')).toBeInTheDocument();
    });

    it('starts an assigned task when the button is clicked', () => {
        const onStart = vi.fn();

        render(
            <RunnerTaskCard
                task={task}
                actionLoading=""
                onStart={onStart}
                onArrive={vi.fn()}
                onComplete={vi.fn()}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', { name: 'MULA TUGASAN' }),
        );

        expect(onStart).toHaveBeenCalledWith(task);
    });

    it('disables actions while another task action is processing', () => {
        render(
            <RunnerTaskCard
                task={task}
                actionLoading="another-task"
                onStart={vi.fn()}
                onArrive={vi.fn()}
                onComplete={vi.fn()}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'MULA TUGASAN' }),
        ).toBeDisabled();
    });

    it('shows arrival action for an in-progress task without arrival time', () => {
        const onArrive = vi.fn();
        const inProgressTask = {
            ...task,
            generic_status: 'IN_PROGRESS',
        };

        render(
            <RunnerTaskCard
                task={inProgressTask}
                actionLoading=""
                onStart={vi.fn()}
                onArrive={onArrive}
                onComplete={vi.fn()}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', { name: 'SAYA SUDAH TIBA' }),
        );

        expect(onArrive).toHaveBeenCalledWith(inProgressTask);
    });

    it('submits an image proof after arrival', () => {
        const onComplete = vi.fn();
        const arrivedTask = {
            ...task,
            generic_status: 'IN_PROGRESS',
            arrived_at: '2026-11-20T11:00:00Z',
        };

        render(
            <RunnerTaskCard
                task={arrivedTask}
                actionLoading=""
                onStart={vi.fn()}
                onArrive={vi.fn()}
                onComplete={onComplete}
            />,
        );

        const file = new File(['image'], 'proof.jpg', {
            type: 'image/jpeg',
        });

        const input = screen.getByLabelText(
            'Gambar bukti (maksimum 5 MB)',
        );

        fireEvent.change(input, {
            target: { files: [file] },
        });

        expect(onComplete).toHaveBeenCalledWith(arrivedTask, file);
        expect(input).toHaveValue('');
    });

    it('does not show task actions for a completed task', () => {
        render(
            <RunnerTaskCard
                task={{
                    ...task,
                    generic_status: 'COMPLETED',
                    completed_at: '2026-11-20T12:00:00Z',
                }}
                actionLoading=""
                onStart={vi.fn()}
                onArrive={vi.fn()}
                onComplete={vi.fn()}
            />,
        );

        expect(
            screen.queryByRole('button', { name: 'MULA TUGASAN' }),
        ).not.toBeInTheDocument();

        expect(
            screen.queryByRole('button', { name: 'SAYA SUDAH TIBA' }),
        ).not.toBeInTheDocument();

        expect(
            screen.queryByLabelText('Gambar bukti (maksimum 5 MB)'),
        ).not.toBeInTheDocument();
    });
});
