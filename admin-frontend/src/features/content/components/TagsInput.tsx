import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useTagSuggestions } from '../hooks';
import type { ContentLocale } from '@/types/content.types';

interface Props {
  value: string[];
  onChange: (next: string[]) => void;
  locale: ContentLocale;
  max?: number;
  placeholder?: string;
  /** Auto-generated suggestions from title/subtitle/body (click to accept). */
  suggested?: string[];
}

/**
 * Chip-input with backend autocomplete.
 * - Backend allow-list: tag names, max 30, max length 50 each.
 * - Enter / Tab / `,` commits a new tag; Backspace on empty removes last.
 * - Suggestions come from /admin/tags (debounced via TanStack staleTime).
 */
export function TagsInput({
  value,
  onChange,
  locale,
  max = 30,
  placeholder,
  suggested = [],
}: Props) {
  const { t } = useTranslation('content');
  const [input, setInput] = useState('');
  const [focused, setFocused] = useState(false);
  const [highlight, setHighlight] = useState(0);
  const inputRef = useRef<HTMLInputElement | null>(null);

  const suggestions = useTagSuggestions(locale, input);

  const items = (suggestions.data ?? []).filter(
    (s) => !value.includes(s.name) && s.name.toLowerCase() !== input.trim().toLowerCase(),
  );

  useEffect(() => {
    setHighlight(0);
  }, [input, items.length]);

  const canAddMore = value.length < max;

  const commit = (raw: string) => {
    const tag = raw.trim().slice(0, 50);
    if (tag === '') return;
    if (value.includes(tag)) return;
    if (!canAddMore) return;
    onChange([...value, tag]);
    setInput('');
  };

  const removeAt = (idx: number) => {
    const next = value.slice();
    next.splice(idx, 1);
    onChange(next);
  };

  const onKey = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' || e.key === 'Tab' || e.key === ',') {
      if (input.trim()) {
        e.preventDefault();
        if (items.length > 0 && focused) {
          commit(items[highlight]?.name ?? input);
        } else {
          commit(input);
        }
      }
    } else if (e.key === 'Backspace' && input === '' && value.length > 0) {
      removeAt(value.length - 1);
    } else if (e.key === 'ArrowDown' && items.length > 0) {
      e.preventDefault();
      setHighlight((h) => Math.min(items.length - 1, h + 1));
    } else if (e.key === 'ArrowUp' && items.length > 0) {
      e.preventDefault();
      setHighlight((h) => Math.max(0, h - 1));
    } else if (e.key === 'Escape') {
      setInput('');
    }
  };

  const open = focused && (items.length > 0 || (input.trim().length > 0 && suggestions.isLoading));

  return (
    <div className="space-y-1.5">
      <div
        className={cn(
          'flex flex-wrap gap-1.5 border border-input bg-background p-2 transition-colors',
          focused && 'ring-2 ring-ring',
        )}
        onClick={() => inputRef.current?.focus()}
      >
        {value.map((tag, idx) => (
          <span
            key={`${tag}-${idx}`}
            className="inline-flex items-center gap-1 border border-border bg-muted/50 px-2 py-0.5 text-xs"
          >
            <span>{tag}</span>
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation();
                removeAt(idx);
              }}
              aria-label={t('articles.form.tags.remove')}
              className="text-muted-foreground hover:text-destructive"
            >
              <X className="h-3 w-3" />
            </button>
          </span>
        ))}
        <input
          ref={inputRef}
          value={input}
          onChange={(e) => setInput(e.target.value)}
          onKeyDown={onKey}
          onFocus={() => setFocused(true)}
          onBlur={() => {
            // Allow click on a suggestion to register before closing
            setTimeout(() => setFocused(false), 120);
          }}
          placeholder={
            canAddMore ? placeholder ?? t('articles.form.tags.placeholder') : ''
          }
          maxLength={50}
          disabled={!canAddMore}
          className="min-w-[120px] flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground/60 disabled:cursor-not-allowed"
        />
      </div>

      <div className="flex items-center justify-between text-xs text-muted-foreground">
        <span>{t('articles.form.tags.help')}</span>
        <span className={cn(!canAddMore && 'text-amber-600 dark:text-amber-400')}>
          {value.length} / {max}
        </span>
      </div>

      {(() => {
        const fresh = suggested.filter((s) => !value.includes(s)).slice(0, 8);
        if (fresh.length === 0 || !canAddMore) return null;
        return (
          <div className="flex flex-wrap items-center gap-1.5">
            <span className="text-xs text-muted-foreground">
              {t('articles.form.tags.suggested')}
            </span>
            {fresh.map((s) => (
              <button
                key={s}
                type="button"
                onClick={() => commit(s)}
                className="inline-flex items-center gap-1 border border-dashed border-input bg-background px-2 py-0.5 text-xs text-muted-foreground transition-colors hover:border-primary hover:text-primary"
              >
                + {s}
              </button>
            ))}
          </div>
        );
      })()}

      {open ? (
        <div className="border border-border bg-background shadow-soft">
          {suggestions.isLoading && items.length === 0 ? (
            <p className="px-3 py-2 text-xs text-muted-foreground">
              {t('articles.form.tags.searching')}
            </p>
          ) : items.length === 0 ? (
            <button
              type="button"
              onClick={() => commit(input)}
              className="block w-full px-3 py-2 text-start text-sm hover:bg-accent"
            >
              {t('articles.form.tags.addNew', { name: input.trim() })}
            </button>
          ) : (
            items.map((s, i) => (
              <button
                key={s.id}
                type="button"
                onMouseEnter={() => setHighlight(i)}
                onClick={() => commit(s.name)}
                className={cn(
                  'block w-full px-3 py-2 text-start text-sm transition-colors',
                  i === highlight ? 'bg-accent' : 'hover:bg-accent/60',
                )}
              >
                {s.name}
              </button>
            ))
          )}
        </div>
      ) : null}
    </div>
  );
}
