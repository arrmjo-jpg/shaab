import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { RefreshCw, CloudUpload, AlertTriangle } from 'lucide-react';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { TextField } from '@/components/form/TextField';
import { SecretField } from '@/components/form/SecretField';
import { SwitchField } from '@/components/form/SwitchField';
import { TestButton } from '@/components/form/TestButton';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useAuth } from '@/hooks/useAuth';
import type { MediaStorageBacklog, MediaStorageUpdatePayload } from '@/types/settings.types';
import { SettingsSection } from '../components/SettingsSection';
import { SaveBar } from '../components/SaveBar';
import {
  useMediaStorageStatus,
  useUpdateMediaStorage,
  useTestMediaStorage,
  useSyncMediaStorage,
} from '../hooks';
import { mediaStorageSchema, type MediaStorageValues } from '../schemas';

const EMPTY: MediaStorageValues = {
  remote_enabled: false,
  remote_driver: 's3',
  remote_key: '',
  remote_secret: '',
  remote_bucket: '',
  remote_region: '',
  remote_endpoint: '',
  remote_url: '',
  remote_use_path_style: false,
};

const STATE_KEYS: Array<{ key: keyof MediaStorageBacklog; variant: 'default' | 'success' | 'muted' | 'destructive' }> = [
  { key: 'synced', variant: 'success' },
  { key: 'pending', variant: 'default' },
  { key: 'syncing', variant: 'default' },
  { key: 'failed', variant: 'destructive' },
  { key: 'disabled', variant: 'muted' },
];

