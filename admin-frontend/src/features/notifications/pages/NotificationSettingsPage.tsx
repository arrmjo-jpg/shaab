import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/TextField';
import { SwitchField } from '@/components/form/SwitchField';
import { ErrorState, LoadingState } from '@/components/feedback';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { useNotificationSettings, useUpdateNotificationSettings } from '../hooks';
import { settingsSchema, type SettingsValues } from '../schemas';

const FALLBACK: SettingsValues = {
  enabled: true,
  critical_bypass: true,
  quiet_hours_enabled: false,
  quiet_hours_start: '22:00',
  quiet_hours_end: '07:00',
  quiet_hours_timezone: 'Asia/Amman',
};

export default function NotificationSettingsPage() {
  const { t } = useTranslation('notifications');
  const { hasPermission } = useAuth();
  const { success } = useToast();
  const q = useNotificationSettings();
  const update = useUpdateNotificationSettings();
  const canManage = hasPermission('notifications.manage');

  const { register, handleSubmit, control, formState } = useForm<SettingsValues>({
    resolver: zodResolver(settingsSchema),
    values: q.data ?? FALLBACK,
  });

  if (q.isLoading) return <LoadingState />;
  if (q.isError) return <ErrorState message={t('common.error')} onRetry={() => void q.refetch()} />;

  const submit = handleSubmit((v) =>
    update.mutate(v, { onSuccess: () => success(t('settings.saved')) }),
  );

  return (
    <div className="max-w-2xl space-y-6">
      <header>
        <h1 className="text-2xl font-bold">{t('settings.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('settings.subtitle')}</p>
      </header>

      <form onSubmit={submit} className="space-y-4" noValidate>
        <Controller
          control={control}
          name="enabled"
          render={({ field }) => (
            <SwitchField
              label={t('settings.enabled')}
              description={t('settings.enabledHint')}
              checked={field.value}
              onChange={field.onChange}
              disabled={!canManage}
            />
          )}
        />
        <Controller
          control={control}
          name="critical_bypass"
          render={({ field }) => (
            <SwitchField
              label={t('settings.criticalBypass')}
              description={t('settings.criticalBypassHint')}
              checked={field.value}
              onChange={field.onChange}
              disabled={!canManage}
            />
          )}
        />
        <Controller
          control={control}
          name="quiet_hours_enabled"
          render={({ field }) => (
            <SwitchField
              label={t('settings.quietEnabled')}
              checked={field.value}
              onChange={field.onChange}
              disabled={!canManage}
            />
          )}
        />

        <div className="grid gap-4 sm:grid-cols-3">
          <TextField
            label={t('settings.quietStart')}
            type="time"
            error={formState.errors.quiet_hours_start}
            disabled={!canManage}
            {...register('quiet_hours_start')}
          />
          <TextField
            label={t('settings.quietEnd')}
            type="time"
            error={formState.errors.quiet_hours_end}
            disabled={!canManage}
            {...register('quiet_hours_end')}
          />
          <TextField
            label={t('settings.timezone')}
            error={formState.errors.quiet_hours_timezone}
            disabled={!canManage}
            {...register('quiet_hours_timezone')}
          />
        </div>

        {canManage ? (
          <Button type="button" onClick={submit} disabled={update.isPending}>
            {update.isPending ? t('common.saving') : t('common.save')}
          </Button>
        ) : null}
      </form>
    </div>
  );
}
