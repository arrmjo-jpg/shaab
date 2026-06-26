import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal } from '@/components/ui/modal';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/hooks/useToast';
import { useCreateWhatsappContact, useUpdateWhatsappContact, useWhatsappGroups } from '../hooks';
import type { WhatsappContactData } from '@/types/whatsapp.types';

interface Props {
  open: boolean;
  onClose: () => void;
  contact?: WhatsappContactData | null;
}

/**
 * مودال إنشاء/تعديل جهة اتصال — الاسم + رقم دولي واحد (E.164) + عضوية المجموعات
 * (قائمة checkboxes — المجموعات قليلة بطبيعتها). التطبيع/التحقق النهائي في الخادم.
 */
export function WhatsappContactFormModal({ open, onClose, contact }: Props) {
  const { t } = useTranslation('whatsapp');
  const { success, error: toastError } = useToast();
  const groupsQ = useWhatsappGroups();
  const create = useCreateWhatsappContact();
  const update = useUpdateWhatsappContact();

  const isEdit = Boolean(contact);
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [groupIds, setGroupIds] = useState<number[]>([]);

  useEffect(() => {
    if (!open) return;
    setName(contact?.name ?? '');
    setPhone(contact?.phone ?? '');
    setGroupIds(contact?.groups.map((g) => g.id) ?? []);
  }, [open, contact]);

  const toggleGroup = (id: number) =>
    setGroupIds((prev) => (prev.includes(id) ? prev.filter((g) => g !== id) : [...prev, id]));

  const submit = () => {
    if (name.trim().length < 2) {
      toastError(t('contacts.form.nameRequired'));
      return;
    }
    if (phone.trim() === '') {
      toastError(t('contacts.form.phoneRequired'));
      return;
    }
    if (groupIds.length === 0) {
      toastError(t('contacts.form.groupRequired'));
      return;
    }
    const payload = { name: name.trim(), phone: phone.trim(), groups: groupIds };
    const onSuccess = () => {
      success(t('contacts.form.saved'));
      onClose();
    };
    if (isEdit && contact) update.mutate({ id: contact.id, payload }, { onSuccess });
    else create.mutate(payload, { onSuccess });
  };

  const saving = create.isPending || update.isPending;
  const groups = groupsQ.data ?? [];

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={isEdit ? t('contacts.form.editTitle') : t('contacts.form.createTitle')}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            {t('common.cancel', { ns: 'common' })}
          </Button>
          <Button onClick={submit} disabled={saving}>
            {saving ? t('contacts.form.saving') : t('contacts.form.save')}
          </Button>
        </>
      }
    >
      <div className="grid gap-4">
        <div>
          <Label htmlFor="wac-name">{t('contacts.form.name')}</Label>
          <Input id="wac-name" value={name} onChange={(e) => setName(e.target.value)} maxLength={150} />
        </div>
        <div>
          <Label htmlFor="wac-phone">{t('contacts.form.phone')}</Label>
          <Input
            id="wac-phone"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            dir="ltr"
            placeholder="+9627XXXXXXXX"
            maxLength={25}
          />
          <p className="mt-1 text-xs text-muted-foreground">{t('contacts.form.phoneHint')}</p>
        </div>
        <div>
          <Label>{t('contacts.form.groups')}</Label>
          <div className="mt-1 grid gap-2">
            {groups.map((g) => (
              <label
                key={g.id}
                className="inline-flex cursor-pointer items-center gap-2 border border-border bg-background px-3 py-2 text-sm"
              >
                <input
                  type="checkbox"
                  checked={groupIds.includes(g.id)}
                  onChange={() => toggleGroup(g.id)}
                  className="h-4 w-4 accent-primary"
                />
                <span>{g.name}</span>
                {g.is_default ? (
                  <span className="text-xs text-muted-foreground">({t('groups.defaultBadge')})</span>
                ) : null}
              </label>
            ))}
            {groups.length === 0 && !groupsQ.isLoading ? (
              <p className="text-sm text-muted-foreground">{t('contacts.form.noGroups')}</p>
            ) : null}
          </div>
        </div>
      </div>
    </Modal>
  );
}
