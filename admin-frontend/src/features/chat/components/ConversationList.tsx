import { useTranslation } from 'react-i18next';
import { Plus, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { storageUrl } from '@/lib/storage';
import type { ChatConversation, ChatParticipant } from '@/types/chat.types';

interface Props {
  conversations: ChatConversation[];
  activeId: number | null;
  currentUserId: number | null;
  onSelect: (id: number) => void;
  onNew: () => void;
  loading?: boolean;
}

/** صورة المحادثة: للمباشرة = صورة الطرف الآخر؛ للمجموعة/العامة = أيقونة جماعية. */
function ConversationAvatar({
  conversation,
  currentUserId,
}: {
  conversation: ChatConversation;
  currentUserId: number | null;
}) {
  const other: ChatParticipant | undefined =
    conversation.type === 'direct'
      ? conversation.participants.find((p) => p.id !== currentUserId)
      : undefined;
  const avatarUrl = storageUrl(other?.avatar);

  if (avatarUrl) {
    return (
      <img
        src={avatarUrl}
        alt=""
        className="h-10 w-10 shrink-0 object-cover"
      />
    );
  }

  return (
    <div className="flex h-10 w-10 shrink-0 items-center justify-center bg-muted text-muted-foreground">
      <Users className="h-5 w-5" />
    </div>
  );
}

export function ConversationList({
  conversations,
  activeId,
  currentUserId,
  onSelect,
  onNew,
  loading,
}: Props) {
  const { t } = useTranslation('chat');

  return (
    <div className="flex h-full flex-col border-e border-border">
      <div className="flex items-center justify-between gap-2 p-3">
        <h2 className="text-sm font-bold">{t('title')}</h2>
        <Button size="sm" variant="outline" onClick={onNew}>
          <Plus className="h-4 w-4" />
          {t('new')}
        </Button>
      </div>

      <div className="flex-1 overflow-y-auto">
        {!loading && conversations.length === 0 ? (
          <p className="p-4 text-sm text-muted-foreground">{t('empty')}</p>
        ) : null}

        {conversations.map((c) => (
          <button
            key={c.id}
            type="button"
            onClick={() => onSelect(c.id)}
            className={cn(
              'flex w-full items-center gap-3 p-3 text-start transition-colors hover:bg-accent/40',
              activeId === c.id && 'bg-accent/60',
            )}
          >
            <ConversationAvatar conversation={c} currentUserId={currentUserId} />
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-medium">{c.title}</p>
              <p className="truncate text-xs text-muted-foreground">
                {c.last_message?.body ?? '—'}
              </p>
            </div>
            {c.unread_count > 0 ? (
              <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-destructive px-1.5 text-[10px] font-bold text-destructive-foreground">
                {c.unread_count > 99 ? '99+' : c.unread_count}
              </span>
            ) : null}
          </button>
        ))}
      </div>
    </div>
  );
}
