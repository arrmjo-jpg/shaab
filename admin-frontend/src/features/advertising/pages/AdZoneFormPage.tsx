import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowRight, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/TextField';
import { TextareaField } from '@/components/form/TextareaField';
import { SelectField } from '@/components/form/SelectField';
import { SwitchField } from '@/components/form/SwitchField';
import { LoadingState } from '@/components/feedback';
import { paths } from '@/router/paths';
import { autoKey, sanitizeKey } from '@/lib/slug';
import { useAdZone, useCreateAdZone, useUpdateAdZone } from '../hooks';
import {
  AD_PLACEMENT_TYPES,
  AD_SELECTOR_STRATEGIES,
  type AdPlacementType,
  type AdSelectorStrategy,
} from '@/types/advertising.types';

interface ZoneForm {
  key: string;
  name: string;
  description: string;
  placement_type: AdPlacementType;
  selector_strategy: AdSelectorStrategy;
  width: string;
  height: string;
  locale: string;
  sort_order: string;
  is_active: boolean;
}

const EMPTY: ZoneForm = {
  key: '',
  name: '',
  description: '',
  placement_type: 'banner',
  selector_strategy: 'weighted',
  width: '',
  height: '',
  locale: '',
  sort_order: '0',
  is_active: true,
};

export default function AdZoneFormPage() {
  const { t } = useTranslation('advertising');
  const navigate = useNavigate();
  const params = useParams();
  const zoneId = params.id ? Number(params.id) : null;
  const isEdit = zoneId !== null;

  const detail = useAdZone(zoneId);
  const create = useCreateAdZone();
  const update = useUpdateAdZone();

  const [form, setForm] = useState<ZoneForm>(EMPTY);
  const [keyTouched, setKeyTouched] = useState(false);
  const patch = (p: Partial<ZoneForm>) => setForm((prev) => ({ ...prev, ...p }));

  // الاسم أوّلاً ثمّ مفتاح يُولَّد منه: نزامن المفتاح من الاسم ما لم يُحرَّر يدويًّا.
  const onNameChange = (name: string) =>
    setForm((prev) => ({ ...prev, name, key: keyTouched ? prev.key : autoKey(name) }));
  const onKeyChange = (raw: string) => {
    setKeyTouched(true);
    patch({ key: sanitizeKey(raw) });
  };

  useEffect(() => {
    if (!detail.data) return;
    const z = detail.data;
    // مفتاح صالح محفوظ ⇒ يُحترَم (touched)؛ مفتاح غير صالح (عربيّ قديم) ⇒ يُعاد توليده من الاسم.
    const validKey = /^[a-z0-9_]+$/.test(z.key);
    setKeyTouched(validKey);
    setForm({
      key: validKey ? z.key : autoKey(z.name),
      name: z.name,
      description: z.description ?? '',
      placement_type: z.placement_type ?? 'banner',
      selector_strategy: z.selector_strategy ?? 'weighted',
      width: z.width != null ? String(z.width) : '',
      height: z.height != null ? String(z.height) : '',
      locale: z.locale ?? '',
      sort_order: String(z.sort_order ?? 0),
      is_active: z.is_active,
    });
  }, [detail.data]);

  const keyValid = /^[a-z0-9_]+$/.test(form.key);
  const canSave = keyValid && form.name.trim().length >= 2;
  const saving = create.isPending || update.isPending;

  const placementOptions = useMemo(
    () => AD_PLACEMENT_TYPES.map((v) => ({ value: v, label: t(`placementType.${v}`) })),
    [t],
  );
  const strategyOptions = useMemo(
    () => AD_SELECTOR_STRATEGIES.map((v) => ({ value: v, label: t(`selectorStrategy.${v}`) })),
    [t],
  );
  const localeOptions = useMemo(
    () => [
      { value: '', label: t('locale.all') },
      { value: 'ar', label: t('locale.ar') },
      { value: 'en', label: t('locale.en') },
    ],
    [t],
  );

  const save = async () => {
    const payload = {
      key: form.key.trim(),
      name: form.name.trim(),
      description: form.description.trim() || null,
      placement_type: form.placement_type,
      selector_strategy: form.selector_strategy,
      width: form.width ? Number(form.width) : null,
      height: form.height ? Number(form.height) : null,
      locale: form.locale || null,
      sort_order: form.sort_order ? Number(form.sort_order) : 0,
      is_active: form.is_active,
    };
    try {
      if (isEdit) await update.mutateAsync({ id: zoneId as number, payload });
      else await create.mutateAsync(payload);
      navigate(paths.adZones);
    } catch {
      /* الخطأ يُعرَض عبر toast في الـ hook */
    }
  };

  if (isEdit && detail.isLoading) return <LoadingState />;

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-bold">{t(isEdit ? 'zones.form.editTitle' : 'zones.form.createTitle')}</h1>
        <Button variant="outline" size="sm" onClick={() => navigate(paths.adZones)}>
          <ArrowRight className="h-4 w-4 rtl:rotate-180" />
          {t('zones.form.back')}
        </Button>
      </header>

      <div className="grid gap-4 lg:grid-cols-2">
        <TextField label={t('zones.form.name')} value={form.name} onChange={(e) => onNameChange(e.target.value)} />
        <TextField label={t('zones.form.key')} value={form.key} onChange={(e) => onKeyChange(e.target.value)} placeholder="home_top" />
      </div>
      <p className="-mt-2 text-xs text-muted-foreground">{t('zones.form.keyHint')}</p>

      <TextareaField
        label={t('zones.form.description')}
        value={form.description}
        onChange={(e) => patch({ description: e.target.value })}
      />

      <div className="grid gap-4 lg:grid-cols-2">
        <SelectField
          label={t('zones.form.placementType')}
          options={placementOptions}
          value={form.placement_type}
          onChange={(e) => patch({ placement_type: e.target.value as AdPlacementType })}
        />
        <SelectField
          label={t('zones.form.selectorStrategy')}
          options={strategyOptions}
          value={form.selector_strategy}
          onChange={(e) => patch({ selector_strategy: e.target.value as AdSelectorStrategy })}
        />
        <TextField label={t('zones.form.width')} type="number" value={form.width} onChange={(e) => patch({ width: e.target.value })} />
        <TextField label={t('zones.form.height')} type="number" value={form.height} onChange={(e) => patch({ height: e.target.value })} />
        <SelectField
          label={t('zones.form.locale')}
          options={localeOptions}
          value={form.locale}
          onChange={(e) => patch({ locale: e.target.value })}
        />
        <TextField
          label={t('zones.form.sortOrder')}
          type="number"
          value={form.sort_order}
          onChange={(e) => patch({ sort_order: e.target.value })}
        />
      </div>

      <SwitchField
        label={t('zones.form.isActive')}
        description={t('zones.form.isActiveHint')}
        checked={form.is_active}
        onChange={(v) => patch({ is_active: v })}
      />

      <div className="flex justify-end">
        <Button onClick={() => void save()} disabled={!canSave || saving}>
          <Save className="h-4 w-4" />
          {saving ? t('zones.form.saving') : t('zones.form.save')}
        </Button>
      </div>
    </div>
  );
}
