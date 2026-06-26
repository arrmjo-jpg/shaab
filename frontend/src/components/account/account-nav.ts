import type { ComponentType } from 'react';

import {
  BellIcon,
  BookmarkIcon,
  DashboardIcon,
  FileTextIcon,
  FilmIcon,
  HeartIcon,
  UserIcon,
  VideoIcon,
} from '@/components/icons';

export interface AccountNavItem {
  href: string;
  label: string;
  icon: ComponentType<{ className?: string }>;
  writerOnly?: boolean;
  match: string; // pathname used for active state
  tab?: string; // for /account/content sub-items (active by ?tab=)
}

// Single source for the dashboard sidebar. Filtered by role; readers never see writer-only items.
export const ACCOUNT_NAV: AccountNavItem[] = [
  { href: '/account', label: 'الرئيسية', icon: DashboardIcon, match: '/account' },
  { href: '/account/profile', label: 'ملفي الشخصي', icon: UserIcon, match: '/account/profile' },
  { href: '/account/content?tab=articles', label: 'مقالاتي', icon: FileTextIcon, writerOnly: true, match: '/account/content', tab: 'articles' },
  { href: '/account/content?tab=videos', label: 'فيديوهاتي', icon: VideoIcon, writerOnly: true, match: '/account/content', tab: 'videos' },
  { href: '/account/content?tab=reels', label: 'ريلز', icon: FilmIcon, writerOnly: true, match: '/account/content', tab: 'reels' },
  { href: '/account/notifications', label: 'الإشعارات', icon: BellIcon, match: '/account/notifications' },
  { href: '/account/liked', label: 'أعجبني', icon: HeartIcon, match: '/account/liked' },
  { href: '/account/saved', label: 'المحفوظات', icon: BookmarkIcon, match: '/account/saved' },
];

export function navForUser(isWriter: boolean): AccountNavItem[] {
  return ACCOUNT_NAV.filter((item) => !item.writerOnly || isWriter);
}
