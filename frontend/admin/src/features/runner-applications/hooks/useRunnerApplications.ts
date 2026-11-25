import { useCallback, useEffect, useRef, useState } from 'react';

import {
  getApiError,
  getRunnerApplications
} from '../../../services/adminApi';

import type { RunnerApplication } from '../../../types/admin';

interface UseRunnerApplicationsOptions {
  enabled?: boolean;
  perPage?: number;
}

export default function useRunnerApplications({
  enabled = false,
  perPage = 15
}: UseRunnerApplicationsOptions = {}) {
  const [applications, setApplications] = useState<RunnerApplication[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [from, setFrom] = useState<number | null>(null);
  const [to, setTo] = useState<number | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const mountedRef = useRef(false);
  const requestIdRef = useRef(0);
  const pageRef = useRef(1);

  const loadApplications = useCallback(async (targetPage?: number) => {
    const requestId = ++requestIdRef.current;

    setLoading(true);
    setError('');

    try {
      const result = await getRunnerApplications({
        page: targetPage ?? pageRef.current,
        per_page: perPage
      });

      if (!mountedRef.current || requestId !== requestIdRef.current) return;

      pageRef.current = result.pagination.current_page;
      setApplications(result.applications);
      setPage(result.pagination.current_page);
      setLastPage(result.pagination.last_page);
      setTotal(result.pagination.total);
      setFrom(result.pagination.from);
      setTo(result.pagination.to);
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
    if (enabled) void loadApplications(1);

    return () => {
      requestIdRef.current += 1;
    };
  }, [enabled, loadApplications]);

  return {
    applications,
    page,
    lastPage,
    total,
    from,
    to,
    loading,
    error,
    setError,
    loadApplications
  };
}
