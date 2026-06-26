import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { Modal } from '@/components/ui/modal';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/TextField';
import { TextareaField } from '@/components/form/TextareaField';
import { permissionGroupSchema, type PermissionGroupValues } from '../schemas';
import { useCreatePermissionGroup, useUpdatePermissionGroup } from '../hooks';
import type {
  PermissionGroupData,
  PermissionGroupUpsertPayload,
} from '@/types/rbac.types';

interface Props {
  open: boolean;
  onClose: () => void;
  group: PermissionGroupData | null;
}

export function PermissionGroupFormModal({ open, onClose, group }: Props) {
  const { t } = useTranslation('users');
  const isEdit = Boolean(group);
  const systemLocked = Boolean(group?.is_system);
  const create = useCreatePermissionGroup();
  const update = useUpdatePermissionGroup();

  const { register, handleSubmit, formState } = useForm<PermissionGroupValues>({
    resolver: zodResolver(permissionGroupSchema),
    values: {
      slug: group?.slug ?? '',
      display_name: group?.display_name ?? '',
      description: group?.description ?? '',
      icon: group?.icon ?? '',
      sort_order: group?.sort_order ?? 0,
    },
  });

  const submit = handleSubmit((v) => {
    const payload: PermissionGroupUpsertPayload = {
      display_name: v.display_name,
      description: v.description || null,
      icon: v.icon || null,
      sort_order: v.sort_order,
    };
    if (!systemLocked) payload.slug = v.slug;
    const onDone = { onSuccess: () => onClose() };
    if (isEdit) update.mutate({ id: group!.id, payload }, onDone);
    else create.mutate({ ...payload, slug: v.slug }, onDone);
  });

  const saving = create.isPending || update.isPending;

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={isEdit ? t('groups.form.editTitle') : t('groups.form.createTitle')}
      size="md"
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
        <TextField
          label={t('groups.form.slug')}
          error={formState.errors.slug}
          disabled={systemLocked}
          {...register('slug')}
        />
        <p className="-mt-2 text-xs text-muted-foreground">
          {systemLocked ? t('groups.form.slugLocked') : t('groups.form.slugHint')}
        </p>
        <TextField
          label={t('groups.form.display_name')}
          error={formState.errors.display_name}
          {...register('display_name')}
        />
        <TextField label={t('groups.form.icon')} {...register('icon')} />
        <TextField
          label={t('groups.form.sort_order')}
          type="number"
          error={formState.errors.sort_order}
          {...register('sort_order', { valueAsNumber: true })}
        />
        <TextareaField label={t('groups.form.description')} {...register('description')} />
      </form>
    </Modal>
  );
}
