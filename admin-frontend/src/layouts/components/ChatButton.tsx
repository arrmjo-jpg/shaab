import { MessageCircle } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { paths } from '@/router/paths';
import { useConversations } from '@/features/chat/chat.hooks';

/**
 * زرّ الشات في النافبار — أيقونة + شارة مجموع غير المقروء (polling عبر useConversations).
 * ينتقل لصفحة الشات.
 */
export function ChatButton() {
  const { t } = useTranslation('chat');
  const navigate = useNavigate();
  const q = useConversations();

  const unread = (q.data ?? []).reduce((sum, c) => sum + (c.unread_count || 0), 0);

  return (
    <Button
      variant="ghost"
      size="icon"
      className="relative"
      aria-label={t('navTitle')}
      title={t('navTitle')}
      onClick={() => navigate(paths.chat)}
    >
      <MessageCircle className="h-5 w-5" />
      {unread > 0 ? (
        <span className="absolute -end-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold leading-none text-destructive-foreground">
          {unread > 99 ? '99+' : unread}
        </span>
      ) : null}
    </Button>
  );
}
