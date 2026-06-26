/** أنواع الأدوار/الصلاحيات/مجموعات الصلاحيات — تطابق عقود الـ backend. */

export interface PermissionRef {
  name: string;
  display_name: string;
}

export interface RolePermissionGroup {
  group: string;
  items: PermissionRef[];
}

export interface RoleData {
  id: number;
  name: string;
  display_name: string;
  description: string | null;
  is_system: boolean;
  permissions_count: number;
  users_count: number;
  created_at: string | null;
  permissions?: RolePermissionGroup[];
}

export interface RoleUpsertPayload {
  name?: string;
  display_name: string;
  description?: string | null;
  permissions?: string[];
}

/** صلاحية مفردة كما يرجعها GET /permissions (PermissionResource). */
export interface PermissionItem {
  name: string;
  display_name: string;
  description: string | null;
  guard: string;
  group: string;
  is_system: boolean;
}

export interface PermissionGroupBlock {
  group: string;
  items: PermissionItem[];
}

/** مجموعة صلاحيات حقيقية (PermissionGroupResource). */
export interface PermissionGroupData {
  id: number;
  slug: string;
  display_name: string;
  description: string | null;
  icon: string | null;
  sort_order: number;
  is_system: boolean;
  permissions_count: number;
  permissions?: { name: string; display_name: string; description: string | null }[];
}

export interface PermissionGroupUpsertPayload {
  slug?: string;
  display_name: string;
  description?: string | null;
  icon?: string | null;
  sort_order?: number;
}
