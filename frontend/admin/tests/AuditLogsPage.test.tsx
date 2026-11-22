
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor
} from '@testing-library/react';

import AuditLogsPage from '../src/features/audit-logs/AuditLogsPage';
import * as adminApi from '../src/services/adminApi';

import type {
  AuditLog,
  AuditLogResult
} from '../src/types/admin';

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const firstLog: AuditLog = {
  id: 'audit-001',
  actor_account_id: 'admin-001',
  target_account_id: 'user-001',
  action: 'ACCOUNT_SUSPENDED',
  reason: 'Pelanggaran polisi.',
  created_at: '2026-11-22T08:00:00.000Z'
};

const secondLog: AuditLog = {
  id: 'audit-002',
  actor_account_id: 'admin-002',
  target_account_id: null,
  action: 'IDENTITY_VERIFIED',
  reason: null,
  created_at: '2026-11-22T09:00:00.000Z'
};

function mockResult(
  logs: AuditLog[],
  page = 1,
  lastPage = 1,
  total = logs.length
): AuditLogResult {
  return {
    audit_logs: logs,
    pagination: {
      current_page: page,
      last_page: lastPage,
      per_page: 25,
      total,
      from: logs.length > 0 ? ((page - 1) * 25) + 1 : null,
      to: logs.length > 0 ? ((page - 1) * 25) + logs.length : null
    }
  };
}

describe('AuditLogsPage', () => {
  it('memaparkan rekod audit daripada API', async () => {
    const getLogs = vi.spyOn(
      adminApi,
      'getAuditLogs'
    ).mockResolvedValue(
      mockResult([firstLog, secondLog], 1, 2, 30)
    );

    render(<AuditLogsPage />);

    expect(
      await screen.findByText('ACCOUNT_SUSPENDED')
    ).toBeTruthy();

    expect(screen.getByText('IDENTITY_VERIFIED')).toBeTruthy();
    expect(screen.getByText('admin-001')).toBeTruthy();
    expect(screen.getByText('user-001')).toBeTruthy();
    expect(screen.getByText('Pelanggaran polisi.')).toBeTruthy();

    expect(
      screen.getByText('Memaparkan 1–2 daripada 30 rekod audit.')
    ).toBeTruthy();

    expect(getLogs).toHaveBeenCalledWith({
      page: 1,
      per_page: 25
    });
  });

  it('memaparkan keadaan kosong apabila tiada rekod', async () => {
    vi.spyOn(adminApi, 'getAuditLogs')
      .mockResolvedValue(mockResult([]));

    render(<AuditLogsPage />);

    expect(
      await screen.findByText('Tiada rekod audit diterima.')
    ).toBeTruthy();
  });

  it('memaparkan butang pagination mengikut halaman', async () => {
    vi.spyOn(adminApi, 'getAuditLogs')
      .mockResolvedValue(
        mockResult([firstLog], 1, 3, 60)
      );

    render(<AuditLogsPage />);

    await screen.findByText('ACCOUNT_SUSPENDED');

    expect(
      screen.getByText('Halaman 1 daripada 3')
    ).toBeTruthy();

    expect(
      screen.getByRole('button', { name: 'Sebelum' })
    ).toHaveProperty('disabled', true);

    expect(
      screen.getByRole('button', { name: 'Seterusnya' })
    ).toHaveProperty('disabled', false);
  });

  it('memuatkan halaman seterusnya apabila ditekan', async () => {
    const getLogs = vi.spyOn(
      adminApi,
      'getAuditLogs'
    );

    getLogs
      .mockResolvedValueOnce(
        mockResult([firstLog], 1, 2, 30)
      )
      .mockResolvedValueOnce(
        mockResult([secondLog], 2, 2, 30)
      );

    render(<AuditLogsPage />);

    await screen.findByText('ACCOUNT_SUSPENDED');

    fireEvent.click(
      screen.getByRole('button', { name: 'Seterusnya' })
    );

    expect(
      await screen.findByText('IDENTITY_VERIFIED')
    ).toBeTruthy();

    expect(
      screen.getByText('Halaman 2 daripada 2')
    ).toBeTruthy();

    expect(getLogs).toHaveBeenLastCalledWith({
      page: 2,
      per_page: 25
    });

    expect(
      screen.getByRole('button', { name: 'Seterusnya' })
    ).toHaveProperty('disabled', true);
  });

  it('memuatkan halaman sebelumnya apabila ditekan', async () => {
    const getLogs = vi.spyOn(
      adminApi,
      'getAuditLogs'
    );

    getLogs
      .mockResolvedValueOnce(
        mockResult([secondLog], 2, 2, 30)
      )
      .mockResolvedValueOnce(
        mockResult([firstLog], 1, 2, 30)
      );

    render(<AuditLogsPage />);

    await screen.findByText('IDENTITY_VERIFIED');

    fireEvent.click(
      screen.getByRole('button', { name: 'Sebelum' })
    );

    expect(
      await screen.findByText('ACCOUNT_SUSPENDED')
    ).toBeTruthy();

    expect(getLogs).toHaveBeenLastCalledWith({
      page: 1,
      per_page: 25
    });
  });

  it('memuat semula rekod audit pada halaman semasa', async () => {
    const getLogs = vi.spyOn(
      adminApi,
      'getAuditLogs'
    );

    getLogs
      .mockResolvedValueOnce(
        mockResult([firstLog], 1, 2, 30)
      )
      .mockResolvedValueOnce(
        mockResult([secondLog], 2, 2, 30)
      )
      .mockResolvedValueOnce(
        mockResult([secondLog], 2, 2, 30)
      );

    render(<AuditLogsPage />);

    await screen.findByText('ACCOUNT_SUSPENDED');

    fireEvent.click(
      screen.getByRole('button', { name: 'Seterusnya' })
    );

    await screen.findByText('IDENTITY_VERIFIED');

    fireEvent.click(
      screen.getByRole('button', { name: 'Muat Semula' })
    );

    await waitFor(() => {
      expect(getLogs).toHaveBeenLastCalledWith({
        page: 2,
        per_page: 25
      });
    });
  });

  it('memaparkan ralat apabila API gagal', async () => {
    vi.spyOn(adminApi, 'getAuditLogs')
      .mockRejectedValue(
        new Error('Audit log API failed')
      );

    render(<AuditLogsPage />);

    expect(
      await screen.findByRole('alert')
    ).toBeTruthy();

    expect(
      screen.getByText('Audit log API failed')
    ).toBeTruthy();
  });
});
