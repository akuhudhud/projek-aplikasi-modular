
import {
    afterEach,
    describe,
    expect,
    it,
} from 'vitest';

import {
    act,
    cleanup,
    renderHook,
} from '@testing-library/react';

import { useCustomerNavigation } from './useCustomerNavigation';

describe('useCustomerNavigation', () => {
    afterEach(() => {
        cleanup();

        window.history.replaceState(
            {},
            '',
            '/',
        );
    });

    it('starts at dashboard when no runner parameter exists', () => {
        window.history.replaceState({}, '', '/');

        const { result } = renderHook(() =>
            useCustomerNavigation(),
        );

        expect(result.current.activePage).toBe('dashboard');
        expect(result.current.runnerSlug).toBe('');
        expect(result.current.hasInvalidRunnerSlug).toBe(false);
    });

    it('opens create page when a valid runner slug exists', () => {
        window.history.replaceState(
            {},
            '',
            '/?runner=RunnerTuaran',
        );

        const { result } = renderHook(() =>
            useCustomerNavigation(),
        );

        expect(result.current.activePage).toBe('create');
        expect(result.current.runnerSlug).toBe('runnertuaran');
        expect(result.current.hasInvalidRunnerSlug).toBe(false);
    });

    it('trims whitespace and converts runner slug to lowercase', () => {
        window.history.replaceState(
            {},
            '',
            '/?runner=%20RunnerABC%20',
        );

        const { result } = renderHook(() =>
            useCustomerNavigation(),
        );

        expect(result.current.runnerSlug).toBe('runnerabc');
        expect(result.current.activePage).toBe('create');
    });

    it('marks an invalid runner slug and opens create page', () => {
        window.history.replaceState(
            {},
            '',
            '/?runner=ab',
        );

        const { result } = renderHook(() =>
            useCustomerNavigation(),
        );

        expect(result.current.activePage).toBe('create');
        expect(result.current.runnerSlug).toBe('');
        expect(result.current.hasInvalidRunnerSlug).toBe(true);
    });

    it('rejects runner slugs containing unsupported characters', () => {
        window.history.replaceState(
            {},
            '',
            '/?runner=runner%40tuaran',
        );

        const { result } = renderHook(() =>
            useCustomerNavigation(),
        );

        expect(result.current.runnerSlug).toBe('');
        expect(result.current.hasInvalidRunnerSlug).toBe(true);
    });

    it('navigates to the selected customer page', () => {
        window.history.replaceState({}, '', '/');

        const { result } = renderHook(() =>
            useCustomerNavigation(),
        );

        act(() => {
            result.current.navigate('requests');
        });

        expect(result.current.activePage).toBe('requests');
    });

    it('opens the create page through openRunnerRequest', () => {
        window.history.replaceState({}, '', '/');

        const { result } = renderHook(() =>
            useCustomerNavigation(),
        );

        act(() => {
            result.current.openRunnerRequest();
        });

        expect(result.current.activePage).toBe('create');
    });

    it('returns to dashboard through returnHome', () => {
        window.history.replaceState({}, '', '/');

        const { result } = renderHook(() =>
            useCustomerNavigation(),
        );

        act(() => {
            result.current.navigate('profile');
        });

        act(() => {
            result.current.returnHome();
        });

        expect(result.current.activePage).toBe('dashboard');
    });
});
