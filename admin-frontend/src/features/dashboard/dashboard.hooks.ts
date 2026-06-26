import { useQuery } from '@tanstack/react-query';
import { articlesService } from '@/services/articles.service';
import { commentsService } from '@/services/comments.service';
import { reelsService } from '@/services/reels.service';
import { siteAnalyticsService } from '@/services/siteAnalytics.service';
import { videosService } from '@/services/videos.service';
import { writerRequestsService } from '@/services/writerRequests.service';

/** تحليلات الموقع الموحّدة (قراءة-فقط) — KPIs + جرد + اتجاه (+top/channels في Phase B). */
export function useSiteAnalytics() {
  return useQuery({
    queryKey: ['site-analytics'],
    queryFn: () => siteAnalyticsService.get(),
  });
}

const RECENT_LIMIT = 10;

/** آخر 10 أخبار/مقالات (الأحدث إنشاءً) — يعيد استخدام endpoint القائمة. */
export function useRecentArticles(enabled = true) {
  return useQuery({
    queryKey: ['dashboard', 'recent-articles'],
    queryFn: () =>
      articlesService.list({
        page: 1,
        per_page: RECENT_LIMIT,
        search: '',
        status: '',
        type: '',
        locale: '',
        category: '',
        placement: '',
        sort: '-created_at',
      }),
    enabled,
    staleTime: 60_000,
  });
}

/** آخر 10 ريلز. */
export function useRecentReels(enabled = true) {
  return useQuery({
    queryKey: ['dashboard', 'recent-reels'],
    queryFn: () =>
      reelsService.list({
        page: 1,
        per_page: RECENT_LIMIT,
        search: '',
        status: '',
        locale: '',
        sort: '-created_at',
      }),
    enabled,
    staleTime: 60_000,
  });
}

/** آخر 10 فيديوهات. */
export function useRecentVideos(enabled = true) {
  return useQuery({
    queryKey: ['dashboard', 'recent-videos'],
    queryFn: () =>
      videosService.list({
        page: 1,
        per_page: RECENT_LIMIT,
        search: '',
        status: '',
        visibility: '',
        source_type: '',
        locale: '',
        sort: '-created_at',
      }),
    enabled,
    staleTime: 60_000,
  });
}

/** عدد التعليقات المعلّقة (status=pending) — من pagination.total. */
export function usePendingCommentsCount(enabled = true) {
  return useQuery({
    queryKey: ['dashboard', 'pending-comments'],
    queryFn: async () => {
      const res = await commentsService.list({ page: 1, per_page: 1, status: 'pending', q: '' });
      return res.pagination.total;
    },
    enabled,
    staleTime: 30_000,
  });
}

/** عدد طلبات الكتّاب المعلّقة (status=pending) — من pagination.total. */
export function usePendingWriterRequestsCount(enabled = true) {
  return useQuery({
    queryKey: ['dashboard', 'pending-writer-requests'],
    queryFn: async () => {
      const res = await writerRequestsService.list({ page: 1, per_page: 1, search: '', status: 'pending' });
      return res.pagination.total;
    },
    enabled,
    staleTime: 30_000,
  });
}
