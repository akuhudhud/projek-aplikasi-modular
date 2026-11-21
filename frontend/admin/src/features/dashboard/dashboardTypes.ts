
export interface DashboardAccountStats {
  total_users: number;
  active_users: number;
  suspended_users: number;
}

export interface DashboardRunnerStats {
  total: number;
  statuses: Record<string, number>;
}

export interface DashboardApplication {
  id: string;
  profile_name: string;
  phone: string | null;
  email: string | null;
  created_at: string;
  updated_at: string;
}

export interface DashboardRunnerApplicationStats {
  pending: number;
  recent: DashboardApplication[];
}

export interface DashboardTaskDailyActivity {
  date: string;
  created: number;
  completed: number;
}

export interface DashboardTaskStats {
  total: number;
  statuses: Record<string, number>;
  daily_activity: DashboardTaskDailyActivity[];
}

export interface AdminDashboard {
  generated_at: string;
  accounts: DashboardAccountStats;
  runners: DashboardRunnerStats;
  runner_applications: DashboardRunnerApplicationStats;
  tasks: DashboardTaskStats;
}
