import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Search, Save, ArrowRight, ListTree, FolderPlus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { useToast } from '@/hooks/useToast';
import {
  useRunCategories,
  useTargetCategories,
  useSaveCategoryMaps,
  useImportTaxonomy,
} from '../hooks';
import type {
  CategoryMapInput,
  TargetCategory,
  WpCategoryDisposition,
} from '@/types/wpMigration.types';

type ContentType = 'news' | 'articles';
type RowState = { disposition: WpCategoryDisposition; type: ContentType; target: number | null };
type FilterKey = 'all' | 'selected' | 'unselected';

const fmt = (n: number): string => new Intl.NumberFormat('en-US').format(n);

export function CategoryMappingStep({
  runId,
  onBack,
  onContinue,
}: {
  runId: number;
  onBack: () => void;
  onContinue: () => void;
}) {
  const { t } = useTranslation('wpMigration');
  const { success } = useToast();
  const catsQ = useRunCategories(runId);
  const poolsQ = useTargetCategories(runId);
  const save = useSaveCategoryMaps(runId);
  const importTaxo = useImportTaxonomy(runId);

  const [state, setState] = useState<Record<number, RowState>>({});
  const [ready, setReady] = useState(false);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState<FilterKey>('all');

  const cats = useMemo(() => catsQ.data ?? [], [catsQ.data]);

  // تهيئة الحالة المحلّية من الخادم مرّة واحدة (قابلة للتحرير ثم الحفظ).
  useEffect(() => {
    if (!ready && catsQ.data) {
      const init: Record<number, RowState> = {};
      for (const c of catsQ.data) {
        init[c.term_id] = {
          disposition: c.disposition,
          type: c.mode === 'articles' ? 'articles' : 'news',
          target: c.target_category_id,
        };
      }
      setState(init);
      setReady(true);
    }
  }, [catsQ.data, ready]);

  const childrenMap = useMemo(() => {
    const map: Record<number, number[]> = {};
    for (const c of cats) {
      (map[c.parent] ||= []).push(c.term_id);
    }
    return map;
  }, [cats]);

  const depthOf = useMemo(() => {
    const parentOf: Record<number, number> = {};
    for (const c of cats) parentOf[c.term_id] = c.parent;
    const cache: Record<number, number> = {};
    const calc = (id: number): number => {
      if (cache[id] !== undefined) return cache[id];
      const parent = parentOf[id];
      cache[id] = parent && parentOf[parent] !== undefined ? 1 + calc(parent) : 0;
      return cache[id];
    };
    const out: Record<number, number> = {};
    for (const c of cats) out[c.term_id] = calc(c.term_id);
    return out;
  }, [cats]);

  if (catsQ.isLoading || poolsQ.isLoading) return <PageSkeleton />;
  if (catsQ.isError || poolsQ.isError) {
    return (
      <ErrorState
        onRetry={() => {
          void catsQ.refetch();
          void poolsQ.refetch();
        }}
      />
    );
  }

  const pools = poolsQ.data;
  const rowState = (termId: number): RowState =>
    state[termId] ?? { disposition: 'exclude', type: 'news', target: null };

  // تغيير التصرّف/النوع يصفّر الهدف (predictable). exclude = لا نوع ولا هدف.
  const setDisposition = (termId: number, disposition: WpCategoryDisposition): void => {
    setState((s) => ({ ...s, [termId]: { ...rowStateFrom(s, termId), disposition, target: null } }));
  };
  const setType = (termId: number, type: ContentType): void => {
    setState((s) => ({ ...s, [termId]: { ...rowStateFrom(s, termId), type, target: null } }));
  };
  const setTarget = (termId: number, target: number | null): void => {
    setState((s) => ({ ...s, [termId]: { ...rowStateFrom(s, termId), target } }));
  };
  const rowStateFrom = (s: Record<number, RowState>, termId: number): RowState =>
    s[termId] ?? { disposition: 'exclude', type: 'news', target: null };

  const descendantsOf = (termId: number): number[] => {
    const out: number[] = [];
    const walk = (id: number): void => {
      for (const child of childrenMap[id] ?? []) {
        out.push(child);
        walk(child);
      }
    };
    walk(termId);
    return out;
  };

  // اختيار الشجرة الفرعية — صريح بأمر المُشغِّل: يطبّق تصرّف/نوع/هدف هذا الصفّ على كل الأبناء.
  const selectSubtree = (termId: number): void => {
    const src = rowState(termId);
    setState((s) => {
      const next = { ...s };
      for (const d of descendantsOf(termId)) {
        next[d] = { disposition: src.disposition, type: src.type, target: src.target };
      }
      return next;
    });
  };

  const poolFor = (type: ContentType): TargetCategory[] =>
    type === 'news' ? pools?.news ?? [] : pools?.articles ?? [];

  const selectedCount = cats.filter((c) => rowState(c.term_id).disposition !== 'exclude').length;
  const createCount = cats.filter((c) => rowState(c.term_id).disposition === 'create').length;

  const term = search.trim().toLowerCase();
  const visible = cats.filter((c) => {
    const st = rowState(c.term_id);
    if (filter === 'selected' && st.disposition === 'exclude') return false;
    if (filter === 'unselected' && st.disposition !== 'exclude') return false;
    if (term && !c.name.toLowerCase().includes(term)) return false;
    return true;
  });

  const buildMaps = (): CategoryMapInput[] =>
    cats.map((c) => {
      const st = rowState(c.term_id);
      const included = st.disposition !== 'exclude';
      return {
        wp_term_id: c.term_id,
        wp_name: c.name,
        wp_slug: c.slug,
        wp_parent_id: c.parent,
        wp_count: c.count,
        mode: included ? st.type : 'exclude',
        disposition: st.disposition,
        target_category_id: st.disposition === 'map' ? st.target : null,
      };
    });

  const onSave = (): void => {
    save.mutate(buildMaps(), { onSuccess: () => success(t('mapping.saved')) });
  };

  // حفظ ثمّ استيراد التصنيفات (إنشاء تصنيفات AlphaCMS) — يجب حفظ الصفوف قبل الاستيراد.
  const onImportTaxonomy = (): void => {
    save.mutate(buildMaps(), {
      onSuccess: () => {
        importTaxo.mutate(undefined, {
          onSuccess: (res) => success(t('mapping.taxonomyImported', { n: res.created })),
        });
      },
    });
  };

  // حفظ ثمّ المتابعة للمعاينة — يضمن أن المعاينة تقرأ آخر التنسيب المحفوظ.
  const onContinueToPreview = (): void => {
    save.mutate(buildMaps(), {
      onSuccess: () => {
        success(t('mapping.saved'));
        onContinue();
      },
    });
  };

  const busy = save.isPending || importTaxo.isPending;

  const filters: Array<{ key: FilterKey; label: string }> = [
    { key: 'all', label: t('mapping.filterAll') },
    { key: 'selected', label: t('mapping.filterSelected') },
    { key: 'unselected', label: t('mapping.filterUnselected') },
  ];

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-xl font-bold">{t('mapping.title')}</h2>
          <p className="text-sm text-muted-foreground">{t('mapping.subtitle')}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Button variant="outline" onClick={onBack}>
            <ArrowRight className="h-4 w-4 rtl:rotate-180" />
            {t('mapping.back')}
          </Button>
          <Button variant="outline" onClick={onSave} disabled={busy}>
            <Save className="h-4 w-4" />
            {save.isPending ? t('mapping.saving') : t('mapping.save')}
          </Button>
          <Button variant="outline" onClick={onImportTaxonomy} disabled={busy || createCount === 0}>
            <FolderPlus className="h-4 w-4" />
            {importTaxo.isPending ? t('mapping.importing') : t('mapping.importTaxonomy')}
            {createCount > 0 ? <span className="tabular-nums opacity-70">({createCount})</span> : null}
          </Button>
          <Button onClick={onContinueToPreview} disabled={busy}>
            {t('preview.continueFromMapping')}
            <ArrowRight className="h-4 w-4 rtl:rotate-180" />
          </Button>
        </div>
      </div>

      <p className="text-xs text-muted-foreground">{t('mapping.taxonomyHint')}</p>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="relative">
          <Search className="pointer-events-none absolute inset-y-0 my-auto h-4 w-4 text-muted-foreground ltr:left-3 rtl:right-3" />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t('mapping.search')}
            className="h-10 w-72 max-w-full border border-input bg-background text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ltr:pl-9 rtl:pr-9"
          />
        </div>
        <div className="flex items-center gap-1">
          {filters.map((f) => (
            <button
              key={f.key}
              type="button"
              onClick={() => setFilter(f.key)}
              className={`px-3 py-1.5 text-sm font-medium transition-colors ${
                filter === f.key ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent/50'
              }`}
            >
              {f.label}
            </button>
          ))}
          <span className="ms-2 text-xs text-muted-foreground">
            {t('mapping.selectedCount', { n: selectedCount, total: cats.length })}
          </span>
        </div>
      </div>

      <div className="overflow-auto border border-border">
        <table className="w-full text-sm">
          <thead className="sticky top-0 bg-muted/50 text-xs text-muted-foreground">
            <tr>
              <th className="p-2 text-start font-medium">{t('mapping.colCategory')}</th>
              <th className="p-2 text-end font-medium">{t('mapping.colDirect')}</th>
              <th className="p-2 text-end font-medium">{t('mapping.colTotal')}</th>
              <th className="p-2 text-start font-medium">{t('mapping.colDisposition')}</th>
              <th className="p-2 text-start font-medium">{t('mapping.colType')}</th>
              <th className="p-2 text-start font-medium">{t('mapping.colTarget')}</th>
              <th className="p-2" />
            </tr>
          </thead>
          <tbody>
            {visible.map((c) => {
              const st = rowState(c.term_id);
              const included = st.disposition !== 'exclude';
              const hasChildren = (childrenMap[c.term_id] ?? []).length > 0;

              return (
                <tr key={c.term_id} className="border-t border-border align-middle">
                  <td className="p-2">
                    <span style={{ paddingInlineStart: `${depthOf[c.term_id] * 16}px` }}>
                      {depthOf[c.term_id] > 0 ? '↳ ' : ''}
                      {c.name}
                    </span>
                  </td>
                  <td className="p-2 text-end tabular-nums text-muted-foreground">{fmt(c.count)}</td>
                  <td className="p-2 text-end font-semibold tabular-nums">{fmt(c.total_count)}</td>
                  <td className="p-2">
                    <select
                      value={st.disposition}
                      onChange={(e) => setDisposition(c.term_id, e.target.value as WpCategoryDisposition)}
                      className="h-9 border border-input bg-background px-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                      <option value="exclude">{t('mapping.dispoExclude')}</option>
                      <option value="create">{t('mapping.dispoCreate')}</option>
                      <option value="map">{t('mapping.dispoMap')}</option>
                    </select>
                  </td>
                  <td className="p-2">
                    {included ? (
                      <select
                        value={st.type}
                        onChange={(e) => setType(c.term_id, e.target.value as ContentType)}
                        className="h-9 border border-input bg-background px-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                      >
                        <option value="news">{t('mapping.modeNews')}</option>
                        <option value="articles">{t('mapping.modeArticles')}</option>
                      </select>
                    ) : (
                      <span className="text-xs text-muted-foreground">—</span>
                    )}
                  </td>
                  <td className="p-2">
                    {st.disposition === 'map' ? (
                      <select
                        value={st.target ?? ''}
                        onChange={(e) => setTarget(c.term_id, e.target.value ? Number(e.target.value) : null)}
                        className="h-9 min-w-44 border border-input bg-background px-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                      >
                        <option value="">{t('mapping.targetPlaceholder')}</option>
                        {poolFor(st.type).map((tc) => (
                          <option key={tc.id} value={tc.id}>
                            {tc.name}
                          </option>
                        ))}
                      </select>
                    ) : st.disposition === 'create' ? (
                      <span className="inline-flex items-center gap-1 bg-primary/10 px-2 py-0.5 text-xs text-primary">
                        <FolderPlus className="h-3.5 w-3.5" />
                        {t('mapping.createHint')}
                      </span>
                    ) : (
                      <span className="text-xs text-muted-foreground">—</span>
                    )}
                  </td>
                  <td className="p-2 text-end">
                    {hasChildren ? (
                      <button
                        type="button"
                        onClick={() => selectSubtree(c.term_id)}
                        title={t('mapping.selectSubtree')}
                        className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                      >
                        <ListTree className="h-3.5 w-3.5" />
                        {t('mapping.selectSubtree')}
                      </button>
                    ) : null}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
        {visible.length === 0 ? (
          <div className="p-6 text-center text-sm text-muted-foreground">{t('mapping.empty')}</div>
        ) : null}
      </div>
    </div>
  );
}
