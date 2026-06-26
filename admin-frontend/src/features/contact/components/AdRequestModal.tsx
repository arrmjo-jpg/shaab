import { useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Download, ExternalLink, Loader2, Mail, Phone, Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Modal } from '@/components/ui/modal';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { adRequestService } from '@/services/inbox.service';
import { useAddAdNote, useAdRequest, useDeleteAd, useUpdateAdStatus } from '../contact.hooks';
import type { AdRequestStatus, AdStatusTarget } from '@/types/inbox.types';

const STATUS_TONE: Record<AdRequestStatus, 'default' | 'success' | 'muted' | 'destructive'> = {
  new: 'default',
  contacted: 'default',
  negotiating: 'default',
  completed: 'success',
  rejected: 'destructive',
  closed: 'muted',
};

const STATUS_TARGETS: AdStatusTarget[] = [
  'contacted',
  'negotiating',
  'completed',
  'rejected',
  'closed',
];

const selectCls =
  'h-10 rounded-xl border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

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

/** تفاصيل طلب إعلان — معلومات + تغيير الحالة + سجلّ ملاحظات + مسار زمنيّ. */
export function AdRequestModal({ id, onClose }: Props) {
  const { t, i18n } = useTranslation('inbox');
  const { hasPermission } = useAuth();
  const { confirm, error } = useToast();

  const canReview = hasPermission('ad-requests.review');
  const canDelete = hasPermission('ad-requests.delete');

  const q = useAdRequest(id);
  const status = useUpdateAdStatus();
  const addNote = useAddAdNote();
  const del = useDeleteAd();

  const [target, setTarget] = useState<'' | AdStatusTarget>('');
  const [note, setNote] = useState('');

  const row = q.data;

  const onApplyStatus = () => {
    if (id !== null && target !== '') status.mutate({ id, status: target });
  };

  const onAddNote = () => {
    if (id === null || note.trim().length < 2) return;
    addNote.mutate({ id, body: note.trim() }, { onSuccess: () => setNote('') });
  };

  const onDelete = async () => {
    if (id === null) return;
    if (
      await confirm({
        title: t('ads.confirm.deleteTitle'),
        text: t('ads.confirm.deleteText'),
        confirmText: t('common.delete'),
        cancelText: t('common.cancel'),
      })
    )
      del.mutate(id, { onSuccess: onClose });
  };

  // تنزيل المرفق — blob عبر العميل المُصادَق ثمّ تنزيل محلّي. لا عرض/تنفيذ/iframe للمحتوى.
  const onDownloadAttachment = async () => {
    if (id === null) return;
    try {
      const blob = await adRequestService.downloadAttachment(id);
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = row?.attachment_name ?? 'attachment';
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    } catch {
      error(t('ads.attachment.error'));
    }
  };

  return (
    <Modal
      open={id !== null}
      onClose={onClose}
      title={t('ads.detail.title')}
      description={row ? row.company_name : undefined}
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
          {/* الترويسة: الشركة + الحالة */}
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="min-w-0">
              <p className="text-base font-bold">{row.company_name}</p>
              <p className="mt-0.5 text-sm text-muted-foreground">{row.contact_name}</p>
            </div>
            <Badge variant={STATUS_TONE[row.status]}>{t(`ads.status.${row.status}`)}</Badge>
          </div>

          {/* بيانات الطلب */}
          <section className="grid gap-x-6 gap-y-3 rounded-xl border border-border bg-muted/30 p-4 sm:grid-cols-2">
            <Field label={t('ads.detail.email')}>
              <a href={`mailto:${row.email}`} className="inline-flex items-center gap-1 hover:text-foreground">
                <Mail className="h-3.5 w-3.5" />
                {row.email}
              </a>
            </Field>
            <Field label={t('ads.detail.phone')}>
              {row.phone ? (
                <a href={`tel:${row.phone}`} className="inline-flex items-center gap-1 hover:text-foreground" dir="ltr">
                  <Phone className="h-3.5 w-3.5" />
                  {row.phone}
                </a>
              ) : (
                '—'
              )}
            </Field>
            <Field label={t('ads.detail.adType')}>{t(`ads.ad_type.${row.ad_type}`)}</Field>
            {row.website ? (
              <Field label={t('ads.detail.website')}>
                <a
                  href={row.website}
                  target="_blank"
                  rel="noopener noreferrer nofollow"
                  className="inline-flex items-center gap-1 break-all text-primary hover:underline"
                  dir="ltr"
                >
                  <ExternalLink className="h-3.5 w-3.5 shrink-0" />
                  {row.website}
                </a>
              </Field>
            ) : null}
          </section>

          {/* تفاصيل الطلب */}
          <section>
            <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('ads.detail.description')}
            </h3>
            <p className="whitespace-pre-wrap rounded-xl border border-border p-4 text-sm leading-relaxed">
              {row.description}
            </p>
          </section>

          {/* المرفق (صورة/ZIP) — تنزيل فقط؛ لا عرض/تنفيذ/iframe لمحتواه */}
          {row.has_attachment ? (
            <section>
              <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {t('ads.attachment.title')}
              </h3>
              <Button variant="outline" size="sm" onClick={() => void onDownloadAttachment()}>
                <Download className="h-4 w-4" />
                {row.ad_type === 'html' ? t('ads.attachment.downloadZip') : t('ads.attachment.downloadImage')}
              </Button>
              {row.attachment_name ? (
                <p className="mt-1 break-all text-xs text-muted-foreground" dir="ltr">
                  {row.attachment_name}
                </p>
              ) : null}
            </section>
          ) : null}

          {/* تغيير الحالة (صلاحية review) */}
          {canReview ? (
            <section className="rounded-xl border border-border p-4">
              <h3 className="mb-2 text-sm font-semibold">{t('ads.status_action.title')}</h3>
              <div className="flex flex-wrap items-center gap-2">
                <select
                  className={selectCls}
                  value={target}
                  onChange={(e) => setTarget(e.target.value as '' | AdStatusTarget)}
                >
                  <option value="" disabled>
                    {t('ads.status_action.title')}
                  </option>
                  {STATUS_TARGETS.map((s) => (
                    <option key={s} value={s} disabled={row.status === s}>
                      {t(`ads.status.${s}`)}
                    </option>
                  ))}
                </select>
                <Button
                  size="sm"
                  onClick={onApplyStatus}
                  disabled={status.isPending || target === '' || target === row.status}
                >
                  {t('ads.status_action.apply')}
                </Button>
              </div>
            </section>
          ) : null}

          {/* الملاحظات الداخليّة */}
          <section>
            <h3 className="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('ads.notes.title')}
            </h3>

            {canReview ? (
              <div className="mb-4 flex flex-col gap-2 sm:flex-row">
                <textarea
                  value={note}
                  onChange={(e) => setNote(e.target.value)}
                  placeholder={t('ads.notes.placeholder')}
                  rows={2}
                  className="flex-1 resize-y rounded-xl border border-input bg-background p-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                />
                <Button
                  size="sm"
                  className="self-start"
                  onClick={onAddNote}
                  disabled={addNote.isPending || note.trim().length < 2}
                >
                  {addNote.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                  {t('ads.notes.add')}
                </Button>
              </div>
            ) : null}

            {row.notes && row.notes.length > 0 ? (
              <ul className="space-y-3">
                {row.notes.map((n) => (
                  <li key={n.id} className="rounded-xl border border-border p-3">
                    <p className="whitespace-pre-wrap text-sm leading-relaxed">{n.body}</p>
                    <p className="mt-1 text-xs text-muted-foreground">
                      {n.author ? `${n.author} · ` : ''}
                      {fmt(n.created_at, i18n.language)}
                    </p>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-sm text-muted-foreground">{t('ads.notes.empty')}</p>
            )}
          </section>

          {/* المسار الزمنيّ */}
          <section>
            <h3 className="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('ads.timeline.title')}
            </h3>
            <ul className="space-y-3 text-sm">
              <TimelineItem label={t('ads.timeline.created')} value={fmt(row.created_at, i18n.language)} />
              {row.read_at ? (
                <TimelineItem label={t('ads.timeline.read')} value={fmt(row.read_at, i18n.language)} />
              ) : null}
              {row.reviewed_at ? (
                <TimelineItem
                  label={t('ads.timeline.reviewed')}
                  value={`${fmt(row.reviewed_at, i18n.language)}${row.reviewed_by ? ` · ${row.reviewed_by}` : ''}`}
                />
              ) : null}
            </ul>
          </section>
        </div>
      )}
    </Modal>
  );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="min-w-0">
      <p className="text-xs text-muted-foreground">{label}</p>
      <div className="mt-0.5 text-sm">{children}</div>
    </div>
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
