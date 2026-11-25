import type { RunnerDirectoryItem } from '../../../types/admin';

import RunnerDirectoryCard from './RunnerDirectoryCard';
import RunnerPagination from './RunnerPagination';

interface RunnerDirectoryListProps {
  runners: RunnerDirectoryItem[];
  page: number;
  lastPage: number;
  total: number;
  loading: boolean;
  canSuspend: boolean;
  canRestore: boolean;
  workingId: string;
  reasons: Record<string, string>;
  onPageChange: (page: number) => void;
  onReasonChange: (id: string, value: string) => void;
  onStatusChange: (
    runner: RunnerDirectoryItem,
    action: 'suspend' | 'restore'
  ) => void;
}

export default function RunnerDirectoryList({
  runners,
  page,
  lastPage,
  total,
  loading,
  canSuspend,
  canRestore,
  workingId,
  reasons,
  onPageChange,
  onReasonChange,
  onStatusChange
}: RunnerDirectoryListProps) {
  if (loading) {
    return (
      <div className="admin-state" role="status" aria-live="polite">
        Memuatkan senarai Runner...
      </div>
    );
  }

  if (runners.length === 0) {
    return (
      <div className="admin-state" role="status">
        Tiada Runner ditemui.
      </div>
    );
  }

  return (
    <>
      <div className="admin-page__footnote" aria-live="polite">
        Jumlah Runner: {total}
      </div>

      <div className="admin-record-list">
        {runners.map((runner) => (
          <RunnerDirectoryCard
            key={runner.id}
            runner={runner}
            canSuspend={canSuspend}
            canRestore={canRestore}
            working={Boolean(workingId)}
            reason={reasons[runner.id] || ''}
            onReasonChange={(value) =>
              onReasonChange(runner.id, value)
            }
            onStatusChange={(action) =>
              onStatusChange(runner, action)
            }
          />
        ))}
      </div>

      <RunnerPagination
        currentPage={page}
        lastPage={lastPage}
        loading={loading}
        onPageChange={onPageChange}
      />
    </>
  );
}
