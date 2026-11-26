
import axios from 'axios';

export const TOKEN_KEY = 'super_runner_admin_session';
export const SESSION_EXPIRED_EVENT = 'super-runner:session-expired';

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

adminApi.interceptors.response.use(
  (response) => response,
  (error: unknown) => {
    if (
      axios.isAxiosError(error) &&
      error.response?.status === 401
    ) {
      const requestUrl = error.config?.url ?? '';

      const isLoginRequest = requestUrl.endsWith('/login');
      const isLogoutRequest = requestUrl.endsWith('/logout');

      if (!isLoginRequest && !isLogoutRequest) {
        sessionStorage.removeItem(TOKEN_KEY);

        if (typeof window !== 'undefined') {
          window.dispatchEvent(
            new Event(SESSION_EXPIRED_EVENT)
          );
        }
      }
    }

    return Promise.reject(error);
  }
);

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
