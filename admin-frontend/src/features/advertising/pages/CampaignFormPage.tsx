import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowRight, Save, Send } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/TextField';
import { SelectField } from '@/components/form/SelectField';
import { LoadingState } from '@/components/feedback';
import { paths } from '@/router/paths';
import { useAdCampaign, useCreateAdCampaign, useTransitionAdCampaign, useUpdateAdCampaign } from '../hooks';
import { AD_PACING_MODES, type AdPacingMode } from '@/types/advertising.types';

interface CampaignForm {
  name: string;
  advertiser_name: string;
  priority: string;
  weight: string;
  starts_at: string;
  ends_at: string;
  budget_total: string;
  pacing_mode: AdPacingMode;
}

const EMPTY: CampaignForm = {
  name: '',
  advertiser_name: '',
  priority: '0',
  weight: '1',
  starts_at: '',
  ends_at: '',
  budget_total: '',
  pacing_mode: 'none',
};

/** ISO → قيمة datetime-local محلية (YYYY-MM-DDTHH:mm). */
function toLocalInput(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export default function CampaignFormPage() {
  const { t } = useTranslation('advertising');
  const navigate = useNavigate();
  const params = useParams();
  const campaignId = params.id ? Number(params.id) : null;
  const isEdit = campaignId !== null;

  const detail = useAdCampaign(campaignId);
  const create = useCreateAdCampaign();
  const update = useUpdateAdCampaign();
  const transition = useTransitionAdCampaign();

  const [form, setForm] = useState<CampaignForm>(EMPTY);
  const patch = (p: Partial<CampaignForm>) => setForm((prev) => ({ ...prev, ...p }));

  useEffect(() => {
    if (!detail.data) return;
    const c = detail.data;
    setForm({
      name: c.name,
      advertiser_name: c.advertiser_name ?? '',
      priority: String(c.priority ?? 0),
      weight: String(c.weight ?? 1),
      starts_at: toLocalInput(c.starts_at),
      ends_at: toLocalInput(c.ends_at),
      budget_total: c.budget_total ?? '',
      pacing_mode: c.pacing_mode ?? 'none',
    });
  }, [detail.data]);

  const canSave = form.name.trim().length >= 2;
  const saving = create.isPending || update.isPending || transition.isPending;
  const pacingOptions = useMemo(() => AD_PACING_MODES.map((v) => ({ value: v, label: t(`pacingMode.${v}`) })), [t]);

  const save = async (publish = false) => {
    const payload = {
      name: form.name.trim(),
      advertiser_name: form.advertiser_name.trim() || null,
      priority: form.priority ? Number(form.priority) : 0,
      weight: form.weight ? Number(form.weight) : 1,
      starts_at: form.starts_at ? new Date(form.starts_at).toISOString() : null,
      ends_at: form.ends_at ? new Date(form.ends_at).toISOString() : null,
      budget_total: form.budget_total ? Number(form.budget_total) : null,
      pacing_mode: form.pacing_mode,
    };
    try {
      if (isEdit) {
        await update.mutateAsync({ id: campaignId as number, payload });
      } else {
        // الإنشاء دائماً مسودّة؛ «نشر» = انتقال محروس (يُرفَض إن كانت غير مكتملة، والتوست يوضّح).
        const created = await create.mutateAsync(payload);
        if (publish) {
          try {
            await transition.mutateAsync({ id: created.id, status: 'scheduled' });
          } catch {
            /* مرفوض (غير مكتملة) — حُفظت مسودّة؛ ننتقل للقائمة لإكمالها ثمّ نشرها */
          }
        }
      }
      navigate(paths.adCampaigns);
    } catch {
      /* الخطأ يُعرَض عبر toast في الـ hook */
    }
  };

  if (isEdit && detail.isLoading) return <LoadingState />;

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-bold">{t(isEdit ? 'campaigns.form.editTitle' : 'campaigns.form.createTitle')}</h1>
          {isEdit && detail.data?.status ? (
            <Badge variant="muted">{t(`campaignStatus.${detail.data.status}`)}</Badge>
          ) : null}
        </div>
        <Button variant="outline" size="sm" onClick={() => navigate(paths.adCampaigns)}>
          <ArrowRight className="h-4 w-4 rtl:rotate-180" />
          {t('campaigns.form.back')}
        </Button>
      </header>

      {!isEdit ? (
        <p className="border border-border bg-muted/40 p-3 text-sm text-muted-foreground">{t('campaigns.form.draftNote')}</p>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-2">
        <TextField label={t('campaigns.form.name')} value={form.name} onChange={(e) => patch({ name: e.target.value })} />
        <TextField
          label={t('campaigns.form.advertiserName')}
          value={form.advertiser_name}
          onChange={(e) => patch({ advertiser_name: e.target.value })}
        />
        <TextField label={t('campaigns.form.priority')} type="number" value={form.priority} onChange={(e) => patch({ priority: e.target.value })} />
        <TextField label={t('campaigns.form.weight')} type="number" value={form.weight} onChange={(e) => patch({ weight: e.target.value })} />
        <TextField
          label={t('campaigns.form.startsAt')}
          type="datetime-local"
          value={form.starts_at}
          onChange={(e) => patch({ starts_at: e.target.value })}
        />
        <TextField
          label={t('campaigns.form.endsAt')}
          type="datetime-local"
          value={form.ends_at}
          onChange={(e) => patch({ ends_at: e.target.value })}
        />
        <TextField
          label={t('campaigns.form.budgetTotal')}
          type="number"
          step="0.01"
          value={form.budget_total}
          onChange={(e) => patch({ budget_total: e.target.value })}
        />
        <SelectField
          label={t('campaigns.form.pacingMode')}
          options={pacingOptions}
          value={form.pacing_mode}
          onChange={(e) => patch({ pacing_mode: e.target.value as AdPacingMode })}
        />
      </div>
      <p className="-mt-2 text-xs text-muted-foreground">{t('campaigns.form.budgetHint')}</p>

      <div className="flex flex-wrap justify-end gap-3">
        {isEdit ? (
          <Button onClick={() => void save()} disabled={!canSave || saving}>
            <Save className="h-4 w-4" />
            {saving ? t('campaigns.form.saving') : t('campaigns.form.save')}
          </Button>
        ) : (
          <>
            <Button variant="outline" onClick={() => void save(false)} disabled={!canSave || saving}>
              <Save className="h-4 w-4" />
              {t('campaigns.form.saveDraft')}
            </Button>
            <Button onClick={() => void save(true)} disabled={!canSave || saving}>
              <Send className="h-4 w-4" />
              {saving ? t('campaigns.form.saving') : t('campaigns.form.publish')}
            </Button>
          </>
        )}
      </div>
    </div>
  );
}
