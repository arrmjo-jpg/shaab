import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { TextField } from '@/components/form/TextField';
import { SecretField } from '@/components/form/SecretField';
import { SwitchField } from '@/components/form/SwitchField';
import { JsonUploadField } from '@/components/upload/JsonUploadField';
import { useAuth } from '@/hooks/useAuth';
import { SettingsSection } from '@/features/settings/components/SettingsSection';
import { SaveBar } from '@/features/settings/components/SaveBar';
import { useThirdParty, useUpdateThirdParty, useUploadFirebase } from '../hooks';
import { firebaseSchema, type FirebaseValues } from '../schemas';
import { stripEmptySecrets } from '../utils';

const SECRETS = ['firebase_api_key'];
const TEXT: (keyof FirebaseValues)[] = [
  'firebase_project_id', 'firebase_auth_domain', 'firebase_database_url',
  'firebase_storage_bucket', 'firebase_messaging_sender_id', 'firebase_app_id',
  'firebase_measurement_id', 'firebase_token_uri',
];

export default function FirebasePage() {
  const { t } = useTranslation('thirdParty');
  const { hasPermission } = useAuth();
  const canEdit = hasPermission('settings.edit');
  const q = useThirdParty();
  const update = useUpdateThirdParty();
  const uploadFb = useUploadFirebase();

  const d = q.data?.firebase;
  const values: FirebaseValues = d
    ? {
        firebase_enabled: d.enabled,
        firebase_project_id: d.project_id,
        firebase_api_key: '',
        firebase_auth_domain: d.auth_domain,
        firebase_database_url: d.database_url,
        firebase_storage_bucket: d.storage_bucket,
        firebase_messaging_sender_id: d.messaging_sender_id,
        firebase_app_id: d.app_id,
        firebase_measurement_id: d.measurement_id,
        firebase_token_uri: d.token_uri,
      }
    : ({
        firebase_enabled: false, firebase_project_id: '', firebase_api_key: '',
        firebase_auth_domain: '', firebase_database_url: '', firebase_storage_bucket: '',
        firebase_messaging_sender_id: '', firebase_app_id: '', firebase_measurement_id: '',
        firebase_token_uri: '',
      } satisfies FirebaseValues);

  const { register, handleSubmit, control, formState } = useForm<FirebaseValues>({
    resolver: zodResolver(firebaseSchema),
    values,
  });

  if (q.isLoading) return <PageSkeleton />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const onSave = handleSubmit((v) => update.mutate(stripEmptySecrets({ ...v }, SECRETS)));

  return (
    <form onSubmit={onSave} className="space-y-5" noValidate>
      <SettingsSection title={t('firebase.title')} description={t('firebase.desc')}>
        <Controller control={control} name="firebase_enabled" render={({ field }) => (
          <SwitchField label={t('firebase.enabled')} checked={field.value} onChange={field.onChange} />
        )} />
        <div className="grid gap-4 sm:grid-cols-2">
          {TEXT.map((k) => (
            <TextField
              key={k}
              label={t(`firebase.${k.replace('firebase_', '')}`)}
              error={formState.errors[k]}
              {...register(k)}
            />
          ))}
        </div>
        <SecretField
          label={t('firebase.api_key')}
          configured={q.data.firebase.api_key_configured}
          {...register('firebase_api_key')}
        />
      </SettingsSection>

      <SettingsSection title={t('firebase.serviceAccount')}>
        <JsonUploadField
          label={t('firebase.serviceAccount')}
          configured={q.data.firebase.service_account_configured}
          uploading={uploadFb.isPending}
          onUpload={(file) => uploadFb.mutate(file)}
        />
        {q.data.firebase.credentials_path ? (
          <p className="text-xs text-muted-foreground">
            {t('firebase.credentials_path')}: {q.data.firebase.credentials_path}
          </p>
        ) : null}
      </SettingsSection>

      <SaveBar saving={update.isPending} disabled={!canEdit} note={!canEdit ? t('common.noEditPermission') : undefined} />
    </form>
  );
}
