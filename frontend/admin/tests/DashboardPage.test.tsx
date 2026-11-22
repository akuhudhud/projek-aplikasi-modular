
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  cleanup,
  render,
  screen
} from '@testing-library/react';

import DashboardPage from '../src/features/dashboard/DashboardPage';
import * as adminApi from '../src/services/adminApi';

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const dashboardData = {
  generated_at: '2026-11-21T10:00:00.000Z',
  accounts: {
    total_users: 120,
    active_users: 110,
    suspended_users: 10
  },
  runners: {
    total: 25,
    statuses: {
      PENDING: 4,
      APPROVED: 18,
      REJECTED: 2,
      SUSPENDED: 1
    }
  },
  runner_applications: {
    pending: 4,
    can_view_recent: true,
    recent: [
      {
        id: 'runner-001',
        profile_name: 'Runner Test',
        phone: '60123456789',
        email: 'runner@example.com',
        created_at: '2026-11-20T10:00:00.000Z',
        updated_at: '2026-11-20T10:00:00.000Z'
      }
    ]
  },
  tasks: {
    total: 80,
    statuses: {
      OPEN: 10,
      ASSIGNED: 20,
      IN_PROGRESS: 5,
      COMPLETED: 40,
      CANCELLED: 5
    },
    daily_activity: [
      { date: '2026-11-15', created: 5, completed: 2 },
      { date: '2026-11-16', created: 4, completed: 3 },
      { date: '2026-11-17', created: 8, completed: 5 },
      { date: '2026-11-18', created: 6, completed: 4 },
      { date: '2026-11-19', created: 7, completed: 6 },
      { date: '2026-11-20', created: 3, completed: 4 },
      { date: '2026-11-21', created: 2, completed: 3 }
    ]
  }
};

describe('DashboardPage', () => {
  it('memaparkan statistik dan permohonan apabila mempunyai kebenaran', async () => {
    vi.spyOn(adminApi, 'getAdminDashboard')
      .mockResolvedValue(dashboardData);

    render(<DashboardPage />);

    expect(
      await screen.findByText('Jumlah Pengguna')
    ).toBeTruthy();

    expect(screen.getByText('Runner Test')).toBeTruthy();
    expect(screen.getByText('Status Tugasan')).toBeTruthy();
    expect(screen.getByText('Status Runner')).toBeTruthy();
    expect(screen.getByText('120')).toBeTruthy();
    expect(screen.getByText('Jumlah: 80')).toBeTruthy();
  });

  it('menyediakan ringkasan data carta untuk pembaca skrin', async () => {
    vi.spyOn(adminApi, 'getAdminDashboard')
      .mockResolvedValue(dashboardData);

    render(<DashboardPage />);

    const chart = await screen.findByRole('img', {
      name: /Aktiviti tugasan tujuh hari/
    });

    expect(chart.getAttribute('aria-label')).toContain(
      '2026-11-15: 5 dicipta, 2 selesai'
    );

    expect(chart.getAttribute('aria-label')).toContain(
      '2026-11-21: 2 dicipta, 3 selesai'
    );
  });

  it('tidak memaparkan butiran pemohon tanpa kebenaran', async () => {
    vi.spyOn(adminApi, 'getAdminDashboard')
      .mockResolvedValue({
        ...dashboardData,
        runner_applications: {
          pending: 4,
          can_view_recent: false,
          recent: []
        }
      });

    render(<DashboardPage />);

    expect(
      await screen.findByText('Jumlah Pengguna')
    ).toBeTruthy();

    expect(
      screen.queryByText('Permohonan Runner Terkini')
    ).toBeNull();

    expect(screen.queryByText('Runner Test')).toBeNull();
    expect(screen.getByText('Permohonan Menunggu')).toBeTruthy();
  });

  it('memaparkan ralat apabila API gagal', async () => {
    vi.spyOn(adminApi, 'getAdminDashboard')
      .mockRejectedValue(new Error('Dashboard API failed'));

    render(<DashboardPage />);

    expect(
      await screen.findByRole('alert')
    ).toBeTruthy();

    expect(
      screen.getByText('Dashboard API failed')
    ).toBeTruthy();
  });

  it('memaparkan keadaan kosong apabila tiada permohonan yang boleh dilihat', async () => {
    vi.spyOn(adminApi, 'getAdminDashboard')
      .mockResolvedValue({
        ...dashboardData,
        runner_applications: {
          pending: 0,
          can_view_recent: true,
          recent: []
        }
      });

    render(<DashboardPage />);

    expect(
      await screen.findByText(
        'Tiada permohonan Runner yang menunggu tindakan.'
      )
    ).toBeTruthy();
  });
});
