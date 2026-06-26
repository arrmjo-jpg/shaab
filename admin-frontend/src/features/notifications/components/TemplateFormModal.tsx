import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { Modal } from '@/components/ui/modal';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/TextField';
import { TextareaField } from '@/components/form/TextareaField';
import { SelectField } from '@/components/form/SelectField';
import { SwitchField } from '@/components/form/SwitchField';
import { useToast } from '@/hooks/useToast';
import { useCreateTemplate, useMatrix, useTemplateVariables, useUpdateTemplate } from '../hooks';
import { templateSchema, type TemplateValues } from '../schemas';
import { ALL_CHANNELS } from '../constants';
import type { TemplateData, TemplatePayload } from '@/types/notifications.types';

interface Props {
  open: boolean;
  onClose: () => void;
  template: TemplateData | null;
}

export function TemplateFormModal({ open, onClose, template }: Props) {
  const { t } = useTranslation('notifications');
  const { success } = useToast();
  const isEdit = Boolean(template);
  const create = useCreateTemplate();
  const update = useUpdateTemplate();
  const matrix = useMatrix();

  const { register, handleSubmit, control, watch, formState } = useForm<TemplateValues>({
    resolver: zodResolver(templateSchema),
    values: {
      event_key: template?.event_key ?? '',
      channel: template?.channel ?? 'firebase',
      locale: template?.locale ?? 'ar',
      title: template?.title ?? '',
      body: template?.body ?? '',
      image_strategy: template?.image_strategy ?? 'none',
      deep_link_type: template?.deep_link_type ?? 'none',
      deep_link_value: template?.deep_link_value ?? '',
      is_default: template?.is_default ?? false,
    },
  });

  const eventKey = watch('event_key');
  const vars = useTemplateVariables(eventKey || null);

  const eventOptions = [
    { value: '', label: '—' },
    ...(matrix.data ?? []).map((e) => ({ value: e.key, label: e.label })),
  ];
  const channelOptions = ALL_CHANNELS.map((c) => ({ value: c, label: t(`channel.${c}`) }));

  const submit = handleSubmit((v) => {
    const payload: TemplatePayload = {
      event_key: v.event_key,
      channel: v.channel,
      locale: v.locale || undefined,
      title: v.title || undefined,
      body: v.body || undefined,
      image_strategy: v.image_strategy || undefined,
      deep_link_type: v.deep_link_type || undefined,
      deep_link_value: v.deep_link_value || undefined,
      is_default: v.is_default,
    };
    const onDone = {
      onSuccess: () => {
        success(t(isEdit ? 'templates.updated' : 'templates.created'));
        onClose();
      },
    };
    if (isEdit) update.mutate({ id: template!.id, payload }, onDone);
    else create.mutate(payload, onDone);
  });

  const saving = create.isPending || update.isPending;

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={isEdit ? t('common.edit') : t('templates.new')}
      size="lg"
      footer={
        <>
          <Button variant="outline" type="button" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={submit} disabled={saving}>
            {saving ? t('common.saving') : t('common.save')}
          </Button>
        </>
      }
    >
      <form onSubmit={submit} className="space-y-4" noValidate>
        <div className="grid gap-4 sm:grid-cols-2">
          <SelectField
            label={t('templates.form.event')}
            options={eventOptions}
            error={formState.errors.event_key}
            {...register('event_key')}
          />
          <SelectField label={t('templates.form.channel')} options={channelOptions} {...register('channel')} />
        </div>

        {vars.data && vars.data.length > 0 ? (
          <div className="flex flex-wrap items-center gap-1.5">
            <span className="text-xs text-muted-foreground">{t('templates.form.variablesHint')}:</span>
            {vars.data.map((v) => (
              <code key={v} className="rounded bg-muted px-1.5 py-0.5 text-xs">{`{{${v}}}`}</code>
            ))}
          </div>
        ) : null}

        <TextField label={t('templates.form.titleField')} error={formState.errors.title} {...register('title')} />
        <TextareaField label={t('templates.form.body')} {...register('body')} />
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField label={t('templates.form.deepLinkType')} {...register('deep_link_type')} />
          <TextField label={t('templates.form.deepLinkValue')} {...register('deep_link_value')} />
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField label={t('templates.form.locale')} {...register('locale')} />
          <Controller
            control={control}
            name="is_default"
            render={({ field }) => (
              <SwitchField label={t('templates.form.isDefault')} checked={field.value} onChange={field.onChange} />
            )}
          />
        </div>
      </form>
    </Modal>
  );
}
