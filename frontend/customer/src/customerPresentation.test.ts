
import { describe, expect, it } from 'vitest';

import {
    formatCustomerRequestStatus,
    formatCustomerTaskStatus,
    getLocationSnapshotFields,
    isRequestExpiryValid,
} from './customerPresentation';

function toLocalDateTimeInput(timestamp: number): string {
    const date = new Date(timestamp);
    const offset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - offset)
        .toISOString()
        .slice(0, 16);
}

describe('getLocationSnapshotFields', () => {
    it('returns readable address and contact fields', () => {
        expect(
            getLocationSnapshotFields({
                address: 'Kedai Tuaran',
                contact_name: 'Ali',
                phone: '0123456789',
            }),
        ).toEqual([
            { label: 'Alamat', value: 'Kedai Tuaran' },
            { label: 'Nama Orang Dihubungi', value: 'Ali' },
            { label: 'Nombor Telefon', value: '0123456789' },
        ]);
    });

    it('trims values and handles missing snapshot values', () => {
        expect(
            getLocationSnapshotFields({
                address: '  Jalan Tuaran  ',
                contact_name: null,
                phone: '',
            }),
        ).toEqual([
            { label: 'Alamat', value: 'Jalan Tuaran' },
            {
                label: 'Nama Orang Dihubungi',
                value: 'Tidak dinyatakan',
            },
            {
                label: 'Nombor Telefon',
                value: 'Tidak dinyatakan',
            },
        ]);
    });

    it('handles a null snapshot', () => {
        expect(getLocationSnapshotFields(null)).toEqual([
            { label: 'Alamat', value: 'Tidak dinyatakan' },
            {
                label: 'Nama Orang Dihubungi',
                value: 'Tidak dinyatakan',
            },
            {
                label: 'Nombor Telefon',
                value: 'Tidak dinyatakan',
            },
        ]);
    });
});

describe('isRequestExpiryValid', () => {
    const now = new Date('2026-11-07T10:00:00.000Z').getTime();

    it('accepts a future expiry within the safe 24-hour limit', () => {
        const value = toLocalDateTimeInput(
            now + 12 * 60 * 60 * 1000,
        );

        expect(isRequestExpiryValid(value, now)).toBe(true);
    });

    it('rejects an expiry in the past', () => {
        const value = toLocalDateTimeInput(
            now - 60 * 1000,
        );

        expect(isRequestExpiryValid(value, now)).toBe(false);
    });

    it('rejects an expiry beyond the safe 24-hour limit', () => {
        const value = toLocalDateTimeInput(
            now + 24 * 60 * 60 * 1000,
        );

        expect(isRequestExpiryValid(value, now)).toBe(false);
    });

    it('rejects an invalid date value', () => {
        expect(isRequestExpiryValid('not-a-date', now)).toBe(false);
    });
});

describe('customer status labels', () => {
    it('formats known task statuses in Bahasa Malaysia', () => {
        expect(formatCustomerTaskStatus('OPEN')).toBe('Menunggu Runner');
        expect(formatCustomerTaskStatus('ASSIGNED')).toBe('Runner ditugaskan');
        expect(formatCustomerTaskStatus('IN_PROGRESS')).toBe('Sedang dilaksanakan');
        expect(formatCustomerTaskStatus('COMPLETED')).toBe('Selesai');
        expect(formatCustomerTaskStatus('CANCELLED')).toBe('Dibatalkan');
    });

    it('uses a safe fallback for unknown task statuses', () => {
        expect(formatCustomerTaskStatus('FUTURE_STATUS')).toBe(
            'Status tugasan dikemas kini',
        );
    });

    it('formats known request statuses in Bahasa Malaysia', () => {
        expect(formatCustomerRequestStatus('OPEN')).toBe('Menunggu sebut harga');
        expect(formatCustomerRequestStatus('AWARDED')).toBe('Sebut harga dipilih');
        expect(formatCustomerRequestStatus('CANCELLED')).toBe('Dibatalkan');
        expect(formatCustomerRequestStatus('EXPIRED')).toBe('Tamat tempoh');
    });

    it('uses a safe fallback for unknown request statuses', () => {
        expect(formatCustomerRequestStatus('FUTURE_STATUS')).toBe(
            'Status permintaan dikemas kini',
        );
    });
});