export default function MediaStorageSettingsPage() {
  const { t } = useTranslation('settings');
  const { hasPermission } = useAuth();
  const canEdit = hasPermission('settings.edit');
  const q = useMediaStorageStatus();
  const update = useUpdateMediaStorage();
  const test = useTestMediaStorage();
  const sync = useSyncMediaStorage();

  const s = q.data?.settings;
  const values: MediaStorageValues = s
    ? {
        remote_enabled: s.remote_enabled,
        remote_driver: s.remote_driver || 's3',
        remote_key: '',
        remote_secret: '',
        remote_bucket: s.remote_bucket,
        remote_region: s.remote_region,
        remote_endpoint: s.remote_endpoint,
        remote_url: s.remote_url,
        remote_use_path_style: s.remote_use_path_style,
      }
    : EMPTY;

  const { register, handleSubmit, control, getValues, formState } = useForm<MediaStorageValues>({
    resolver: zodResolver(mediaStorageSchema),
    values,
  });

  if (q.isLoading) return <PageSkeleton />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const { backlog, failures, remote_healthy } = q.data;
  const enabled = q.data.settings.remote_enabled;

  const onSave = handleSubmit((v) => {
    const payload: MediaStorageUpdatePayload = { ...v };
    // فارغ = إبقاء السرّ المحفوظ
    if (!v.remote_key) delete payload.remote_key;
    if (!v.remote_secret) delete payload.remote_secret;
    update.mutate(payload);
  });

  const runTest = () => {
    const v = getValues();
    test.mutate({
      remote_key: v.remote_key,
      remote_secret: v.remote_secret,
      remote_bucket: v.remote_bucket,
      remote_region: v.remote_region,
      remote_endpoint: v.remote_endpoint,
      remote_url: v.remote_url,
      remote_use_path_style: v.remote_use_path_style,
    });
  };

  return (
    <form onSubmit={onSave} className="space-y-5" noValidate>
      {/* ── حالة المزامنة + صحّة المرآة ── */}
      <SettingsSection title={t('mediaStorage.statusCard')} description={t('mediaStorage.statusDesc')}>
        <div className="flex flex-wrap items-center gap-2">
          {enabled ? (
            remote_healthy === false ? (
              <Badge variant="destructive">{t('mediaStorage.healthDown')}</Badge>
            ) : (
              <Badge variant="success">{t('mediaStorage.healthUp')}</Badge>
            )
          ) : (
            <Badge variant="muted">{t('mediaStorage.remoteOff')}</Badge>
          )}
          {STATE_KEYS.map(({ key, variant }) => (
            <Badge key={key} variant={variant}>
              {t(`mediaStorage.state.${key}`)}: {backlog[key]}
            </Badge>
          ))}
        </div>

        {/* مفعّل لكن المرآة غير متاحة → غالباً اعتماديات خاطئة (تحقّق + احفظ) */}
        {enabled && remote_healthy === false ? (
          <div className="flex items-start gap-2.5 border border-destructive/40 bg-destructive/10 px-4 py-3">
            <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-destructive" />
            <p className="text-sm text-destructive">{t('mediaStorage.healthDownHint')}</p>
          </div>
        ) : null}

        {/* أسباب الفشل — يُظهر «لماذا» لا مجرّد عدّاد failed */}
        {failures.length > 0 ? (
          <div className="space-y-2 border border-border bg-muted/40 px-4 py-3">
            <p className="text-sm font-semibold">{t('mediaStorage.failuresTitle')}</p>
            <ul className="space-y-1.5">
              {failures.map((f) => (
                <li key={f.id} className="text-xs text-muted-foreground">
                  <span className="font-medium text-foreground">{f.name}</span>
                  {f.error ? <span className="font-mono"> — {f.error}</span> : null}
                </li>
              ))}
            </ul>
          </div>
        ) : null}

        {backlog.unsynced > 0 ? (
          <div className="flex flex-col gap-3 border border-amber-500/40 bg-amber-500/10 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-start gap-2.5">
              <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
              <div>
                <p className="text-sm font-semibold text-amber-700 dark:text-amber-300">
                  {t('mediaStorage.unsyncedTitle', { count: backlog.unsynced })}
                </p>
                <p className="text-xs text-amber-700/80 dark:text-amber-300/80">
                  {t('mediaStorage.unsyncedDesc')}
                </p>
              </div>
            </div>
            <Button
              type="button"
              variant="outline"
              onClick={() => sync.mutate()}
              disabled={!canEdit || !q.data.settings.remote_enabled || sync.isPending}
            >
              <RefreshCw className={sync.isPending ? 'h-4 w-4 animate-spin' : 'h-4 w-4'} />
              {sync.isPending ? t('mediaStorage.syncing') : t('mediaStorage.syncNow')}
            </Button>
          </div>
        ) : null}
      </SettingsSection>

      {/* ── التفعيل ── */}
      <SettingsSection title={t('mediaStorage.toggleCard')} description={t('mediaStorage.toggleDesc')}>
        <Controller
          control={control}
          name="remote_enabled"
          render={({ field }) => (
            <SwitchField
              label={t('mediaStorage.remote_enabled')}
              description={t('mediaStorage.remote_enabled_desc')}
              checked={field.value}
              onChange={field.onChange}
              disabled={!canEdit}
            />
          )}
        />
      </SettingsSection>

      {/* ── الاعتماديات ── */}
      <SettingsSection title={t('mediaStorage.connCard')} description={t('mediaStorage.connDesc')}>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField label={t('mediaStorage.remote_driver')} disabled {...register('remote_driver')} />
          <TextField
            label={t('mediaStorage.remote_bucket')}
            {...register('remote_bucket')}
          />
          <SecretField
            label={t('mediaStorage.remote_key')}
            configured={s?.remote_key_configured ?? false}
            {...register('remote_key')}
          />
          <SecretField
            label={t('mediaStorage.remote_secret')}
            configured={s?.remote_secret_configured ?? false}
            {...register('remote_secret')}
          />
          <TextField
            label={t('mediaStorage.remote_endpoint')}
            error={formState.errors.remote_endpoint}
            {...register('remote_endpoint')}
          />
          <TextField label={t('mediaStorage.remote_region')} {...register('remote_region')} />
          <TextField
            label={t('mediaStorage.remote_url')}
            error={formState.errors.remote_url}
            {...register('remote_url')}
          />
        </div>

        <Controller
          control={control}
          name="remote_use_path_style"
          render={({ field }) => (
            <SwitchField
              label={t('mediaStorage.remote_use_path_style')}
              description={t('mediaStorage.remote_use_path_style_desc')}
              checked={field.value}
              onChange={field.onChange}
              disabled={!canEdit}
            />
          )}
        />

        <div className="flex items-center gap-3">
          <TestButton
            label={t('mediaStorage.testBtn')}
            loadingLabel={t('mediaStorage.testing')}
            loading={test.isPending}
            disabled={!canEdit}
            onClick={runTest}
          />
          <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
            <CloudUpload className="h-3.5 w-3.5" />
            {t('mediaStorage.testHint')}
          </span>
        </div>

        {/* فخّ السرّ المُقنَّع: الاختبار يستخدم قيم النموذج، لكن لا تُطبَّق إلا بالحفظ.
            إن نجح الاختبار وما زال هناك تغيير غير محفوظ ⇒ ذكّر بالحفظ. */}
        {test.isSuccess && formState.isDirty ? (
          <div className="flex items-start gap-2.5 border border-amber-500/40 bg-amber-500/10 px-4 py-3">
            <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
            <p className="text-sm text-amber-700 dark:text-amber-300">
              {t('mediaStorage.saveReminder')}
            </p>
          </div>
        ) : null}
      </SettingsSection>

      <SaveBar
        saving={update.isPending}
        disabled={!canEdit}
        note={!canEdit ? t('common.noEditPermission') : undefined}
      />
    </form>
  );
}
