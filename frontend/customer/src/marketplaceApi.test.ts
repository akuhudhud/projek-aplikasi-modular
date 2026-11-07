import { beforeEach, describe, expect, it, vi } from 'vitest';

const { getMock, postMock } = vi.hoisted(() => ({
    getMock: vi.fn(),
    postMock: vi.fn(),
}));

vi.mock('axios', () => ({
    default: {
        create: vi.fn(() => ({
            get: getMock,
            post: postMock,
        })),
    },
}));

import { customerApi } from './api';

describe('customerApi.createRequest', () => {
    beforeEach(() => {
        postMock.mockReset();
    });

    const payload = {
        task_class: 'NON_DELIVERY' as const,
        description: 'Bantu beli barang di kedai.',
        task_location_snapshot: {
            address: 'Kedai Tuaran',
            contact_name: 'Ali',
            phone: '0123456789',
        },
        expires_at: '2026-11-08T12:00:00.000Z',
    };

    const createdRequest = {
        request_id: 'request-001',
        customer_account_id: 101,
        target_runner_account_id: null,
        source_type: 'marketplace',
        task_class: 'NON_DELIVERY',
        description: payload.description,
        pickup_location_snapshot: null,
        delivery_location_snapshot: null,
        task_location_snapshot: payload.task_location_snapshot,
        status: 'OPEN',
        expires_at: payload.expires_at,
        created_at: '2026-11-07T10:00:00.000Z',
    };

    it('sends the correct payload to the create endpoint', async () => {
        postMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    request: createdRequest,
                },
            },
        });

        await customerApi.createRequest(payload);

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(postMock).toHaveBeenCalledWith(
            '/web/customer/requests',
            payload,
        );
    });

    it('returns the created Open Request', async () => {
        postMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    request: createdRequest,
                },
            },
        });

        const result = await customerApi.createRequest(payload);

        expect(result).toEqual(createdRequest);
    });

    it('propagates API errors', async () => {
        const apiError = new Error('Create request failed.');

        postMock.mockRejectedValue(apiError);

        await expect(
            customerApi.createRequest(payload),
        ).rejects.toBe(apiError);
    });
});

describe('customerApi.getRequests', () => {
    beforeEach(() => {
        getMock.mockReset();
    });

    const responseData = {
        requests: [
            {
                request_id: 'request-001',
                task_class: 'NON_DELIVERY',
                description: 'Bantu beli barang.',
                pickup_location_snapshot: null,
                delivery_location_snapshot: null,
                task_location_snapshot: {
                    address: 'Kedai Tuaran',
                    contact_name: 'Ali',
                    phone: '0123456789',
                },
                status: 'OPEN',
                expires_at: '2026-11-08T12:00:00.000Z',
                awarded_at: null,
                cancelled_at: null,
                created_at: '2026-11-07T10:00:00.000Z',
                updated_at: '2026-11-07T10:00:00.000Z',
            },
        ],
        pagination: {
            current_page: 2,
            per_page: 25,
            total: 26,
            last_page: 2,
        },
    };

    it('requests the correct page from the endpoint', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: responseData,
            },
        });

        await customerApi.getRequests(2);

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock).toHaveBeenCalledWith(
            '/web/customer/requests',
            {
                params: {
                    page: 2,
                },
            },
        );
    });

    it('returns requests and pagination data', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: responseData,
            },
        });

        const result = await customerApi.getRequests(2);

        expect(result).toEqual(responseData);
        expect(result.requests).toHaveLength(1);
        expect(result.pagination.current_page).toBe(2);
        expect(result.pagination.per_page).toBe(25);
        expect(result.pagination.total).toBe(26);
    });

    it('uses page one by default', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: responseData,
            },
        });

        await customerApi.getRequests();

        expect(getMock).toHaveBeenCalledWith(
            '/web/customer/requests',
            {
                params: {
                    page: 1,
                },
            },
        );
    });

    it('propagates API errors', async () => {
        const apiError = new Error('Get requests failed.');

        getMock.mockRejectedValue(apiError);

        await expect(
            customerApi.getRequests(),
        ).rejects.toBe(apiError);
    });
});

