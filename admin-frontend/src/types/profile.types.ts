import type { PaginationMeta } from './users.types';

export interface ProfileRoleRef {
  name: string;
  display_name: string;
}

export interface ProfileData {
  id: number;
  name: string;
  email: string;
  status: string;
  status_label: string;
  email_verified: boolean;
  is_writer: boolean;
  avatar: string | null;
  bio: string | null;
  social_links: Record<string, string>;
  last_login_at: string | null;
  last_login_ip: string | null;
  created_at: string;
  roles: ProfileRoleRef[];
}

export interface ProfileUpdatePayload {
  name: string;
  bio?: string | null;
  avatar?: string | null;
  social_links?: Record<string, string>;
}

export interface ChangePasswordPayload {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export interface ProfileActivity {
  id: number;
  event: string | null;
  log_name: string | null;
  description: string | null;
  context: Record<string, string | number | boolean>;
  created_at: string | null;
}

export interface ProfileActivityResult {
  data: ProfileActivity[];
  pagination: PaginationMeta;
}

export interface ProfileSession {
  id: number;
  name: string;
  current: boolean;
  last_used_at: string | null;
  created_at: string | null;
}

export interface ProfileActivityQuery {
  page?: number;
  'filter[log_name]'?: string;
  'filter[event]'?: string;
}

export interface ProfileAnalytics {
  articles: { created: number; published: number; drafts: number; views_generated: number };
  reels: { created: number; published: number; drafts: number };
  media: { uploads: number };
  ai: { requests: number; tokens: number; estimated_cost: number };
}

export interface ProfilePermissionGroup {
  group: string;
  count: number;
  permissions: { name: string; display_name: string }[];
}

export interface ProfilePermissions {
  roles: ProfileRoleRef[];
  is_super_admin: boolean;
  summary: { roles_count: number; permissions_count: number; groups_count: number };
  groups: ProfilePermissionGroup[];
}

export interface ProfileSecurity {
  email_verified: boolean;
  email_verified_at: string | null;
  last_login_at: string | null;
  last_login_ip: string | null;
  password_changed_at: string | null;
  reset_requests_count: number;
  last_reset_requested_at: string | null;
  active_sessions_count: number;
  account_created_at: string;
}
