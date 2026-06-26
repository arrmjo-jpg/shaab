import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Check, Pencil, Trash2, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { storageUrl } from '@/lib/storage';
import { useToast } from '@/hooks/useToast';
import { useChatMessages, useDeleteMessage, useUpdateMessage } from '../chat.hooks';
import type { ChatMessage } from '@/types/chat.types';

function fmtTime(iso: string | null, locale: string): string {
  if (!iso) return '';
  return new Date(iso).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
}

export function MessageThread({
  conversationId,
  showSenderAvatar = false,
}: {
  conversationId: number;
  showSenderAvatar?: boolean;
}) {
  const { t, i18n } = useTranslation('chat');
  const { confirm } = useToast();
  const q = useChatMessages(conversationId);
  const del = useDeleteMessage();
  const upd = useUpdateMessage();

  const [editingId, setEditingId] = useState<number | null>(null);
  const [editText, setEditText] = useState('');
  const bottomRef = useRef<HTMLDivElement>(null);

  // الـ backend يُرجِع الأحدث أولاً — نعكس للعرض الزمنيّ (الأقدم أعلى).
  const messages = useMemo(() => [...(q.data?.data ?? [])].reverse(), [q.data]);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages.length]);

  const onDelete = async (m: ChatMessage) => {
    if (
      await confirm({
        title: t('message.deleteTitle'),
        text: t('message.deleteText'),
        confirmText: t('message.deleteYes'),
        cancelText: t('message.cancel'),
      })
    )
      del.mutate(m.id);
  };

  const saveEdit = (m: ChatMessage) => {
    const body = editText.trim();
    if (body && body !== m.body) upd.mutate({ id: m.id, body });
    setEditingId(null);
  };

  if (q.isLoading) {
    return <p className="p-6 text-sm text-muted-foreground">{t('loadingMessages')}</p>;
  }

  if (messages.length === 0) {
    return <p className="p-6 text-sm text-muted-foreground">{t('noMessages')}</p>;
  }

  return (
    <div className="flex-1 space-y-3 overflow-y-auto p-4">
      {messages.map((m) => {
        // Tombstone — رسالة محذوفة، محايدة بلا محتوى.
        if (m.deleted) {
          return (
            <div key={m.id} className="flex justify-center">
              <span className="border border-border bg-muted/40 px-3 py-1 text-xs italic text-muted-foreground">
                {t('message.deleted')}
              </span>
            </div>
          );
        }

        // صورة المرسِل بجانب رسالة الآخرين في المحادثات الجماعية فقط.
        const senderAvatar =
          showSenderAvatar && !m.mine ? storageUrl(m.sender?.avatar) : null;

        return (
          <div key={m.id} className={cn('flex items-end gap-2', m.mine ? 'justify-end' : 'justify-start')}>
            {showSenderAvatar && !m.mine ? (
              senderAvatar ? (
                <img src={senderAvatar} alt="" className="h-8 w-8 shrink-0 object-cover" />
              ) : (
                <span className="h-8 w-8 shrink-0 bg-muted" />
              )
            ) : null}
            <div className="group max-w-[78%] px-3 py-2 text-sm">
              {!m.mine ? (
                <p className="mb-0.5 text-xs font-semibold text-muted-foreground">
                  {m.sender?.name ?? '—'}
                </p>
              ) : null}

              {m.attachment?.is_image && (m.attachment.thumb ?? m.attachment.url) ? (
                <a href={m.attachment.url ?? '#'} target="_blank" rel="noopener noreferrer">
                  <img
                    src={(m.attachment.thumb ?? m.attachment.url) as string}
                    alt={m.attachment.name}
                    loading="lazy"
                    className="mb-1 max-h-48 max-w-full border border-border object-cover"
                  />
                </a>
              ) : null}

              {editingId === m.id ? (
                <div className="flex items-end gap-1">
                  <textarea
                    value={editText}
                    onChange={(e) => setEditText(e.target.value)}
                    rows={2}
                    className="min-w-[180px] flex-1 border border-input bg-background px-2 py-1 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  />
                  <button type="button" onClick={() => saveEdit(m)} title={t('message.save')} className="text-emerald-600">
                    <Check className="h-4 w-4" />
                  </button>
                  <button type="button" onClick={() => setEditingId(null)} title={t('message.cancel')} className="text-muted-foreground">
                    <X className="h-4 w-4" />
                  </button>
                </div>
              ) : m.body ? (
                <p className="whitespace-pre-wrap break-words">{m.body}</p>
              ) : null}

              <div className="mt-0.5 flex items-center gap-2">
                <span className="text-[10px] text-muted-foreground">{fmtTime(m.created_at, i18n.language)}</span>
                {m.edited_at ? <span className="text-[10px] text-muted-foreground">({t('message.edited')})</span> : null}
                {m.mine && editingId !== m.id ? (
                  <span className="ms-auto hidden items-center gap-1.5 group-hover:flex">
                    <button
                      type="button"
                      onClick={() => {
                        setEditingId(m.id);
                        setEditText(m.body ?? '');
                      }}
                      title={t('message.edit')}
                      className="text-muted-foreground hover:text-foreground"
                    >
                      <Pencil className="h-3 w-3" />
                    </button>
                    <button
                      type="button"
                      onClick={() => void onDelete(m)}
                      title={t('message.delete')}
                      className="text-muted-foreground hover:text-destructive"
                    >
                      <Trash2 className="h-3 w-3" />
                    </button>
                  </span>
                ) : null}
              </div>
            </div>
          </div>
        );
      })}
      <div ref={bottomRef} />
    </div>
  );
}
