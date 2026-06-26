import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { TextField } from '@/components/form/TextField';
import { SecretField } from '@/components/form/SecretField';
import { SwitchField } from '@/components/form/SwitchField';
import { useAuth } from '@/hooks/useAuth';
import { SettingsSection } from '@/features/settings/components/SettingsSection';
import { SaveBar } from '@/features/settings/components/SaveBar';
import { useThirdParty, useUpdateThirdParty } from '../hooks';
import { socialLoginSchema, type SocialLoginValues } from '../schemas';
import { stripEmptySecrets } from '../utils';

const SECRETS = ['google_client_secret', 'facebook_client_secret'];
const EMPTY: SocialLoginValues = {
  google_enabled: false, google_client_id: '', google_client_secret: '', google_redirect_url: '',
  facebook_enabled: false, facebook_client_id: '', facebook_client_secret: '', facebook_redirect_url: '',
};

export default function SocialLoginPage() {
  const { t } = useTranslation('thirdParty');
  const { hasPermission } = useAuth();
  const canEdit = hasPermission('settings.edit');
  const q = useThirdParty();
  const update = useUpdateThirdParty();

  const d = q.data?.social_login;
  const values: SocialLoginValues = d
    ? {
        google_enabled: d.google_enabled,
        google_client_id: d.google_client_id,
        google_client_secret: '',
        google_redirect_url: d.google_redirect_url,
        facebook_enabled: d.facebook_enabled,
        facebook_client_id: d.facebook_client_id,
        facebook_client_secret: '',
        facebook_redirect_url: d.facebook_redirect_url,
      }
    : EMPTY;

  const { register, handleSubmit, control, formState } = useForm<SocialLoginValues>({
    resolver: zodResolver(socialLoginSchema),
    values,
  });

  if (q.isLoading) return <PageSkeleton />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const onSave = handleSubmit((v) => update.mutate(stripEmptySecrets({ ...v }, SECRETS)));

  return (
    <form onSubmit={onSave} className="space-y-5" noValidate>
      <SettingsSection title={t('socialLogin.googleCard')} description={t('socialLogin.desc')}>
        <Controller control={control} name="google_enabled" render={({ field }) => (
          <SwitchField label={t('socialLogin.google_enabled')} checked={field.value} onChange={field.onChange} />
        )} />
        <TextField label={t('socialLogin.google_client_id')} {...register('google_client_id')} />
        <SecretField
          label={t('socialLogin.google_client_secret')}
          configured={q.data.social_login.google_client_secret_configured}
          {...register('google_client_secret')}
        />
        <TextField label={t('socialLogin.google_redirect_url')} error={formState.errors.google_redirect_url} {...register('google_redirect_url')} />
      </SettingsSection>

      <SettingsSection title={t('socialLogin.facebookCard')}>
        <Controller control={control} name="facebook_enabled" render={({ field }) => (
          <SwitchField label={t('socialLogin.facebook_enabled')} checked={field.value} onChange={field.onChange} />
        )} />
        <TextField label={t('socialLogin.facebook_client_id')} {...register('facebook_client_id')} />
        <SecretField
          label={t('socialLogin.facebook_client_secret')}
          configured={q.data.social_login.facebook_client_secret_configured}
          {...register('facebook_client_secret')}
        />
        <TextField label={t('socialLogin.facebook_redirect_url')} error={formState.errors.facebook_redirect_url} {...register('facebook_redirect_url')} />
      </SettingsSection>

      <SaveBar saving={update.isPending} disabled={!canEdit} note={!canEdit ? t('common.noEditPermission') : undefined} />
    </form>
  );
}
