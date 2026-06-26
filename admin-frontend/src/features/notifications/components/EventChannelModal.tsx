import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { Modal } from '@/components/ui/modal';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/TextField';
import { SelectField } from '@/components/form/SelectField';
import { useToast } from '@/hooks/useToast';
import { useTemplates, useUpdateEventChannel } from '../hooks';
import { eventChannelSchema, type EventChannelValues } from '../schemas';
import { ALL_CHANNELS } from '../constants';
import type { EventChannelRow, UpdateEventChannelPayload } from '@/types/notifications.types';

interface Props {
  open: boolean;
  onClose: () => void;
  eventKey: string;
  row: EventChannelRow;
}

export function EventChannelModal({ open, onClose, eventKey, row }: Props) {
  const { t } = useTranslation('notifications');
  const { success } = useToast();
  const update = useUpdateEventChannel();
  const templates = useTemplates({ event_key: eventKey, channel: row.channel });

  const { register, handleSubmit, formState } = useForm<EventChannelValues>({
    resolver: zodResolver(eventChannelSchema),
    values: {
      mode: row.mode,
      channel_priority: row.channel_priority,
      fallback_channel: row.fallback_channel ?? '',
      template_id: row.template_id != null ? String(row.template_id) : '',
    },
  });

  const modeOptions = (['automatic', 'manual_approval', 'disabled'] as const).map((m) => ({
    value: m,
    label: t(`mode.${m}`),
  }));
  const fallbackOptions = [
    { value: '', label: t('matrix.none') },
    ...ALL_CHANNELS.filter((c) => c !== row.channel).map((c) => ({ value: c, label: t(`channel.${c}`) })),
  ];
  const templateOptions = [
    { value: '', label: t('matrix.noTemplate') },
    ...(templates.data ?? []).map((tp) => ({
      value: String(tp.id),
      label: `${tp.title ?? tp.event_label} (${tp.locale ?? '—'})`,
    })),
  ];

  const submit = handleSubmit((v) => {
    const payload: UpdateEventChannelPayload = {
      mode: v.mode,
      channel_priority: v.channel_priority,
      fallback_channel: v.fallback_channel || null,
      template_id: v.template_id ? Number(v.template_id) : null,
    };
    update.mutate(
      { id: row.id, payload },
      {
        onSuccess: () => {
          success(t('matrix.channelUpdated'));
          onClose();
        },
      },
    );
  });

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={`${t('matrix.editChannel')} · ${t(`channel.${row.channel}`)}`}
      size="md"
      footer={
        <>
          <Button variant="outline" type="button" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={submit} disabled={update.isPending}>
            {update.isPending ? t('common.saving') : t('common.save')}
          </Button>
        </>
      }
    >
      <form onSubmit={submit} className="space-y-4" noValidate>
        <SelectField label={t('matrix.mode')} options={modeOptions} {...register('mode')} />
        <TextField
          label={t('matrix.priority')}
          type="number"
          error={formState.errors.channel_priority}
          {...register('channel_priority')}
        />
        <SelectField label={t('matrix.fallback')} options={fallbackOptions} {...register('fallback_channel')} />
        <SelectField label={t('matrix.template')} options={templateOptions} {...register('template_id')} />
      </form>
    </Modal>
  );
}
