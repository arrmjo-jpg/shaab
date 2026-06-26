import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { TextField } from '@/components/form/TextField';
import { TextareaField } from '@/components/form/TextareaField';
import { useAuth } from '@/hooks/useAuth';
import type { GeneralUpdatePayload } from '@/types/settings.types';
import { SettingsSection } from '../components/SettingsSection';
import { SaveBar } from '../components/SaveBar';
import { useGeneralSettings, useUpdateGeneral } from '../hooks';
import { analyticsSchema, type AnalyticsValues } from '../schemas';

const FIELDS: (keyof AnalyticsValues)[] = [
  'google_meta_tag', 'google_analytics', 'facebook_pixel',
  'facebook_page_id', 'tiktok_pixel', 'instagram_pixel', 'other_meta',
];

const EMPTY = Object.fromEntries(FIELDS.map((k) => [k, ''])) as AnalyticsValues;

export default function AnalyticsSettingsPage() {
  const { t } = useTranslation('settings');
  const { hasPermission } = useAuth();
  const canEdit = hasPermission('settings.edit');
  const q = useGeneralSettings();
  const update = useUpdateGeneral();

  const values: AnalyticsValues = q.data ? { ...EMPTY, ...q.data.analytics } : EMPTY;

  const { register, handleSubmit } = useForm<AnalyticsValues>({
    resolver: zodResolver(analyticsSchema),
    values,
  });

  if (q.isLoading) return <PageSkeleton />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const onSave = handleSubmit((v) => {
    const payload: GeneralUpdatePayload = {};
    for (const k of FIELDS) payload[`analytics_${k}`] = v[k];
    update.mutate(payload);
  });

  return (
    <form onSubmit={onSave} className="space-y-5" noValidate>
      <SettingsSection title={t('analytics.title')} description={t('analytics.desc')}>
        <div className="grid gap-4 sm:grid-cols-2">
          {(['google_meta_tag', 'google_analytics', 'facebook_pixel', 'facebook_page_id', 'tiktok_pixel', 'instagram_pixel'] as const).map((k) => (
            <TextField key={k} label={t(`analytics.${k}`)} {...register(k)} />
          ))}
        </div>
        <TextareaField label={t('analytics.other_meta')} {...register('other_meta')} />
      </SettingsSection>
      <SaveBar saving={update.isPending} disabled={!canEdit} note={!canEdit ? t('common.noEditPermission') : undefined} />
    </form>
  );
}
