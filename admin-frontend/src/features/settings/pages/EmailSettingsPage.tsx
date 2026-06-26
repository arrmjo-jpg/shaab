import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { SendHorizonal } from 'lucide-react';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { TextField } from '@/components/form/TextField';
import { SelectField } from '@/components/form/SelectField';
import { SecretField } from '@/components/form/SecretField';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/hooks/useAuth';
import type { GeneralUpdatePayload } from '@/types/settings.types';
import { SettingsSection } from '../components/SettingsSection';
import { SaveBar } from '../components/SaveBar';
import { useGeneralSettings, useUpdateGeneral, useTestMail } from '../hooks';
import { emailSchema, type EmailValues } from '../schemas';

const MAILERS = ['smtp', 'sendmail', 'ses', 'postmark', 'log', 'array'];
const ENCRYPTIONS = ['tls', 'ssl', 'null'];

const EMPTY: EmailValues = {
  mail_mailer: 'smtp', mail_host: '', mail_port: 587, mail_encryption: 'tls',
  mail_from_name: '', mail_from_email: '', mail_username: '', mail_password: '',
};

export default function EmailSettingsPage() {
  const { t } = useTranslation('settings');
  const { hasPermission } = useAuth();
  const canEdit = hasPermission('settings.edit');
  const q = useGeneralSettings();
  const update = useUpdateGeneral();
  const testMail = useTestMail();
  const [recipient, setRecipient] = useState('');

  const m = q.data?.mail;
  const values: EmailValues = m
    ? {
        mail_mailer: m.mail_mailer,
        mail_host: m.mail_host,
        mail_port: m.mail_port,
        mail_encryption: m.mail_encryption,
        mail_from_name: m.mail_from_name,
        mail_from_email: m.mail_from_email,
        mail_username: m.mail_username,
        mail_password: '',
      }
    : EMPTY;

  const { register, handleSubmit, formState, getValues } = useForm<EmailValues>({
    resolver: zodResolver(emailSchema),
    values,
  });

  if (q.isLoading) return <PageSkeleton />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const onSave = handleSubmit((v) => {
    const payload: GeneralUpdatePayload = { ...v };
    if (!v.mail_password) delete payload.mail_password; // فارغ = إبقاء القيمة
    update.mutate(payload);
  });

  const runTest = () => testMail.mutate(recipient || getValues('mail_from_email'));

  return (
    <form onSubmit={onSave} className="space-y-5" noValidate>
      <SettingsSection title={t('email.title')} description={t('email.desc')}>
        <div className="grid gap-4 sm:grid-cols-2">
          <SelectField
            label={t('email.mail_mailer')}
            options={MAILERS.map((x) => ({ value: x, label: x }))}
            {...register('mail_mailer')}
          />
          <SelectField
            label={t('email.mail_encryption')}
            options={ENCRYPTIONS.map((x) => ({ value: x, label: x }))}
            {...register('mail_encryption')}
          />
          <TextField label={t('email.mail_host')} {...register('mail_host')} />
          <TextField
            label={t('email.mail_port')}
            type="number"
            error={formState.errors.mail_port}
            {...register('mail_port', { valueAsNumber: true })}
          />
          <TextField label={t('email.mail_from_name')} {...register('mail_from_name')} />
          <TextField
            label={t('email.mail_from_email')}
            type="email"
            error={formState.errors.mail_from_email}
            {...register('mail_from_email')}
          />
          <TextField label={t('email.mail_username')} {...register('mail_username')} />
          <SecretField
            label={t('email.mail_password')}
            configured={q.data.mail.mail_password_configured}
            error={formState.errors.mail_password}
            {...register('mail_password')}
          />
        </div>
      </SettingsSection>

      <SettingsSection title={t('email.testCard')}>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
          <div className="flex-1 space-y-1.5">
            <label className="text-sm font-medium">{t('email.testRecipient')}</label>
            <Input
              type="email"
              value={recipient}
              onChange={(e) => setRecipient(e.target.value)}
              placeholder={q.data.mail.mail_from_email}
            />
          </div>
          <Button
            type="button"
            variant="outline"
            onClick={runTest}
            disabled={testMail.isPending}
          >
            <SendHorizonal className="h-4 w-4" />
            {testMail.isPending ? t('email.testing') : t('email.testBtn')}
          </Button>
        </div>
      </SettingsSection>

      <SaveBar saving={update.isPending} disabled={!canEdit} note={!canEdit ? t('common.noEditPermission') : undefined} />
    </form>
  );
}
