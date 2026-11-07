import { describe, expect, it } from 'vitest';

import {
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
