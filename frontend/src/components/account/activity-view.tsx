import Link from 'next/link';

import { AccountTabs, type AccountTab } from '@/components/account/account-tabs';
import { EmptyState } from '@/components/account/empty-state';
import { ArticleCard } from '@/components/articles/article-card';
import { BookmarkIcon, FileTextIcon, FilmIcon, GridIcon, HeartIcon, VideoIcon } from '@/components/icons';
import { ReelCard } from '@/components/reels/reel-card';
import { buttonVariants } from '@/components/ui/button';
import { VideoCard } from '@/components/videos/video-card';
import { getMyActivity, type ActivityKind, type ActivityTab } from '@/lib/activity';
import { cn } from '@/lib/utils';

// **View واحد** لكلّ نشاط المستخدم — «المحفوظات»=activity"saved"، «أعجبني»=activity"liked".
// لا منطق مختلف بين الصفحتين؛ **الفرق الوحيد قيمة `activity`**. يعيد استخدام بالكامل: AccountTabs
// (S2) + ArticleCard/VideoCard/ReelCard + EmptyState + getMyActivity (طبقة S2 العامّة). ترقيم
// صفحيّ خادميّ عبر `?page=` (URL-driven، نفس نمط `?tab=` — لا نظام تحميل جديد).

const ACTIVITY_TABS: AccountTab[] = [
  { key: 'all', label: 'الكل', icon: GridIcon },
  { key: 'article', label: 'المقالات', icon: FileTextIcon },
  { key: 'video', label: 'الفيديوهات', icon: VideoIcon },
  { key: 'reel', label: 'الريلز', icon: FilmIcon },
];

// نصوص العرض فقط حسب النشاط (لا منطق) — يطابق قرار «الفرق الوحيد activity».
const COPY = {
  saved: {
    title: 'المحفوظات',
    icon: BookmarkIcon,
    emptyTitle: 'لا يوجد محتوى محفوظ بعد',
    emptyDesc: 'احفظ المقالات والفيديوهات والريلز لتعود إليها بسهولة من هنا.',
  },
  liked: {
    title: 'أعجبني',
    icon: HeartIcon,
    emptyTitle: 'لا يوجد محتوى أعجبك بعد',
    emptyDesc: 'سجّل إعجابك بالمقالات والفيديوهات والريلز لتظهر هنا.',
  },
} as const;

function parseTab(v: string | undefined): ActivityTab {
  return v === 'article' || v === 'video' || v === 'reel' ? v : 'all';
}

export async function ActivityView({
  activity,
  searchParams,
}: {
  activity: ActivityKind;
  searchParams: { tab?: string; page?: string };
}) {
  const tab = parseTab(searchParams.tab);
  const page = Math.max(1, Number(searchParams.page) || 1);
  const { items, pagination } = await getMyActivity(activity, tab, page);

  const copy = COPY[activity];
  const basePath = `/account/${activity}`;
  const pageHref = (p: number) => `${basePath}?tab=${tab}&page=${p}`;
  const pageLink = 'border border-border px-4 py-2 text-sm font-medium text-fg transition-colors hover:border-primary hover:text-primary';

  return (
    <div className="flex flex-col gap-5">
      <h1 className="font-heading text-h2 font-extrabold text-fg">{copy.title}</h1>

      <AccountTabs tabs={ACTIVITY_TABS} basePath={basePath} active={tab} />

      {items.length === 0 ? (
        <EmptyState
          icon={copy.icon}
          title={copy.emptyTitle}
          description={copy.emptyDesc}
          action={
            <Link href="/" className={cn(buttonVariants({ variant: 'primary', size: 'md' }))}>
              تصفّح المحتوى
            </Link>
          }
        />
      ) : (
        <>
          <div className="grid grid-cols-2 items-start gap-4 sm:grid-cols-3 lg:grid-cols-4">
            {items.map((it) =>
              it.contentType === 'video' ? (
                <VideoCard key={`v-${it.data.id}`} video={it.data} />
              ) : it.contentType === 'reel' ? (
                <ReelCard key={`r-${it.data.id}`} reel={it.data} className="w-full" />
              ) : (
                <ArticleCard key={`a-${it.data.id}`} item={it.data} />
              ),
            )}
          </div>

          {pagination.totalPages > 1 && (
            <nav className="flex items-center justify-center gap-4 pt-2" aria-label="ترقيم الصفحات">
              {page > 1 ? (
                <Link href={pageHref(page - 1)} className={pageLink}>
                  السابق
                </Link>
              ) : (
                <span className="px-4 py-2 text-sm text-muted opacity-40">السابق</span>
              )}
              <span className="text-sm tabular-nums text-muted">
                صفحة {pagination.currentPage} من {pagination.totalPages}
              </span>
              {page < pagination.totalPages ? (
                <Link href={pageHref(page + 1)} className={pageLink}>
                  التالي
                </Link>
              ) : (
                <span className="px-4 py-2 text-sm text-muted opacity-40">التالي</span>
              )}
            </nav>
          )}
        </>
      )}
    </div>
  );
}
