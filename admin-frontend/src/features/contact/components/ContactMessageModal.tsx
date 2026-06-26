import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Loader2, Mail, Phone, Send, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Modal } from '@/components/ui/modal';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import {
  useContactMessage,
  useDeleteContact,
  useReplyContact,
  useUpdateContactStatus,
} from '../contact.hooks';
import type {
  ContactMessageStatus,
  ContactMessageType,
  ContactStatusTarget,
} from '@/types/inbox.types';

const STATUS_TONE: Record<ContactMessageStatus, 'default' | 'success' | 'muted'> = {
  new: 'default',
  in_review: 'muted',
  replied: 'success',
  closed: 'muted',
};

const STATUS_TARGETS: ContactStatusTarget[] = ['in_review', 'closed'];

function fmt(iso: string | null, locale: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString(locale, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

interface Props {
  id: number | null;
  onClose: () => void;
}

/** تفاصيل رسالة اتصال — معلومات + نصّ + ردّ + تغيير الحالة + مسار زمنيّ. */
export function ContactMessageModal({ id, onClose }: Props) {
  const { t, i18n } = useTranslation('inbox');
  const { hasPermission } = useAuth();
  const { confirm } = useToast();

  const canReply = hasPermission('contact-messages.reply');
  const canDelete = hasPermission('contact-messages.delete');

  const q = useContactMessage(id);
  const reply = useReplyContact();
  const status = useUpdateContactStatus();
  const del = useDeleteContact();

  const [replyBody, setReplyBody] = useState('');

  const row = q.data;

  const onReply = () => {
    if (id === null || replyBody.trim().length < 2) return;
    reply.mutate({ id, body: replyBody.trim() }, { onSuccess: () => setReplyBody('') });
  };

  const onStatus = (target: ContactStatusTarget) => {
    if (id !== null) status.mutate({ id, status: target });
  };

  const onDelete = async () => {
    if (id === null) return;
    if (
      await confirm({
        title: t('contact.confirm.deleteTitle'),
        text: t('contact.confirm.deleteText'),
        confirmText: t('common.delete'),
        cancelText: t('common.cancel'),
      })
    )
      del.mutate(id, { onSuccess: onClose });
  };

  return (
    <Modal
      open={id !== null}
      onClose={onClose}
      title={t('contact.detail.title')}
      description={row ? row.subject : undefined}
      size="lg"
      footer={
        <>
          {canDelete && row ? (
            <Button
              variant="outline"
              size="sm"
              className="me-auto text-destructive hover:text-destructive"
              onClick={() => void onDelete()}
              disabled={del.isPending}
            >
              <Trash2 className="h-4 w-4" />
              {t('common.delete')}
            </Button>
          ) : null}
          <Button variant="outline" size="sm" onClick={onClose}>
            {t('common.close')}
          </Button>
        </>
      }
    >
      {q.isLoading || !row ? (
        <div className="flex items-center justify-center py-16">
          <Loader2 className="h-6 w-6 animate-spin text-primary" />
        </div>
      ) : (
        <div className="space-y-6">
          {/* الترويسة: المُرسِل + الحالة + النوع */}
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="min-w-0">
              <p className="text-base font-bold">{row.name}</p>
              <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                <a href={`mailto:${row.email}`} className="inline-flex items-center gap-1 hover:text-foreground">
                  <Mail className="h-3.5 w-3.5" />
                  {row.email}
                </a>
                {row.phone ? (
                  <a href={`tel:${row.phone}`} className="inline-flex items-center gap-1 hover:text-foreground" dir="ltr">
                    <Phone className="h-3.5 w-3.5" />
                    {row.phone}
                  </a>
                ) : null}
              </div>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="muted">{t(`contact.type.${row.type as ContactMessageType}`)}</Badge>
              <Badge variant={STATUS_TONE[row.status]}>{t(`contact.status.${row.status}`)}</Badge>
            </div>
          </div>

          {/* نصّ الرسالة */}
          <section>
            <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('contact.detail.message')}
            </h3>
            <p className="whitespace-pre-wrap rounded-xl border border-border bg-muted/30 p-4 text-sm leading-relaxed">
              {row.message}
            </p>
          </section>

          {/* الردّ السابق (إن وُجد) */}
          {row.reply_body ? (
            <section>
              <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {t('contact.reply.previous')}
              </h3>
              <p className="whitespace-pre-wrap rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4 text-sm leading-relaxed">
                {row.reply_body}
              </p>
              <p className="mt-1 text-xs text-muted-foreground">
                {row.replied_by ? `${t('common.by')} ${row.replied_by} · ` : ''}
                {fmt(row.replied_at, i18n.language)}
              </p>
            </section>
          ) : null}

          {/* نموذج الردّ + تغيير الحالة (صلاحية reply) */}
          {canReply ? (
            <section className="space-y-4 rounded-xl border border-border p-4">
              <div>
                <h3 className="mb-2 text-sm font-semibold">{t('contact.reply.title')}</h3>
                <textarea
                  value={replyBody}
                  onChange={(e) => setReplyBody(e.target.value)}
                  placeholder={t('contact.reply.placeholder')}
                  rows={4}
                  className="w-full resize-y rounded-xl border border-input bg-background p-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                />
                <div className="mt-2 flex justify-end">
                  <Button size="sm" onClick={onReply} disabled={reply.isPending || replyBody.trim().length < 2}>
                    {reply.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                    {t('contact.reply.send')}
                  </Button>
                </div>
              </div>

              <div className="border-t border-border pt-4">
                <h3 className="mb-2 text-sm font-semibold">{t('contact.status_action.title')}</h3>
                <div className="flex flex-wrap gap-2">
                  {STATUS_TARGETS.map((target) => (
                    <Button
                      key={target}
                      variant="outline"
                      size="sm"
                      disabled={status.isPending || row.status === target}
                      onClick={() => onStatus(target)}
                    >
                      {t(`contact.status_action.to_${target}`)}
                    </Button>
                  ))}
                </div>
              </div>
            </section>
          ) : null}

          {/* المسار الزمنيّ */}
          <section>
            <h3 className="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('contact.timeline.title')}
            </h3>
            <ul className="space-y-3 text-sm">
              <TimelineItem label={t('contact.timeline.created')} value={fmt(row.created_at, i18n.language)} />
              {row.read_at ? (
                <TimelineItem label={t('contact.timeline.read')} value={fmt(row.read_at, i18n.language)} />
              ) : null}
              {row.status === 'replied' ? (
                <TimelineItem
                  label={t('contact.timeline.replied')}
                  value={`${fmt(row.replied_at, i18n.language)}${row.replied_by ? ` · ${row.replied_by}` : ''}`}
                />
              ) : null}
            </ul>
          </section>
        </div>
      )}
    </Modal>
  );
}

function TimelineItem({ label, value }: { label: string; value: string }) {
  return (
    <li className="flex items-center gap-3">
      <span className="h-2 w-2 shrink-0 rounded-full bg-primary" />
      <span className="font-medium">{label}</span>
      <span className="ms-auto text-xs text-muted-foreground">{value}</span>
    </li>
  );
}
