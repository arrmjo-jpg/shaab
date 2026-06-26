import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal } from '@/components/ui/modal';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/hooks/useToast';
import { useCreateCategory, useUpdateCategory } from '../hooks';
import type {
  CategoryData,
  CategoryScope,
  CategoryStatus,
  ContentLocale,
} from '@/types/content.types';

const selectCls =
  'h-10 w-full border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

interface Props {
  open: boolean;
  onClose: () => void;
  /** Edit mode if `category` is provided. */
  category?: CategoryData | null;
  /** Pre-fill parent (e.g. when adding a sub-category to a given node). */
  parent?: CategoryData | null;
  /** Used to enforce locale match when creating a child. */
  allCategories: CategoryData[];
}

interface FormState {
  name: string;
  slug: string;
  locale: ContentLocale;
  parent_id: number | null;
  scope: CategoryScope;
  status: CategoryStatus;
  description: string;
  show_in_header: boolean;
  show_in_body: boolean;
  show_in_footer: boolean;
}

function emptyState(parent: CategoryData | null | undefined): FormState {
  return {
    name: '',
    slug: '',
    locale: parent?.locale ?? 'ar',
    parent_id: parent?.id ?? null,
    scope: parent?.scope ?? 'news',
    status: 'active',
    description: '',
    show_in_header: false,
    show_in_body: true,
    show_in_footer: false,
  };
}

export function CategoryFormModal({
  open,
  onClose,
  category,
  parent,
  allCategories,
}: Props) {
  const { t } = useTranslation('content');
  const { success, error: toastError } = useToast();
  const create = useCreateCategory();
  const update = useUpdateCategory();

  const isEdit = Boolean(category);
  const [form, setForm] = useState<FormState>(() => emptyState(parent));

  // Hydrate / reset whenever the modal opens
  useEffect(() => {
    if (!open) return;
    if (category) {
      setForm({
        name: category.name,
        slug: category.slug,
        locale: category.locale,
        parent_id: category.parent_id,
        scope: category.scope,
        status: category.status,
        description: category.description ?? '',
        show_in_header: category.show_in_header,
        show_in_body: category.show_in_body,
        show_in_footer: category.show_in_footer,
      });
    } else {
      setForm(emptyState(parent));
    }
  }, [open, category, parent]);

  const eligibleParents = useMemo(() => {
    // Flatten the tree, keep nodes of the matching locale, exclude self + descendants.
    // Defensive: tolerate missing/non-array children at any depth.
    const out: Array<{ id: number; label: string }> = [];

    const walk = (nodes: unknown, depth: number) => {
      if (!Array.isArray(nodes)) return;
      for (const n of nodes) {
        if (!n || typeof n !== 'object') continue;
        const node = n as CategoryData;
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
      scope: form.scope,
      status: form.status,
      description: form.description.trim() ? form.description.trim() : null,
      show_in_header: form.show_in_header,
      show_in_body: form.show_in_body,
      show_in_footer: form.show_in_footer,
    };

    if (isEdit && category) {
      update.mutate(
        { id: category.id, payload },
        {
          onSuccess: () => {
            success(t('categories.form.saved'));
            onClose();
          },
        },
      );
    } else {
      create.mutate(payload, {
        onSuccess: () => {
          success(t('categories.form.saved'));
          onClose();
        },
      });
    }
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
          <Label htmlFor="cat-name">{t('categories.form.name')}</Label>
          <Input
            id="cat-name"
            value={form.name}
            onChange={(e) => patch({ name: e.target.value })}
            maxLength={150}
          />
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <Label htmlFor="cat-slug">{t('categories.form.slug')}</Label>
            <Input
              id="cat-slug"
              value={form.slug}
              onChange={(e) => patch({ slug: e.target.value })}
              dir="ltr"
              placeholder={t('articles.form.slugPlaceholder')}
              maxLength={160}
            />
          </div>
          <div>
            <Label htmlFor="cat-locale">{t('articles.form.locale')}</Label>
            <select
              id="cat-locale"
              value={form.locale}
              onChange={(e) => patch({ locale: e.target.value as ContentLocale, parent_id: null })}
              className={selectCls}
              disabled={isEdit && (category?.children?.length ?? 0) > 0}
            >
              <option value="ar">{t('articles.locale.ar')}</option>
              <option value="en">{t('articles.locale.en')}</option>
            </select>
          </div>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <Label htmlFor="cat-parent">{t('categories.form.parent')}</Label>
            <select
              id="cat-parent"
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
          </div>
          <div>
            <Label htmlFor="cat-scope">{t('categories.form.scope')}</Label>
            <select
              id="cat-scope"
              value={form.scope}
              onChange={(e) => patch({ scope: e.target.value as CategoryScope })}
              className={selectCls}
            >
              <option value="news">{t('categories.scope.news')}</option>
              <option value="opinion">{t('categories.scope.opinion')}</option>
              <option value="both">{t('categories.scope.both')}</option>
            </select>
          </div>
        </div>

        <div>
          <Label htmlFor="cat-status">{t('categories.form.status')}</Label>
          <select
            id="cat-status"
            value={form.status}
            onChange={(e) => patch({ status: e.target.value as CategoryStatus })}
            className={selectCls}
          >
            <option value="active">{t('categories.status.active')}</option>
            <option value="hidden">{t('categories.status.hidden')}</option>
          </select>
        </div>

        <div>
          <Label htmlFor="cat-desc">{t('categories.form.description')}</Label>
          <textarea
            id="cat-desc"
            rows={3}
            value={form.description}
            onChange={(e) => patch({ description: e.target.value })}
            maxLength={2000}
            className="flex w-full border border-input bg-background px-3.5 py-2.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          />
        </div>

        <div className="grid gap-2 sm:grid-cols-3">
          {(['show_in_header', 'show_in_body', 'show_in_footer'] as const).map((k) => (
            <label
              key={k}
              className="inline-flex cursor-pointer items-center gap-2 border border-border bg-background px-3 py-2 text-sm"
            >
              <input
                type="checkbox"
                checked={form[k]}
                onChange={(e) => patch({ [k]: e.target.checked } as Partial<FormState>)}
                className="h-4 w-4"
              />
              <span>{t(`categories.form.${k}`)}</span>
            </label>
          ))}
        </div>
      </div>
    </Modal>
  );
}
