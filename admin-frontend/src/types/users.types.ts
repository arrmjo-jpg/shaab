/** أنواع وحدة إدارة المستخدمين — تطابق عقود الـ backend (UserResource). */

export interface UserRoleRef {
  name: string;
  display_name: string;
}

export interface UserData {
  id: number;
  name: string;
  email: string;
  status: 'active' | 'suspended' | 'banned';
  status_label: string;
  email_verified: boolean;
  is_admin: boolean;
  is_writer: boolean;
  avatar: string | null;
  bio: string | null;
  social_links: Record<string, string>;
  last_login_at: string | null;
  last_login_ip: string | null;
  created_at: string;
  deleted_at: string | null;
  roles: UserRoleRef[];
}

export interface PaginationMeta {
  total: number;
  count: number;
  per_page: number;
  current_page: number;
  total_pages: number;
}

export interface UsersListResult {
  data: UserData[];
  pagination: PaginationMeta;
}

export type UserTrashedFilter = 'none' | 'only' | 'with';
export type UserAccountType = 'all' | 'admins' | 'regular';

export interface UsersListParams {
  page: number;
  per_page: number;
  search: string;
  status: '' | 'active' | 'suspended' | 'banned';
  role: string;
  trashed: UserTrashedFilter;
  /** When set to 1, returns only users with is_writer=true. */
  is_writer?: 0 | 1;
}

/** حمولة إنشاء/تعديل المستخدم (UpdateUserRequest / StoreUserRequest). */
export interface UserUpsertPayload {
  name: string;
  email: string;
  password?: string;
  password_confirmation?: string;
  status?: string;
  email_verified?: boolean;
  is_writer?: boolean;
  bio?: string | null;
  avatar?: string | null;
  social_links?: Record<string, string>;
  roles?: string[];
}
