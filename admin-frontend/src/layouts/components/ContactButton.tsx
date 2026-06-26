import { Inbox } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { paths } from '@/router/paths';
import { useAuth } from '@/hooks/useAuth';
import { useInboxUnread } from '@/features/contact/contact.hooks';

/**
 * زرّ صندوق الوارد في النافبار — أيقونة + شارة المجموع (status='new' للوحدتين). المصدر الوحيد
 * هو /admin/inbox/unread-count (لا notifications). يظهر فقط لمن يملك صلاحية رؤية إحدى الوحدتين.
 */
export function ContactButton() {
  const { t } = useTranslation('inbox');
  const navigate = useNavigate();
  const { hasPermission } = useAuth();

  const canView = hasPermission('contact-messages.view') || hasPermission('ad-requests.view');
  const q = useInboxUnread(canView);

  if (!canView) return null;

  const unread = q.data?.total ?? 0;

  return (
    <Button
      variant="ghost"
      size="icon"
      className="relative"
      aria-label={t('nav.title')}
      title={t('nav.title')}
      onClick={() => navigate(paths.contactUs)}
    >
      <Inbox className="h-5 w-5" />
      {unread > 0 ? (
        <span className="absolute -end-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold leading-none text-destructive-foreground">
          {unread > 99 ? '99+' : unread}
        </span>
      ) : null}
    </Button>
  );
}
