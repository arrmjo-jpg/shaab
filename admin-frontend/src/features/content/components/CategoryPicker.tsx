import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Check, Search, X } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Searchable, indented, multi-select section (category) picker — replaces the
 * flat checkbox chips that don't scale past a handful of categories.
 *
 * Contract-preserving: emits the SAME toggle/clear semantics the form already
 * uses (first pick → primary_category_id, rest → secondary_category_ids). It is
 * a pure presentational control over the already-flattened+filtered option list,
 * so all category business rules (scope/locale/type) stay in the parent.
 *
 * Square corners (system style), RTL-safe (logical padding + start/end), a11y
 * (real buttons, keyboard reachable). No border-radius introduced.
 */
interface Option {
  id: number;
  /** Pre-indented label, e.g. "— — Politics" (depth encoded as "— " prefixes). */
  label: string;
}

interface CategoryPickerProps {
  options: Option[];
  selected: number[];
  onToggle: (id: number) => void;
  onClear?: () => void;
  error?: string;
  emptyText?: string;
}

/** Strip the "— " depth prefixes for matching + chip display. */
const bareLabel = (label: string): string => label.replace(/^(?:—\s)+/, '');
const depthOf = (label: string): number => (label.match(/—\s/g) ?? []).length;

export function CategoryPicker({
  options,
  selected,
  onToggle,
  onClear,
  error,
  emptyText,
}: CategoryPickerProps) {
  const { t } = useTranslation('content');
  const [query, setQuery] = useState('');
  const selectedSet = useMemo(() => new Set(selected), [selected]);

  const filtered = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (!needle) return options;
    return options.filter((o) => bareLabel(o.label).toLowerCase().includes(needle));
  }, [options, query]);

  if (options.length === 0) {
    return (
      <p className="text-xs text-muted-foreground">
        {emptyText ?? t('articles.form.noCategories')}
      </p>
    );
  }

  const selectedOptions = options.filter((o) => selectedSet.has(o.id));

  return (
    <div className="space-y-2">
      {selectedOptions.length > 0 ? (
        <div className="flex flex-wrap gap-1.5">
          {selectedOptions.map((o) => (
            <button
              key={o.id}
              type="button"
              onClick={() => onToggle(o.id)}
              className="inline-flex items-center gap-1 border border-primary bg-primary/10 px-2 py-1 text-xs text-primary transition-colors hover:bg-primary/15"
            >
              <span className="max-w-[12rem] truncate">{bareLabel(o.label)}</span>
              <X className="h-3 w-3 shrink-0" />
            </button>
          ))}
        </div>
      ) : null}

      <div className="relative">
        <Search className="pointer-events-none absolute inset-y-0 my-auto start-2.5 h-4 w-4 text-muted-foreground" />
        <input
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder={t('articles.form.cockpit.categorySearch')}
          aria-label={t('articles.form.cockpit.categorySearch')}
          className="h-9 w-full border border-input bg-background pe-3 ps-8 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        />
      </div>

      <div className="max-h-56 space-y-0.5 overflow-y-auto border border-border bg-background p-1">
        {filtered.length === 0 ? (
          <p className="px-2 py-3 text-center text-xs text-muted-foreground">
            {t('articles.form.cockpit.categoryNoResults')}
          </p>
        ) : (
          filtered.map((o) => {
            const checked = selectedSet.has(o.id);
            return (
              <button
                key={o.id}
                type="button"
                role="checkbox"
                aria-checked={checked}
                onClick={() => onToggle(o.id)}
                style={{ paddingInlineStart: `${0.5 + depthOf(o.label) * 0.85}rem` }}
                className={cn(
                  'flex w-full items-center gap-2 py-1.5 pe-2 text-start text-sm transition-colors hover:bg-muted',
                  checked && 'bg-primary/5 font-medium',
                )}
              >
                <span
                  className={cn(
                    'flex h-4 w-4 shrink-0 items-center justify-center border',
                    checked ? 'border-primary bg-primary text-primary-foreground' : 'border-input',
                  )}
                >
                  {checked ? <Check className="h-3 w-3" /> : null}
                </span>
                <span className="truncate">{bareLabel(o.label)}</span>
              </button>
            );
          })
        )}
      </div>

      <div className="flex items-center justify-between text-xs text-muted-foreground">
        <span>{t('articles.form.cockpit.categoriesSelected', { count: selected.length })}</span>
        {selected.length > 0 && onClear ? (
          <button type="button" onClick={onClear} className="font-medium hover:text-foreground">
            {t('articles.form.cockpit.categoryClear')}
          </button>
        ) : null}
      </div>

      {error ? <p className="text-xs font-medium text-destructive">{error}</p> : null}
    </div>
  );
}