describe('customerApi.getTasks', () => {
    beforeEach(() => {
        getMock.mockReset();
    });

    const responseData = {
        tasks: [
            {
                task: {
                    task_id: 'task-001',
                    module_key: 'Marketplace',
                    task_type: 'MARKETPLACE_SERVICE_REQUEST',
                    source_type: 'marketplace_request',
                    source_id: 'request-001',
                    generic_status: 'ASSIGNED',
                    assigned_at: '2026-11-07T11:00:00.000Z',
                    started_at: null,
                    completed_at: null,
                    cancelled_at: null,
                    created_at: '2026-11-07T11:00:00.000Z',
                    updated_at: '2026-11-07T11:00:00.000Z',
                },
                request: {
                    request_id: 'request-001',
                    task_class: 'NON_DELIVERY',
                    description: 'Bantu beli barang.',
                    pickup_location_snapshot: null,
                    delivery_location_snapshot: null,
                    task_location_snapshot: {
                        address: 'Kedai Tuaran',
                        contact_name: 'Ali',
                        phone: '0123456789',
                    },
                    status: 'AWARDED',
                    expires_at: '2026-11-08T12:00:00.000Z',
                    awarded_at: '2026-11-07T11:00:00.000Z',
                    cancelled_at: null,
                },
            },
        ],
        pagination: {
            current_page: 1,
            per_page: 25,
            total: 1,
            last_page: 1,
        },
    };

    it('requests the correct task page', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: responseData,
            },
        });

        await customerApi.getTasks(1);

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock).toHaveBeenCalledWith(
            '/web/customer/tasks',
            {
                params: {
                    page: 1,
                },
            },
        );
    });

    it('returns task records and pagination', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: responseData,
            },
        });

        const result = await customerApi.getTasks(1);

        expect(result).toEqual(responseData);
        expect(result.tasks).toHaveLength(1);
        expect(result.tasks[0].task.task_id).toBe('task-001');
        expect(result.tasks[0].request.request_id).toBe('request-001');
        expect(result.pagination.per_page).toBe(25);
    });

    it('uses page one by default', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: responseData,
            },
        });

        await customerApi.getTasks();

        expect(getMock).toHaveBeenCalledWith(
            '/web/customer/tasks',
            {
                params: {
                    page: 1,
                },
            },
        );
    });

    it('propagates API errors', async () => {
        const apiError = new Error('Get tasks failed.');

        getMock.mockRejectedValue(apiError);

        await expect(
            customerApi.getTasks(),
        ).rejects.toBe(apiError);
    });
});

describe('customerApi.getTask', () => {
    beforeEach(() => {
        getMock.mockReset();
    });

    const taskDetail = {
        task: {
            task_id: 'task-001',
            module_key: 'Marketplace',
            task_type: 'MARKETPLACE_SERVICE_REQUEST',
            source_type: 'marketplace_request',
            source_id: 'request-001',
            generic_status: 'ASSIGNED',
            assigned_at: '2026-11-07T11:00:00.000Z',
            started_at: null,
            completed_at: null,
            cancelled_at: null,
            created_at: '2026-11-07T11:00:00.000Z',
            updated_at: '2026-11-07T11:00:00.000Z',
        },
        request: {
            request_id: 'request-001',
            task_class: 'NON_DELIVERY',
            description: 'Bantu beli barang.',
            pickup_location_snapshot: null,
            delivery_location_snapshot: null,
            task_location_snapshot: {
                address: 'Kedai Tuaran',
                contact_name: 'Ali',
                phone: '0123456789',
            },
            status: 'AWARDED',
            expires_at: '2026-11-08T12:00:00.000Z',
            awarded_at: '2026-11-07T11:00:00.000Z',
            cancelled_at: null,
        },
    };

    it('requests the correct task detail endpoint', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: taskDetail,
            },
        });

        await customerApi.getTask('task-001');

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock).toHaveBeenCalledWith(
            '/web/customer/tasks/task-001',
        );
    });

    it('returns task and request details', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: taskDetail,
            },
        });

        const result = await customerApi.getTask('task-001');

        expect(result).toEqual(taskDetail);
        expect(result.task.task_id).toBe('task-001');
        expect(result.request.request_id).toBe('request-001');
    });

    it('encodes the task ID in the endpoint', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: taskDetail,
            },
        });

        await customerApi.getTask('task/001');

        expect(getMock).toHaveBeenCalledWith(
            '/web/customer/tasks/task%2F001',
        );
    });

    it('propagates API errors', async () => {
        const apiError = new Error('Get task failed.');

        getMock.mockRejectedValue(apiError);

        await expect(
            customerApi.getTask('task-001'),
        ).rejects.toBe(apiError);
    });
});
