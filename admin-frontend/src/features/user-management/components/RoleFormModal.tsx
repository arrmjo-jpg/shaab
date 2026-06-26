import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { Modal } from '@/components/ui/modal';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/TextField';
import { TextareaField } from '@/components/form/TextareaField';
import { roleSchema, type RoleValues } from '../schemas';
import { useCreateRole, useUpdateRole } from '../hooks';
import { PermissionAssignment } from './PermissionAssignment';
import type { RoleData, RoleUpsertPayload } from '@/types/rbac.types';

interface Props {
  open: boolean;
  onClose: () => void;
  role: RoleData | null;
}

export function RoleFormModal({ open, onClose, role }: Props) {
  const { t } = useTranslation('users');
  const isEdit = Boolean(role);
  const locked = role?.name === 'super_admin';
  const create = useCreateRole();
  const update = useUpdateRole();

  // استخراج دفاعي: يدعم الشكل المجمّع [{group, items:[{name}]}]
  // أو المسطّح [{name}] أو غياب القيمة — دون إسقاط الصفحة.
  const initialPerms: string[] = Array.isArray(role?.permissions)
    ? (role!.permissions as unknown[]).flatMap((b) => {
        const block = b as { items?: { name: string }[]; name?: string };
        if (Array.isArray(block.items)) return block.items.map((i) => i.name);
        if (typeof block.name === 'string') return [block.name];
        return [];
      })
    : [];

  const { register, handleSubmit, control, formState } = useForm<RoleValues>({
    resolver: zodResolver(roleSchema),
    values: {
      name: role?.name ?? '',
      display_name: role?.display_name ?? '',
      description: role?.description ?? '',
      permissions: initialPerms,
    },
  });

  const submit = handleSubmit((v) => {
    const payload: RoleUpsertPayload = {
      display_name: v.display_name,
      description: v.description || null,
    };
    if (!locked) {
      payload.permissions = v.permissions;
      if (!isEdit) payload.name = v.name;
      else if (v.name !== role?.name) payload.name = v.name;
    }
    const onDone = { onSuccess: () => onClose() };
    if (isEdit) update.mutate({ id: role!.id, payload }, onDone);
    else create.mutate({ ...payload, name: v.name, permissions: v.permissions }, onDone);
  });

  const saving = create.isPending || update.isPending;

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={isEdit ? t('roles.form.editTitle') : t('roles.form.createTitle')}
      size="xl"
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
        {locked ? (
          <p className="rounded-2xl border border-border bg-muted/50 px-4 py-3 text-sm text-muted-foreground">
            {t('roles.form.systemLocked')}
          </p>
        ) : null}

        <div className="grid gap-4 sm:grid-cols-2">
          <TextField
            label={t('roles.form.name')}
            error={formState.errors.name}
            disabled={isEdit && locked}
            {...register('name')}
          />
          <TextField
            label={t('roles.form.display_name')}
            error={formState.errors.display_name}
            {...register('display_name')}
          />
        </div>
        {!isEdit ? (
          <p className="-mt-2 text-xs text-muted-foreground">{t('roles.form.nameHint')}</p>
        ) : null}

        <TextareaField label={t('roles.form.description')} {...register('description')} />

        <div className="space-y-2">
          <p className="text-sm font-medium">{t('roles.form.permissions')}</p>
          <Controller
            control={control}
            name="permissions"
            render={({ field }) => (
              <PermissionAssignment
                value={field.value}
                onChange={field.onChange}
                disabled={locked}
              />
            )}
          />
        </div>
      </form>
    </Modal>
  );
}
