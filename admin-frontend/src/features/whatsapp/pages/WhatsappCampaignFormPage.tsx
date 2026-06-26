import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowRight, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { TextField } from '@/components/form/TextField';
import { SelectField } from '@/components/form/SelectField';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { paths } from '@/router/paths';
import { whatsappService } from '@/services/whatsapp.service';
import { useCreateWhatsappCampaign, useWhatsappGroups } from '../hooks';
import { WhatsappArticlePicker } from '../components/WhatsappArticlePicker';
import { WhatsappMediaPicker } from '../components/WhatsappMediaPicker';
import type { WhatsappCampaignType, WhatsappMediaType } from '@/types/whatsapp.types';

const areaCls =
  'flex w-full border border-input bg-background px-3.5 py-2.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

/** إنشاء حملة واتساب — إعلانية (نص/صورة/فيديو/مع نص) أو خبر. حفظ كمسوّدة/مجدوَلة ثم التفاصيل للإرسال. */
export default function WhatsappCampaignFormPage() {
  const { t } = useTranslation('whatsapp');
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const { error: toastError } = useToast();

  const canSend = hasPermission('whatsapp.send');
  const groupsQ = useWhatsappGroups();
  const create = useCreateWhatsappCampaign();

  const [name, setName] = useState('');
  const [type, setType] = useState<WhatsappCampaignType>('promo');
  const [groupIds, setGroupIds] = useState<number[]>([]);
  const [messageText, setMessageText] = useState('');
  const [mediaType, setMediaType] = useState<WhatsappMediaType>('none');
  const [mediaAssetId, setMediaAssetId] = useState<number | null>(null);
  const [article, setArticle] = useState<{ id: number; title: string } | null>(null);
  const [scheduledAt, setScheduledAt] = useState('');

  // عدد المستلمين المتوقَّع — حيّ مع تغيّر المجموعات (قبل الإرسال).
  const countQ = useQuery({
    queryKey: ['whatsapp', 'recipients-count', [...groupIds].sort()],
    queryFn: () => whatsappService.recipientsCount(groupIds),
    enabled: groupIds.length > 0,
  });
  const recipients = groupIds.length > 0 ? (countQ.data ?? null) : 0;

  const toggleGroup = (id: number) =>
    setGroupIds((prev) => (prev.includes(id) ? prev.filter((g) => g !== id) : [...prev, id]));

  const groups = groupsQ.data ?? [];

  const valid = useMemo(() => {
    if (name.trim().length < 2 || groupIds.length === 0) return false;
    if (type === 'article') return article !== null;
    const hasText = messageText.trim() !== '';
    const hasMedia = mediaType !== 'none';
    if (!hasText && !hasMedia) return false;
    if (hasMedia && mediaAssetId === null) return false;
    return true;
  }, [name, groupIds, type, article, messageText, mediaType, mediaAssetId]);

  const submit = () => {
    if (!valid) {
      toastError(t('campaigns.form.invalid'));
      return;
    }
    const payload =
      type === 'article'
        ? { name: name.trim(), type, groups: groupIds, article_id: article!.id, scheduled_at: scheduledAt || null }
        : {
            name: name.trim(),
            type,
            groups: groupIds,
            message_text: messageText.trim() ? messageText.trim() : null,
            media_type: mediaType,
            media_asset_id: mediaType === 'none' ? null : mediaAssetId,
            scheduled_at: scheduledAt || null,
          };
    create.mutate(payload, {
      onSuccess: (c) => navigate(paths.whatsappCampaignDetail.replace(':id', String(c.id))),
    });
  };

  if (!canSend) return null;

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <header className="flex items-center gap-3">
        <Button variant="ghost" size="icon" aria-label={t('common.back', { ns: 'common' })} onClick={() => navigate(paths.whatsappCampaigns)}>
          <ArrowRight className="h-5 w-5" />
        </Button>
        <h1 className="text-2xl font-bold">{t('campaigns.form.createTitle')}</h1>
      </header>

      <div className="space-y-5 border border-border p-5">
        <TextField label={t('campaigns.form.name')} value={name} onChange={(e) => setName(e.target.value)} maxLength={150} />

        {/* النوع */}
        <div>
          <Label>{t('campaigns.form.type')}</Label>
          <div className="mt-1 flex gap-2">
            {(['promo', 'article'] as const).map((ty) => (
              <Button key={ty} type="button" variant={type === ty ? 'default' : 'outline'} onClick={() => setType(ty)}>
                {t(`campaigns.type.${ty}`)}
              </Button>
            ))}
          </div>
        </div>

        {/* إعلانية */}
        {type === 'promo' ? (
          <>
            <div>
              <Label htmlFor="wa-msg">{t('campaigns.form.message')}</Label>
              <textarea id="wa-msg" rows={4} value={messageText} onChange={(e) => setMessageText(e.target.value)} maxLength={4096} className={areaCls} />
              <p className="mt-1 text-xs text-muted-foreground">{t('campaigns.form.autoFooter')}</p>
            </div>
            <SelectField
              label={t('campaigns.form.mediaType')}
              value={mediaType}
              onChange={(e) => {
                setMediaType(e.target.value as WhatsappMediaType);
                setMediaAssetId(null);
              }}
              options={[
                { value: 'none', label: t('campaigns.form.mediaNone') },
                { value: 'image', label: t('campaigns.form.mediaImage') },
                { value: 'video', label: t('campaigns.form.mediaVideo') },
              ]}
            />
            {mediaType !== 'none' ? (
              <WhatsappMediaPicker kind={mediaType} selectedId={mediaAssetId} onSelect={(a) => setMediaAssetId(a?.id ?? null)} />
            ) : null}
          </>
        ) : (
          <div>
            <Label>{t('campaigns.form.article')}</Label>
            <p className="mb-2 text-xs text-muted-foreground">{t('campaigns.form.articleHint')}</p>
            <WhatsappArticlePicker selected={article} onSelect={setArticle} />
          </div>
        )}

        {/* المجموعات + عدد المستلمين */}
        <div>
          <Label>{t('campaigns.form.groups')}</Label>
          <div className="mt-1 grid gap-2 sm:grid-cols-2">
            {groups.map((g) => (
              <label key={g.id} className="inline-flex cursor-pointer items-center gap-2 border border-border bg-background px-3 py-2 text-sm">
                <input type="checkbox" checked={groupIds.includes(g.id)} onChange={() => toggleGroup(g.id)} className="h-4 w-4 accent-primary" />
                <span>{g.name}</span>
                <span className="ms-auto text-xs text-muted-foreground">{g.contacts_count}</span>
              </label>
            ))}
          </div>
          {groupIds.length > 0 ? (
            <p className="mt-2 text-sm">
              {t('campaigns.form.recipients')}:{' '}
              <strong>{recipients === null ? '…' : recipients}</strong>
            </p>
          ) : null}
        </div>

        {/* جدولة اختيارية */}
        <div>
          <Label htmlFor="wa-sched">{t('campaigns.form.scheduledAt')}</Label>
          <Input id="wa-sched" type="datetime-local" value={scheduledAt} onChange={(e) => setScheduledAt(e.target.value)} dir="ltr" />
          <p className="mt-1 text-xs text-muted-foreground">{t('campaigns.form.scheduledHint')}</p>
        </div>

        <div className="flex justify-end border-t border-border pt-4">
          <Button onClick={submit} disabled={create.isPending}>
            <Save className="h-4 w-4" />
            {create.isPending ? t('campaigns.form.saving') : t('campaigns.form.save')}
          </Button>
        </div>
      </div>
    </div>
  );
}
