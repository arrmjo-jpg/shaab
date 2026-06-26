import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal } from '@/components/ui/modal';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/hooks/useToast';
import { useCreateBroadcastCategory, useUpdateBroadcastCategory } from '../hooks';
import type { BroadcastCategoryData } from '@/types/broadcast.types';

const areaCls =
  'flex w-full border border-input bg-background px-3.5 py-2.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

interface Props {
  open: boolean;
  onClose: () => void;
  category?: BroadcastCategoryData | null;
}

interface FormState {
  name: string;
  slug: string;
  description: string;
  is_active: boolean;
  sort_order: string;
  seo_title: string;
  seo_description: string;
}

const EMPTY: FormState = {
  name: '',
  slug: '',
  description: '',
  is_active: true,
  sort_order: '0',
  seo_title: '',
  seo_description: '',
};

export function BroadcastCategoryFormModal({ open, onClose, category }: Props) {
  const { t } = useTranslation('broadcast');
  const { success, error: toastError } = useToast();
  const create = useCreateBroadcastCategory();
  const update = useUpdateBroadcastCategory();

  const isEdit = Boolean(category);
  const [form, setForm] = useState<FormState>(EMPTY);

  useEffect(() => {
    if (!open) return;
    if (category) {
      setForm({
        name: category.name,
        slug: category.slug,
        description: category.description ?? '',
        is_active: category.is_active,
        sort_order: String(category.sort_order),
        seo_title: category.seo.title ?? '',
        seo_description: category.seo.description ?? '',
      });
    } else {
      setForm(EMPTY);
    }
  }, [open, category]);

  const patch = (p: Partial<FormState>) => setForm((prev) => ({ ...prev, ...p }));

  const submit = () => {
    if (form.name.trim().length < 2) {
      toastError(t('categories.form.validation.nameRequired'));
      return;
    }
    const payload = {
      name: form.name.trim(),
      slug: form.slug.trim() ? form.slug.trim() : null,
      description: form.description.trim() ? form.description.trim() : null,
      is_active: form.is_active,
      sort_order: form.sort_order.trim() === '' ? 0 : Number(form.sort_order),
      seo_title: form.seo_title.trim() ? form.seo_title.trim() : null,
      seo_description: form.seo_description.trim() ? form.seo_description.trim() : null,
    };
    const onSuccess = () => {
      success(t('categories.form.saved'));
      onClose();
    };
    if (isEdit && category) update.mutate({ id: category.id, payload }, { onSuccess });
    else create.mutate(payload, { onSuccess });
  };

  const saving = create.isPending || update.isPending;

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={isEdit ? t('categories.form.editTitle') : t('categories.form.createTitle')}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            {t('common.cancel', { ns: 'common' })}
          </Button>
          <Button onClick={submit} disabled={saving}>
            {saving ? t('categories.form.saving') : t('categories.form.save')}
          </Button>
        </>
      }
    >
      <div className="grid gap-4">
        <div>
          <Label htmlFor="bcat-name">{t('categories.form.name')}</Label>
          <Input id="bcat-name" value={form.name} onChange={(e) => patch({ name: e.target.value })} maxLength={150} />
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <Label htmlFor="bcat-slug">{t('categories.form.slug')}</Label>
            <Input id="bcat-slug" value={form.slug} onChange={(e) => patch({ slug: e.target.value })} dir="ltr" placeholder={t('categories.form.slugPlaceholder')} maxLength={160} />
          </div>
          <div>
            <Label htmlFor="bcat-order">{t('categories.form.sortOrder')}</Label>
            <Input id="bcat-order" type="number" min={0} value={form.sort_order} onChange={(e) => patch({ sort_order: e.target.value })} dir="ltr" />
          </div>
        </div>

        <div>
          <Label htmlFor="bcat-desc">{t('categories.form.description')}</Label>
          <textarea id="bcat-desc" rows={2} value={form.description} onChange={(e) => patch({ description: e.target.value })} maxLength={2000} className={areaCls} />
        </div>

        <label className="inline-flex cursor-pointer items-center gap-2 border border-border bg-background px-3 py-2 text-sm">
          <input type="checkbox" checked={form.is_active} onChange={(e) => patch({ is_active: e.target.checked })} className="h-4 w-4 accent-primary" />
          <span>{t('categories.form.active')}</span>
        </label>

        <div className="space-y-3 border-t border-border pt-4">
          <p className="text-xs font-semibold uppercase text-muted-foreground">{t('categories.form.seoSection')}</p>
          <div>
            <Label htmlFor="bcat-seotitle">{t('categories.form.seoTitle')}</Label>
            <Input id="bcat-seotitle" value={form.seo_title} onChange={(e) => patch({ seo_title: e.target.value })} maxLength={255} />
          </div>
          <div>
            <Label htmlFor="bcat-seodesc">{t('categories.form.seoDescription')}</Label>
            <textarea id="bcat-seodesc" rows={2} value={form.seo_description} onChange={(e) => patch({ seo_description: e.target.value })} maxLength={1000} className={areaCls} />
          </div>
        </div>
      </div>
    </Modal>
  );
}
