
import {
    useEffect,
    useState,
} from 'react';

import type { FormEvent } from 'react';

import type {
    CustomerTaskRatingPayload,
} from '../api';

import type {
    CustomerTaskDetailItem,
} from '../features/tasks/useCustomerTasks';

import { LocationSnapshotPanel } from '../customerPresentation';

type TaskDetailPageProps = {
    item: CustomerTaskDetailItem;
    onBack: () => void;
    onSubmitRating: (
        payload: CustomerTaskRatingPayload,
    ) => Promise<void>;
    ratingSaving: boolean;
    ratingError: string;
    ratingMessage: string;
};

function formatDate(value: string | null | undefined): string {
    if (!value) return '—';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return '—';

    return new Intl.DateTimeFormat('ms-MY', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function taskClassLabel(taskClass: string): string {
    if (taskClass === 'PICKUP_DELIVERY') {
        return 'Ambil dan Hantar';
    }

    if (taskClass === 'NON_DELIVERY') {
        return 'Tugasan Bukan Penghantaran';
    }

    return taskClass;
}

function ratingLabel(rating: number): string {
    const labels: Record<number, string> = {
        1: 'Sangat Tidak Memuaskan',
        2: 'Tidak Memuaskan',
        3: 'Sederhana',
        4: 'Memuaskan',
        5: 'Sangat Memuaskan',
    };

    return labels[rating] ?? '';
}

export default function TaskDetailPage({
    item,
    onBack,
    onSubmitRating,
    ratingSaving,
    ratingError,
    ratingMessage,
}: TaskDetailPageProps) {
    const { task, request, rating } = item;

    const [selectedRating, setSelectedRating] = useState(
        rating?.rating ?? 5,
    );

    const [review, setReview] = useState(
        rating?.review ?? '',
    );

    useEffect(() => {
        setSelectedRating(rating?.rating ?? 5);
        setReview(rating?.review ?? '');
    }, [rating]);

    const taskIsCompleted =
        task.generic_status === 'COMPLETED';

    async function handleRatingSubmit(
        event: FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();

        await onSubmitRating({
            rating: selectedRating,
            review: review.trim() || null,
        });
    }

    return (
        <div className="sr-task-detail-page">
            <section className="customer-panel">
                <button
                    className="customer-back-button"
                    type="button"
                    onClick={onBack}
                >
                    ← Kembali ke senarai
                </button>

                <div className="customer-section-heading">
                    <div>
                        <p className="customer-task-class">
                            {taskClassLabel(request.task_class)}
                        </p>

                        <h2>Butiran Tugasan</h2>
                    </div>
                </div>

                <h3>{request.description}</h3>

                <dl className="customer-detail-list">
                    <div>
                        <dt>ID Tugasan</dt>
                        <dd>{task.task_id}</dd>
                    </div>

                    <div>
                        <dt>Status Tugasan</dt>
                        <dd>{task.generic_status}</dd>
                    </div>

                    <div>
                        <dt>Status Permintaan</dt>
                        <dd>{request.status}</dd>
                    </div>

                    <div>
                        <dt>Dicipta</dt>
                        <dd>{formatDate(task.created_at)}</dd>
                    </div>

                    <div>
                        <dt>Runner Ditugaskan</dt>
                        <dd>{formatDate(task.assigned_at)}</dd>
                    </div>

                    <div>
                        <dt>Waktu Mula</dt>
                        <dd>{formatDate(task.started_at)}</dd>
                    </div>

                    <div>
                        <dt>Waktu Selesai</dt>
                        <dd>{formatDate(task.completed_at)}</dd>
                    </div>

                    <div>
                        <dt>Tarikh Luput Permintaan</dt>
                        <dd>{formatDate(request.expires_at)}</dd>
                    </div>
                </dl>

                {request.task_class === 'PICKUP_DELIVERY' && (
                    <div className="customer-location-grid">
                        <LocationSnapshotPanel
                            title="Lokasi Pengambilan"
                            snapshot={request.pickup_location_snapshot}
                        />

                        <LocationSnapshotPanel
                            title="Lokasi Penghantaran"
                            snapshot={request.delivery_location_snapshot}
                        />
                    </div>
                )}

                {request.task_class === 'NON_DELIVERY' && (
                    <div className="customer-location-grid">
                        <LocationSnapshotPanel
                            title="Lokasi Tugasan"
                            snapshot={request.task_location_snapshot}
                        />
                    </div>
                )}

                {taskIsCompleted && (
                    <section
                        className="customer-rating-section"
                        aria-labelledby="customer-rating-title"
                    >
                        <div className="customer-section-heading">
                            <div>
                                <p className="customer-task-class">
                                    PENILAIAN RUNNER
                                </p>

                                <h2 id="customer-rating-title">
                                    {rating
                                        ? 'Kemaskini Penilaian'
                                        : 'Nilai Pengalaman Anda'}
                                </h2>
                            </div>
                        </div>

                        <p>
                            Berikan penilaian berdasarkan pengalaman
                            anda dengan Runner untuk tugasan ini.
                        </p>

                        <form
                            onSubmit={handleRatingSubmit}
                            className="customer-rating-form"
                        >
                            <fieldset
                                className="customer-rating-stars"
                                disabled={ratingSaving}
                            >
                                <legend>Penilaian bintang</legend>

                                <div
                                    role="radiogroup"
                                    aria-label="Pilih penilaian satu hingga lima bintang"
                                >
                                    {[1, 2, 3, 4, 5].map((value) => (
                                        <button
                                            key={value}
                                            type="button"
                                            role="radio"
                                            aria-checked={
                                                selectedRating === value
                                            }
                                            aria-label={`${value} bintang`}
                                            className={
                                                selectedRating >= value
                                                    ? 'customer-rating-star is-selected'
                                                    : 'customer-rating-star'
                                            }
                                            onClick={() =>
                                                setSelectedRating(value)
                                            }
                                        >
                                            ★
                                        </button>
                                    ))}
                                </div>

                                <p aria-live="polite">
                                    {ratingLabel(selectedRating)}
                                </p>
                            </fieldset>

                            <label
                                htmlFor="customer-runner-review"
                                className="customer-form-label"
                            >
                                Ulasan (Pilihan)
                            </label>

                            <textarea
                                id="customer-runner-review"
                                className="customer-form-input"
                                rows={4}
                                maxLength={5000}
                                placeholder="Kongsikan pengalaman anda dengan Runner..."
                                value={review}
                                onChange={(event) =>
                                    setReview(event.target.value)
                                }
                                disabled={ratingSaving}
                            />

                            <p className="customer-form-hint">
                                Ulasan tidak wajib. Maksimum 5,000 aksara.
                            </p>

                            {ratingError && (
                                <p role="alert" className="customer-form-error">
                                    {ratingError}
                                </p>
                            )}

                            {ratingMessage && (
                                <p
                                    role="status"
                                    className="customer-form-success"
                                >
                                    {ratingMessage}
                                </p>
                            )}

                            <button
                                type="submit"
                                className="customer-primary-button"
                                disabled={ratingSaving}
                            >
                                {ratingSaving
                                    ? 'Menyimpan...'
                                    : rating
                                        ? 'Kemaskini Penilaian'
                                        : 'Hantar Penilaian'}
                            </button>
                        </form>

                        {rating && (
                            <p className="customer-rating-note">
                                Penilaian ini boleh dikemaskini tetapi
                                tidak boleh dipadam.
                            </p>
                        )}
                    </section>
                )}
            </section>
        </div>
    );
}
