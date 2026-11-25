import type { RunnerDirectoryItem } from '../../../types/admin';

import RunnerDirectoryFilters from './RunnerDirectoryFilters';
import RunnerDirectoryList from './RunnerDirectoryList';

interface RunnerDirectoryPanelProps {
  runners: RunnerDirectoryItem[];
  search: string;
  status: string;
  page: number;
  lastPage: number;
  total: number;
  loading: boolean;
  canSuspend: boolean;
  canRestore: boolean;
  workingId: string;
  reasons: Record<string, string>;
  onSearchChange: (value: string) => void;
  onStatusChange: (value: string) => void;
  onSearch: () => void;
  onPageChange: (page: number) => void;
  onReasonChange: (id: string, value: string) => void;
  onRunnerStatusChange: (
    runner: RunnerDirectoryItem,
    action: 'suspend' | 'restore'
  ) => void;
}

export default function RunnerDirectoryPanel({
  runners,
  search,
  status,
  page,
  lastPage,
  total,
  loading,
  canSuspend,
  canRestore,
  workingId,
  reasons,
  onSearchChange,
  onStatusChange,
  onSearch,
  onPageChange,
  onReasonChange,
  onRunnerStatusChange
}: RunnerDirectoryPanelProps) {
  return (
    <div
      id="runner-panel-directory"
      role="tabpanel"
      aria-labelledby="runner-tab-directory"
      tabIndex={0}
    >
      <RunnerDirectoryFilters
        search={search}
        status={status}
        loading={loading}
        onSearchChange={onSearchChange}
        onStatusChange={onStatusChange}
        onSearch={onSearch}
      />

      <RunnerDirectoryList
        runners={runners}
        page={page}
        lastPage={lastPage}
        total={total}
        loading={loading}
        canSuspend={canSuspend}
        canRestore={canRestore}
        workingId={workingId}
        reasons={reasons}
        onPageChange={onPageChange}
        onReasonChange={onReasonChange}
        onStatusChange={onRunnerStatusChange}
      />
    </div>
  );
}
