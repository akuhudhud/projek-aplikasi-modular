
import type { RunnerTask } from '../../api';

import { formatDate, taskStatusLabel } from '../../utils/runnerFormatters';

interface RunnerTaskCardProps {
    task: RunnerTask;
    actionLoading: string;
    onStart: (task: RunnerTask) => void;
    onArrive: (task: RunnerTask) => void;
    onComplete: (task: RunnerTask, file?: File) => void;
}

export default function RunnerTaskCard({
    task,
    actionLoading,
    onStart,
    onArrive,
    onComplete,
}: RunnerTaskCardProps) {
    const isBusy = actionLoading === task.task_id;

    return (
        <article
            className="runner-dashboard__item"
            key={task.task_id}
        >
            <div className="runner-dashboard__item-top">
                <strong>
                    Tugasan #{task.task_id.slice(0, 8)}
                </strong>

                <span
                    className={`runner-dashboard__status runner-dashboard__status--${task.generic_status.toLowerCase()}`}
                >
                    {taskStatusLabel(task.generic_status)}
                </span>
            </div>

            <p>Rujukan: {task.source_id}</p>

            <small>
                Dicipta: {formatDate(task.created_at)}
            </small>

            {task.started_at && (
                <small>
                    Mula: {formatDate(task.started_at)}
                </small>
            )}

            {task.arrived_at && (
                <small>
                    Tiba: {formatDate(task.arrived_at)}
                </small>
            )}

            {task.completed_at && (
                <small>
                    Selesai: {formatDate(task.completed_at)}
                </small>
            )}

            {task.generic_status === 'ASSIGNED' && (
                <button
                    type="button"
                    className="runner-dashboard__primary-action"
                    onClick={() => onStart(task)}
                    disabled={isBusy || Boolean(actionLoading)}
                >
                    {isBusy ? 'SEDANG DIPROSES...' : 'MULA TUGASAN'}
                </button>
            )}

            {task.generic_status === 'IN_PROGRESS'
                && !task.arrived_at && (
                    <div className="runner-dashboard__task-action">
                        <p>
                            Tekan tiba apabila anda sudah sampai di
                            lokasi tugasan. GPS peranti akan direkodkan.
                        </p>

                        <button
                            type="button"
                            className="runner-dashboard__primary-action"
                            onClick={() => onArrive(task)}
                            disabled={isBusy || Boolean(actionLoading)}
                        >
                            {isBusy
                                ? 'MENDAPATKAN GPS...'
                                : 'SAYA SUDAH TIBA'}
                        </button>
                    </div>
                )}

            {task.generic_status === 'IN_PROGRESS'
                && Boolean(task.arrived_at) && (
                    <div className="runner-dashboard__task-action">
                        <p>
                            Muat naik gambar sebagai bukti tugasan
                            sebelum menandakan tugasan selesai.
                        </p>

                        <label>
                            Gambar bukti (maksimum 5 MB)
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                capture="environment"
                                disabled={isBusy || Boolean(actionLoading)}
                                onChange={(event) => {
                                    const file =
                                        event.currentTarget.files?.[0];

                                    if (file) {
                                        onComplete(task, file);
                                    }

                                    event.currentTarget.value = '';
                                }}
                            />
                        </label>

                        {isBusy && (
                            <p
                                className="runner-dashboard__notice"
                                role="status"
                            >
                                Sedang memuat naik bukti gambar...
                            </p>
                        )}
                    </div>
                )}
        </article>
    );
}
