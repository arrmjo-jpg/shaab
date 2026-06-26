import { useEffect, useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Send } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/TextField';
import { TextareaField } from '@/components/form/TextareaField';
import { SelectField } from '@/components/form/SelectField';
import { SwitchField } from '@/components/form/SwitchField';
import { useToast } from '@/hooks/useToast';
import { paths } from '@/router/paths';
import {
  useAudiencePreview,
  useAudiences,
  useComposeCampaign,
  useMatrix,
  useTemplateVariables,
} from '../hooks';
import { composeSchema, type ComposeValues } from '../schemas';
import { ALL_CHANNELS } from '../constants';
import type { ChannelKey, ComposeCampaignPayload } from '@/types/notifications.types';

export default function CampaignComposerPage() {
  const { t } = useTranslation('notifications');
  const navigate = useNavigate();
  const { success } = useToast();
  const matrix = useMatrix();
  const audiences = useAudiences();
  const compose = useComposeCampaign();

  const { register, handleSubmit, watch, control, formState } = useForm<ComposeValues>({
    resolver: zodResolver(composeSchema),
    defaultValues: {
      event_key: '',
      title: '',
      body: '',
      audience: 'all',
      scheduled_at: '',
      requires_approval: false,
    },
  });

  const eventKey = watch('event_key');
  const audience = watch('audience');
  const varDefs = useTemplateVariables(eventKey || null);
  const preview = useAudiencePreview(audience ?? null);

  const [channels, setChannels] = useState<ChannelKey[]>([]);
  const [vars, setVars] = useState<Record<string, string>>({});
  useEffect(() => {
    setVars({});
  }, [eventKey]);

  const eventOptions = [
    { value: '', label: '—' },
    ...(matrix.data ?? []).filter((e) => e.manual_dispatch).map((e) => ({ value: e.key, label: e.label })),
  ];
  const audienceOptions = (audiences.data ?? []).map((a) => ({ value: a.key, label: a.key }));

  const toggleChannel = (c: ChannelKey) =>
    setChannels((prev) => (prev.includes(c) ? prev.filter((x) => x !== c) : [...prev, c]));

  const submit = handleSubmit((v) => {
    const payload: ComposeCampaignPayload = {
      event_key: v.event_key,
      title: v.title,
      body: v.body || undefined,
      audience: v.audience,
      channels: channels.length ? channels : undefined,
      scheduled_at: v.scheduled_at || null,
      requires_approval: v.requires_approval,
      variables: Object.keys(vars).length ? vars : undefined,
    };
    compose.mutate(payload, {
      onSuccess: (data) => {
        success(t('composer.created'));
        navigate(paths.notifCampaignDetail.replace(':uuid', data.uuid));
      },
    });
  });

  return (
    <div className="max-w-3xl space-y-6">
      <header>
        <h1 className="text-2xl font-bold">{t('composer.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('composer.subtitle')}</p>
      </header>

      <form onSubmit={submit} className="space-y-4" noValidate>
        <SelectField
          label={t('composer.event')}
          options={eventOptions}
          error={formState.errors.event_key}
          {...register('event_key')}
        />
        <TextField label={t('composer.contentTitle')} error={formState.errors.title} {...register('title')} />
        <TextareaField label={t('composer.body')} {...register('body')} />

        {varDefs.data && varDefs.data.length > 0 ? (
          <div className="space-y-2 border border-border p-3">
            <p className="text-sm font-medium">{t('composer.variables')}</p>
            <div className="grid gap-3 sm:grid-cols-2">
              {varDefs.data.map((vn) => (
                <div key={vn} className="space-y-1">
                  <label className="text-xs text-muted-foreground">{`{{${vn}}}`}</label>
                  <input
                    className="h-10 w-full rounded-xl border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    value={vars[vn] ?? ''}
                    onChange={(e) => setVars((p) => ({ ...p, [vn]: e.target.value }))}
                  />
                </div>
              ))}
            </div>
          </div>
        ) : null}

        <div className="grid items-end gap-4 sm:grid-cols-2">
          <SelectField label={t('composer.audience')} options={audienceOptions} {...register('audience')} />
          <div className="pb-2 text-sm text-muted-foreground">
            {preview.data ? (
              <span>
                {t('composer.previewUsers')}:{' '}
                <b className="tabular-nums text-foreground">{preview.data.users}</b> ·{' '}
                {t('composer.previewDevices')}:{' '}
                <b className="tabular-nums text-foreground">{preview.data.devices}</b>
              </span>
            ) : null}
          </div>
        </div>

        <div className="space-y-2">
          <p className="text-sm font-medium">{t('composer.channels')}</p>
          <div className="flex flex-wrap gap-2">
            {ALL_CHANNELS.map((c) => (
              <button
                type="button"
                key={c}
                onClick={() => toggleChannel(c)}
                className={`rounded-full border px-3 py-1 text-sm transition-colors ${
                  channels.includes(c)
                    ? 'border-primary bg-primary/10 text-primary'
                    : 'border-border text-muted-foreground'
                }`}
              >
                {t(`channel.${c}`)}
              </button>
            ))}
          </div>
        </div>

        <TextField label={t('composer.scheduledAt')} type="datetime-local" {...register('scheduled_at')} />
        <Controller
          control={control}
          name="requires_approval"
          render={({ field }) => (
            <SwitchField
              label={t('composer.requiresApproval')}
              description={t('composer.requiresApprovalHint')}
              checked={field.value}
              onChange={field.onChange}
            />
          )}
        />

        <Button type="button" onClick={submit} disabled={compose.isPending}>
          <Send className="h-4 w-4" />
          {compose.isPending ? t('composer.submitting') : t('composer.submit')}
        </Button>
      </form>
    </div>
  );
}
