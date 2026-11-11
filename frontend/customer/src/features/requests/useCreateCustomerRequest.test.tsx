
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

import {
    act,
    cleanup,
    renderHook,
} from '@testing-library/react';

const { createRequestMock } = vi.hoisted(() => ({
    createRequestMock: vi.fn(),
}));

vi.mock('axios', () => ({
    default: {
        isAxiosError: vi.fn(() => false),
    },
}));

vi.mock('../../api', () => ({
    customerApi: {
        createRequest: createRequestMock,
    },
}));

import { useCreateCustomerRequest } from './useCreateCustomerRequest';

function futureExpiry(minutes = 60): string {
    const date = new Date(Date.now() + minutes * 60 * 1000);
    const offset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - offset)
        .toISOString()
        .slice(0, 16);
}

function submitEvent() {
    return {
        preventDefault: vi.fn(),
    } as unknown as React.FormEvent<HTMLFormElement>;
}

describe('useCreateCustomerRequest', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        createRequestMock.mockResolvedValue({
            request_id: 'request-test-id',
        });
    });

    afterEach(() => {
        cleanup();
    });

    it('rejects an invalid Runner profile link before API submission', async () => {
        const { result } = renderHook(() =>
            useCreateCustomerRequest({
                runnerSlug: '',
                hasInvalidRunnerSlug: true,
            }),
        );

        await act(async () => {
            await result.current.submit(submitEvent());
        });

        expect(createRequestMock).not.toHaveBeenCalled();
        expect(result.current.error).toBe(
            'Pautan profil Runner tidak sah. Sila buka semula pautan yang diberikan oleh Runner.',
        );
    });

    it('rejects an expired request before API submission', async () => {
        const { result } = renderHook(() =>
            useCreateCustomerRequest({
                runnerSlug: '',
                hasInvalidRunnerSlug: false,
            }),
        );

        act(() => {
            result.current.setExpiresAt(
                new Date(Date.now() - 60 * 1000)
                    .toISOString()
                    .slice(0, 16),
            );
        });

        await act(async () => {
            await result.current.submit(submitEvent());
        });

        expect(createRequestMock).not.toHaveBeenCalled();
        expect(result.current.error).toBe(
            'Tarikh tamat mesti selepas masa sekarang dan tidak melebihi 24 jam.',
        );
    });

    it('rejects a request expiry exceeding the maximum lifetime', async () => {
        const { result } = renderHook(() =>
            useCreateCustomerRequest({
                runnerSlug: '',
                hasInvalidRunnerSlug: false,
            }),
        );

        act(() => {
            result.current.setExpiresAt(futureExpiry(24 * 60));
        });

        await act(async () => {
            await result.current.submit(submitEvent());
        });

        expect(createRequestMock).not.toHaveBeenCalled();
        expect(result.current.error).toBe(
            'Tarikh tamat mesti selepas masa sekarang dan tidak melebihi 24 jam.',
        );
    });

    it('creates PICKUP_DELIVERY with pickup and delivery snapshots', async () => {
        const onCreated = vi.fn();

        const { result } = renderHook(() =>
            useCreateCustomerRequest({
                runnerSlug: '',
                hasInvalidRunnerSlug: false,
                onCreated,
            }),
        );

        act(() => {
            result.current.setDescription(
                '  Hantar parcel kepada pelanggan.  ',
            );
            result.current.setPickupAddress('  Pusat Pos Tuaran  ');
            result.current.setPickupContact('  Ali  ');
            result.current.setPickupPhone('  0123456789  ');
            result.current.setDeliveryAddress('  Kampung Tambalang  ');
            result.current.setDeliveryContact('  Abu  ');
            result.current.setDeliveryPhone('  0198765432  ');
            result.current.setExpiresAt(futureExpiry());
        });

        await act(async () => {
            await result.current.submit(submitEvent());
        });

        expect(createRequestMock).toHaveBeenCalledTimes(1);
        expect(createRequestMock).toHaveBeenCalledWith(
            expect.objectContaining({
                source_type: 'marketplace',
                task_class: 'PICKUP_DELIVERY',
                description: 'Hantar parcel kepada pelanggan.',
                pickup_location_snapshot: {
                    address: 'Pusat Pos Tuaran',
                    contact_name: 'Ali',
                    phone: '0123456789',
                },
                delivery_location_snapshot: {
                    address: 'Kampung Tambalang',
                    contact_name: 'Abu',
                    phone: '0198765432',
                },
            }),
        );

        const payload = createRequestMock.mock.calls[0][0];

        expect(payload).not.toHaveProperty('task_location_snapshot');
        expect(onCreated).toHaveBeenCalledTimes(1);
        expect(result.current.success).toBe(
            'Permintaan tugasan berjaya dihantar.',
        );
    });

    it('creates NON_DELIVERY with only task location snapshot', async () => {
        const { result } = renderHook(() =>
            useCreateCustomerRequest({
                runnerSlug: '',
                hasInvalidRunnerSlug: false,
            }),
        );

        act(() => {
            result.current.setTaskClass('NON_DELIVERY');
            result.current.setDescription('Bantu membeli barang.');
            result.current.setTaskAddress('Pekan Tuaran');
            result.current.setTaskContact('Ali');
            result.current.setTaskPhone('0123456789');
            result.current.setExpiresAt(futureExpiry());
        });

        await act(async () => {
            await result.current.submit(submitEvent());
        });

        expect(createRequestMock).toHaveBeenCalledWith(
            expect.objectContaining({
                source_type: 'marketplace',
                task_class: 'NON_DELIVERY',
                description: 'Bantu membeli barang.',
                task_location_snapshot: {
                    address: 'Pekan Tuaran',
                    contact_name: 'Ali',
                    phone: '0123456789',
                },
            }),
        );

        const payload = createRequestMock.mock.calls[0][0];

        expect(payload).not.toHaveProperty('pickup_location_snapshot');
        expect(payload).not.toHaveProperty('delivery_location_snapshot');
    });

    it('targets the selected Runner when creating from a profile link', async () => {
        const { result } = renderHook(() =>
            useCreateCustomerRequest({
                runnerSlug: 'test-runner',
                hasInvalidRunnerSlug: false,
            }),
        );

        act(() => {
            result.current.setDescription('Bantu urus tugasan.');
            result.current.setTaskAddress('Pekan Tuaran');
            result.current.setTaskClass('NON_DELIVERY');
            result.current.setExpiresAt(futureExpiry());
        });

        await act(async () => {
            await result.current.submit(submitEvent());
        });

        expect(createRequestMock).toHaveBeenCalledWith(
            expect.objectContaining({
                source_type: 'runner_profile_link',
                target_runner_slug: 'test-runner',
                task_class: 'NON_DELIVERY',
            }),
        );
    });

    it('resets the form after successful submission', async () => {
        const { result } = renderHook(() =>
            useCreateCustomerRequest({
                runnerSlug: '',
                hasInvalidRunnerSlug: false,
            }),
        );

        act(() => {
            result.current.setDescription('Permintaan ujian.');
            result.current.setTaskClass('NON_DELIVERY');
            result.current.setTaskAddress('Pekan Tuaran');
            result.current.setExpiresAt(futureExpiry());
        });

        await act(async () => {
            await result.current.submit(submitEvent());
        });

        expect(result.current.taskClass).toBe('PICKUP_DELIVERY');
        expect(result.current.description).toBe('');
        expect(result.current.taskAddress).toBe('');
        expect(result.current.pickupAddress).toBe('');
        expect(result.current.deliveryAddress).toBe('');
        expect(result.current.loading).toBe(false);
    });
});
