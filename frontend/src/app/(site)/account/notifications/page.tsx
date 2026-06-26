import { EmptyState } from '@/components/account/empty-state';
import { MarkAllReadButton } from '@/components/account/mark-all-read-button';
import { NotificationItem } from '@/components/account/notification-item';
import { NotificationsTabs } from '@/components/account/notifications-tabs';
import { InboxIcon } from '@/components/icons';
import { getNotifications, type NotificationFilter } from '@/lib/account';

export default async function NotificationsPage({
  searchParams,
}: {
  searchParams: Promise<{ filter?: string }>;
}) {
  const sp = await searchParams;
  const filter: NotificationFilter = sp.filter === 'unread' || sp.filter === 'read' ? sp.filter : 'all';
  const items = await getNotifications(filter);
  const hasUnread = items.some((n) => !n.read);

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-center justify-between gap-3">
        <h1 className="font-heading text-h2 font-extrabold text-fg">الإشعارات</h1>
        {hasUnread && <MarkAllReadButton />}
      </div>

      <NotificationsTabs active={filter} />

      {items.length ? (
        <div className="divide-y divide-border overflow-hidden rounded-xl border border-border bg-surface">
          {items.map((n) => (
            <NotificationItem
              key={String(n.id)}
              id={String(n.id)}
              message={n.message || n.title || '—'}
              createdAt={n.created_at}
              read={n.read}
            />
          ))}
        </div>
      ) : (
        <EmptyState
          icon={InboxIcon}
          title="لا إشعارات"
          description={filter === 'unread' ? 'لا توجد إشعارات غير مقروءة حالياً.' : 'ستظهر إشعاراتك هنا فور وصولها.'}
        />
      )}
    </div>
  );
}
