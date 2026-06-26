import Link from 'next/link';

import { EmptyState } from '@/components/account/empty-state';
import { PhoneCaptureCard } from '@/components/account/phone-capture-card';
import { SectionCard } from '@/components/account/section-card';
import { StatCard } from '@/components/account/stat-card';
import { roleLabel } from '@/components/account/user-summary';
import { WriterRequestCard } from '@/components/account/writer-request-card';
import {
  BellIcon,
  BookmarkIcon,
  EyeIcon,
  FileTextIcon,
  FilmIcon,
  InboxIcon,
  MessageSquareIcon,
  VideoIcon,
} from '@/components/icons';
import { buttonVariants } from '@/components/ui/button';
import { getAccountStats, getMyContent, getNotifications, getUnreadCount } from '@/lib/account';
import { getCurrentUser } from '@/lib/auth';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

const STATUS_LABEL: Record<string, string> = {
  active: 'نشط',
  suspended: 'موقوف',
  banned: 'محظور',
  pending: 'قيد المراجعة',
};

export default async function AccountHome() {
  const user = await getCurrentUser();
  if (!user) return null; // layout guards; defensive.
  const isWriter = user.is_writer;

  const [stats, unread, recentArticles, recentNotifs] = await Promise.all([
    getAccountStats(),
    getUnreadCount(),
    isWriter ? getMyContent('articles') : Promise.resolve([]),
    getNotifications('all'),
  ]);

  const content = stats?.content ?? {};
  const engagement = stats?.engagement ?? {};

  const statCards = isWriter
    ? [
        { label: 'المقالات', value: content.articles ?? 0, icon: FileTextIcon },
        { label: 'الفيديوهات', value: content.videos ?? 0, icon: VideoIcon },
        { label: 'الريلز', value: content.reels ?? 0, icon: FilmIcon },
        { label: 'المشاهدات', value: engagement.views ?? 0, icon: EyeIcon },
        { label: 'التعليقات', value: engagement.comments ?? 0, icon: MessageSquareIcon },
        { label: 'إشعارات غير مقروءة', value: unread, icon: BellIcon },
      ]
    : [
        { label: 'المحفوظات', value: engagement.favorites ?? 0, icon: BookmarkIcon },
        { label: 'التعليقات', value: engagement.comments ?? 0, icon: MessageSquareIcon },
        { label: 'إشعارات غير مقروءة', value: unread, icon: BellIcon },
      ];

  return (
    <div className="flex flex-col gap-6">
      {/* Welcome */}
      <div className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-5 sm:flex-row sm:items-center">
        <div className="avatar flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-surface-2 text-fg">
          {user.avatar ? (
            // eslint-disable-next-line @next/next/no-img-element -- raw <img> until the unified Image-Platform slice
            <img src={user.avatar} alt={user.name} className="size-full object-cover" />
          ) : (
            <span className="font-heading text-2xl font-bold">{user.name?.charAt(0) || '؟'}</span>
          )}
        </div>
        <div className="min-w-0">
          <h1 className="font-heading text-h2 font-extrabold text-fg">مرحباً، {user.name}</h1>
          <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-caption text-muted">
            <span className="bg-primary/10 px-2 py-0.5 font-bold text-primary">{roleLabel(user)}</span>
            <span>انضمّ {formatDate(user.created_at)}</span>
            {user.status && <span>الحالة: {STATUS_LABEL[user.status] ?? user.status}</span>}
          </div>
        </div>
      </div>

      {/* رقم الهاتف وإشعارات واتساب — بطاقة دائمة أسفل الترحيب: تعرض الرقم الحاليّ (قابل للتعديل)
          وحالة الاشتراك، وإزالة العلامة + الحفظ = إلغاء اشتراك واتساب. */}
      <PhoneCaptureCard phone={user.phone ?? null} whatsappSubscribed={user.whatsapp_subscribed ?? false} />

      {/* Stats */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
        {statCards.map((s) => (
          <StatCard key={s.label} label={s.label} value={s.value} icon={s.icon} />
        ))}
      </div>

      {/* Quick actions */}
      <div className="flex flex-wrap gap-3">
        {isWriter ? (
          <>
            <Link href="/account/content/new?type=article" className={cn(buttonVariants({ variant: 'primary', size: 'md' }))}>
              إنشاء مقال
            </Link>
            <Link href="/account/content/new?type=news" className={cn(buttonVariants({ variant: 'outline', size: 'md' }))}>
              إنشاء خبر
            </Link>
            <Link href="/account/content/new?type=video" className={cn(buttonVariants({ variant: 'outline', size: 'md' }))}>
              إنشاء فيديو
            </Link>
            <Link href="/account/content/new?type=reel" className={cn(buttonVariants({ variant: 'outline', size: 'md' }))}>
              إنشاء ريل
            </Link>
            <Link href="/account/content" className={cn(buttonVariants({ variant: 'ghost', size: 'md' }))}>
              محتواي
            </Link>
          </>
        ) : (
          <>
            <Link href="/" className={cn(buttonVariants({ variant: 'primary', size: 'md' }))}>
              تصفّح الأخبار
            </Link>
            <Link href="/account/profile" className={cn(buttonVariants({ variant: 'outline', size: 'md' }))}>
              تعديل ملفي
            </Link>
          </>
        )}
      </div>

      {/* Writer upgrade — non-writers (also available on the profile page) */}
      {!isWriter && <WriterRequestCard status={user.writer_request?.status ?? null} />}

      {/* Recent activity */}
      <div className={cn('grid gap-6', isWriter && 'lg:grid-cols-2')}>
        {isWriter && (
          <SectionCard
            title="آخر مقالاتك"
            action={
              <Link href="/account/content?tab=articles" className="text-sm font-medium text-primary hover:underline">
                عرض الكل
              </Link>
            }
            bodyClassName="p-0"
          >
            {recentArticles.length ? (
              <ul className="divide-y divide-border">
                {recentArticles.slice(0, 5).map((a) => (
                  <li key={a.id} className="flex items-center justify-between gap-3 px-5 py-3">
                    <span className="line-clamp-1 text-sm font-medium text-fg">{a.title ?? '—'}</span>
                    <span className="shrink-0 text-caption text-muted">{formatDate(a.created_at)}</span>
                  </li>
                ))}
              </ul>
            ) : (
              <div className="p-4">
                <EmptyState icon={FileTextIcon} title="لا مقالات بعد" description="ابدأ بكتابة أوّل مقال لك." />
              </div>
            )}
          </SectionCard>
        )}

        <SectionCard
          title="آخر الإشعارات"
          action={
            <Link href="/account/notifications" className="text-sm font-medium text-primary hover:underline">
              عرض الكل
            </Link>
          }
          bodyClassName="p-0"
        >
          {recentNotifs.length ? (
            <ul className="divide-y divide-border">
              {recentNotifs.slice(0, 5).map((n) => (
                <li key={String(n.id)} className="flex items-start gap-3 px-5 py-3">
                  <span className={cn('mt-1.5 size-2 shrink-0 rounded-full', n.read ? 'bg-border' : 'bg-primary')} />
                  <div className="min-w-0">
                    <p className="line-clamp-2 text-sm text-fg">{n.message || n.title || '—'}</p>
                    <p className="mt-0.5 text-caption text-muted">{formatDate(n.created_at)}</p>
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <div className="p-4">
              <EmptyState icon={InboxIcon} title="لا إشعارات" description="ستظهر إشعاراتك هنا فور وصولها." />
            </div>
          )}
        </SectionCard>
      </div>
    </div>
  );
}
