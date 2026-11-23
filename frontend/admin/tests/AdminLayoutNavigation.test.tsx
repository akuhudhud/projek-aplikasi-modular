
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor
} from '@testing-library/react';

import AdminLayout from '../src/app/AdminLayout';
import type { AdminPage, AdminProfile } from '../src/types/admin';

const rootAccount: AdminProfile = {
  id: 'root-001',
  profile_name: 'Root Admin',
  verified_name: null,
  phone: '0123456789',
  email: 'root@example.com',
  profile_picture: null,
  role: 'ROOT_SUPER_ADMIN',
  status: 'ACTIVE',
  permissions: []
};

const superAdminAccount: AdminProfile = {
  ...rootAccount,
  id: 'admin-001',
  profile_name: 'Super Admin',
  email: 'admin@example.com',
  role: 'SUPER_ADMIN',
  permissions: [
    'accounts.view',
    'runner_applications.view'
  ]
};

function renderLayout(
  account: AdminProfile = rootAccount,
  activePage: AdminPage = 'dashboard'
) {
  const onNavigate = vi.fn();

  const result = render(
    <AdminLayout
      account={account}
      activePage={activePage}
      onNavigate={onNavigate}
      onLogout={vi.fn()}
    >
      <div>Halaman ujian</div>
      <button id="runner-tab-applications" type="button">
        Tab Permohonan
      </button>
      <button id="runner-tab-directory" type="button">
        Tab Direktori
      </button>
    </AdminLayout>
  );

  return {
    ...result,
    onNavigate
  };
}

beforeEach(() => {
  Object.defineProperty(HTMLElement.prototype, 'scrollIntoView', {
    configurable: true,
    value: vi.fn()
  });
});

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

describe('AdminLayout navigation', () => {
  it('membuka dan menutup sidebar melalui hamburger', () => {
    renderLayout();

    const sidebar = document.getElementById('admin-sidebar');
    const hamburger = document.querySelector(
      '.admin-menu-toggle'
    ) as HTMLButtonElement;

    expect(sidebar?.getAttribute('aria-hidden')).toBe('true');

    fireEvent.click(hamburger);

    expect(sidebar?.getAttribute('aria-hidden')).toBe('false');

    fireEvent.click(hamburger);

    expect(sidebar?.getAttribute('aria-hidden')).toBe('true');
  });

  it('menutup sidebar apabila pengguna menekan kawasan luar', () => {
    renderLayout();

    const hamburger = document.querySelector(
      '.admin-menu-toggle'
    ) as HTMLButtonElement;

    fireEvent.click(hamburger);

    expect(
      document.getElementById('admin-sidebar')?.getAttribute('aria-hidden')
    ).toBe('false');

    const backdrop = document.querySelector(
      '.admin-sidebar-backdrop'
    ) as HTMLButtonElement;

    fireEvent.click(backdrop);

    expect(
      document.getElementById('admin-sidebar')?.getAttribute('aria-hidden')
    ).toBe('true');
  });

  it('membuka submenu tanpa menutup sidebar', () => {
    renderLayout();

    fireEvent.click(
      document.querySelector('.admin-menu-toggle') as HTMLButtonElement
    );

    const parent = screen.getByRole('button', {
      name: /Pengurusan Pengguna/
    });

    expect(parent.getAttribute('aria-expanded')).toBe('false');

    fireEvent.click(parent);

    expect(parent.getAttribute('aria-expanded')).toBe('true');
    expect(
      screen.getByRole('button', { name: 'Senarai Akaun' })
    ).toBeTruthy();

    expect(
      document.getElementById('admin-sidebar')?.getAttribute('aria-hidden')
    ).toBe('false');
  });

  it('memaparkan Pengurusan Super Admin kepada Root sahaja', () => {
    const rootView = renderLayout(rootAccount);

    fireEvent.click(
      document.querySelector('.admin-menu-toggle') as HTMLButtonElement
    );

    fireEvent.click(
      screen.getByRole('button', {
        name: /Pengurusan Pengguna/
      })
    );

    expect(
      screen.getByRole('button', {
        name: 'Pengurusan Super Admin'
      })
    ).toBeTruthy();

    rootView.unmount();

    renderLayout(superAdminAccount);

    fireEvent.click(
      document.querySelector('.admin-menu-toggle') as HTMLButtonElement
    );

    fireEvent.click(
      screen.getByRole('button', {
        name: /Pengurusan Pengguna/
      })
    );

    expect(
      screen.queryByRole('button', {
        name: 'Pengurusan Super Admin'
      })
    ).toBeNull();

    expect(
      screen.getByRole('button', {
        name: 'Senarai Akaun'
      })
    ).toBeTruthy();
  });

  it('membuka tab Direktori Runner dan menutup sidebar', async () => {
    const { onNavigate } = renderLayout(
      rootAccount,
      'runner-applications'
    );

    const directoryTab = document.getElementById(
      'runner-tab-directory'
    ) as HTMLButtonElement;

    const clickSpy = vi.spyOn(directoryTab, 'click');

    fireEvent.click(
      document.querySelector('.admin-menu-toggle') as HTMLButtonElement
    );

    fireEvent.click(
      screen.getByRole('button', {
        name: 'Direktori Runner'
      })
    );

    expect(onNavigate).toHaveBeenCalledWith('runner-applications');

    await waitFor(() => {
      expect(clickSpy).toHaveBeenCalled();
    });

    expect(
      document.getElementById('admin-sidebar')?.getAttribute('aria-hidden')
    ).toBe('true');
  });

  it('mengarahkan Root ke bahagian Pengurusan Super Admin', async () => {
    const { onNavigate } = renderLayout(rootAccount, 'accounts');

    const target = document.createElement('div');
    target.id = 'super-admin-management';
    document.body.appendChild(target);

    const scrollSpy = vi.spyOn(target, 'scrollIntoView');

    fireEvent.click(
      document.querySelector('.admin-menu-toggle') as HTMLButtonElement
    );

    fireEvent.click(
      screen.getByRole('button', {
        name: /Pengurusan Pengguna/
      })
    );

    fireEvent.click(
      screen.getByRole('button', {
        name: 'Pengurusan Super Admin'
      })
    );

    expect(onNavigate).toHaveBeenCalledWith('accounts');

    await waitFor(() => {
      expect(scrollSpy).toHaveBeenCalled();
    });

    target.remove();
  });

  it('menutup sidebar apabila Escape ditekan', () => {
    renderLayout();

    fireEvent.click(
      document.querySelector('.admin-menu-toggle') as HTMLButtonElement
    );

    fireEvent.keyDown(window, { key: 'Escape' });

    expect(
      document.getElementById('admin-sidebar')?.getAttribute('aria-hidden')
    ).toBe('true');
  });
});
