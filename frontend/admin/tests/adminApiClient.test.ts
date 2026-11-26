
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { AxiosAdapter } from 'axios';

import {
  adminApi,
  SESSION_EXPIRED_EVENT,
  TOKEN_KEY
} from '../src/services/adminApiClient';

const originalAdapter = adminApi.defaults.adapter;

afterEach(() => {
  adminApi.defaults.adapter = originalAdapter;
  sessionStorage.clear();
  vi.restoreAllMocks();
});

function createErrorAdapter(status: number): AxiosAdapter {
  return async (config) => {
    return Promise.reject({
      isAxiosError: true,
      config,
      response: {
        status,
        statusText: status === 401 ? 'Unauthorized' : 'Forbidden',
        data: {
          message: status === 401
            ? 'Unauthenticated.'
            : 'Access denied.'
        },
        headers: {},
        config
      }
    });
  };
}

describe('Admin API session handling', () => {
  it('memadam token dan menghantar event apabila API memulangkan 401', async () => {
    sessionStorage.setItem(TOKEN_KEY, 'expired-token');

    const sessionExpired = vi.fn();

    window.addEventListener(
      SESSION_EXPIRED_EVENT,
      sessionExpired
    );

    adminApi.defaults.adapter = createErrorAdapter(401);

    await expect(
      adminApi.get('/admin/accounts')
    ).rejects.toBeTruthy();

    expect(sessionStorage.getItem(TOKEN_KEY)).toBeNull();
    expect(sessionExpired).toHaveBeenCalledTimes(1);

    window.removeEventListener(
      SESSION_EXPIRED_EVENT,
      sessionExpired
    );
  });

  it('tidak menghantar event tamat sesi untuk permintaan login 401', async () => {
    sessionStorage.setItem(TOKEN_KEY, 'existing-token');

    const sessionExpired = vi.fn();

    window.addEventListener(
      SESSION_EXPIRED_EVENT,
      sessionExpired
    );

    adminApi.defaults.adapter = createErrorAdapter(401);

    await expect(
      adminApi.post('/login', {
        email: 'admin@example.com',
        password: 'invalid'
      })
    ).rejects.toBeTruthy();

    expect(sessionStorage.getItem(TOKEN_KEY)).toBe('existing-token');
    expect(sessionExpired).not.toHaveBeenCalled();

    window.removeEventListener(
      SESSION_EXPIRED_EVENT,
      sessionExpired
    );
  });

  it('tidak menghantar event tamat sesi untuk permintaan logout 401', async () => {
    sessionStorage.setItem(TOKEN_KEY, 'existing-token');

    const sessionExpired = vi.fn();

    window.addEventListener(
      SESSION_EXPIRED_EVENT,
      sessionExpired
    );

    adminApi.defaults.adapter = createErrorAdapter(401);

    await expect(
      adminApi.post('/logout')
    ).rejects.toBeTruthy();

    expect(sessionStorage.getItem(TOKEN_KEY)).toBe('existing-token');
    expect(sessionExpired).not.toHaveBeenCalled();

    window.removeEventListener(
      SESSION_EXPIRED_EVENT,
      sessionExpired
    );
  });

  it('mengekalkan token apabila API memulangkan 403', async () => {
    sessionStorage.setItem(TOKEN_KEY, 'valid-token');

    const sessionExpired = vi.fn();

    window.addEventListener(
      SESSION_EXPIRED_EVENT,
      sessionExpired
    );

    adminApi.defaults.adapter = createErrorAdapter(403);

    await expect(
      adminApi.get('/admin/accounts')
    ).rejects.toBeTruthy();

    expect(sessionStorage.getItem(TOKEN_KEY)).toBe('valid-token');
    expect(sessionExpired).not.toHaveBeenCalled();

    window.removeEventListener(
      SESSION_EXPIRED_EVENT,
      sessionExpired
    );
  });
});
