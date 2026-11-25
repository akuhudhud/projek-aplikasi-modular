import { useState } from 'react';

import {
  changeRunnerStatus,
  decideRunnerApplication,
  getApiError
} from '../../../services/adminApi';

import type { RunnerDirectoryItem } from '../../../types/admin';

interface UseRunnerActionsOptions {
  applicationPage: number;
  applicationCount: number;
  runnerPage: number;
  refreshApplications: (page: number) => Promise<void>;
  refreshRunners: (page: number) => Promise<void>;
}

export default function useRunnerActions({
  applicationPage,
  applicationCount,
  runnerPage,
  refreshApplications,
  refreshRunners
}: UseRunnerActionsOptions) {
  const [reasons, setReasons] = useState<Record<string, string>>({});
  const [workingId, setWorkingId] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  function updateReason(id: string, value: string) {
    setReasons((current) => ({
      ...current,
      [id]: value
    }));
  }

  function clearReason(id: string) {
    setReasons((current) => {
      const next = { ...current };
      delete next[id];
      return next;
    });
  }

  async function decide(
    accountId: string,
    decision: 'approve' | 'reject'
  ) {
    if (workingId) return;

    const reason = (reasons[accountId] || '').trim();

    if (!reason) {
      setError('Alasan keputusan wajib diisi.');
      return;
    }

    setWorkingId(accountId);
    setError('');
    setMessage('');

    try {
      await decideRunnerApplication(accountId, decision, reason);

      setMessage(
        decision === 'approve'
          ? 'Permohonan Runner berjaya diluluskan.'
          : 'Permohonan Runner berjaya ditolak.'
      );

      clearReason(accountId);

      const nextPage =
        applicationCount === 1 && applicationPage > 1
          ? applicationPage - 1
          : applicationPage;

      await refreshApplications(nextPage);
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setWorkingId('');
    }
  }

  async function updateRunnerStatus(
    runner: RunnerDirectoryItem,
    action: 'suspend' | 'restore'
  ) {
    if (workingId) return;

    const reason = (reasons[runner.id] || '').trim();

    if (!reason) {
      setError('Alasan tindakan wajib diisi.');
      return;
    }

    setWorkingId(runner.id);
    setError('');
    setMessage('');

    try {
      await changeRunnerStatus(runner.id, action, reason);

      setMessage(
        action === 'suspend'
          ? 'Kelayakan Runner berjaya digantung.'
          : 'Kelayakan Runner berjaya dipulihkan.'
      );

      clearReason(runner.id);

      await refreshRunners(runnerPage);
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setWorkingId('');
    }
  }

  return {
    reasons,
    workingId,
    error,
    message,
    setError,
    setMessage,
    updateReason,
    decide,
    updateRunnerStatus
  };
}
