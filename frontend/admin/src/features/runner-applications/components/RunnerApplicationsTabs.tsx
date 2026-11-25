import type { KeyboardEvent } from 'react';

export type RunnerTab = 'applications' | 'directory';

interface RunnerApplicationsTabsProps {
  activeTab: RunnerTab;
  onTabChange: (tab: RunnerTab) => void;
}

export default function RunnerApplicationsTabs({
  activeTab,
  onTabChange
}: RunnerApplicationsTabsProps) {
  function handleKeyDown(event: KeyboardEvent<HTMLButtonElement>) {
    const tabs = Array.from(
      event.currentTarget
        .closest('[role="tablist"]')
        ?.querySelectorAll<HTMLButtonElement>('[role="tab"]') ?? []
    );

    if (tabs.length === 0) return;

    const currentIndex = tabs.indexOf(event.currentTarget);
    let nextIndex = currentIndex;

    if (event.key === 'ArrowRight') {
      nextIndex = (currentIndex + 1) % tabs.length;
    } else if (event.key === 'ArrowLeft') {
      nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
    } else if (event.key === 'Home') {
      nextIndex = 0;
    } else if (event.key === 'End') {
      nextIndex = tabs.length - 1;
    } else {
      return;
    }

    event.preventDefault();
    tabs[nextIndex]?.focus();
    tabs[nextIndex]?.click();
  }

  return (
    <div
      className="admin-tabs"
      role="tablist"
      aria-label="Bahagian Runner"
    >
      <button
        id="runner-tab-applications"
        type="button"
        role="tab"
        aria-controls="runner-panel-applications"
        aria-selected={activeTab === 'applications'}
        tabIndex={activeTab === 'applications' ? 0 : -1}
        className={`admin-tab ${
          activeTab === 'applications' ? 'admin-tab--active' : ''
        }`}
        onClick={() => onTabChange('applications')}
        onKeyDown={handleKeyDown}
      >
        Permohonan
      </button>

      <button
        id="runner-tab-directory"
        type="button"
        role="tab"
        aria-controls="runner-panel-directory"
        aria-selected={activeTab === 'directory'}
        tabIndex={activeTab === 'directory' ? 0 : -1}
        className={`admin-tab ${
          activeTab === 'directory' ? 'admin-tab--active' : ''
        }`}
        onClick={() => onTabChange('directory')}
        onKeyDown={handleKeyDown}
      >
        Senarai Runner
      </button>
    </div>
  );
}
