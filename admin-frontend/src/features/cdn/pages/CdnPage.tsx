import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { TextField } from '@/components/form/TextField';
import { SecretField } from '@/components/form/SecretField';
import { SwitchField } from '@/components/form/SwitchField';
import { SelectField } from '@/components/form/SelectField';
import { useAuth } from '@/hooks/useAuth';
import { SettingsSection } from '@/features/settings/components/SettingsSection';
import { SaveBar } from '@/features/settings/components/SaveBar';
import { CdnStatusCards } from '../components/CdnStatusCards';
import { CdnActions } from '../components/CdnActions';
import { CdnCacheRecommendations } from '../components/CdnCacheRecommendations';
import { useCdnStatus, useCdnSettings, useUpdateCdn } from '../hooks';
import { cdnSettingsSchema, type CdnSettingsValues } from '../schemas';

const PLANS = ['free', 'pro', 'business', 'enterprise'];

export default function CdnPage() {
  const { t } = useTranslation('cdn');
  const { hasPermission } = useAuth();
  const canEdit = hasPermission('cdn.edit');
  const canPurge = hasPermission('cdn.purge');

  const statusQ = useCdnStatus();
  const settingsQ = useCdnSettings();
  const update = useUpdateCdn();

  const c = settingsQ.data;
  const values: CdnSettingsValues = c
    ? {
        cdn_enabled: c.cdn_enabled,
        cdn_auto_purge: c.auto_purge,
        cdn_plan: c.plan,
        cdn_zone_id: c.zone_id,
        cdn_api_token: '',
      }
    : { cdn_enabled: false, cdn_auto_purge: false, cdn_plan: 'free', cdn_zone_id: '', cdn_api_token: '' };

  const { register, handleSubmit, control } = useForm<CdnSettingsValues>({
    resolver: zodResolver(cdnSettingsSchema),
    values,
  });

  if (statusQ.isLoading || settingsQ.isLoading) return <PageSkeleton />;
  if (statusQ.isError || !statusQ.data || settingsQ.isError || !settingsQ.data)
    return (
      <ErrorState
        onRetry={() => {
          void statusQ.refetch();
          void settingsQ.refetch();
        }}
      />
    );

  const onSave = handleSubmit((v) => {
    const payload: Record<string, string | boolean> = {
      cdn_enabled: v.cdn_enabled,
      cdn_auto_purge: v.cdn_auto_purge,
      cdn_plan: v.cdn_plan,
      cdn_zone_id: v.cdn_zone_id,
    };
    if (v.cdn_api_token) payload.cdn_api_token = v.cdn_api_token; // فارغ = إبقاء الرمز
    update.mutate(payload);
  });

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-bold">{t('title')}</h1>
        <p className="mt-1 text-sm text-muted-foreground">{t('subtitle')}</p>
      </header>

      <CdnStatusCards status={statusQ.data} />

      <form onSubmit={onSave} className="space-y-5" noValidate>
        <SettingsSection title={t('settings.title')} description={t('settings.desc')}>
          <Controller control={control} name="cdn_enabled" render={({ field }) => (
            <SwitchField label={t('settings.enabled')} checked={field.value} onChange={field.onChange} />
          )} />
          <Controller control={control} name="cdn_auto_purge" render={({ field }) => (
            <SwitchField label={t('settings.auto_purge')} checked={field.value} onChange={field.onChange} />
          )} />
          <Controller control={control} name="cdn_plan" render={({ field }) => (
            <SelectField
              label={t('settings.plan')}
              value={field.value}
              onChange={field.onChange}
              options={PLANS.map((p) => ({ value: p, label: t(`plans.${p}`) }))}
            />
          )} />
          <TextField label={t('settings.zone_id')} {...register('cdn_zone_id')} />
          <SecretField
            label={t('settings.api_token')}
            configured={settingsQ.data.api_token_configured}
            {...register('cdn_api_token')}
          />
        </SettingsSection>

        <SaveBar
          saving={update.isPending}
          disabled={!canEdit}
          note={!canEdit ? t('settings.noEditPermission') : undefined}
        />
      </form>

      <CdnActions canPurge={canPurge} />

      <CdnCacheRecommendations />
    </div>
  );
}
