import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowRight, Info, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/TextField';
import { SelectField } from '@/components/form/SelectField';
import { SwitchField } from '@/components/form/SwitchField';
import { LoadingState } from '@/components/feedback';
import { paths } from '@/router/paths';
import { useAdCampaigns, useAdCreative, useCreateAdCreative, useUpdateAdCreative } from '../hooks';
import { CreativeImagePicker } from '../components/CreativeImagePicker';
import {
  AD_CREATIVE_TYPES_SELECTABLE,
  type AdCreativeType,
  type AdCreativeUpsertPayload,
} from '@/types/advertising.types';

interface CreativeForm {
  ad_campaign_id: string;
  type: AdCreativeType;
  title: string;
  alt_text: string;
  landing_url: string;
  weight: string;
  is_active: boolean;
  media_asset_id: number | null;
  media_url: string | null;
  html_code: string;
}

const EMPTY: CreativeForm = {
  ad_campaign_id: '',
  type: 'image',
  title: '',
  alt_text: '',
  landing_url: '',
  weight: '1',
  is_active: true,
  media_asset_id: null,
  media_url: null,
  html_code: '',
};

export default function CreativeFormPage() {
  const { t } = useTranslation('advertising');
  const navigate = useNavigate();
  const params = useParams();
  const creativeId = params.id ? Number(params.id) : null;
  const isEdit = creativeId !== null;

  const detail = useAdCreative(creativeId);
  const campaignsQ = useAdCampaigns({ page: 1, per_page: 100, search: '', status: '', pacing_mode: '', sort: 'name', trashed: '' });
  const create = useCreateAdCreative();
  const update = useUpdateAdCreative();

  const [form, setForm] = useState<CreativeForm>(EMPTY);
  const patch = (p: Partial<CreativeForm>) => setForm((prev) => ({ ...prev, ...p }));

  useEffect(() => {
    if (!detail.data) return;
    const c = detail.data;
    setForm({
      ad_campaign_id: String(c.ad_campaign_id),
      type: c.type === 'video' ? 'image' : (c.type ?? 'image'),
      title: c.title,
      alt_text: c.alt_text ?? '',
      landing_url: c.landing_url ?? '',
      weight: String(c.weight ?? 1),
      is_active: c.is_active,
      media_asset_id: c.media_asset_id,
      media_url: c.media?.url ?? null,
      html_code: c.html_code ?? '',
    });
  }, [detail.data]);

  const campaigns = campaignsQ.data?.data ?? [];
  const typeOptions = useMemo(
    () => AD_CREATIVE_TYPES_SELECTABLE.map((v) => ({ value: v, label: t(`creativeType.${v}`) })),
    [t],
  );
  const campaignOptions = useMemo(
    () => [{ value: '', label: t('creatives.form.campaignPlaceholder') }, ...campaigns.map((c) => ({ value: String(c.id), label: c.name }))],
    [campaigns, t],
  );

  const typeComplete = form.type === 'image' ? form.media_asset_id !== null : form.html_code.trim() !== '';
  const canSave = form.title.trim().length >= 2 && typeComplete && (isEdit || form.ad_campaign_id !== '');
  const saving = create.isPending || update.isPending;

  const save = async () => {
    const payload: AdCreativeUpsertPayload = {
      type: form.type,
      title: form.title.trim(),
      alt_text: form.alt_text.trim() || null,
      landing_url: form.landing_url.trim() || null,
      weight: form.weight ? Number(form.weight) : 1,
      is_active: form.is_active,
      media_asset_id: form.type === 'image' ? form.media_asset_id : null,
      html_code: form.type === 'html' ? form.html_code : null,
    };
    if (!isEdit) payload.ad_campaign_id = Number(form.ad_campaign_id);

    try {
      if (isEdit) await update.mutateAsync({ id: creativeId as number, payload });
      else await create.mutateAsync(payload);
      navigate(paths.adCreatives);
    } catch {
      /* الخطأ يُعرَض عبر toast في الـ hook */
    }
  };

  if (isEdit && detail.isLoading) return <LoadingState />;

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-bold">{t(isEdit ? 'creatives.form.editTitle' : 'creatives.form.createTitle')}</h1>
        <Button variant="outline" size="sm" onClick={() => navigate(paths.adCreatives)}>
          <ArrowRight className="h-4 w-4 rtl:rotate-180" />
          {t('creatives.form.back')}
        </Button>
      </header>

      <div className="grid gap-4 lg:grid-cols-2">
        {isEdit ? (
          <div className="space-y-1.5">
            <p className="text-sm font-medium">{t('creatives.form.campaign')}</p>
            <div className="flex h-11 items-center border border-input bg-muted/40 px-3.5 text-sm text-muted-foreground">
              {detail.data?.campaign?.name ?? '—'}
            </div>
          </div>
        ) : (
          <SelectField
            label={t('creatives.form.campaign')}
            options={campaignOptions}
            value={form.ad_campaign_id}
            onChange={(e) => patch({ ad_campaign_id: e.target.value })}
          />
        )}
        <SelectField
          label={t('creatives.form.type')}
          options={typeOptions}
          value={form.type}
          onChange={(e) => patch({ type: e.target.value as AdCreativeType })}
        />
      </div>

      <p className="-mt-2 flex items-center gap-1.5 text-xs text-muted-foreground">
        <Info className="h-3.5 w-3.5" />
        {t('creatives.form.videoNote')}
      </p>

      <div className="grid gap-4 lg:grid-cols-2">
        <TextField label={t('creatives.form.title')} value={form.title} onChange={(e) => patch({ title: e.target.value })} />
        <TextField label={t('creatives.form.altText')} value={form.alt_text} onChange={(e) => patch({ alt_text: e.target.value })} />
        <TextField label={t('creatives.form.landingUrl')} type="url" value={form.landing_url} onChange={(e) => patch({ landing_url: e.target.value })} placeholder="https://" />
        <TextField label={t('creatives.form.weight')} type="number" value={form.weight} onChange={(e) => patch({ weight: e.target.value })} />
      </div>
      <p className="-mt-2 text-xs text-muted-foreground">{t('creatives.form.landingHint')}</p>

      {form.type === 'image' ? (
        <CreativeImagePicker
          value={form.media_url}
          onChange={(asset) => patch({ media_asset_id: asset?.id ?? null, media_url: asset?.url ?? null })}
          label={t('creatives.form.image')}
          hint={t('creatives.form.imageHint')}
        />
      ) : (
        <div className="grid gap-4 lg:grid-cols-2">
          <div className="space-y-1.5">
            <label className="text-sm font-medium">{t('creatives.form.html')}</label>
            <textarea
              value={form.html_code}
              onChange={(e) => patch({ html_code: e.target.value })}
              rows={12}
              spellCheck={false}
              className="w-full border border-input bg-background p-3 font-mono text-xs leading-relaxed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
            <p className="text-xs text-muted-foreground">{t('creatives.form.htmlHint')}</p>
          </div>
          <div className="space-y-1.5">
            <label className="text-sm font-medium">{t('creatives.form.preview')}</label>
            {/* معاينة معزولة: sandbox فارغ ⇒ لا تُنفَّذ سكربتات (المُنقّي يُطبَّق في الـ backend عند الحفظ). */}
            <iframe title="creative-preview" sandbox="" srcDoc={form.html_code} className="h-72 w-full border border-border bg-white" />
            <p className="text-xs text-muted-foreground">{t('creatives.form.previewHint')}</p>
          </div>
        </div>
      )}

      <SwitchField
        label={t('creatives.form.isActive')}
        description={t('creatives.form.isActiveHint')}
        checked={form.is_active}
        onChange={(v) => patch({ is_active: v })}
      />

      <div className="flex justify-end">
        <Button onClick={() => void save()} disabled={!canSave || saving}>
          <Save className="h-4 w-4" />
          {saving ? t('creatives.form.saving') : t('creatives.form.save')}
        </Button>
      </div>
    </div>
  );
}
