/** عقد مهمة مجدوَلة كما يحسبه الـ backend (لا حساب جدولة في الواجهة). */
export type TaskHealth = 'healthy' | 'stale' | 'failed' | 'never' | 'disabled';
export type LastStatus = 'never' | 'running' | 'success' | 'failed';

export interface ScheduledTask {
  key: string;
  name: string;
  description: string;
  type: 'command';
  command: string | null;
  cron: string | null;
  frequency: string;
  critical: boolean;
  manual_run_allowed: boolean;
  enabled: boolean;
  notes: string | null;
  last_run_at: string | null;
  last_status: LastStatus;
  last_runtime_ms: number | null;
  last_error: string | null;
  next_run_at: string | null;
  health: TaskHealth;
}

export interface UpdateScheduledTaskPayload {
  enabled?: boolean;
  notes?: string | null;
}
