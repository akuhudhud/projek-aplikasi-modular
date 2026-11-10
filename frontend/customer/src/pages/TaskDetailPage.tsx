
import type { CustomerTaskItem } from '../api';
import { LocationSnapshotPanel } from '../customerPresentation';

type TaskDetailPageProps = {
    item: CustomerTaskItem;
    onBack: () => void;
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
        return 'Pickup & Delivery';
    }

    if (taskClass === 'NON_DELIVERY') {
        return 'Non-Delivery';
    }

    return taskClass;
}

export default function TaskDetailPage({
    item,
    onBack,
}: TaskDetailPageProps) {
    const { task, request } = item;

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
                        <dt>Status</dt>
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
                        <dt>Runner ditugaskan</dt>
                        <dd>{formatDate(task.assigned_at)}</dd>
                    </div>

                    <div>
                        <dt>Mula</dt>
                        <dd>{formatDate(task.started_at)}</dd>
                    </div>

                    <div>
                        <dt>Selesai</dt>
                        <dd>{formatDate(task.completed_at)}</dd>
                    </div>

                    <div>
                        <dt>Tarikh luput permintaan</dt>
                        <dd>{formatDate(request.expires_at)}</dd>
                    </div>
                </dl>

                {request.task_class === 'PICKUP_DELIVERY' && (
                    <div className="customer-location-grid">
                        <LocationSnapshotPanel
                            title="Lokasi Pickup"
                            snapshot={request.pickup_location_snapshot}
                        />

                        <LocationSnapshotPanel
                            title="Lokasi Delivery"
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
            </section>
        </div>
    );
}
