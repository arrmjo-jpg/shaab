import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { TextField } from '@/components/form/TextField';
import { SecretField } from '@/components/form/SecretField';
import { SwitchField } from '@/components/form/SwitchField';
import { TestButton } from '@/components/form/TestButton';
import { useAuth } from '@/hooks/useAuth';
import { SettingsSection } from '@/features/settings/components/SettingsSection';
import { SaveBar } from '@/features/settings/components/SaveBar';
import { useThirdParty, useTestWhatsapp, useUpdateThirdParty } from '../hooks';
import { whatsappSchema, type WhatsappValues } from '../schemas';
import { stripEmptySecrets } from '../utils';

const SECRETS = ['whatsapp_token'];

export default function WhatsappPage() {
  const { t } = useTranslation('thirdParty');
  const { hasPermission } = useAuth();
  const canEdit = hasPermission('settings.edit');
  const q = useThirdParty();
  const update = useUpdateThirdParty();
  const test = useTestWhatsapp();

  const d = q.data?.whatsapp;
  const values: WhatsappValues = d
    ? {
        whatsapp_enabled: d.enabled,
        whatsapp_instance_id: d.instance_id,
        whatsapp_token: '',
        whatsapp_base_url: d.base_url,
        whatsapp_batch_size: d.batch_size,
        whatsapp_delay_seconds: d.delay_seconds,
      }
    : {
        whatsapp_enabled: false, whatsapp_instance_id: '', whatsapp_token: '',
        whatsapp_base_url: '', whatsapp_batch_size: 10, whatsapp_delay_seconds: 5,
      };

  const { register, handleSubmit, control } = useForm<WhatsappValues>({
    resolver: zodResolver(whatsappSchema),
    values,
  });

  if (q.isLoading) return <PageSkeleton />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const onSave = handleSubmit((v) => update.mutate(stripEmptySecrets({ ...v }, SECRETS)));

  return (
    <form onSubmit={onSave} className="space-y-5" noValidate>
      <SettingsSection title={t('whatsapp.title')} description={t('whatsapp.desc')}>
        <Controller control={control} name="whatsapp_enabled" render={({ field }) => (
          <SwitchField label={t('whatsapp.enabled')} checked={field.value} onChange={field.onChange} />
        )} />
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField label={t('whatsapp.instance_id')} {...register('whatsapp_instance_id')} />
          <TextField label={t('whatsapp.base_url')} {...register('whatsapp_base_url')} />
          <TextField label={t('whatsapp.batch_size')} type="number" {...register('whatsapp_batch_size', { valueAsNumber: true })} />
          <TextField label={t('whatsapp.delay_seconds')} type="number" {...register('whatsapp_delay_seconds', { valueAsNumber: true })} />
        </div>
        <SecretField
          label={t('whatsapp.token')}
          configured={q.data.whatsapp.token_configured}
          {...register('whatsapp_token')}
        />
        <TestButton
          label={t('integrations.test')}
          loadingLabel={t('integrations.testing')}
          loading={test.isPending}
          onClick={() => test.mutate()}
        />
      </SettingsSection>

      <SaveBar saving={update.isPending} disabled={!canEdit} note={!canEdit ? t('common.noEditPermission') : undefined} />
    </form>
  );
}
