
import './customerPresentation.css';

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
