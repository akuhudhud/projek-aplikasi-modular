
// @vitest-environment jsdom

import {
    act,
    cleanup,
    renderHook,
    waitFor,
} from '@testing-library/react';

import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

const {
    meMock,
    logoutMock,
} = vi.hoisted(() => ({
    meMock: vi.fn(),
    logoutMock: vi.fn(),
}));

vi.mock('../../api', () => ({
    customerApi: {
        me: meMock,
        logout: logoutMock,
    },
}));

import { useCustomerSession } from './useCustomerSession';

const account = {
    id: 101,
    name: 'Customer Test',
    phone: '60123456789',
    email: 'customer@example.com',
    profile_picture: null,
    role: 'customer',
    status: 'active',
};

describe('useCustomerSession', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        cleanup();
    });

    it('restores an existing authenticated session', async () => {
        meMock.mockResolvedValue(account);

        const { result } = renderHook(
            () => useCustomerSession(),
        );

        expect(result.current.checkingSession).toBe(true);
        expect(result.current.account).toBeNull();

        await waitFor(() => {
            expect(result.current.checkingSession).toBe(false);
        });

        expect(result.current.account).toEqual(account);
        expect(meMock).toHaveBeenCalledTimes(1);
    });

    it('returns to unauthenticated state when session restoration fails', async () => {
        meMock.mockRejectedValue(
            new Error('Unauthenticated'),
        );

        const { result } = renderHook(
            () => useCustomerSession(),
        );

        await waitFor(() => {
            expect(result.current.checkingSession).toBe(false);
        });

        expect(result.current.account).toBeNull();
        expect(meMock).toHaveBeenCalledTimes(1);
    });

    it('clears the authenticated account after successful logout', async () => {
        meMock.mockResolvedValue(account);
        logoutMock.mockResolvedValue(undefined);

        const { result } = renderHook(
            () => useCustomerSession(),
        );

        await waitFor(() => {
            expect(result.current.account).toEqual(account);
        });

        let logoutResult = false;

        await act(async () => {
            logoutResult = await result.current.logout();
        });

        expect(logoutResult).toBe(true);
        expect(logoutMock).toHaveBeenCalledTimes(1);
        expect(result.current.account).toBeNull();
        expect(result.current.logoutError).toBe('');
        expect(result.current.logoutLoading).toBe(false);
    });

    it('keeps the account active when logout fails', async () => {
        meMock.mockResolvedValue(account);
        logoutMock.mockRejectedValue(
            new Error('Logout failed'),
        );

        const { result } = renderHook(
            () => useCustomerSession(),
        );

        await waitFor(() => {
            expect(result.current.account).toEqual(account);
        });

        let logoutResult = true;

        await act(async () => {
            logoutResult = await result.current.logout();
        });

        expect(logoutResult).toBe(false);
        expect(result.current.account).toEqual(account);
        expect(result.current.logoutError).toContain(
            'Tidak berjaya logout.',
        );
        expect(result.current.logoutLoading).toBe(false);
    });
});
