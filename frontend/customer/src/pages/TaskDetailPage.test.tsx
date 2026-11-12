
// @vitest-environment jsdom

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
    CustomerTaskRatingPayload,
} from '../api';

import type {
    CustomerTaskDetailItem,
} from '../features/tasks/useCustomerTasks';

import TaskDetailPage from './TaskDetailPage';

function makeTask(
    status = 'COMPLETED',
    rating: CustomerTaskDetailItem['rating'] = null,
): CustomerTaskDetailItem {
    const now = '2026-11-12T10:00:00.000Z';

    return {
        task: {
            task_id: 'task-test-001',
            module_key: 'Marketplace',
            task_type: 'MARKETPLACE_SERVICE_REQUEST',
            source_type: 'marketplace_request',
            source_id: 'request-test-001',
            generic_status: status,
            assigned_at: now,
            started_at: now,
            completed_at:
                status === 'COMPLETED' ? now : null,
            cancelled_at: null,
            created_at: now,
            updated_at: now,
        },
        request: {
            request_id: 'request-test-001',
            task_class: 'NON_DELIVERY',
            description: 'Bantu membeli barang di kedai.',
            pickup_location_snapshot: null,
            delivery_location_snapshot: null,
            task_location_snapshot: {
                address: 'Pekan Tuaran',
            },
            status: 'AWARDED',
            expires_at: null,
            awarded_at: now,
            cancelled_at: null,
        },
        rating,
    };
}

function renderTaskDetail(
    item: CustomerTaskDetailItem,
    options?: {
        ratingSaving?: boolean;
        ratingError?: string;
        ratingMessage?: string;
        onSubmitRating?: (
            payload: CustomerTaskRatingPayload,
        ) => Promise<void>;
    },
) {
    const onSubmitRating =
        options?.onSubmitRating ??
        vi.fn(
            async (
                _payload: CustomerTaskRatingPayload,
            ): Promise<void> => {},
        );

    const onBack = vi.fn();

    render(
        <TaskDetailPage
            item={item}
            onBack={onBack}
            onSubmitRating={onSubmitRating}
            ratingSaving={options?.ratingSaving ?? false}
            ratingError={options?.ratingError ?? ''}
            ratingMessage={options?.ratingMessage ?? ''}
        />,
    );

    return {
        onBack,
        onSubmitRating,
    };
}

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

describe('TaskDetailPage rating', () => {
    it(
        'does not show the rating form before task completion',
        () => {
            renderTaskDetail(
                makeTask('ASSIGNED'),
            );

            expect(
                screen.queryByRole('heading', {
                    name: 'Nilai Pengalaman Anda',
                }),
            ).toBeNull();

            expect(
                screen.queryByRole('button', {
                    name: 'Hantar Penilaian',
                }),
            ).toBeNull();
        },
    );

    it(
        'shows the rating form after task completion',
        () => {
            renderTaskDetail(
                makeTask('COMPLETED'),
            );

            expect(
                screen.getByRole('heading', {
                    name: 'Nilai Pengalaman Anda',
                }),
            ).toBeTruthy();

            expect(
                screen.getByRole('button', {
                    name: 'Hantar Penilaian',
                }),
            ).toBeTruthy();

            expect(
                screen.getByLabelText('Ulasan (Pilihan)'),
            ).toBeTruthy();
        },
    );

    it(
        'shows five selectable rating stars',
        () => {
            renderTaskDetail(
                makeTask('COMPLETED'),
            );

            for (let value = 1; value <= 5; value += 1) {
                expect(
                    screen.getByRole('radio', {
                        name: `${value} bintang`,
                    }),
                ).toBeTruthy();
            }

            expect(
                screen.getByText('Sangat Memuaskan'),
            ).toBeTruthy();
        },
    );

    it(
        'submits the selected rating and optional review',
        async () => {
            const onSubmitRating = vi.fn(
                async (
                    _payload: CustomerTaskRatingPayload,
                ): Promise<void> => {},
            );

            renderTaskDetail(
                makeTask('COMPLETED'),
                {
                    onSubmitRating,
                },
            );

            fireEvent.click(
                screen.getByRole('radio', {
                    name: '3 bintang',
                }),
            );

            fireEvent.change(
                screen.getByLabelText('Ulasan (Pilihan)'),
                {
                    target: {
                        value: '  Runner sangat membantu.  ',
                    },
                },
            );

            fireEvent.click(
                screen.getByRole('button', {
                    name: 'Hantar Penilaian',
                }),
            );

            expect(onSubmitRating).toHaveBeenCalledTimes(1);

            expect(onSubmitRating).toHaveBeenCalledWith({
                rating: 3,
                review: 'Runner sangat membantu.',
            });
        },
    );

    it(
        'submits null when the optional review is empty',
        async () => {
            const onSubmitRating = vi.fn(
                async (
                    _payload: CustomerTaskRatingPayload,
                ): Promise<void> => {},
            );

            renderTaskDetail(
                makeTask('COMPLETED'),
                {
                    onSubmitRating,
                },
            );

            fireEvent.click(
                screen.getByRole('button', {
                    name: 'Hantar Penilaian',
                }),
            );

            expect(onSubmitRating).toHaveBeenCalledWith({
                rating: 5,
                review: null,
            });
        },
    );

    it(
        'shows an existing rating and update option',
        () => {
            const item = makeTask('COMPLETED', {
                rating: 4,
                review: 'Runner menepati masa.',
                created_at: '2026-11-12T10:00:00.000Z',
                updated_at: '2026-11-12T10:00:00.000Z',
            });

            renderTaskDetail(item);

            expect(
                screen.getByRole('heading', {
                    name: 'Kemaskini Penilaian',
                }),
            ).toBeTruthy();

            expect(
                screen.getByRole('button', {
                    name: 'Kemaskini Penilaian',
                }),
            ).toBeTruthy();

            expect(
                screen.getByLabelText('Ulasan (Pilihan)'),
            ).toHaveProperty(
                'value',
                'Runner menepati masa.',
            );

            expect(
                screen.getByText(
                    'Penilaian ini boleh dikemaskini tetapi tidak boleh dipadam.',
                ),
            ).toBeTruthy();
        },
    );

    it(
        'disables rating controls while saving',
        () => {
            renderTaskDetail(
                makeTask('COMPLETED'),
                {
                    ratingSaving: true,
                },
            );

            expect(
                screen.getByRole('button', {
                    name: 'Menyimpan...',
                }).hasAttribute('disabled'),
            ).toBe(true);

            expect(
                screen.getByRole('radio', {
                    name: '1 bintang',
                }).hasAttribute('disabled'),
            ).toBe(true);

            expect(
                screen.getByLabelText('Ulasan (Pilihan)')
                    .hasAttribute('disabled'),
            ).toBe(true);
        },
    );

    it(
        'shows API error and success messages',
        () => {
            renderTaskDetail(
                makeTask('COMPLETED'),
                {
                    ratingError: 'Penilaian gagal disimpan.',
                    ratingMessage: 'Penilaian berjaya dihantar.',
                },
            );

            expect(
                screen.getByRole('alert').textContent,
            ).toContain('Penilaian gagal disimpan.');

            expect(
                screen.getByRole('status').textContent,
            ).toContain('Penilaian berjaya dihantar.');
        },
    );
});
