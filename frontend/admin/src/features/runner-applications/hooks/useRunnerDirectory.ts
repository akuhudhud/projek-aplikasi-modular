import { useCallback, useEffect, useRef, useState } from 'react';

import {
  getApiError,
  getRunnerDirectory
} from '../../../services/adminApi';

import type { RunnerDirectoryItem } from '../../../types/admin';

interface DirectoryFilters {
  search: string;
  status: string;
}

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
  const filtersRef = useRef<DirectoryFilters>({ search: '', status: '' });

  filtersRef.current = { search, status };

  const loadRunners = useCallback(async (
    targetPage?: number,
    filters?: DirectoryFilters
  ): Promise<void> => {
    const requestId = ++requestIdRef.current;
    const activeFilters = filters ?? filtersRef.current;

    setLoading(true);
    setError('');

    try {
      const result = await getRunnerDirectory({
        search: activeFilters.search.trim() || undefined,
        status: activeFilters.status || undefined,
        page: targetPage ?? pageRef.current,
        per_page: perPage
      });

      if (!mountedRef.current || requestId !== requestIdRef.current) return;

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
  }, [perPage]);

  useEffect(() => {
    mountedRef.current = true;

    return () => {
      mountedRef.current = false;
      requestIdRef.current += 1;
    };
  }, []);

  useEffect(() => {
    if (enabled) void loadRunners(1);
    // Load only when the directory tab becomes active.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enabled]);

  const searchRunners = useCallback(() => {
    pageRef.current = 1;
    void loadRunners(1, filtersRef.current);
  }, [loadRunners]);

  const filterByStatus = useCallback((nextStatus: string) => {
    const nextFilters = {
      search: filtersRef.current.search,
      status: nextStatus
    };

    filtersRef.current = nextFilters;
    setStatus(nextStatus);
    pageRef.current = 1;
    void loadRunners(1, nextFilters);
  }, [loadRunners]);

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
