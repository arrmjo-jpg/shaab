import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Info } from 'lucide-react';
import { Modal } from '@/components/ui/modal';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/hooks/useToast';
import { useCreateVideoCategory, useUpdateVideoCategory } from '../hooks';
import type { ContentLocale } from '@/types/content.types';
import type { VideoCategoryData } from '@/types/videoLibrary.types';

const selectCls =
  'h-10 w-full border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const areaCls =
  'flex w-full border border-input bg-background px-3.5 py-2.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

interface Props {
  open: boolean;
  onClose: () => void;
  category?: VideoCategoryData | null;
  parent?: VideoCategoryData | null;
  allCategories: VideoCategoryData[];
}

interface FormState {
  name: string;
  slug: string;
  locale: ContentLocale;
  parent_id: number | null;
  description: string;
  is_active: boolean;
  seo_title: string;
  seo_description: string;
}

function emptyState(parent: VideoCategoryData | null | undefined): FormState {
  return {
    name: '',
    slug: '',
    locale: parent?.locale ?? 'ar',
    parent_id: parent?.id ?? null,
    description: '',
    is_active: true,
    seo_title: '',
    seo_description: '',
  };
}

export function VideoCategoryFormModal({ open, onClose, category, parent, allCategories }: Props) {
  const { t } = useTranslation('videoLibrary');
  const { success, error: toastError } = useToast();
  const create = useCreateVideoCategory();
  const update = useUpdateVideoCategory();

  const isEdit = Boolean(category);
  const [form, setForm] = useState<FormState>(() => emptyState(parent));

  useEffect(() => {
    if (!open) return;
    if (category) {
      setForm({
        name: category.name,
        slug: category.slug,
        locale: category.locale,
        parent_id: category.parent_id,
        description: category.description ?? '',
        is_active: category.is_active,
        seo_title: category.seo.title ?? '',
        seo_description: category.seo.description ?? '',
      });
    } else {
      setForm(emptyState(parent));
    }
  }, [open, category, parent]);

  // الآباء المؤهّلون: نفس اللغة، باستثناء النفس + النسل (منع الدوران من الواجهة).
  const eligibleParents = useMemo(() => {
    const out: Array<{ id: number; label: string }> = [];
    const walk = (nodes: unknown, depth: number) => {
      if (!Array.isArray(nodes)) return;
      for (const n of nodes) {
        if (!n || typeof n !== 'object') continue;
        const node = n as VideoCategoryData;
        if (node.locale === form.locale && node.id !== category?.id) {
          out.push({ id: node.id, label: `${'— '.repeat(depth)}${node.name}` });
        }
        if (node.id !== category?.id) walk(node.children, depth + 1);
      }
    };
    walk(allCategories, 0);
    return out;
  }, [allCategories, form.locale, category?.id]);

  const patch = (p: Partial<FormState>) => setForm((prev) => ({ ...prev, ...p }));

  const submit = () => {
    if (form.name.trim().length < 2) {
      toastError(t('categories.form.validation.nameRequired'));
      return;
    }
    const payload = {
      name: form.name.trim(),
      locale: form.locale,
      parent_id: form.parent_id,
      slug: form.slug.trim() ? form.slug.trim() : null,
      description: form.description.trim() ? form.description.trim() : null,
      is_active: form.is_active,
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
      description={isEdit ? undefined : t('categories.form.createHint')}
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
          <Label htmlFor="vcat-name">{t('categories.form.name')}</Label>
          <Input id="vcat-name" value={form.name} onChange={(e) => patch({ name: e.target.value })} maxLength={160} />
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <Label htmlFor="vcat-slug">{t('categories.form.slug')}</Label>
            <Input id="vcat-slug" value={form.slug} onChange={(e) => patch({ slug: e.target.value })} dir="ltr" placeholder={t('form.slugPlaceholder')} maxLength={160} />
          </div>
          <div>
            <Label htmlFor="vcat-locale">{t('categories.form.locale')}</Label>
            <select
              id="vcat-locale"
              value={form.locale}
              onChange={(e) => patch({ locale: e.target.value as ContentLocale, parent_id: null })}
              className={selectCls}
              disabled={isEdit && (category?.children?.length ?? 0) > 0}
            >
              <option value="ar">{t('locale.ar')}</option>
              <option value="en">{t('locale.en')}</option>
            </select>
          </div>
        </div>

        <div>
          <Label htmlFor="vcat-parent">{t('categories.form.parent')}</Label>
          <select
            id="vcat-parent"
            value={form.parent_id ?? ''}
            onChange={(e) => patch({ parent_id: e.target.value ? Number(e.target.value) : null })}
            className={selectCls}
          >
            <option value="">{t('categories.form.parentNone')}</option>
            {eligibleParents.map((p) => (
              <option key={p.id} value={p.id}>
                {p.label}
              </option>
            ))}
          </select>
          <p className="mt-1.5 flex items-start gap-1.5 text-xs text-muted-foreground">
            <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
            {t('categories.form.depthHint')}
          </p>
        </div>

        <div>
          <Label htmlFor="vcat-desc">{t('categories.form.description')}</Label>
          <textarea id="vcat-desc" rows={2} value={form.description} onChange={(e) => patch({ description: e.target.value })} maxLength={2000} className={areaCls} />
        </div>

        <label className="inline-flex cursor-pointer items-center gap-2 border border-border bg-background px-3 py-2 text-sm">
          <input type="checkbox" checked={form.is_active} onChange={(e) => patch({ is_active: e.target.checked })} className="h-4 w-4 accent-primary" />
          <span>{t('categories.form.active')}</span>
        </label>

        <div className="space-y-3 border-t border-border pt-4">
          <p className="text-xs font-semibold uppercase text-muted-foreground">{t('categories.form.seoSection')}</p>
          <div>
            <Label htmlFor="vcat-seotitle">{t('categories.form.seoTitle')}</Label>
            <Input id="vcat-seotitle" value={form.seo_title} onChange={(e) => patch({ seo_title: e.target.value })} maxLength={255} />
          </div>
          <div>
            <Label htmlFor="vcat-seodesc">{t('categories.form.seoDescription')}</Label>
            <textarea id="vcat-seodesc" rows={2} value={form.seo_description} onChange={(e) => patch({ seo_description: e.target.value })} maxLength={1000} className={areaCls} />
          </div>
        </div>
      </div>
    </Modal>
  );
}
