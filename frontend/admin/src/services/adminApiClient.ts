import axios from 'axios';

export const TOKEN_KEY = 'super_runner_admin_session';

export const adminApi = axios.create({
  baseURL: '/api',
  headers: {
    Accept: 'application/json'
  }
});

adminApi.interceptors.request.use((config) => {
  const token = sessionStorage.getItem(TOKEN_KEY);

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

export function isUnauthorizedError(error: unknown): boolean {
  return axios.isAxiosError(error) && error.response?.status === 401;
}

export function getApiError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    return (
      error.response?.data?.message ||
      'Permintaan tidak berjaya. Sila cuba lagi.'
    );
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return 'Ralat tidak dijangka berlaku.';
}
