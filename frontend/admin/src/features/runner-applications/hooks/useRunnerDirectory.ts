import { useCallback, useEffect, useRef, useState } from 'react';

import {
  getApiError,
  getRunnerDirectory
} from '../../../services/adminApi';

import type { RunnerDirectoryItem } from '../../../types/admin';

interface UseRunnerDirectoryOptions {
  enabled?: boolean;
  perPage?: number;
}

export default function useRunnerDirectory({
  enabled = false,
  perPage = 15
}: UseRunnerDirectoryOptions = {}) {
  const [runners, setRunners] = useState<RunnerDirectoryItem[]>([]);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const mountedRef = useRef(false);
  const requestIdRef = useRef(0);
  const pageRef = useRef(1);

  const loadRunners = useCallback(async (
    targetPage?: number,
    filters?: { search?: string; status?: string }
  ) => {
    const requestId = ++requestIdRef.current;

    setLoading(true);
    setError('');

    try {
      const result = await getRunnerDirectory({
        search: (filters?.search ?? search).trim() || undefined,
        status: filters?.status ?? status || undefined,
        page: targetPage ?? pageRef.current,
        per_page: perPage
      });

      if (!mountedRef.current || requestId !== requestIdRef.current) {
        return;
      }

      pageRef.current = result.pagination.current_page;

      setRunners(result.runners);
      setPage(result.pagination.current_page);
      setLastPage(result.pagination.last_page);
      setTotal(result.pagination.total);
    } catch (err) {
      if (mountedRef.current && requestId === requestIdRef.current) {
        setError(getApiError(err));
      }
    } finally {
      if (mountedRef.current && requestId === requestIdRef.current) {
        setLoading(false);
      }
    }
  }, [perPage, search, status]);

  useEffect(() => {
    mountedRef.current = true;

    return () => {
      mountedRef.current = false;
      requestIdRef.current += 1;
    };
  }, []);

  useEffect(() => {
    if (enabled) {
      void loadRunners(1);
    }
  }, [enabled, loadRunners]);

  const searchRunners = useCallback(() => {
    pageRef.current = 1;
    void loadRunners(1);
  }, [loadRunners]);

  const filterByStatus = useCallback((nextStatus: string) => {
    setStatus(nextStatus);
    pageRef.current = 1;
    void loadRunners(1, { search, status: nextStatus });
  }, [loadRunners, search]);

  return {
    runners,
    search,
    setSearch,
    status,
    setStatus,
    page,
    lastPage,
    total,
    loading,
    error,
    setError,
    loadRunners,
    searchRunners,
    filterByStatus
  };
}
