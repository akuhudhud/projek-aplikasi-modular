
import './customerPresentation.css';

const TASK_STATUS_LABELS: Record<string, string> = {
    OPEN: 'Menunggu Runner',
    ASSIGNED: 'Runner ditugaskan',
    IN_PROGRESS: 'Sedang dilaksanakan',
    COMPLETED: 'Selesai',
    CANCELLED: 'Dibatalkan',
};

const REQUEST_STATUS_LABELS: Record<string, string> = {
    OPEN: 'Menunggu sebut harga',
    AWARDED: 'Sebut harga dipilih',
    CANCELLED: 'Dibatalkan',
    EXPIRED: 'Tamat tempoh',
};

export function formatCustomerTaskStatus(status: string): string {
    return TASK_STATUS_LABELS[status] ?? 'Status tugasan dikemas kini';
}

export function formatCustomerRequestStatus(status: string): string {
    return REQUEST_STATUS_LABELS[status] ?? 'Status permintaan dikemas kini';
}

export interface LocationSnapshot {
    address?: unknown;
    contact_name?: unknown;
    phone?: unknown;
    [key: string]: unknown;
}

export interface LocationSnapshotField {
    label: string;
    value: string;
}

function readSnapshotValue(
    snapshot: LocationSnapshot | null,
    key: string,
): string {
    const value = snapshot?.[key];

    return typeof value === 'string' && value.trim().length > 0
        ? value.trim()
        : 'Tidak dinyatakan';
}

export function getLocationSnapshotFields(
    snapshot: LocationSnapshot | null,
): LocationSnapshotField[] {
    return [
        {
            label: 'Alamat',
            value: readSnapshotValue(snapshot, 'address'),
        },
        {
            label: 'Nama Orang Dihubungi',
            value: readSnapshotValue(snapshot, 'contact_name'),
        },
        {
            label: 'Nombor Telefon',
            value: readSnapshotValue(snapshot, 'phone'),
        },
    ];
}

export function formatCustomerDate(
    value: string | null | undefined,
): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('ms-MY', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

export function isRequestExpiryValid(
    value: string,
    now = Date.now(),
): boolean {
    const expiry = new Date(value).getTime();
    const maximumLifetime = 24 * 60 * 60 * 1000 - 10 * 60 * 1000;

    return (
        Number.isFinite(expiry) &&
        expiry > now &&
        expiry <= now + maximumLifetime
    );
}

export function LocationSnapshotPanel({
    title,
    snapshot,
}: {
    title: string;
    snapshot: LocationSnapshot | null;
}) {
    const fields = getLocationSnapshotFields(snapshot);

    return (
        <div className="customer-location-card">
            <h4>{title}</h4>

            <dl className="customer-detail-list">
                {fields.map((field) => (
                    <div key={field.label}>
                        <dt>{field.label}</dt>
                        <dd>{field.value}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
