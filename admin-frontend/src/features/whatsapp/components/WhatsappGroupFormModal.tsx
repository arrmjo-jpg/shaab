import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal } from '@/components/ui/modal';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/hooks/useToast';
import { useCreateWhatsappGroup, useUpdateWhatsappGroup } from '../hooks';
import type { WhatsappGroupData } from '@/types/whatsapp.types';

const areaCls =
  'flex w-full border border-input bg-background px-3.5 py-2.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

interface Props {
  open: boolean;
  onClose: () => void;
  group?: WhatsappGroupData | null;
}

/** مودال إنشاء/تعديل مجموعة واتساب — اسم + وصف اختياري فقط (نمط BroadcastCategoryFormModal). */
export function WhatsappGroupFormModal({ open, onClose, group }: Props) {
  const { t } = useTranslation('whatsapp');
  const { success, error: toastError } = useToast();
  const create = useCreateWhatsappGroup();
  const update = useUpdateWhatsappGroup();

  const isEdit = Boolean(group);
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');

  useEffect(() => {
    if (!open) return;
    setName(group?.name ?? '');
    setDescription(group?.description ?? '');
  }, [open, group]);

  const submit = () => {
    if (name.trim().length < 2) {
      toastError(t('groups.form.nameRequired'));
      return;
    }
    const payload = {
      name: name.trim(),
      description: description.trim() ? description.trim() : null,
    };
    const onSuccess = () => {
      success(t('groups.form.saved'));
      onClose();
    };
    if (isEdit && group) update.mutate({ id: group.id, payload }, { onSuccess });
    else create.mutate(payload, { onSuccess });
  };

  const saving = create.isPending || update.isPending;

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={isEdit ? t('groups.form.editTitle') : t('groups.form.createTitle')}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            {t('common.cancel', { ns: 'common' })}
          </Button>
          <Button onClick={submit} disabled={saving}>
            {saving ? t('groups.form.saving') : t('groups.form.save')}
          </Button>
        </>
      }
    >
      <div className="grid gap-4">
        <div>
          <Label htmlFor="wag-name">{t('groups.form.name')}</Label>
          <Input id="wag-name" value={name} onChange={(e) => setName(e.target.value)} maxLength={150} />
        </div>
        <div>
          <Label htmlFor="wag-desc">{t('groups.form.description')}</Label>
          <textarea
            id="wag-desc"
            rows={2}
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            maxLength={500}
            className={areaCls}
          />
        </div>
      </div>
    </Modal>
  );
}
