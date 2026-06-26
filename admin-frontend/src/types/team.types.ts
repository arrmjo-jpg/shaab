/** أنواع نطاق فريق العمل — تطابق عقود الـ backend (TeamMemberResource). */
import type { PaginationMeta } from '@/types/users.types';

export type TeamMemberStatus = 'active' | 'inactive';

/** صورة العضو مشتقّة من المكتبة المركزية (MediaAsset) — روابط CDN + conversions. */
export interface TeamMemberAvatar {
  id: number;
  url: string | null;
  thumb: string | null;
  medium: string | null;
  width: number | null;
  height: number | null;
}

export interface TeamMemberSeo {
  title: string | null;
  description: string | null;
  keywords: string | null;
  canonical_url: string | null;
  robots: string | null;
}

export interface TeamMemberData {
  id: number;
  uuid: string;
  name: string;
  job_title: string;
  department: string | null;
  status: TeamMemberStatus;
  slug: string;
  bio_html: string | null;
  avatar_asset_id: number | null;
  avatar: TeamMemberAvatar | null;
  social_links: Record<string, string>;
  seo: TeamMemberSeo;
  canonical_path: string;
  sort_order: number;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

export interface TeamMembersListParams {
  page: number;
  per_page: number;
  search: string;
  status: '' | TeamMemberStatus;
  department: string;
  sort: '' | '-created_at' | 'name' | 'sort_order' | 'id';
  trashed?: '' | 'only' | 'with';
}

export interface TeamMembersListResult {
  data: TeamMemberData[];
  pagination: PaginationMeta;
}

/**
 * StoreTeamMemberRequest / UpdateTeamMemberRequest payload — كل الحقول `sometimes`
 * على التعديل؛ على الإنشاء name + job_title مطلوبان. social_links مفاتيح مهيكَلة.
 */
export interface TeamMemberUpsertPayload {
  name?: string;
  job_title?: string;
  department?: string | null;
  slug?: string | null;
  bio?: string | null;
  avatar_asset_id?: number | null;
  social_links?: Record<string, string>;
  seo_title?: string | null;
  seo_description?: string | null;
  seo_keywords?: string | null;
  canonical_url?: string | null;
  robots?: string | null;
  status?: TeamMemberStatus;
  sort_order?: number;
}
