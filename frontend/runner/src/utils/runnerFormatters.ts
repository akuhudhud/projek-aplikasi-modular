
import type {
    RunnerMarketplaceRequest,
    RunnerQuotation,
} from '../api';

export function formatDate(value: string | null): string {
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

export function formatAmount(
    amount: number,
    currency: string,
): string {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency,
    }).format(amount);
}

export function taskStatusLabel(status: string): string {
    const labels: Record<string, string> = {
        ASSIGNED: 'DITUGASKAN',
        IN_PROGRESS: 'SEDANG BERJALAN',
        COMPLETED: 'SELESAI',
        CANCELLED: 'DIBATALKAN',
        OPEN: 'TERBUKA',
    };

    return labels[status] ?? status;
}

export function requestStatusLabel(status: string): string {
    const labels: Record<string, string> = {
        OPEN: 'TERBUKA',
        AWARDED: 'DITERIMA',
        CANCELLED: 'DIBATALKAN',
        EXPIRED: 'TAMAT TEMPOH',
    };

    return labels[status] ?? status;
}

export function quotationStatusLabel(status: string): string {
    const labels: Record<string, string> = {
        SUBMITTED: 'SEBUT HARGA DIHANTAR',
        ACCEPTED: 'SEBUT HARGA DITERIMA',
        NOT_SELECTED: 'TIDAK DIPILIH',
        WITHDRAWN: 'DITARIK BALIK',
        EXPIRED: 'TAMAT TEMPOH',
    };

    return labels[status] ?? status;
}

export function getLatestQuotation(
    quotations: RunnerQuotation[],
): RunnerQuotation | null {
    if (quotations.length === 0) {
        return null;
    }

    return [...quotations].sort(
        (a, b) =>
            new Date(b.created_at).getTime()
            - new Date(a.created_at).getTime(),
    )[0];
}

export function getRequestLocation(
    request: RunnerMarketplaceRequest,
): string {
    if (request.task_class === 'PICKUP_DELIVERY') {
        return [
            request.pickup_location_snapshot?.address,
            request.delivery_location_snapshot?.address,
        ]
            .filter(Boolean)
            .join(' → ');
    }

    return request.task_location_snapshot?.address
        ?? 'Lokasi tidak dinyatakan';
}
