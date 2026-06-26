import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { TextField } from '@/components/form/TextField';
import { TextareaField } from '@/components/form/TextareaField';
import { SwitchField } from '@/components/form/SwitchField';
import { useAuth } from '@/hooks/useAuth';
import { SettingsSection } from '../components/SettingsSection';
import { SaveBar } from '../components/SaveBar';
import { useGeneralSettings, useUpdateGeneral } from '../hooks';
import { generalSchema, type GeneralValues } from '../schemas';

const EMPTY: GeneralValues = {
  site_name: '', site_email: '', site_url: '', timezone: '', site_phone: '',
  site_description: '', copyright_text: '', footer_extra_text: '', cookie_policy_text: '',
  latitude: '', longitude: '', comments_enabled: false, maintenance_mode: false,
};

export default function GeneralSettingsPage() {
  const { t } = useTranslation('settings');
  const { hasPermission } = useAuth();
  const canEdit = hasPermission('settings.edit');
  const q = useGeneralSettings();
  const update = useUpdateGeneral();

  const s = q.data?.site;
  const values: GeneralValues = s
    ? {
        site_name: s.site_name,
        site_email: s.site_email,
        site_url: s.site_url,
        timezone: s.timezone,
        site_phone: s.site_phone,
        site_description: s.site_description,
        copyright_text: s.copyright_text,
        footer_extra_text: s.footer_extra_text,
        cookie_policy_text: s.cookie_policy_text,
        latitude: s.latitude ?? '',
        longitude: s.longitude ?? '',
        comments_enabled: s.comments_enabled,
        maintenance_mode: s.maintenance_mode,
      }
    : EMPTY;

  const { register, handleSubmit, control, formState } = useForm<GeneralValues>({
    resolver: zodResolver(generalSchema),
    values,
  });

  if (q.isLoading) return <PageSkeleton />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const onSave = handleSubmit((v) => update.mutate(v));

  return (
    <form onSubmit={onSave} className="space-y-5" noValidate>
      <SettingsSection title={t('general.siteCard')} description={t('general.desc')}>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField label={t('general.site_name')} error={formState.errors.site_name} {...register('site_name')} />
          <TextField label={t('general.site_email')} type="email" error={formState.errors.site_email} {...register('site_email')} />
          <TextField label={t('general.site_url')} error={formState.errors.site_url} {...register('site_url')} />
          <TextField label={t('general.timezone')} error={formState.errors.timezone} {...register('timezone')} />
          <TextField label={t('general.site_phone')} {...register('site_phone')} />
        </div>
      </SettingsSection>

      <SettingsSection title={t('general.footerCard')}>
        <TextareaField label={t('general.site_description')} {...register('site_description')} />
        <p className="-mt-2 text-xs text-muted-foreground">{t('general.site_description_hint')}</p>
        <TextField label={t('general.copyright_text')} {...register('copyright_text')} />
        <TextareaField label={t('general.footer_extra_text')} {...register('footer_extra_text')} />
        <TextareaField label={t('general.cookie_policy_text')} {...register('cookie_policy_text')} />
      </SettingsSection>

      <SettingsSection title={t('general.controlsCard')}>
        <Controller
          control={control}
          name="maintenance_mode"
          render={({ field }) => (
            <SwitchField
              label={t('general.maintenance_mode')}
              description={t('general.maintenance_mode_desc')}
              checked={field.value}
              onChange={field.onChange}
            />
          )}
        />
        <Controller
          control={control}
          name="comments_enabled"
          render={({ field }) => (
            <SwitchField label={t('general.comments_enabled')} checked={field.value} onChange={field.onChange} />
          )}
        />
      </SettingsSection>

      <SettingsSection title={t('general.mapCard')}>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField label={t('general.latitude')} {...register('latitude')} />
          <TextField label={t('general.longitude')} {...register('longitude')} />
        </div>
      </SettingsSection>

      <SaveBar saving={update.isPending} disabled={!canEdit} note={!canEdit ? t('common.noEditPermission') : undefined} />
    </form>
  );
}
