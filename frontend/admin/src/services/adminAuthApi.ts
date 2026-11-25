import type {
  AdminProfile,
  ApiResponse
} from '../types/admin';

import { adminApi, TOKEN_KEY } from './adminApiClient';

export function saveSession(token: string): void {
  sessionStorage.setItem(TOKEN_KEY, token);
}

export function clearSession(): void {
  sessionStorage.removeItem(TOKEN_KEY);
}

export function hasSession(): boolean {
  return Boolean(sessionStorage.getItem(TOKEN_KEY));
}

export async function login(
  identifier: string,
  password: string
): Promise<AdminProfile> {
  const isEmail = identifier.includes('@');

  const response = await adminApi.post<
    ApiResponse<{
      account: AdminProfile;
      session: { token: string };
    }>
  >('/login', {
    [isEmail ? 'email' : 'phone']: identifier,
    password
  });

  const token = response.data.data.session?.token;

  if (!token) {
    throw new Error('Token sesi tidak diterima daripada server.');
  }

  saveSession(token);

  try {
    return await getCurrentAdmin();
  } catch (error) {
    clearSession();
    throw error;
  }
}

export async function getCurrentAdmin(): Promise<AdminProfile> {
  const response = await adminApi.get<
    ApiResponse<{ account: AdminProfile }>
  >('/me');

  return response.data.data.account;
}

export async function logout(): Promise<void> {
  try {
    await adminApi.post('/logout');
  } finally {
    clearSession();
  }
}
